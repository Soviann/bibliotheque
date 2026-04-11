<?php

declare(strict_types=1);

namespace App\Repository;

use App\DTO\ComicSeriesFilter;
use App\DTO\ComicSeriesListItem;
use App\Entity\ComicSeries;
use App\Entity\Tome;
use App\Enum\ComicStatus;
use App\Enum\ComicType;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

/**
 * @extends ServiceEntityRepository<ComicSeries>
 */
class ComicSeriesRepository extends ServiceEntityRepository
{
    public function __construct(
        #[Autowire(service: 'comic_series_api.cache')]
        private readonly CacheInterface $cache,
        ManagerRegistry $registry,
    ) {
        parent::__construct($registry, ComicSeries::class);
    }

    /**
     * @return ComicSeries[]
     */
    public function findWithFilters(ComicSeriesFilter $filters = new ComicSeriesFilter()): array
    {
        $qb = $this->createQueryBuilder('c');

        // Wishlist filter : isWishlist est calculé à partir du statut
        if (null !== $filters->isWishlist) {
            if ($filters->isWishlist) {
                $qb->andWhere('c.status = :wishlistStatus')
                    ->setParameter('wishlistStatus', ComicStatus::WISHLIST);
            } else {
                $qb->andWhere('c.status != :wishlistStatus')
                    ->setParameter('wishlistStatus', ComicStatus::WISHLIST);
            }
        }

        // Type filter
        if ($filters->type instanceof ComicType) {
            $qb->andWhere('c.type = :type')
                ->setParameter('type', $filters->type);
        }

        // Status filter
        if ($filters->status instanceof ComicStatus) {
            $qb->andWhere('c.status = :status')
                ->setParameter('status', $filters->status);
        }

        // NAS filter — EXISTS subquery (pas de JOIN)
        if (null !== $filters->onNas) {
            if ($filters->onNas) {
                $qb->andWhere('EXISTS (SELECT tn.id FROM App\Entity\Tome tn WHERE tn.comicSeries = c AND tn.onNas = true)');
            } else {
                $qb->andWhere('NOT EXISTS (SELECT tn2.id FROM App\Entity\Tome tn2 WHERE tn2.comicSeries = c AND tn2.onNas = true)');
            }
        }

        // Reading filter
        if (null !== $filters->reading) {
            match ($filters->reading) {
                'reading' => $qb
                    ->andWhere('EXISTS (SELECT r1.id FROM App\Entity\Tome r1 WHERE r1.comicSeries = c AND r1.read = true)')
                    ->andWhere('EXISTS (SELECT r2.id FROM App\Entity\Tome r2 WHERE r2.comicSeries = c AND r2.read = false)'),
                'read' => $qb
                    ->andWhere('NOT EXISTS (SELECT r3.id FROM App\Entity\Tome r3 WHERE r3.comicSeries = c AND r3.read = false)'),
                'unread' => $qb
                    ->andWhere('NOT EXISTS (SELECT r4.id FROM App\Entity\Tome r4 WHERE r4.comicSeries = c AND r4.read = true)'),
                default => null,
            };
        }

        // Search filter — JOIN tomes uniquement pour la recherche ISBN
        if (null !== $filters->search && '' !== $filters->search) {
            $qb->leftJoin('c.tomes', 't')
                ->andWhere('c.title LIKE :search OR t.isbn LIKE :search')
                ->setParameter('search', '%'.$filters->search.'%')
                ->distinct();
        }

        // Sorting
        match ($filters->sort) {
            'title_desc' => $qb->orderBy('c.title', 'DESC'),
            'updated_desc' => $qb->orderBy('c.updatedAt', 'DESC'),
            'updated_asc' => $qb->orderBy('c.updatedAt', 'ASC'),
            'status' => $qb->orderBy('c.status', 'ASC')
                ->addOrderBy('c.title', 'ASC'),
            default => $qb->orderBy('c.title', 'ASC'),
        };

        return $qb->getQuery()->getResult();
    }

    /**
     * Retourne les séries en cours d'acquisition (achat ou téléchargement) éligibles à la vérification de nouvelles parutions.
     *
     * @return ComicSeries[]
     */
    public function findActiveForReleaseCheck(?int $limit = null): array
    {
        $qb = $this->createQueryBuilder('c')
            ->where('c.status IN (:statuses)')
            ->andWhere('c.latestPublishedIssueComplete = false')
            ->andWhere('c.isOneShot = false')
            ->setParameter('statuses', [ComicStatus::BUYING, ComicStatus::DOWNLOADING])
            ->addOrderBy('c.newReleasesCheckedAt', 'ASC') // NULL values first in MariaDB
            ->addOrderBy('c.title', 'ASC');

        if (null !== $limit && $limit > 0) {
            $qb->setMaxResults($limit);
        }

        /** @var ComicSeries[] $result */
        $result = $qb->getQuery()->getResult();

        return $result;
    }

    /**
     * Retourne les séries avec une URL de couverture externe mais sans fichier local.
     *
     * @return ComicSeries[]
     */
    public function findWithExternalCoverOnly(?int $limit = null): array
    {
        $qb = $this->createQueryBuilder('c')
            ->where('c.coverUrl IS NOT NULL')
            ->andWhere('c.coverImage IS NULL')
            ->orderBy('c.title', 'ASC');

        if (null !== $limit && $limit > 0) {
            $qb->setMaxResults($limit);
        }

        /** @var ComicSeries[] $result */
        $result = $qb->getQuery()->getResult();

        return $result;
    }

    /**
     * Retourne les séries ayant une couverture locale (coverImage non null).
     *
     * @return ComicSeries[]
     */
    public function findWithLocalCover(): array
    {
        /** @var ComicSeries[] $result */
        $result = $this->createQueryBuilder('c')
            ->where('c.coverImage IS NOT NULL')
            ->orderBy('c.title', 'ASC')
            ->getQuery()
            ->getResult();

        return $result;
    }

    /**
     * Retourne les séries dont au moins un champ remplissable par lookup est vide.
     *
     * @return ComicSeries[]
     */
    public function findWithMissingLookupData(?ComicType $type = null, ?int $limit = null, bool $force = false): array
    {
        $qb = $this->buildMissingDataQueryBuilder($type, $limit);

        if (!$force) {
            $qb->andWhere('c.lookupCompletedAt IS NULL');
        }

        /** @var ComicSeries[] $result */
        $result = $qb->getQuery()->getResult();

        return $result;
    }

    /**
     * Retourne les séries à enrichir automatiquement :
     * - données manquantes ET (lookupCompletedAt null OU obsolète).
     *
     * @return ComicSeries[]
     */
    public function findForAutoEnrich(
        bool $force = false,
        ?int $limit = null,
        ?\DateTimeImmutable $staleDate = null,
        ?ComicType $type = null,
    ): array {
        $qb = $this->buildMissingDataQueryBuilder($type, $limit);

        if (!$force) {
            $qb->andWhere('c.lookupCompletedAt IS NULL OR c.lookupCompletedAt < :staleDate')
                ->setParameter('staleDate', $staleDate ?? new \DateTimeImmutable('-30 days'));
        }

        /** @var ComicSeries[] $result */
        $result = $qb->getQuery()->getResult();

        return $result;
    }

    /**
     * Retourne les séries éligibles à la détection de tomes manquants.
     *
     * @return ComicSeries[]
     */
    public function findForMissingTomeDetection(): array
    {
        /** @var ComicSeries[] $result */
        $result = $this->createQueryBuilder('c')
            ->andWhere('c.status IN (:statuses)')
            ->andWhere('c.isOneShot = false')
            ->andWhere('c.latestPublishedIssue IS NOT NULL')
            ->orderBy('c.title', 'ASC')
            ->setParameter('statuses', [ComicStatus::BUYING, ComicStatus::DOWNLOADING, ComicStatus::FINISHED])
            ->getQuery()
            ->getResult();

        return $result;
    }

    /**
     * Retourne les séries candidates à la détection de fusion.
     *
     * @return ComicSeries[]
     */
    public function findForMergeDetection(bool $force = false, ?string $startsWith = null, ?ComicType $type = null): array
    {
        $qb = $this->createQueryBuilder('c')
            ->orderBy('c.title', 'ASC');

        if (!$force) {
            $qb->andWhere('c.mergeCheckedAt IS NULL');
        }

        if (null !== $startsWith) {
            if ('0-9' === $startsWith) {
                $qb->andWhere('c.title REGEXP :numericPattern')
                    ->setParameter('numericPattern', '^[0-9]');
            } else {
                $qb->andWhere('UPPER(SUBSTRING(c.title, 1, 1)) = :startsWith')
                    ->setParameter('startsWith', \mb_strtoupper($startsWith));
            }
        }

        if ($type instanceof ComicType) {
            $qb->andWhere('c.type = :type')
                ->setParameter('type', $type);
        }

        /** @var ComicSeries[] $result */
        $result = $qb->getQuery()->getResult();

        return $result;
    }

    /**
     * Retourne les séries soft-deleted depuis plus de N jours.
     *
     * Désactive temporairement le filtre soft-delete pour accéder aux séries supprimées.
     *
     * @return ComicSeries[]
     */
    public function findPurgeable(int $days): array
    {
        $cutoffDate = new \DateTime(\sprintf('-%d days', $days));

        $this->getEntityManager()->getFilters()->disable('soft_delete');

        try {
            /** @var ComicSeries[] $series */
            $series = $this->createQueryBuilder('c')
                ->where('c.deletedAt IS NOT NULL')
                ->andWhere('c.deletedAt <= :cutoff')
                ->setParameter('cutoff', $cutoffDate)
                ->getQuery()
                ->getResult();
        } finally {
            $this->getEntityManager()->getFilters()->enable('soft_delete');
        }

        return $series;
    }

    /**
     * Retourne les séries en corbeille (soft-deleted), triées par date de suppression décroissante.
     *
     * @return ComicSeries[]
     */
    public function findTrashed(): array
    {
        $this->getEntityManager()->getFilters()->disable('soft_delete');

        try {
            /** @var ComicSeries[] $series */
            $series = $this->createQueryBuilder('c')
                ->where('c.deletedAt IS NOT NULL')
                ->orderBy('c.deletedAt', 'DESC')
                ->getQuery()
                ->getResult();
        } finally {
            $this->getEntityManager()->getFilters()->enable('soft_delete');
        }

        return $series;
    }

    /**
     * Cherche une série par titre normalisé (sans tirets, ponctuation, casse).
     * Fallback fuzzy quand le match exact échoue.
     */
    public function findOneByFuzzyTitle(string $title, ComicType $type): ?ComicSeries
    {
        // 1. Match exact
        $exact = $this->findOneBy(['title' => $title, 'type' => $type]);
        if (null !== $exact) {
            return $exact;
        }

        // 2. Charger les candidats du même type avec un LIKE large, filtrer en PHP
        return $this->fuzzySearch($title, $type);
    }

    /**
     * Variante sans type (pour ImportBooksService qui ne connaît pas le type à l'avance).
     */
    public function findOneByFuzzyTitleAnyType(string $title): ?ComicSeries
    {
        $exact = $this->findOneBy(['title' => $title]);
        if (null !== $exact) {
            return $exact;
        }

        return $this->fuzzySearch($title);
    }

    /**
     * Retourne tous les titres en minuscules (via SQL), sans charger les entités.
     *
     * @return list<string>
     */
    public function findAllTitlesLower(): array
    {
        /** @var list<array{title: string}> $rows */
        $rows = $this->createQueryBuilder('c')
            ->select('LOWER(c.title) AS title')
            ->getQuery()
            ->getScalarResult();

        return \array_column($rows, 'title');
    }

    /**
     * Retourne toutes les séries avec leurs relations pour l'API PWA.
     *
     * Utilise un cache applicatif (30 min) pour éviter de requêter la base
     * à chaque chargement. Le cache est invalidé par ComicSeriesCacheInvalidator.
     *
     * @return list<ComicSeriesListItem>
     */
    public function findAllForApi(): array
    {
        /* @var list<ComicSeriesListItem> */
        return $this->cache->get('comic_series_api_all', function (ItemInterface $item): array {
            $item->expiresAfter(1800);

            return $this->doFindAllForApi();
        });
    }

    /**
     * Construit le QueryBuilder commun pour les séries avec données manquantes.
     *
     * Les vérifications scalaires (NULL) sont placées dans WHERE (pas d'agrégation nécessaire).
     * L'absence d'auteurs est détectée via NOT EXISTS pour éviter un GROUP BY sur toute la table.
     */
    private function buildMissingDataQueryBuilder(?ComicType $type, ?int $limit): \Doctrine\ORM\QueryBuilder
    {
        $qb = $this->createQueryBuilder('c')
            ->where(
                'c.description IS NULL'
                .' OR c.publisher IS NULL'
                .' OR c.publishedDate IS NULL'
                .' OR (c.coverUrl IS NULL AND c.coverImage IS NULL)'
                .' OR c.latestPublishedIssue IS NULL'
                .' OR NOT EXISTS (SELECT a2.id FROM App\Entity\Author a2 WHERE a2 MEMBER OF c.authors)'
            )
            ->orderBy('c.title', 'ASC');

        if ($type instanceof ComicType) {
            $qb->andWhere('c.type = :type')
                ->setParameter('type', $type);
        }

        if (null !== $limit && $limit > 0) {
            $qb->setMaxResults($limit);
        }

        return $qb;
    }

    /**
     * Recherche fuzzy : extrait les mots-clés du titre, cherche via LIKE, puis compare normalisé en PHP.
     */
    private function fuzzySearch(string $title, ?ComicType $type = null): ?ComicSeries
    {
        $normalized = $this->normalizeForComparison($title);

        // Extraire le mot le plus long comme filtre SQL (pour limiter les candidats)
        $words = \explode(' ', $normalized);
        \usort($words, static fn (string $a, string $b): int => \strlen($b) <=> \strlen($a));
        $keyword = $words[0];

        if (\strlen($keyword) < 3) {
            return null;
        }

        $qb = $this->createQueryBuilder('c')
            ->where('LOWER(c.title) LIKE :keyword')
            ->setParameter('keyword', '%'.$keyword.'%');

        if ($type instanceof ComicType) {
            $qb->andWhere('c.type = :type')->setParameter('type', $type);
        }

        /** @var ComicSeries[] $candidates */
        $candidates = $qb->getQuery()->getResult();

        foreach ($candidates as $candidate) {
            if ($this->normalizeForComparison($candidate->getTitle()) === $normalized) {
                return $candidate;
            }
        }

        return null;
    }

    /**
     * Normalise un titre pour comparaison : lowercase, sans tirets/ponctuation/espaces multiples.
     */
    private function normalizeForComparison(string $title): string
    {
        $n = \mb_strtolower($title);
        // Translitération accents (é → e, etc.)
        $n = \transliterator_transliterate('NFD; [:Nonspacing Mark:] Remove; NFC', $n) ?: $n;
        $n = \str_replace(['-', "'", "\u{2019}", '.', ',', ':', '!', '?', '(', ')'], ' ', $n);
        $n = (string) \preg_replace('/\s+/', ' ', $n);

        return \trim($n);
    }

    /**
     * Exécute la requête complète avec eager loading pour éviter le N+1.
     *
     * Utilise deux requêtes séparées (séries+auteurs, puis tomes) au lieu d'un seul
     * LEFT JOIN croisé pour éviter le produit cartésien séries×auteurs×tomes.
     *
     * @return list<ComicSeriesListItem>
     */
    private function doFindAllForApi(): array
    {
        // Requête 1 : séries + auteurs (pas de cartésien avec les tomes)
        /** @var ComicSeries[] $comics */
        $comics = $this->createQueryBuilder('c')
            ->leftJoin('c.authors', 'a')
            ->addSelect('a')
            ->orderBy('c.title', 'ASC')
            ->getQuery()
            ->getResult();

        if ([] === $comics) {
            return [];
        }

        // Requête 2 : charger les tomes via l'identity map (Doctrine les associe automatiquement)
        $ids = \array_map(static fn (ComicSeries $c): ?int => $c->getId(), $comics);
        $this->getEntityManager()->createQueryBuilder()
            ->select('t')
            ->from(Tome::class, 't')
            ->where('t.comicSeries IN (:ids)')
            ->setParameter('ids', $ids)
            ->getQuery()
            ->getResult();

        $result = [];
        foreach ($comics as $comic) {
            $hasNasTome = $comic->getTomes()->exists(static fn (int $_key, Tome $t): bool => $t->isOnNas());

            $result[] = ComicSeriesListItem::fromEntity($comic, $hasNasTome);
        }

        return $result;
    }
}

<?php

declare(strict_types=1);

namespace App\Repository;

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
     * @param array{
     *     isWishlist?: bool,
     *     onNas?: bool|null,
     *     reading?: string|null,
     *     search?: string|null,
     *     sort?: string,
     *     status?: ComicStatus|null,
     *     type?: ComicType|null,
     * } $filters
     *
     * @return ComicSeries[]
     */
    public function findWithFilters(array $filters = []): array
    {
        $qb = $this->createQueryBuilder('c')
            ->leftJoin('c.tomes', 't')
            ->addSelect('t');

        // Wishlist filter : isWishlist est calculé à partir du statut
        if (isset($filters['isWishlist'])) {
            if ($filters['isWishlist']) {
                $qb->andWhere('c.status = :wishlistStatus')
                    ->setParameter('wishlistStatus', ComicStatus::WISHLIST);
            } else {
                $qb->andWhere('c.status != :wishlistStatus')
                    ->setParameter('wishlistStatus', ComicStatus::WISHLIST);
            }
        }

        // Type filter
        if (!empty($filters['type'])) {
            $qb->andWhere('c.type = :type')
                ->setParameter('type', $filters['type']);
        }

        // Status filter
        if (!empty($filters['status'])) {
            $qb->andWhere('c.status = :status')
                ->setParameter('status', $filters['status']);
        }

        // NAS filter (vérifie si au moins un tome est sur le NAS)
        if (isset($filters['onNas']) && null !== $filters['onNas']) {
            if ($filters['onNas']) {
                $qb->andWhere('t.onNas = :onNas')
                    ->setParameter('onNas', true);
            } else {
                // Séries sans aucun tome sur NAS
                $qb->andWhere('NOT EXISTS (SELECT t2.id FROM App\Entity\Tome t2 WHERE t2.comicSeries = c AND t2.onNas = true)');
            }
        }

        // Reading filter
        if (!empty($filters['reading'])) {
            match ($filters['reading']) {
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

        // Search filter (titre uniquement, ISBN est maintenant sur les tomes)
        if (!empty($filters['search'])) {
            $qb->andWhere('c.title LIKE :search OR t.isbn LIKE :search')
                ->setParameter('search', '%'.$filters['search'].'%');
        }

        // Distinct pour éviter les doublons à cause du JOIN
        $qb->distinct();

        // Sorting
        $sort = $filters['sort'] ?? 'title_asc';
        match ($sort) {
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
     * Retourne les séries dont au moins un champ remplissable par lookup est vide.
     *
     * @return ComicSeries[]
     */
    public function findWithMissingLookupData(?ComicType $type = null, ?int $limit = null, bool $force = false): array
    {
        $qb = $this->createQueryBuilder('c')
            ->leftJoin('c.authors', 'a')
            ->groupBy('c.id')
            ->having(
                'c.description IS NULL'
                .' OR c.publisher IS NULL'
                .' OR c.publishedDate IS NULL'
                .' OR (c.coverUrl IS NULL AND c.coverImage IS NULL)'
                .' OR c.latestPublishedIssue IS NULL'
                .' OR COUNT(a.id) = 0'
            )
            ->orderBy('c.title', 'ASC');

        if (!$force) {
            $qb->andWhere('c.lookupCompletedAt IS NULL');
        }

        if (null !== $type) {
            $qb->andWhere('c.type = :type')
                ->setParameter('type', $type);
        }

        if (null !== $limit && $limit > 0) {
            $qb->setMaxResults($limit);
        }

        /* @var ComicSeries[] */
        return $qb->getQuery()->getResult();
    }

    /**
     * Retourne toutes les séries avec leurs relations pour l'API PWA.
     *
     * Utilise un cache applicatif (15 min) pour éviter de requêter la base
     * à chaque chargement. Le cache est invalidé par ComicSeriesCacheInvalidator.
     *
     * @return list<ComicSeriesListItem>
     */
    public function findAllForApi(): array
    {
        /* @var list<ComicSeriesListItem> */
        return $this->cache->get('comic_series_api_all', function (ItemInterface $item): array {
            $item->expiresAfter(900);

            return $this->doFindAllForApi();
        });
    }

    /**
     * Exécute la requête complète avec eager loading pour éviter le N+1.
     *
     * @return list<ComicSeriesListItem>
     */
    private function doFindAllForApi(): array
    {
        /** @var ComicSeries[] $comics */
        $comics = $this->createQueryBuilder('c')
            ->leftJoin('c.authors', 'a')
            ->addSelect('a')
            ->leftJoin('c.tomes', 't')
            ->addSelect('t')
            ->orderBy('c.title', 'ASC')
            ->getQuery()
            ->getResult();

        $result = [];
        foreach ($comics as $comic) {
            $hasNasTome = $comic->getTomes()->exists(static fn (int $key, Tome $t): bool => $t->isOnNas());

            $result[] = ComicSeriesListItem::fromEntity($comic, $hasNasTome);
        }

        return $result;
    }
}

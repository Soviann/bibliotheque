<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\ComicSeries;
use App\Entity\Tome;
use App\Enum\ComicStatus;
use App\Enum\ComicType;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ComicSeries>
 */
class ComicSeriesRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ComicSeries::class);
    }

    /**
     * Retourne les séries soft-deleted, triées par date de suppression décroissante.
     *
     * @return ComicSeries[]
     */
    public function findSoftDeleted(): array
    {
        $em = $this->getEntityManager();
        $em->getFilters()->disable('soft_delete');

        /** @var ComicSeries[] $results */
        $results = $this->createQueryBuilder('c')
            ->where('c.deletedAt IS NOT NULL')
            ->orderBy('c.deletedAt', 'DESC')
            ->getQuery()
            ->getResult();

        $em->getFilters()->enable('soft_delete');

        return $results;
    }

    /**
     * Retourne une série soft-deleted par son ID, ou null si non trouvée.
     */
    public function findSoftDeletedById(int $id): ?ComicSeries
    {
        $em = $this->getEntityManager();
        $em->getFilters()->disable('soft_delete');

        $comic = $this->find($id);

        $em->getFilters()->enable('soft_delete');

        if (!$comic instanceof ComicSeries || !$comic->isDeleted()) {
            return null;
        }

        return $comic;
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
     * Recherche par titre ou ISBN de tome.
     *
     * @return ComicSeries[]
     */
    public function search(string $query): array
    {
        return $this->createQueryBuilder('c')
            ->leftJoin('c.tomes', 't')
            ->addSelect('t')
            ->andWhere('c.title LIKE :query OR t.isbn LIKE :query')
            ->setParameter('query', '%'.$query.'%')
            ->distinct()
            ->orderBy('c.title', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return ComicSeries[]
     */
    public function findByStatus(ComicStatus $status): array
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.status = :status')
            ->setParameter('status', $status)
            ->orderBy('c.title', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Retourne toutes les séries avec leurs relations pour l'API PWA.
     *
     * Utilise un eager loading avec leftJoin + addSelect pour éviter
     * le problème N+1 (une requête par série pour chaque relation).
     *
     * @return list<array<string, mixed>>
     */
    public function findAllForApi(): array
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

            $result[] = [
                'authors' => $comic->getAuthorsAsString(),
                'coverUrl' => $comic->getCoverUrl(),
                'currentIssue' => $comic->getCurrentIssue(),
                'currentIssueComplete' => $comic->isCurrentIssueComplete(),
                'description' => $comic->getDescription(),
                'hasNasTome' => $hasNasTome,
                'id' => $comic->getId(),
                'isCurrentlyReading' => $comic->isCurrentlyReading(),
                'isFullyRead' => $comic->isFullyRead(),
                'isOneShot' => $comic->isOneShot(),
                'isWishlist' => $comic->isWishlist(),
                'lastBought' => $comic->getLastBought(),
                'lastBoughtComplete' => $comic->isLastBoughtComplete(),
                'lastDownloaded' => $comic->getLastDownloaded(),
                'lastDownloadedComplete' => $comic->isLastDownloadedComplete(),
                'lastRead' => $comic->getLastRead(),
                'lastReadComplete' => $comic->isLastReadComplete(),
                'latestPublishedIssue' => $comic->getLatestPublishedIssue(),
                'latestPublishedIssueComplete' => $comic->isLatestPublishedIssueComplete(),
                'missingTomesNumbers' => $comic->getMissingTomesNumbers(),
                'ownedTomesNumbers' => $comic->getOwnedTomesNumbers(),
                'publishedDate' => $comic->getPublishedDate(),
                'publisher' => $comic->getPublisher(),
                'readTomesCount' => $comic->getReadTomesCount(),
                'status' => $comic->getStatus()->value,
                'statusLabel' => $comic->getStatus()->getLabel(),
                'title' => $comic->getTitle(),
                'tomesCount' => $comic->getTomes()->count(),
                'type' => $comic->getType()->value,
                'typeLabel' => $comic->getType()->getLabel(),
                'updatedAt' => $comic->getUpdatedAt()->format('c'),
            ];
        }

        return $result;
    }
}

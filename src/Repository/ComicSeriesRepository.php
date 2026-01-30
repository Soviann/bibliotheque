<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\ComicSeries;
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
     * @param array{
     *     isWishlist?: bool,
     *     onNas?: bool|null,
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
            ->leftJoin('c.tomes', 't');

        // Wishlist filter (required)
        if (isset($filters['isWishlist'])) {
            $qb->andWhere('c.isWishlist = :isWishlist')
                ->setParameter('isWishlist', $filters['isWishlist']);
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

        // Search filter (titre uniquement, ISBN est maintenant sur les tomes)
        if (!empty($filters['search'])) {
            $qb->andWhere('c.title LIKE :search OR t.isbn LIKE :search')
                ->setParameter('search', '%'.$filters['search'].'%');
        }

        // Distinct pour éviter les doublons à cause du JOIN
        $qb->distinct();

        // Sorting
        $sort = $filters['sort'] ?? 'title_asc';
        switch ($sort) {
            case 'title_desc':
                $qb->orderBy('c.title', 'DESC');
                break;
            case 'updated_desc':
                $qb->orderBy('c.updatedAt', 'DESC');
                break;
            case 'updated_asc':
                $qb->orderBy('c.updatedAt', 'ASC');
                break;
            case 'status':
                $qb->orderBy('c.status', 'ASC')
                    ->addOrderBy('c.title', 'ASC');
                break;
            case 'title_asc':
            default:
                $qb->orderBy('c.title', 'ASC');
                break;
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * @return ComicSeries[]
     *
     * @deprecated Use findWithFilters instead
     */
    public function findLibrary(?ComicType $type = null): array
    {
        return $this->findWithFilters([
            'isWishlist' => false,
            'type' => $type,
        ]);
    }

    /**
     * @return ComicSeries[]
     *
     * @deprecated Use findWithFilters instead
     */
    public function findWishlist(?ComicType $type = null): array
    {
        return $this->findWithFilters([
            'isWishlist' => true,
            'type' => $type,
        ]);
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
     * @return list<array<string, mixed>>
     */
    public function findAllForApi(): array
    {
        /** @var ComicSeries[] $comics */
        $comics = $this->createQueryBuilder('c')
            ->orderBy('c.title', 'ASC')
            ->getQuery()
            ->getResult();

        $result = [];
        foreach ($comics as $comic) {
            $result[] = [
                'authors' => $comic->getAuthorsAsString(),
                'coverUrl' => $comic->getCoverUrl(),
                'currentIssue' => $comic->getCurrentIssue(),
                'currentIssueComplete' => $comic->isCurrentIssueComplete(),
                'description' => $comic->getDescription(),
                'id' => $comic->getId(),
                'isWishlist' => $comic->isWishlist(),
                'lastBought' => $comic->getLastBought(),
                'lastBoughtComplete' => $comic->isLastBoughtComplete(),
                'lastDownloaded' => $comic->getLastDownloaded(),
                'lastDownloadedComplete' => $comic->isLastDownloadedComplete(),
                'latestPublishedIssue' => $comic->getLatestPublishedIssue(),
                'latestPublishedIssueComplete' => $comic->isLatestPublishedIssueComplete(),
                'missingTomesNumbers' => $comic->getMissingTomesNumbers(),
                'ownedTomesNumbers' => $comic->getOwnedTomesNumbers(),
                'publishedDate' => $comic->getPublishedDate(),
                'publisher' => $comic->getPublisher(),
                'status' => $comic->getStatus()->value,
                'title' => $comic->getTitle(),
                'tomesCount' => $comic->getTomes()->count(),
                'type' => $comic->getType()->value,
                'updatedAt' => $comic->getUpdatedAt()->format('c'),
            ];
        }

        return $result;
    }
}

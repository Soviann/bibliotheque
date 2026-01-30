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
        $qb = $this->createQueryBuilder('c');

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

        // NAS filter
        if (isset($filters['onNas']) && null !== $filters['onNas']) {
            $qb->andWhere('c.onNas = :onNas')
                ->setParameter('onNas', $filters['onNas']);
        }

        // Search filter (titre ou ISBN)
        if (!empty($filters['search'])) {
            $qb->andWhere('c.title LIKE :search OR c.isbn LIKE :search')
                ->setParameter('search', '%'.$filters['search'].'%');
        }

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
     * Recherche par titre ou ISBN.
     *
     * @return ComicSeries[]
     */
    public function search(string $query): array
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.title LIKE :query OR c.isbn LIKE :query')
            ->setParameter('query', '%'.$query.'%')
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
     * @return array<string, mixed>
     */
    public function findAllForApi(): array
    {
        $comics = $this->createQueryBuilder('c')
            ->orderBy('c.title', 'ASC')
            ->getQuery()
            ->getResult();

        return \array_map(static fn (ComicSeries $comic) => [
            'authors' => $comic->getAuthorsAsString(),
            'coverUrl' => $comic->getCoverUrl(),
            'currentIssue' => $comic->getCurrentIssue(),
            'currentIssueComplete' => $comic->isCurrentIssueComplete(),
            'description' => $comic->getDescription(),
            'id' => $comic->getId(),
            'isbn' => $comic->getIsbn(),
            'isWishlist' => $comic->isWishlist(),
            'lastBought' => $comic->getLastBought(),
            'lastBoughtComplete' => $comic->isLastBoughtComplete(),
            'lastDownloaded' => $comic->getLastDownloaded(),
            'lastDownloadedComplete' => $comic->isLastDownloadedComplete(),
            'missingIssues' => $comic->getMissingIssues(),
            'onNas' => $comic->isOnNas(),
            'ownedIssues' => $comic->getOwnedIssues(),
            'publishedCount' => $comic->getPublishedCount(),
            'publishedCountComplete' => $comic->isPublishedCountComplete(),
            'publishedDate' => $comic->getPublishedDate(),
            'publisher' => $comic->getPublisher(),
            'status' => $comic->getStatus()->value,
            'title' => $comic->getTitle(),
            'type' => $comic->getType()->value,
            'updatedAt' => $comic->getUpdatedAt()->format('c'),
        ], $comics);
    }
}

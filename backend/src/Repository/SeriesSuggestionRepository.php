<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\SeriesSuggestion;
use App\Enum\ComicType;
use App\Enum\SuggestionStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<SeriesSuggestion>
 */
class SeriesSuggestionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SeriesSuggestion::class);
    }

    public function existsPendingByTitleAndType(string $title, ComicType $type): bool
    {
        return (int) $this->createQueryBuilder('s')
            ->select('COUNT(s.id)')
            ->andWhere('s.title = :title')
            ->andWhere('s.type = :type')
            ->andWhere('s.status = :status')
            ->setParameter('status', SuggestionStatus::PENDING)
            ->setParameter('title', $title)
            ->setParameter('type', $type)
            ->getQuery()
            ->getSingleScalarResult() > 0;
    }

    /**
     * @return list<string>
     */
    public function findDismissedTitles(): array
    {
        /** @var list<array{title: string}> $rows */
        $rows = $this->createQueryBuilder('s')
            ->select('s.title')
            ->andWhere('s.status = :status')
            ->setParameter('status', SuggestionStatus::DISMISSED)
            ->getQuery()
            ->getArrayResult();

        return \array_column($rows, 'title');
    }

    /**
     * @return list<SeriesSuggestion>
     */
    public function findPending(): array
    {
        /** @var list<SeriesSuggestion> $result */
        $result = $this->createQueryBuilder('s')
            ->andWhere('s.status = :status')
            ->orderBy('s.createdAt', 'DESC')
            ->setParameter('status', SuggestionStatus::PENDING)
            ->getQuery()
            ->getResult();

        return $result;
    }
}

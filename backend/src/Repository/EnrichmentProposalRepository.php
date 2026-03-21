<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\ComicSeries;
use App\Entity\EnrichmentProposal;
use App\Enum\EnrichableField;
use App\Enum\ProposalStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<EnrichmentProposal>
 */
class EnrichmentProposalRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, EnrichmentProposal::class);
    }

    public function countPending(): int
    {
        return (int) $this->createQueryBuilder('p')
            ->select('COUNT(p.id)')
            ->andWhere('p.status = :status')
            ->setParameter('status', ProposalStatus::PENDING)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * @return list<EnrichmentProposal>
     */
    public function findPendingBySeries(ComicSeries $series): array
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.comicSeries = :series')
            ->andWhere('p.status = :status')
            ->orderBy('p.createdAt', 'DESC')
            ->setParameter('series', $series)
            ->setParameter('status', ProposalStatus::PENDING)
            ->getQuery()
            ->getResult();
    }

    public function findPendingBySeriesAndField(ComicSeries $series, EnrichableField $field): ?EnrichmentProposal
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.comicSeries = :series')
            ->andWhere('p.field = :field')
            ->andWhere('p.status = :status')
            ->setParameter('field', $field)
            ->setParameter('series', $series)
            ->setParameter('status', ProposalStatus::PENDING)
            ->getQuery()
            ->getOneOrNullResult();
    }
}

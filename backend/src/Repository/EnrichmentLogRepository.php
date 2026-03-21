<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\ComicSeries;
use App\Entity\EnrichmentLog;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<EnrichmentLog>
 */
class EnrichmentLogRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, EnrichmentLog::class);
    }

    /**
     * @return list<EnrichmentLog>
     */
    public function findBySeriesOrderedDesc(ComicSeries $series): array
    {
        return $this->createQueryBuilder('l')
            ->andWhere('l.comicSeries = :series')
            ->orderBy('l.createdAt', 'DESC')
            ->setParameter('series', $series)
            ->getQuery()
            ->getResult();
    }
}

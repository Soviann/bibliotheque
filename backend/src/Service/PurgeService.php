<?php

declare(strict_types=1);

namespace App\Service;

use App\DTO\PurgeableSeries;
use App\Entity\ComicSeries;
use App\Repository\ComicSeriesRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Service de purge des séries soft-deleted.
 */
class PurgeService
{
    public function __construct(
        private readonly ComicSeriesRepository $comicSeriesRepository,
        private readonly ComicSeriesService $comicSeriesService,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * Exécute la purge définitive des séries identifiées par leurs IDs.
     *
     * @param int[] $seriesIds
     *
     * @return int nombre de séries purgées
     */
    public function executePurge(array $seriesIds): int
    {
        if ([] === $seriesIds) {
            return 0;
        }

        $this->entityManager->getFilters()->disable('soft_delete');

        $count = 0;

        foreach ($seriesIds as $id) {
            $series = $this->comicSeriesRepository->find($id);

            if ($series instanceof ComicSeries) {
                $this->comicSeriesService->permanentDelete($id, $series);
                ++$count;
            }
        }

        $this->entityManager->getFilters()->enable('soft_delete');

        return $count;
    }

    /**
     * Recherche les séries éligibles à la purge (soft-deleted depuis plus de N jours).
     *
     * @return PurgeableSeries[]
     */
    public function findPurgeable(int $days): array
    {
        $cutoffDate = new \DateTime(\sprintf('-%d days', $days));

        $this->entityManager->getFilters()->disable('soft_delete');

        /** @var ComicSeries[] $series */
        $series = $this->comicSeriesRepository->createQueryBuilder('c')
            ->where('c.deletedAt IS NOT NULL')
            ->andWhere('c.deletedAt <= :cutoff')
            ->setParameter('cutoff', $cutoffDate)
            ->getQuery()
            ->getResult();

        $this->entityManager->getFilters()->enable('soft_delete');

        return \array_map(
            static function (ComicSeries $s): PurgeableSeries {
                /** @var \DateTimeInterface $deletedAt query filtre deletedAt IS NOT NULL */
                $deletedAt = $s->getDeletedAt();
                /** @var int $id entité persistée */
                $id = $s->getId();

                return new PurgeableSeries(
                    deletedAt: \DateTimeImmutable::createFromInterface($deletedAt),
                    id: $id,
                    title: $s->getTitle(),
                );
            },
            $series,
        );
    }
}

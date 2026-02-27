<?php

declare(strict_types=1);

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Entity\ComicSeries;
use App\Repository\ComicSeriesRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Provider pour la collection de séries supprimées (corbeille).
 *
 * Désactive le filtre soft-delete et retourne uniquement les séries avec deleted_at != NULL.
 *
 * @implements ProviderInterface<ComicSeries>
 */
final readonly class TrashCollectionProvider implements ProviderInterface
{
    public function __construct(
        private ComicSeriesRepository $comicSeriesRepository,
        private EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * @return array<int, ComicSeries>
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): array
    {
        $this->entityManager->getFilters()->disable('soft_delete');

        try {
            /** @var array<int, ComicSeries> $comics */
            $comics = $this->comicSeriesRepository->createQueryBuilder('c')
                ->where('c.deletedAt IS NOT NULL')
                ->orderBy('c.deletedAt', 'DESC')
                ->getQuery()
                ->getResult();
        } finally {
            $this->entityManager->getFilters()->enable('soft_delete');
        }

        return $comics;
    }
}

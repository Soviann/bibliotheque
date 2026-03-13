<?php

declare(strict_types=1);

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Entity\ComicSeries;
use App\Repository\ComicSeriesRepository;

/**
 * Provider pour la collection de séries supprimées (corbeille).
 *
 * Délègue au repository qui gère la désactivation du filtre soft-delete.
 *
 * @implements ProviderInterface<ComicSeries>
 */
final readonly class TrashCollectionProvider implements ProviderInterface
{
    public function __construct(
        private ComicSeriesRepository $comicSeriesRepository,
    ) {
    }

    /**
     * @return array<int, ComicSeries>
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): array
    {
        return $this->comicSeriesRepository->findTrashed();
    }
}

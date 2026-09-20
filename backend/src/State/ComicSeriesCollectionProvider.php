<?php

declare(strict_types=1);

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Entity\ComicSeries;
use App\Repository\ComicSeriesRepository;

/**
 * Provider pour la collection de séries actives.
 *
 * Évite le problème N+1 en préchargeant les auteurs et les tomes
 * de manière optimisée pour éliminer les requêtes paresseuses lors de la sérialisation.
 *
 * @implements ProviderInterface<ComicSeries>
 */
final readonly class ComicSeriesCollectionProvider implements ProviderInterface
{
    public function __construct(
        private ComicSeriesRepository $comicSeriesRepository,
    ) {
    }

    /**
     * @return list<ComicSeries>
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): array
    {
        return $this->comicSeriesRepository->findCollectionForApi();
    }
}

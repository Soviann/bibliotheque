<?php

declare(strict_types=1);

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\ComicSeries;
use App\Service\ComicSeries\ComicSeriesService;

/**
 * Suppression définitive d'une série (DBAL) via API Platform.
 *
 * @implements ProcessorInterface<ComicSeries, void>
 */
final readonly class ComicSeriesPermanentDeleteProcessor implements ProcessorInterface
{
    public function __construct(
        private ComicSeriesService $comicSeriesService,
    ) {
    }

    /**
     * @param ComicSeries $data
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): void
    {
        $id = $data->getId();

        if (null === $id) {
            throw new \LogicException('Impossible de supprimer définitivement une série sans identifiant.');
        }

        $this->comicSeriesService->permanentDelete($id, $data);
    }
}

<?php

declare(strict_types=1);

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\ComicSeries;
use App\Service\ComicSeriesService;

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
        $this->comicSeriesService->permanentDelete($data->getId(), $data);
    }
}

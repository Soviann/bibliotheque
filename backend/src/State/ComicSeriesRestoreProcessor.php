<?php

declare(strict_types=1);

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\ComicSeries;
use App\Service\ComicSeriesService;

/**
 * Restaure une série soft-deleted via API Platform.
 *
 * @implements ProcessorInterface<ComicSeries, ComicSeries>
 */
final readonly class ComicSeriesRestoreProcessor implements ProcessorInterface
{
    public function __construct(
        private ComicSeriesService $comicSeriesService,
    ) {
    }

    /**
     * @param ComicSeries $data
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): ComicSeries
    {
        $this->comicSeriesService->restore($data);

        return $data;
    }
}

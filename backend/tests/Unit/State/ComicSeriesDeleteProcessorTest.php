<?php

declare(strict_types=1);

namespace App\Tests\Unit\State;

use ApiPlatform\Metadata\Operation;
use App\Entity\ComicSeries;
use App\Service\ComicSeriesService;
use App\State\ComicSeriesDeleteProcessor;
use PHPUnit\Framework\TestCase;

/**
 * Tests unitaires pour le processeur de suppression douce ComicSeriesDeleteProcessor.
 */
final class ComicSeriesDeleteProcessorTest extends TestCase
{
    public function testProcessCallsSoftDeleteWithData(): void
    {
        $comic = new ComicSeries();

        $comicSeriesService = $this->createMock(ComicSeriesService::class);
        $comicSeriesService
            ->expects(self::once())
            ->method('softDelete')
            ->with($comic);

        $processor = new ComicSeriesDeleteProcessor($comicSeriesService);
        $operation = $this->createStub(Operation::class);

        $processor->process($comic, $operation);
    }
}

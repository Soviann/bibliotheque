<?php

declare(strict_types=1);

namespace App\Tests\Unit\State;

use ApiPlatform\Metadata\Operation;
use App\Entity\ComicSeries;
use App\Service\ComicSeriesService;
use App\State\ComicSeriesPermanentDeleteProcessor;
use PHPUnit\Framework\TestCase;

/**
 * Tests unitaires pour le processeur de suppression définitive ComicSeriesPermanentDeleteProcessor.
 */
final class ComicSeriesPermanentDeleteProcessorTest extends TestCase
{
    public function testProcessWithValidIdCallsPermanentDelete(): void
    {
        $comic = $this->createMock(ComicSeries::class);
        $comic->method('getId')->willReturn(42);

        $comicSeriesService = $this->createMock(ComicSeriesService::class);
        $comicSeriesService
            ->expects(self::once())
            ->method('permanentDelete')
            ->with(42, $comic);

        $processor = new ComicSeriesPermanentDeleteProcessor($comicSeriesService);
        $operation = $this->createMock(Operation::class);

        $processor->process($comic, $operation);
    }

    public function testProcessWithNullIdThrowsLogicException(): void
    {
        $comic = $this->createMock(ComicSeries::class);
        $comic->method('getId')->willReturn(null);

        $comicSeriesService = $this->createMock(ComicSeriesService::class);
        $comicSeriesService
            ->expects(self::never())
            ->method('permanentDelete');

        $processor = new ComicSeriesPermanentDeleteProcessor($comicSeriesService);
        $operation = $this->createMock(Operation::class);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Impossible de supprimer définitivement une série sans identifiant.');

        $processor->process($comic, $operation);
    }
}

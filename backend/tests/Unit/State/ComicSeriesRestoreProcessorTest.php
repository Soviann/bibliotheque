<?php

declare(strict_types=1);

namespace App\Tests\Unit\State;

use ApiPlatform\Metadata\Operation;
use App\Entity\ComicSeries;
use App\Service\ComicSeriesService;
use App\State\ComicSeriesRestoreProcessor;
use PHPUnit\Framework\TestCase;

/**
 * Tests unitaires pour le processeur de restauration ComicSeriesRestoreProcessor.
 */
final class ComicSeriesRestoreProcessorTest extends TestCase
{
    public function testProcessCallsRestoreAndReturnsData(): void
    {
        $comic = new ComicSeries();

        $comicSeriesService = $this->createMock(ComicSeriesService::class);
        $comicSeriesService
            ->expects(self::once())
            ->method('restore')
            ->with($comic);

        $processor = new ComicSeriesRestoreProcessor($comicSeriesService);
        $operation = $this->createStub(Operation::class);

        $result = $processor->process($comic, $operation);

        self::assertSame($comic, $result);
    }
}

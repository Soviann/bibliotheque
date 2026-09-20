<?php

declare(strict_types=1);

namespace App\Tests\Unit\State;

use ApiPlatform\Metadata\Operation;
use App\Entity\ComicSeries;
use App\Repository\ComicSeriesRepository;
use App\State\ComicSeriesCollectionProvider;
use PHPUnit\Framework\TestCase;

/**
 * Tests unitaires pour ComicSeriesCollectionProvider.
 */
final class ComicSeriesCollectionProviderTest extends TestCase
{
    public function testProvideDelegatesToRepository(): void
    {
        $comic1 = new ComicSeries();
        $comic2 = new ComicSeries();
        $expectedResult = [$comic1, $comic2];

        $repository = $this->createMock(ComicSeriesRepository::class);
        $repository
            ->expects(self::once())
            ->method('findCollectionForApi')
            ->willReturn($expectedResult);

        $provider = new ComicSeriesCollectionProvider($repository);
        $operation = $this->createStub(Operation::class);

        $result = $provider->provide($operation);

        self::assertSame($expectedResult, $result);
    }

    public function testProvideReturnsEmptyArrayWhenNoComics(): void
    {
        $repository = $this->createMock(ComicSeriesRepository::class);
        $repository
            ->expects(self::once())
            ->method('findCollectionForApi')
            ->willReturn([]);

        $provider = new ComicSeriesCollectionProvider($repository);
        $operation = $this->createStub(Operation::class);

        $result = $provider->provide($operation);

        self::assertSame([], $result);
    }
}

<?php

declare(strict_types=1);

namespace App\Tests\Unit\State;

use ApiPlatform\Metadata\Operation;
use App\Entity\ComicSeries;
use App\Repository\ComicSeriesRepository;
use App\State\TrashCollectionProvider;
use PHPUnit\Framework\TestCase;

/**
 * Tests unitaires pour le fournisseur de la corbeille TrashCollectionProvider.
 */
final class TrashCollectionProviderTest extends TestCase
{
    public function testProvideDelegatesToRepository(): void
    {
        $comic1 = new ComicSeries();
        $comic2 = new ComicSeries();
        $expectedResult = [$comic1, $comic2];

        $repository = $this->createMock(ComicSeriesRepository::class);
        $repository
            ->expects(self::once())
            ->method('findTrashed')
            ->willReturn($expectedResult);

        $provider = new TrashCollectionProvider($repository);
        $operation = $this->createStub(Operation::class);

        $result = $provider->provide($operation);

        self::assertSame($expectedResult, $result);
    }

    public function testProvideReturnsEmptyArrayWhenNoDeletedComics(): void
    {
        $repository = $this->createMock(ComicSeriesRepository::class);
        $repository
            ->expects(self::once())
            ->method('findTrashed')
            ->willReturn([]);

        $provider = new TrashCollectionProvider($repository);
        $operation = $this->createStub(Operation::class);

        $result = $provider->provide($operation);

        self::assertSame([], $result);
    }
}

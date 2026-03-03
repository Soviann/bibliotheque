<?php

declare(strict_types=1);

namespace App\Tests\Unit\State;

use ApiPlatform\Metadata\Operation;
use App\Entity\ComicSeries;
use App\Repository\ComicSeriesRepository;
use App\State\SoftDeletedComicSeriesProvider;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query\FilterCollection;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Tests unitaires pour le fournisseur de séries supprimées SoftDeletedComicSeriesProvider.
 */
final class SoftDeletedComicSeriesProviderTest extends TestCase
{
    public function testProvideReturnsDeletedComic(): void
    {
        $comic = $this->createStub(ComicSeries::class);
        $comic->method('isDeleted')->willReturn(true);

        $repository = $this->createStub(ComicSeriesRepository::class);
        $repository
            ->method('find')
            ->willReturn($comic);

        $filterCollection = $this->createMock(FilterCollection::class);
        $filterCollection
            ->expects(self::once())
            ->method('disable')
            ->with('soft_delete');

        $filterCollection
            ->expects(self::once())
            ->method('enable')
            ->with('soft_delete');

        $provider = $this->createProvider($repository, $filterCollection);
        $operation = $this->createStub(Operation::class);

        $result = $provider->provide($operation, ['id' => 7]);

        self::assertSame($comic, $result);
    }

    public function testProvideThrowsNotFoundWhenComicNotFound(): void
    {
        $repository = $this->createStub(ComicSeriesRepository::class);
        $repository
            ->method('find')
            ->willReturn(null);

        $provider = $this->createProvider($repository);
        $operation = $this->createStub(Operation::class);

        $this->expectException(NotFoundHttpException::class);
        $this->expectExceptionMessage('Série non trouvée.');

        $provider->provide($operation, ['id' => 99]);
    }

    public function testProvideThrowsNotFoundWhenComicIsNotDeleted(): void
    {
        $comic = $this->createStub(ComicSeries::class);
        $comic->method('isDeleted')->willReturn(false);

        $repository = $this->createStub(ComicSeriesRepository::class);
        $repository
            ->method('find')
            ->willReturn($comic);

        $provider = $this->createProvider($repository);
        $operation = $this->createStub(Operation::class);

        $this->expectException(NotFoundHttpException::class);

        $provider->provide($operation, ['id' => 5]);
    }

    public function testProvideUsesZeroAsFallbackWhenNoIdInUriVariables(): void
    {
        $repository = $this->createMock(ComicSeriesRepository::class);
        $repository
            ->expects(self::once())
            ->method('find')
            ->with(0)
            ->willReturn(null);

        $provider = $this->createProvider($repository);
        $operation = $this->createStub(Operation::class);

        $this->expectException(NotFoundHttpException::class);

        $provider->provide($operation, []);
    }

    public function testProvideReEnablesFilterEvenOnException(): void
    {
        $repository = $this->createStub(ComicSeriesRepository::class);
        $repository
            ->method('find')
            ->willThrowException(new \RuntimeException('DB error'));

        $filterCollection = $this->createMock(FilterCollection::class);
        $filterCollection
            ->expects(self::once())
            ->method('disable')
            ->with('soft_delete');

        $filterCollection
            ->expects(self::once())
            ->method('enable')
            ->with('soft_delete');

        $provider = $this->createProvider($repository, $filterCollection);
        $operation = $this->createStub(Operation::class);

        $this->expectException(\RuntimeException::class);

        $provider->provide($operation, ['id' => 1]);
    }

    private function createProvider(
        ComicSeriesRepository $repository,
        ?FilterCollection $filterCollection = null,
    ): SoftDeletedComicSeriesProvider {
        $filterCollection ??= $this->createStub(FilterCollection::class);

        $entityManager = $this->createStub(EntityManagerInterface::class);
        $entityManager
            ->method('getFilters')
            ->willReturn($filterCollection);

        return new SoftDeletedComicSeriesProvider(
            $repository,
            $entityManager,
        );
    }
}

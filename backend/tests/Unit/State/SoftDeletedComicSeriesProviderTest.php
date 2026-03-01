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
    private ComicSeriesRepository $repository;
    private EntityManagerInterface $entityManager;
    private FilterCollection $filterCollection;
    private SoftDeletedComicSeriesProvider $provider;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(ComicSeriesRepository::class);
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->filterCollection = $this->createMock(FilterCollection::class);

        $this->entityManager
            ->method('getFilters')
            ->willReturn($this->filterCollection);

        $this->provider = new SoftDeletedComicSeriesProvider(
            $this->repository,
            $this->entityManager,
        );
    }

    public function testProvideReturnsDeletedComic(): void
    {
        $comic = $this->createMock(ComicSeries::class);
        $comic->method('isDeleted')->willReturn(true);

        $this->repository
            ->method('find')
            ->with(7)
            ->willReturn($comic);

        $this->filterCollection
            ->expects(self::once())
            ->method('disable')
            ->with('soft_delete');

        $this->filterCollection
            ->expects(self::once())
            ->method('enable')
            ->with('soft_delete');

        $operation = $this->createMock(Operation::class);

        $result = $this->provider->provide($operation, ['id' => 7]);

        self::assertSame($comic, $result);
    }

    public function testProvideThrowsNotFoundWhenComicNotFound(): void
    {
        $this->repository
            ->method('find')
            ->with(99)
            ->willReturn(null);

        $operation = $this->createMock(Operation::class);

        $this->expectException(NotFoundHttpException::class);
        $this->expectExceptionMessage('Série non trouvée.');

        $this->provider->provide($operation, ['id' => 99]);
    }

    public function testProvideThrowsNotFoundWhenComicIsNotDeleted(): void
    {
        $comic = $this->createMock(ComicSeries::class);
        $comic->method('isDeleted')->willReturn(false);

        $this->repository
            ->method('find')
            ->with(5)
            ->willReturn($comic);

        $operation = $this->createMock(Operation::class);

        $this->expectException(NotFoundHttpException::class);

        $this->provider->provide($operation, ['id' => 5]);
    }

    public function testProvideUsesZeroAsFallbackWhenNoIdInUriVariables(): void
    {
        $this->repository
            ->expects(self::once())
            ->method('find')
            ->with(0)
            ->willReturn(null);

        $operation = $this->createMock(Operation::class);

        $this->expectException(NotFoundHttpException::class);

        $this->provider->provide($operation, []);
    }

    public function testProvideReEnablesFilterEvenOnException(): void
    {
        $this->repository
            ->method('find')
            ->willThrowException(new \RuntimeException('DB error'));

        $this->filterCollection
            ->expects(self::once())
            ->method('disable')
            ->with('soft_delete');

        $this->filterCollection
            ->expects(self::once())
            ->method('enable')
            ->with('soft_delete');

        $operation = $this->createMock(Operation::class);

        $this->expectException(\RuntimeException::class);

        $this->provider->provide($operation, ['id' => 1]);
    }
}

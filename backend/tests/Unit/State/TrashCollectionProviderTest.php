<?php

declare(strict_types=1);

namespace App\Tests\Unit\State;

use ApiPlatform\Metadata\Operation;
use App\Entity\ComicSeries;
use App\Repository\ComicSeriesRepository;
use App\State\TrashCollectionProvider;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query;
use Doctrine\ORM\Query\FilterCollection;
use Doctrine\ORM\QueryBuilder;
use PHPUnit\Framework\TestCase;

/**
 * Tests unitaires pour le fournisseur de la corbeille TrashCollectionProvider.
 */
final class TrashCollectionProviderTest extends TestCase
{
    private ComicSeriesRepository $repository;

    protected function setUp(): void
    {
        $this->repository = $this->createStub(ComicSeriesRepository::class);
    }

    public function testProvideReturnsDeletedComics(): void
    {
        $comic1 = new ComicSeries();
        $comic2 = new ComicSeries();
        $expectedResult = [$comic1, $comic2];

        $query = $this->createStub(Query::class);
        $query->method('getResult')->willReturn($expectedResult);

        $queryBuilder = $this->createStub(QueryBuilder::class);
        $queryBuilder->method('where')->willReturn($queryBuilder);
        $queryBuilder->method('orderBy')->willReturn($queryBuilder);
        $queryBuilder->method('getQuery')->willReturn($query);

        $this->repository
            ->method('createQueryBuilder')
            ->willReturn($queryBuilder);

        $filterCollection = $this->createMock(FilterCollection::class);
        $filterCollection
            ->expects(self::once())
            ->method('disable')
            ->with('soft_delete');

        $filterCollection
            ->expects(self::once())
            ->method('enable')
            ->with('soft_delete');

        $provider = $this->createProvider($filterCollection);
        $operation = $this->createStub(Operation::class);

        $result = $provider->provide($operation);

        self::assertSame($expectedResult, $result);
    }

    public function testProvideReturnsEmptyArrayWhenNoDeletedComics(): void
    {
        $query = $this->createStub(Query::class);
        $query->method('getResult')->willReturn([]);

        $queryBuilder = $this->createStub(QueryBuilder::class);
        $queryBuilder->method('where')->willReturn($queryBuilder);
        $queryBuilder->method('orderBy')->willReturn($queryBuilder);
        $queryBuilder->method('getQuery')->willReturn($query);

        $this->repository
            ->method('createQueryBuilder')
            ->willReturn($queryBuilder);

        $provider = $this->createProvider($this->createStub(FilterCollection::class));
        $operation = $this->createStub(Operation::class);

        $result = $provider->provide($operation);

        self::assertSame([], $result);
    }

    public function testProvideReEnablesFilterEvenOnException(): void
    {
        $queryBuilder = $this->createStub(QueryBuilder::class);
        $queryBuilder->method('where')->willReturn($queryBuilder);
        $queryBuilder->method('orderBy')->willReturn($queryBuilder);
        $queryBuilder->method('getQuery')->willThrowException(new \RuntimeException('DB error'));

        $this->repository
            ->method('createQueryBuilder')
            ->willReturn($queryBuilder);

        $filterCollection = $this->createMock(FilterCollection::class);
        $filterCollection
            ->expects(self::once())
            ->method('disable')
            ->with('soft_delete');

        $filterCollection
            ->expects(self::once())
            ->method('enable')
            ->with('soft_delete');

        $provider = $this->createProvider($filterCollection);
        $operation = $this->createStub(Operation::class);

        $this->expectException(\RuntimeException::class);

        $provider->provide($operation);
    }

    private function createProvider(FilterCollection $filterCollection): TrashCollectionProvider
    {
        $entityManager = $this->createStub(EntityManagerInterface::class);
        $entityManager
            ->method('getFilters')
            ->willReturn($filterCollection);

        return new TrashCollectionProvider(
            $this->repository,
            $entityManager,
        );
    }
}

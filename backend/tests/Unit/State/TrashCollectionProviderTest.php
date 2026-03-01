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
    private EntityManagerInterface $entityManager;
    private FilterCollection $filterCollection;
    private TrashCollectionProvider $provider;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(ComicSeriesRepository::class);
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->filterCollection = $this->createMock(FilterCollection::class);

        $this->entityManager
            ->method('getFilters')
            ->willReturn($this->filterCollection);

        $this->provider = new TrashCollectionProvider(
            $this->repository,
            $this->entityManager,
        );
    }

    public function testProvideReturnsDeletedComics(): void
    {
        $comic1 = new ComicSeries();
        $comic2 = new ComicSeries();
        $expectedResult = [$comic1, $comic2];

        $query = $this->createMock(Query::class);
        $query->method('getResult')->willReturn($expectedResult);

        $queryBuilder = $this->createMock(QueryBuilder::class);
        $queryBuilder->method('where')->with('c.deletedAt IS NOT NULL')->willReturn($queryBuilder);
        $queryBuilder->method('orderBy')->with('c.deletedAt', 'DESC')->willReturn($queryBuilder);
        $queryBuilder->method('getQuery')->willReturn($query);

        $this->repository
            ->method('createQueryBuilder')
            ->with('c')
            ->willReturn($queryBuilder);

        $this->filterCollection
            ->expects(self::once())
            ->method('disable')
            ->with('soft_delete');

        $this->filterCollection
            ->expects(self::once())
            ->method('enable')
            ->with('soft_delete');

        $operation = $this->createMock(Operation::class);

        $result = $this->provider->provide($operation);

        self::assertSame($expectedResult, $result);
    }

    public function testProvideReturnsEmptyArrayWhenNoDeletedComics(): void
    {
        $query = $this->createMock(Query::class);
        $query->method('getResult')->willReturn([]);

        $queryBuilder = $this->createMock(QueryBuilder::class);
        $queryBuilder->method('where')->willReturn($queryBuilder);
        $queryBuilder->method('orderBy')->willReturn($queryBuilder);
        $queryBuilder->method('getQuery')->willReturn($query);

        $this->repository
            ->method('createQueryBuilder')
            ->with('c')
            ->willReturn($queryBuilder);

        $operation = $this->createMock(Operation::class);

        $result = $this->provider->provide($operation);

        self::assertSame([], $result);
    }

    public function testProvideReEnablesFilterEvenOnException(): void
    {
        $queryBuilder = $this->createMock(QueryBuilder::class);
        $queryBuilder->method('where')->willReturn($queryBuilder);
        $queryBuilder->method('orderBy')->willReturn($queryBuilder);
        $queryBuilder->method('getQuery')->willThrowException(new \RuntimeException('DB error'));

        $this->repository
            ->method('createQueryBuilder')
            ->with('c')
            ->willReturn($queryBuilder);

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

        $this->provider->provide($operation);
    }
}

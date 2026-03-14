<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\DTO\PurgeableSeries;
use App\Entity\ComicSeries;
use App\Repository\ComicSeriesRepository;
use App\Service\ComicSeriesService;
use App\Service\PurgeService;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query\FilterCollection;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Tests unitaires pour PurgeService.
 */
final class PurgeServiceTest extends TestCase
{
    private ComicSeriesRepository&MockObject $comicSeriesRepository;
    private ComicSeriesService&MockObject $comicSeriesService;
    private EntityManagerInterface&MockObject $entityManager;
    private FilterCollection&MockObject $filterCollection;
    private PurgeService $purgeService;

    protected function setUp(): void
    {
        $this->comicSeriesRepository = $this->createMock(ComicSeriesRepository::class);
        $this->comicSeriesService = $this->createMock(ComicSeriesService::class);
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->filterCollection = $this->createMock(FilterCollection::class);

        $this->entityManager->method('getFilters')->willReturn($this->filterCollection);

        $this->purgeService = new PurgeService(
            $this->comicSeriesRepository,
            $this->comicSeriesService,
            $this->entityManager,
        );
    }

    public function testExecutePurgeCallsPermanentDeleteForEachId(): void
    {
        $series1 = $this->createComicSeriesMock(1, 'Naruto', new \DateTimeImmutable());
        $series2 = $this->createComicSeriesMock(2, 'One Piece', new \DateTimeImmutable());

        $this->comicSeriesRepository->method('findBy')
            ->with(['id' => [1, 2]])
            ->willReturn([$series1, $series2]);

        $this->comicSeriesService->expects(self::exactly(2))
            ->method('permanentDelete')
            ->willReturnCallback(static function (int $id, ComicSeries $series) use ($series1, $series2): void {
                match ($id) {
                    1 => self::assertSame($series1, $series),
                    2 => self::assertSame($series2, $series),
                    default => self::fail('Unexpected series ID: '.$id),
                };
            });

        $count = $this->purgeService->executePurge([1, 2]);

        self::assertSame(2, $count);
    }

    public function testExecutePurgeDisablesAndReenablesSoftDeleteFilter(): void
    {
        $this->filterCollection->expects(self::once())->method('disable')->with('soft_delete');
        $this->filterCollection->expects(self::once())->method('enable')->with('soft_delete');

        $this->purgeService->executePurge([999]);
    }

    public function testExecutePurgeSkipsNonexistentIds(): void
    {
        $series1 = $this->createComicSeriesMock(1, 'Naruto', new \DateTimeImmutable());

        $this->comicSeriesRepository->method('findBy')
            ->with(['id' => [1, 999]])
            ->willReturn([$series1]);

        $this->comicSeriesService->expects(self::once())
            ->method('permanentDelete')
            ->with(1, $series1);

        $count = $this->purgeService->executePurge([1, 999]);

        self::assertSame(1, $count);
    }

    public function testExecutePurgeReturnsZeroForEmptyArray(): void
    {
        $count = $this->purgeService->executePurge([]);

        self::assertSame(0, $count);
    }

    public function testExecutePurgeUsesBatchLoadingNotIndividualQueries(): void
    {
        $series1 = $this->createComicSeriesMock(1, 'Naruto', new \DateTimeImmutable());
        $series2 = $this->createComicSeriesMock(2, 'One Piece', new \DateTimeImmutable());
        $series3 = $this->createComicSeriesMock(3, 'Bleach', new \DateTimeImmutable());

        // findBy doit être appelé une seule fois (batch), jamais find()
        $this->comicSeriesRepository->expects(self::once())
            ->method('findBy')
            ->with(['id' => [1, 2, 3]])
            ->willReturn([$series1, $series2, $series3]);

        $this->comicSeriesRepository->expects(self::never())->method('find');

        $count = $this->purgeService->executePurge([1, 2, 3]);

        self::assertSame(3, $count);
    }

    public function testPurgeableSeriesIsJsonSerializable(): void
    {
        $dto = new PurgeableSeries(
            deletedAt: new \DateTimeImmutable('2025-06-15T10:30:00+00:00'),
            id: 42,
            title: 'Test Series',
        );

        $json = \json_encode($dto);
        self::assertNotFalse($json);

        $data = \json_decode($json, true);
        self::assertSame(42, $data['id']);
        self::assertSame('Test Series', $data['title']);
        self::assertSame('2025-06-15T10:30:00+00:00', $data['deletedAt']);
    }

    private function createComicSeriesMock(int $id, string $title, \DateTimeImmutable $deletedAt): ComicSeries&MockObject
    {
        $series = $this->createMock(ComicSeries::class);
        $series->method('getDeletedAt')->willReturn($deletedAt);
        $series->method('getId')->willReturn($id);
        $series->method('getTitle')->willReturn($title);

        return $series;
    }
}

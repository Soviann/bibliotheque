<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Dto\Input\ComicSeriesInput;
use App\Entity\ComicSeries;
use App\Enum\ComicStatus;
use App\Service\ComicSeriesMapper;
use App\Service\ComicSeriesService;
use App\Service\CoverRemoverInterface;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\DriverException;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Tests unitaires pour ComicSeriesService.
 */
#[CoversClass(ComicSeriesService::class)]
class ComicSeriesServiceTest extends TestCase
{
    private ComicSeriesService $service;
    private ComicSeriesMapper&MockObject $comicSeriesMapper;
    private Connection&MockObject $connection;
    private CoverRemoverInterface&MockObject $coverRemover;
    private EntityManagerInterface&MockObject $entityManager;

    protected function setUp(): void
    {
        $this->comicSeriesMapper = $this->createMock(ComicSeriesMapper::class);
        $this->connection = $this->createMock(Connection::class);
        $this->coverRemover = $this->createMock(CoverRemoverInterface::class);
        $this->entityManager = $this->createMock(EntityManagerInterface::class);

        $this->service = new ComicSeriesService(
            $this->comicSeriesMapper,
            $this->connection,
            $this->coverRemover,
            $this->entityManager,
        );
    }

    public function testCreatePersistsAndFlushes(): void
    {
        $input = new ComicSeriesInput();
        $comic = new ComicSeries();

        $this->comicSeriesMapper->expects(self::once())
            ->method('mapToEntity')
            ->with($input, null)
            ->willReturn($comic);

        $this->entityManager->expects(self::once())
            ->method('persist')
            ->with($comic);

        $this->entityManager->expects(self::once())
            ->method('flush');

        $result = $this->service->create($input);

        self::assertSame($comic, $result);
    }

    public function testCreateThrowsDriverException(): void
    {
        $input = new ComicSeriesInput();
        $comic = new ComicSeries();

        $this->comicSeriesMapper->method('mapToEntity')->willReturn($comic);
        $this->entityManager->method('flush')
            ->willThrowException($this->createMock(DriverException::class));

        $this->expectException(DriverException::class);

        $this->service->create($input);
    }

    public function testUpdateMapsAndFlushes(): void
    {
        $input = new ComicSeriesInput();
        $comic = new ComicSeries();

        $this->comicSeriesMapper->expects(self::once())
            ->method('mapToEntity')
            ->with($input, $comic)
            ->willReturn($comic);

        $this->entityManager->expects(self::never())
            ->method('persist');

        $this->entityManager->expects(self::once())
            ->method('flush');

        $result = $this->service->update($input, $comic);

        self::assertSame($comic, $result);
    }

    public function testSoftDeleteRemovesAndFlushes(): void
    {
        $comic = new ComicSeries();

        $this->entityManager->expects(self::once())
            ->method('remove')
            ->with($comic);

        $this->entityManager->expects(self::once())
            ->method('flush');

        $this->service->softDelete($comic);
    }

    public function testMoveToLibrarySetsStatusBuying(): void
    {
        $comic = new ComicSeries();
        $comic->setStatus(ComicStatus::WISHLIST);

        $this->entityManager->expects(self::once())
            ->method('flush');

        $this->service->moveToLibrary($comic);

        self::assertSame(ComicStatus::BUYING, $comic->getStatus());
    }

    public function testRestoreCallsRestoreAndFlushes(): void
    {
        $comic = $this->createMock(ComicSeries::class);

        $comic->expects(self::once())
            ->method('restore');

        $this->entityManager->expects(self::once())
            ->method('flush');

        $this->service->restore($comic);
    }

    public function testPermanentDeleteRemovesCoverAndDbalCascade(): void
    {
        $comic = new ComicSeries();
        $id = 42;

        $this->coverRemover->expects(self::once())
            ->method('remove')
            ->with($comic);

        $this->connection->expects(self::exactly(3))
            ->method('delete')
            ->willReturnCallback(static function (string $table, array $criteria) use ($id): int {
                static $call = 0;
                ++$call;
                match ($call) {
                    1 => self::assertSame(['comic_series_id' => $id, 'table' => $table], ['comic_series_id' => $criteria['comic_series_id'], 'table' => 'comic_series_author']),
                    2 => self::assertSame(['comic_series_id' => $id, 'table' => $table], ['comic_series_id' => $criteria['comic_series_id'], 'table' => 'tome']),
                    3 => self::assertSame(['id' => $id, 'table' => $table], ['id' => $criteria['id'], 'table' => 'comic_series']),
                };

                return 1;
            });

        $this->service->permanentDelete($id, $comic);
    }
}

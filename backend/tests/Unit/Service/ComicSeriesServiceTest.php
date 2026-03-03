<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Entity\ComicSeries;
use App\Enum\ComicStatus;
use App\Event\ComicSeriesDeletedEvent;
use App\Service\ComicSeriesService;
use App\Service\CoverRemoverInterface;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Tests unitaires pour ComicSeriesService.
 */
final class ComicSeriesServiceTest extends TestCase
{
    private Connection&MockObject $connection;
    private CoverRemoverInterface&MockObject $coverRemover;
    private EntityManagerInterface&MockObject $entityManager;
    private EventDispatcherInterface&MockObject $eventDispatcher;
    private ComicSeriesService $service;

    protected function setUp(): void
    {
        $this->connection = $this->createMock(Connection::class);
        $this->coverRemover = $this->createMock(CoverRemoverInterface::class);
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->eventDispatcher = $this->createMock(EventDispatcherInterface::class);

        $this->service = new ComicSeriesService(
            $this->connection,
            $this->coverRemover,
            $this->entityManager,
            $this->eventDispatcher,
        );
    }

    /**
     * Teste que softDelete appelle remove puis flush sur l'EntityManager.
     */
    public function testSoftDeleteCallsRemoveAndFlush(): void
    {
        $comic = new ComicSeries();

        $this->entityManager
            ->expects(self::once())
            ->method('remove')
            ->with($comic);

        $this->entityManager
            ->expects(self::once())
            ->method('flush');

        $this->service->softDelete($comic);
    }

    /**
     * Teste que softDelete ne supprime PAS les fichiers de couverture (preservation physique).
     */
    public function testSoftDeleteDoesNotCallCoverRemover(): void
    {
        $comic = new ComicSeries();

        $this->coverRemover
            ->expects(self::never())
            ->method('remove');

        $this->service->softDelete($comic);
    }

    /**
     * Teste que moveToLibrary passe le statut \u00e0 BUYING et flush.
     */
    public function testMoveToLibrarySetsStatusToBuyingAndFlushes(): void
    {
        $comic = new ComicSeries();
        $comic->setStatus(ComicStatus::WISHLIST);

        $this->entityManager
            ->expects(self::once())
            ->method('flush');

        $this->service->moveToLibrary($comic);

        self::assertSame(ComicStatus::BUYING, $comic->getStatus());
    }

    /**
     * Teste que restore appelle restore() sur la s\u00e9rie et flush.
     */
    public function testRestoreCallsRestoreOnComicAndFlushes(): void
    {
        $comic = $this->createMock(ComicSeries::class);

        $comic
            ->expects(self::once())
            ->method('restore');

        $this->entityManager
            ->expects(self::once())
            ->method('flush');

        $this->service->restore($comic);
    }

    /**
     * Teste que permanentDelete supprime la couverture puis les entr\u00e9es DBAL dans l'ordre correct.
     */
    public function testPermanentDeleteRemovesCoverThenDbalEntriesInOrder(): void
    {
        $comic = new ComicSeries();
        $id = 42;

        $this->coverRemover
            ->expects(self::once())
            ->method('remove')
            ->with($comic);

        // V\u00e9rifie l'ordre des appels DBAL : comic_series_author, tome, comic_series
        $deleteCallOrder = [];
        $this->connection
            ->expects(self::exactly(3))
            ->method('delete')
            ->willReturnCallback(function (string $table, array $criteria) use (&$deleteCallOrder, $id): int {
                $deleteCallOrder[] = $table;

                if ('comic_series' === $table) {
                    self::assertSame(['id' => $id], $criteria);
                } else {
                    self::assertSame(['comic_series_id' => $id], $criteria);
                }

                return 1;
            });

        $this->service->permanentDelete($id, $comic);

        self::assertSame(
            ['comic_series_author', 'tome', 'comic_series'],
            $deleteCallOrder,
        );
    }

    /**
     * Teste que permanentDelete dispatche un ComicSeriesDeletedEvent.
     */
    public function testPermanentDeleteDispatchesDeletedEvent(): void
    {
        $comic = new ComicSeries();
        $comic->setTitle('Bleach');
        $id = 7;

        $this->eventDispatcher
            ->expects(self::once())
            ->method('dispatch')
            ->with(self::callback(static function (object $event): bool {
                return $event instanceof ComicSeriesDeletedEvent
                    && 7 === $event->getId()
                    && 'Bleach' === $event->getTitle();
            }));

        $this->service->permanentDelete($id, $comic);
    }
}

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
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Tests unitaires pour ComicSeriesService.
 */
final class ComicSeriesServiceTest extends TestCase
{
    private Connection $connection;
    private CoverRemoverInterface $coverRemover;
    private EntityManagerInterface $entityManager;
    private EventDispatcherInterface $eventDispatcher;
    private ComicSeriesService $service;

    protected function setUp(): void
    {
        $this->connection = $this->createStub(Connection::class);
        $this->coverRemover = $this->createStub(CoverRemoverInterface::class);
        $this->entityManager = $this->createStub(EntityManagerInterface::class);
        $this->eventDispatcher = $this->createStub(EventDispatcherInterface::class);

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
        $em = $this->createMock(EntityManagerInterface::class);
        $this->rebuildService(entityManager: $em);

        $comic = new ComicSeries();

        $em->expects(self::once())
            ->method('remove')
            ->with($comic);

        $em->expects(self::once())
            ->method('flush');

        $this->service->softDelete($comic);
    }

    /**
     * Teste que softDelete ne supprime PAS les fichiers de couverture (preservation physique).
     */
    public function testSoftDeleteDoesNotCallCoverRemover(): void
    {
        $coverRemover = $this->createMock(CoverRemoverInterface::class);
        $this->rebuildService(coverRemover: $coverRemover);

        $comic = new ComicSeries();

        $coverRemover->expects(self::never())
            ->method('remove');

        $this->service->softDelete($comic);
    }

    /**
     * Teste que moveToLibrary passe le statut \u00e0 BUYING et flush.
     */
    public function testMoveToLibrarySetsStatusToBuyingAndFlushes(): void
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $this->rebuildService(entityManager: $em);

        $comic = new ComicSeries();
        $comic->setStatus(ComicStatus::WISHLIST);

        $em->expects(self::once())
            ->method('flush');

        $this->service->moveToLibrary($comic);

        self::assertSame(ComicStatus::BUYING, $comic->getStatus());
    }

    /**
     * Teste que restore appelle restore() sur la s\u00e9rie et flush.
     */
    public function testRestoreCallsRestoreOnComicAndFlushes(): void
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $this->rebuildService(entityManager: $em);

        $comic = $this->createMock(ComicSeries::class);

        $comic->expects(self::once())
            ->method('restore');

        $em->expects(self::once())
            ->method('flush');

        $this->service->restore($comic);
    }

    /**
     * Teste que permanentDelete supprime la couverture puis les entr\u00e9es DBAL dans l'ordre correct.
     */
    public function testPermanentDeleteRemovesCoverThenDbalEntriesInOrder(): void
    {
        $coverRemover = $this->createMock(CoverRemoverInterface::class);
        $connection = $this->createMock(Connection::class);
        $this->rebuildService(connection: $connection, coverRemover: $coverRemover);

        $comic = new ComicSeries();
        $id = 42;

        $coverRemover->expects(self::once())
            ->method('remove')
            ->with($comic);

        // V\u00e9rifie l'ordre des appels DBAL : comic_series_author, tome, comic_series
        $deleteCallOrder = [];
        $connection->expects(self::exactly(3))
            ->method('delete')
            ->willReturnCallback(static function (string $table, array $criteria) use (&$deleteCallOrder, $id): int {
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
        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $this->rebuildService(eventDispatcher: $eventDispatcher);

        $comic = new ComicSeries();
        $comic->setTitle('Bleach');
        $id = 7;

        $eventDispatcher->expects(self::once())
            ->method('dispatch')
            ->with(self::callback(static fn (object $event): bool => $event instanceof ComicSeriesDeletedEvent
                && 7 === $event->getId()
                && 'Bleach' === $event->getTitle()));

        $this->service->permanentDelete($id, $comic);
    }

    private function rebuildService(
        ?Connection $connection = null,
        ?CoverRemoverInterface $coverRemover = null,
        ?EntityManagerInterface $entityManager = null,
        ?EventDispatcherInterface $eventDispatcher = null,
    ): void {
        if ($connection instanceof Connection) {
            $this->connection = $connection;
        }
        if ($coverRemover instanceof CoverRemoverInterface) {
            $this->coverRemover = $coverRemover;
        }
        if ($entityManager instanceof EntityManagerInterface) {
            $this->entityManager = $entityManager;
        }
        if ($eventDispatcher instanceof EventDispatcherInterface) {
            $this->eventDispatcher = $eventDispatcher;
        }
        $this->service = new ComicSeriesService(
            $this->connection,
            $this->coverRemover,
            $this->entityManager,
            $this->eventDispatcher,
        );
    }
}

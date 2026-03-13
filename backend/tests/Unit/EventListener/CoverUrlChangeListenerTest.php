<?php

declare(strict_types=1);

namespace App\Tests\Unit\EventListener;

use App\Entity\ComicSeries;
use App\EventListener\CoverUrlChangeListener;
use App\Service\CoverDownloader;
use App\Tests\Factory\EntityFactory;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\PreUpdateEventArgs;
use Doctrine\ORM\Events;
use Doctrine\ORM\UnitOfWork;
use PHPUnit\Framework\TestCase;

/**
 * Tests unitaires pour CoverUrlChangeListener.
 */
final class CoverUrlChangeListenerTest extends TestCase
{
    private CoverDownloader $coverDownloader;
    private CoverUrlChangeListener $listener;
    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        $this->coverDownloader = $this->createMock(CoverDownloader::class);
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->listener = new CoverUrlChangeListener($this->coverDownloader, $this->entityManager);
    }

    public function testDownloadsWhenCoverUrlChanges(): void
    {
        $series = EntityFactory::createComicSeries('Test');

        $changeSet = ['coverUrl' => ['https://old.com/cover.jpg', 'https://new.com/cover.jpg']];
        $args = new PreUpdateEventArgs($series, $this->entityManager, $changeSet);

        $this->coverDownloader->expects(self::once())
            ->method('downloadAndStore')
            ->with($series, 'https://new.com/cover.jpg')
            ->willReturn(true);

        $uow = $this->createMock(UnitOfWork::class);
        $uow->expects(self::once())->method('recomputeSingleEntityChangeSet');
        $this->entityManager->method('getUnitOfWork')->willReturn($uow);
        $this->entityManager->method('getClassMetadata')->willReturn(
            new \Doctrine\ORM\Mapping\ClassMetadata(ComicSeries::class)
        );

        $this->listener->preUpdate($args);
    }

    public function testDownloadsWhenCoverUrlSetFromNull(): void
    {
        $series = EntityFactory::createComicSeries('Test');

        $changeSet = ['coverUrl' => [null, 'https://new.com/cover.jpg']];
        $args = new PreUpdateEventArgs($series, $this->entityManager, $changeSet);

        $this->coverDownloader->expects(self::once())
            ->method('downloadAndStore')
            ->with($series, 'https://new.com/cover.jpg')
            ->willReturn(true);

        $uow = $this->createMock(UnitOfWork::class);
        $uow->expects(self::once())->method('recomputeSingleEntityChangeSet');
        $this->entityManager->method('getUnitOfWork')->willReturn($uow);
        $this->entityManager->method('getClassMetadata')->willReturn(
            new \Doctrine\ORM\Mapping\ClassMetadata(ComicSeries::class)
        );

        $this->listener->preUpdate($args);
    }

    public function testDoesNotDownloadWhenCoverUrlCleared(): void
    {
        $series = EntityFactory::createComicSeries('Test');

        $changeSet = ['coverUrl' => ['https://old.com/cover.jpg', null]];
        $args = new PreUpdateEventArgs($series, $this->entityManager, $changeSet);

        $this->coverDownloader->expects(self::never())
            ->method('downloadAndStore');

        $this->listener->preUpdate($args);
    }

    public function testDoesNotDownloadWhenCoverUrlUnchanged(): void
    {
        $series = EntityFactory::createComicSeries('Test');

        $changeSet = ['title' => ['Old Title', 'New Title']];
        $args = new PreUpdateEventArgs($series, $this->entityManager, $changeSet);

        $this->coverDownloader->expects(self::never())
            ->method('downloadAndStore');

        $this->listener->preUpdate($args);
    }

    public function testIgnoresNonComicSeriesEntities(): void
    {
        $entity = new \stdClass();

        $changeSet = ['coverUrl' => [null, 'https://new.com/cover.jpg']];
        $args = new PreUpdateEventArgs($entity, $this->entityManager, $changeSet);

        $this->coverDownloader->expects(self::never())
            ->method('downloadAndStore');

        $this->listener->preUpdate($args);
    }

    public function testDoesNotDownloadWhenCoverUrlSameValue(): void
    {
        $series = EntityFactory::createComicSeries('Test');

        $changeSet = ['coverUrl' => ['https://same.com/cover.jpg', 'https://same.com/cover.jpg']];
        $args = new PreUpdateEventArgs($series, $this->entityManager, $changeSet);

        $this->coverDownloader->expects(self::never())
            ->method('downloadAndStore');

        $this->listener->preUpdate($args);
    }
}

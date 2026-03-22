<?php

declare(strict_types=1);

namespace App\Tests\Unit\EventListener;

use App\Entity\ComicSeries;
use App\EventListener\CoverUrlChangeListener;
use App\Message\DownloadCoverMessage;
use App\Tests\Factory\EntityFactory;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\PreUpdateEventArgs;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Tests unitaires pour CoverUrlChangeListener.
 */
final class CoverUrlChangeListenerTest extends TestCase
{
    private EntityManagerInterface&MockObject $entityManager;
    private CoverUrlChangeListener $listener;
    private MessageBusInterface&MockObject $messageBus;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->messageBus = $this->createMock(MessageBusInterface::class);
        $this->listener = new CoverUrlChangeListener($this->messageBus);
    }

    public function testDispatchesMessageWhenCoverUrlChanges(): void
    {
        $series = $this->createSeriesWithId(42);

        $changeSet = ['coverUrl' => ['https://old.com/cover.jpg', 'https://new.com/cover.jpg']];
        $args = new PreUpdateEventArgs($series, $this->entityManager, $changeSet);

        $this->messageBus->expects(self::once())
            ->method('dispatch')
            ->with(self::callback(
                static fn ($msg) => $msg instanceof DownloadCoverMessage
                    && 42 === $msg->seriesId
                    && 'https://new.com/cover.jpg' === $msg->coverUrl,
            ))
            ->willReturn(new Envelope(new DownloadCoverMessage(42, 'https://new.com/cover.jpg')));

        $this->listener->preUpdate($args);
    }

    public function testDispatchesMessageWhenCoverUrlSetFromNull(): void
    {
        $series = $this->createSeriesWithId(10);

        $changeSet = ['coverUrl' => [null, 'https://new.com/cover.jpg']];
        $args = new PreUpdateEventArgs($series, $this->entityManager, $changeSet);

        $this->messageBus->expects(self::once())
            ->method('dispatch')
            ->with(self::callback(
                static fn ($msg) => $msg instanceof DownloadCoverMessage
                    && 10 === $msg->seriesId
                    && 'https://new.com/cover.jpg' === $msg->coverUrl,
            ))
            ->willReturn(new Envelope(new DownloadCoverMessage(10, 'https://new.com/cover.jpg')));

        $this->listener->preUpdate($args);
    }

    public function testDoesNotDispatchWhenCoverUrlCleared(): void
    {
        $series = EntityFactory::createComicSeries('Test');

        $changeSet = ['coverUrl' => ['https://old.com/cover.jpg', null]];
        $args = new PreUpdateEventArgs($series, $this->entityManager, $changeSet);

        $this->messageBus->expects(self::never())->method('dispatch');

        $this->listener->preUpdate($args);
    }

    public function testDoesNotDispatchWhenCoverUrlUnchanged(): void
    {
        $series = EntityFactory::createComicSeries('Test');

        $changeSet = ['title' => ['Old Title', 'New Title']];
        $args = new PreUpdateEventArgs($series, $this->entityManager, $changeSet);

        $this->messageBus->expects(self::never())->method('dispatch');

        $this->listener->preUpdate($args);
    }

    public function testIgnoresNonComicSeriesEntities(): void
    {
        $entity = new \stdClass();

        $changeSet = ['coverUrl' => [null, 'https://new.com/cover.jpg']];
        $args = new PreUpdateEventArgs($entity, $this->entityManager, $changeSet);

        $this->messageBus->expects(self::never())->method('dispatch');

        $this->listener->preUpdate($args);
    }

    public function testDoesNotDispatchWhenCoverUrlSameValue(): void
    {
        $series = EntityFactory::createComicSeries('Test');

        $changeSet = ['coverUrl' => ['https://same.com/cover.jpg', 'https://same.com/cover.jpg']];
        $args = new PreUpdateEventArgs($series, $this->entityManager, $changeSet);

        $this->messageBus->expects(self::never())->method('dispatch');

        $this->listener->preUpdate($args);
    }

    public function testDoesNotDispatchWhenSeriesHasNoId(): void
    {
        $series = EntityFactory::createComicSeries('Test');

        $changeSet = ['coverUrl' => [null, 'https://new.com/cover.jpg']];
        $args = new PreUpdateEventArgs($series, $this->entityManager, $changeSet);

        $this->messageBus->expects(self::never())->method('dispatch');

        $this->listener->preUpdate($args);
    }

    private function createSeriesWithId(int $id): ComicSeries&MockObject
    {
        $series = $this->createMock(ComicSeries::class);
        $series->method('getId')->willReturn($id);

        return $series;
    }
}

<?php

declare(strict_types=1);

namespace App\Tests\Unit\EventListener;

use App\Entity\ComicSeries;
use App\Event\ComicSeriesCreatedEvent;
use App\Event\ComicSeriesDeletedEvent;
use App\Event\ComicSeriesUpdatedEvent;
use App\EventListener\ComicSeriesEventListener;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\PostPersistEventArgs;
use Doctrine\ORM\Event\PostRemoveEventArgs;
use Doctrine\ORM\Event\PostUpdateEventArgs;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Tests unitaires pour ComicSeriesEventListener.
 */
final class ComicSeriesEventListenerTest extends TestCase
{
    private EntityManagerInterface $entityManager;
    private EventDispatcherInterface&MockObject $eventDispatcher;
    private ComicSeriesEventListener $listener;

    protected function setUp(): void
    {
        $this->eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $this->entityManager = $this->createStub(EntityManagerInterface::class);

        $this->listener = new ComicSeriesEventListener($this->eventDispatcher);
    }

    /**
     * Teste que postPersist dispatche un ComicSeriesCreatedEvent.
     */
    public function testPostPersistDispatchesCreatedEvent(): void
    {
        $comic = new ComicSeries();
        $comic->setTitle('Naruto');

        $args = new PostPersistEventArgs($comic, $this->entityManager);

        $this->eventDispatcher
            ->expects(self::once())
            ->method('dispatch')
            ->with(self::callback(static function (object $event) use ($comic): bool {
                return $event instanceof ComicSeriesCreatedEvent
                    && $event->getComicSeries() === $comic;
            }));

        $this->listener->postPersist($args);
    }

    /**
     * Teste que postPersist ne fait rien pour une entité non-ComicSeries.
     */
    public function testPostPersistIgnoresNonComicSeriesEntity(): void
    {
        $entity = new \stdClass();
        $args = new PostPersistEventArgs($entity, $this->entityManager);

        $this->eventDispatcher
            ->expects(self::never())
            ->method('dispatch');

        $this->listener->postPersist($args);
    }

    /**
     * Teste que postUpdate dispatche un ComicSeriesUpdatedEvent pour une mise à jour normale.
     */
    public function testPostUpdateDispatchesUpdatedEvent(): void
    {
        $comic = new ComicSeries();
        $comic->setTitle('One Piece');

        $args = new PostUpdateEventArgs($comic, $this->entityManager);

        $this->eventDispatcher
            ->expects(self::once())
            ->method('dispatch')
            ->with(self::callback(static function (object $event) use ($comic): bool {
                return $event instanceof ComicSeriesUpdatedEvent
                    && $event->getComicSeries() === $comic;
            }));

        $this->listener->postUpdate($args);
    }

    /**
     * Teste que postUpdate dispatche un ComicSeriesDeletedEvent pour un soft-delete.
     */
    public function testPostUpdateDispatchesDeletedEventOnSoftDelete(): void
    {
        $comic = new ComicSeries();
        $comic->setTitle('Bleach');
        // Simuler un soft-delete
        $comic->delete();

        // On a besoin d'un ID pour le DeletedEvent — utiliser Reflection
        $reflection = new \ReflectionProperty(ComicSeries::class, 'id');
        $reflection->setValue($comic, 7);

        $args = new PostUpdateEventArgs($comic, $this->entityManager);

        $this->eventDispatcher
            ->expects(self::once())
            ->method('dispatch')
            ->with(self::callback(static function (object $event): bool {
                return $event instanceof ComicSeriesDeletedEvent
                    && 7 === $event->getId()
                    && 'Bleach' === $event->getTitle();
            }));

        $this->listener->postUpdate($args);
    }

    /**
     * Teste que postUpdate ne fait rien pour une entité non-ComicSeries.
     */
    public function testPostUpdateIgnoresNonComicSeriesEntity(): void
    {
        $entity = new \stdClass();
        $args = new PostUpdateEventArgs($entity, $this->entityManager);

        $this->eventDispatcher
            ->expects(self::never())
            ->method('dispatch');

        $this->listener->postUpdate($args);
    }

    /**
     * Teste que postRemove dispatche un ComicSeriesDeletedEvent.
     */
    public function testPostRemoveDispatchesDeletedEvent(): void
    {
        $comic = new ComicSeries();
        $comic->setTitle('Dragon Ball');

        $reflection = new \ReflectionProperty(ComicSeries::class, 'id');
        $reflection->setValue($comic, 42);

        $args = new PostRemoveEventArgs($comic, $this->entityManager);

        $this->eventDispatcher
            ->expects(self::once())
            ->method('dispatch')
            ->with(self::callback(static function (object $event): bool {
                return $event instanceof ComicSeriesDeletedEvent
                    && 42 === $event->getId()
                    && 'Dragon Ball' === $event->getTitle();
            }));

        $this->listener->postRemove($args);
    }

    /**
     * Teste que postRemove ne fait rien pour une entité non-ComicSeries.
     */
    public function testPostRemoveIgnoresNonComicSeriesEntity(): void
    {
        $entity = new \stdClass();
        $args = new PostRemoveEventArgs($entity, $this->entityManager);

        $this->eventDispatcher
            ->expects(self::never())
            ->method('dispatch');

        $this->listener->postRemove($args);
    }
}

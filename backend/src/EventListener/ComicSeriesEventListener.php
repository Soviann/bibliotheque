<?php

declare(strict_types=1);

namespace App\EventListener;

use App\Entity\ComicSeries;
use App\Event\ComicSeriesCreatedEvent;
use App\Event\ComicSeriesDeletedEvent;
use App\Event\ComicSeriesUpdatedEvent;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Event\PostPersistEventArgs;
use Doctrine\ORM\Event\PostRemoveEventArgs;
use Doctrine\ORM\Event\PostUpdateEventArgs;
use Doctrine\ORM\Events;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Listener Doctrine qui dispatche des événements domaine pour ComicSeries.
 *
 * - postPersist → ComicSeriesCreatedEvent
 * - postUpdate → ComicSeriesUpdatedEvent (ou ComicSeriesDeletedEvent si soft-delete)
 * - postRemove → ComicSeriesDeletedEvent
 */
#[AsDoctrineListener(event: Events::postPersist)]
#[AsDoctrineListener(event: Events::postRemove)]
#[AsDoctrineListener(event: Events::postUpdate)]
class ComicSeriesEventListener
{
    public function __construct(
        private readonly EventDispatcherInterface $eventDispatcher,
    ) {
    }

    public function postPersist(PostPersistEventArgs $args): void
    {
        $entity = $args->getObject();

        if (!$entity instanceof ComicSeries) {
            return;
        }

        $this->eventDispatcher->dispatch(new ComicSeriesCreatedEvent($entity));
    }

    public function postRemove(PostRemoveEventArgs $args): void
    {
        $entity = $args->getObject();

        if (!$entity instanceof ComicSeries) {
            return;
        }

        $this->eventDispatcher->dispatch(new ComicSeriesDeletedEvent(
            (int) $entity->getId(),
            $entity->getTitle(),
        ));
    }

    public function postUpdate(PostUpdateEventArgs $args): void
    {
        $entity = $args->getObject();

        if (!$entity instanceof ComicSeries) {
            return;
        }

        // Un soft-delete positionne deletedAt : on dispatche un événement de suppression
        if ($entity->isDeleted()) {
            $this->eventDispatcher->dispatch(new ComicSeriesDeletedEvent(
                (int) $entity->getId(),
                $entity->getTitle(),
            ));

            return;
        }

        $this->eventDispatcher->dispatch(new ComicSeriesUpdatedEvent($entity));
    }
}

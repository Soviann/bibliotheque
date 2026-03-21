<?php

declare(strict_types=1);

namespace App\EventListener;

use App\Event\ComicSeriesCreatedEvent;
use App\Message\EnrichSeriesMessage;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Dispatche un message d'enrichissement à la création d'une série.
 */
#[AsEventListener(event: ComicSeriesCreatedEvent::class)]
final readonly class EnrichOnCreateListener
{
    public function __construct(
        private MessageBusInterface $messageBus,
    ) {
    }

    public function __invoke(ComicSeriesCreatedEvent $event): void
    {
        $series = $event->getComicSeries();
        $id = $series->getId();

        if (null === $id) {
            return;
        }

        $this->messageBus->dispatch(new EnrichSeriesMessage($id));
    }
}

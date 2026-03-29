<?php

declare(strict_types=1);

namespace App\EventListener;

use App\Event\ComicSeriesUpdatedEvent;
use App\Message\EnrichSeriesMessage;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Re-dispatche un enrichissement si la série a encore des champs vides après mise à jour.
 */
#[AsEventListener(event: ComicSeriesUpdatedEvent::class)]
final readonly class ReEnrichOnUpdateListener
{
    public function __construct(
        private MessageBusInterface $messageBus,
    ) {
    }

    public function __invoke(ComicSeriesUpdatedEvent $event): void
    {
        $series = $event->getComicSeries();
        $id = $series->getId();

        if (null === $id) {
            return;
        }

        // Re-enrichir seulement si des champs clés sont encore vides
        $needsEnrichment = null === $series->getDescription()
            || null === $series->getPublisher()
            || (null === $series->getCoverUrl() && null === $series->getCoverImage());

        if (!$needsEnrichment) {
            return;
        }

        // Ne pas re-enrichir si un lookup a été fait récemment (< 24h)
        $lookupAt = $series->getLookupCompletedAt();

        if (null !== $lookupAt && $lookupAt > new \DateTimeImmutable('-1 day')) {
            return;
        }

        $this->messageBus->dispatch(new EnrichSeriesMessage($id, 'event:update'));
    }
}

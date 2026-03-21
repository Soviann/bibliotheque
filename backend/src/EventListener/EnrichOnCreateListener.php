<?php

declare(strict_types=1);

namespace App\EventListener;

use App\Event\ComicSeriesCreatedEvent;
use App\Message\EnrichSeriesMessage;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Dispatche un message d'enrichissement à la création d'une série.
 *
 * Peut être désactivé pendant les opérations batch (imports)
 * pour éviter de saturer la file de messages.
 */
#[AsEventListener(event: ComicSeriesCreatedEvent::class)]
final class EnrichOnCreateListener
{
    private static bool $enabled = true;

    public function __construct(
        private readonly MessageBusInterface $messageBus,
    ) {
    }

    public static function disable(): void
    {
        self::$enabled = false;
    }

    public static function enable(): void
    {
        self::$enabled = true;
    }

    public function __invoke(ComicSeriesCreatedEvent $event): void
    {
        if (!self::$enabled) {
            return;
        }

        $series = $event->getComicSeries();
        $id = $series->getId();

        if (null === $id) {
            return;
        }

        $this->messageBus->dispatch(new EnrichSeriesMessage($id));
    }
}

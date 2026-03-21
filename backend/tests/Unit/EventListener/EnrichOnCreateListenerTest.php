<?php

declare(strict_types=1);

namespace App\Tests\Unit\EventListener;

use App\Entity\ComicSeries;
use App\Event\ComicSeriesCreatedEvent;
use App\EventListener\EnrichOnCreateListener;
use App\Message\EnrichSeriesMessage;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Tests unitaires pour le listener d'enrichissement à la création.
 */
final class EnrichOnCreateListenerTest extends TestCase
{
    private EnrichOnCreateListener $listener;
    private MockObject&MessageBusInterface $messageBus;

    protected function setUp(): void
    {
        $this->messageBus = $this->createMock(MessageBusInterface::class);
        $this->listener = new EnrichOnCreateListener($this->messageBus);
    }

    /**
     * Teste que le listener dispatche un message pour une série avec ID.
     */
    public function testDispatchesMessageForPersistedSeries(): void
    {
        $series = $this->createMock(ComicSeries::class);
        $series->method('getId')->willReturn(42);

        $this->messageBus->expects(self::once())
            ->method('dispatch')
            ->with(self::callback(static fn ($msg) => $msg instanceof EnrichSeriesMessage && 42 === $msg->seriesId))
            ->willReturn(new Envelope(new EnrichSeriesMessage(42)));

        ($this->listener)(new ComicSeriesCreatedEvent($series));
    }

    /**
     * Teste que le listener ne dispatche rien si la série n'a pas d'ID.
     */
    public function testDoesNotDispatchIfNoId(): void
    {
        $series = $this->createMock(ComicSeries::class);
        $series->method('getId')->willReturn(null);

        $this->messageBus->expects(self::never())->method('dispatch');

        ($this->listener)(new ComicSeriesCreatedEvent($series));
    }

    /**
     * Teste que le listener ne dispatche rien quand il est désactivé.
     */
    public function testDoesNotDispatchWhenDisabled(): void
    {
        EnrichOnCreateListener::disable();

        try {
            $series = $this->createMock(ComicSeries::class);
            $series->method('getId')->willReturn(42);

            $this->messageBus->expects(self::never())->method('dispatch');

            ($this->listener)(new ComicSeriesCreatedEvent($series));
        } finally {
            EnrichOnCreateListener::enable();
        }
    }
}

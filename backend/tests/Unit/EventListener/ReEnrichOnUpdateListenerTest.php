<?php

declare(strict_types=1);

namespace App\Tests\Unit\EventListener;

use App\Entity\ComicSeries;
use App\Event\ComicSeriesUpdatedEvent;
use App\EventListener\ReEnrichOnUpdateListener;
use App\Message\EnrichSeriesMessage;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Tests unitaires pour le listener de re-enrichissement à la mise à jour.
 */
final class ReEnrichOnUpdateListenerTest extends TestCase
{
    private ReEnrichOnUpdateListener $listener;
    private MockObject&MessageBusInterface $messageBus;

    protected function setUp(): void
    {
        $this->messageBus = $this->createMock(MessageBusInterface::class);
        $this->listener = new ReEnrichOnUpdateListener($this->messageBus);
    }

    /**
     * Teste que le listener dispatche un message si la description est manquante.
     */
    public function testDispatchesWhenDescriptionMissing(): void
    {
        $series = $this->createMock(ComicSeries::class);
        $series->method('getId')->willReturn(42);
        $series->method('getDescription')->willReturn(null);
        $series->method('getPublisher')->willReturn('Glénat');
        $series->method('getCoverUrl')->willReturn('https://example.com/cover.jpg');
        $series->method('getCoverImage')->willReturn(null);
        $series->method('getLookupCompletedAt')->willReturn(null);

        $this->messageBus->expects(self::once())
            ->method('dispatch')
            ->with(self::callback(static fn ($msg) => $msg instanceof EnrichSeriesMessage && 42 === $msg->seriesId))
            ->willReturn(new Envelope(new EnrichSeriesMessage(42)));

        ($this->listener)(new ComicSeriesUpdatedEvent($series));
    }

    /**
     * Teste que le listener ne dispatche pas si tous les champs sont remplis.
     */
    public function testDoesNotDispatchWhenAllFieldsFilled(): void
    {
        $series = $this->createMock(ComicSeries::class);
        $series->method('getId')->willReturn(42);
        $series->method('getDescription')->willReturn('Description');
        $series->method('getPublisher')->willReturn('Glénat');
        $series->method('getCoverUrl')->willReturn('https://example.com/cover.jpg');
        $series->method('getCoverImage')->willReturn(null);
        $series->method('getLookupCompletedAt')->willReturn(null);

        $this->messageBus->expects(self::never())->method('dispatch');

        ($this->listener)(new ComicSeriesUpdatedEvent($series));
    }

    /**
     * Teste que le listener ne dispatche pas si un lookup récent a été fait.
     */
    public function testDoesNotDispatchIfRecentLookup(): void
    {
        $series = $this->createMock(ComicSeries::class);
        $series->method('getId')->willReturn(42);
        $series->method('getDescription')->willReturn(null);
        $series->method('getPublisher')->willReturn(null);
        $series->method('getCoverUrl')->willReturn(null);
        $series->method('getCoverImage')->willReturn(null);
        $series->method('getLookupCompletedAt')->willReturn(new \DateTimeImmutable('-1 hour'));

        $this->messageBus->expects(self::never())->method('dispatch');

        ($this->listener)(new ComicSeriesUpdatedEvent($series));
    }
}

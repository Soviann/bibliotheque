<?php

declare(strict_types=1);

namespace App\Tests\Unit\Event;

use App\Entity\ComicSeries;
use App\Event\ComicSeriesUpdatedEvent;
use PHPUnit\Framework\TestCase;

/**
 * Tests unitaires pour ComicSeriesUpdatedEvent.
 */
final class ComicSeriesUpdatedEventTest extends TestCase
{
    /**
     * Teste que l'événement retourne la série passée au constructeur.
     */
    public function testGetComicSeriesReturnsEntity(): void
    {
        $comicSeries = new ComicSeries();
        $comicSeries->setTitle('One Piece');

        $event = new ComicSeriesUpdatedEvent($comicSeries);

        self::assertSame($comicSeries, $event->getComicSeries());
    }
}

<?php

declare(strict_types=1);

namespace App\Tests\Unit\Event;

use App\Entity\ComicSeries;
use App\Event\ComicSeriesCreatedEvent;
use PHPUnit\Framework\TestCase;

/**
 * Tests unitaires pour ComicSeriesCreatedEvent.
 */
final class ComicSeriesCreatedEventTest extends TestCase
{
    /**
     * Teste que l'événement retourne la série passée au constructeur.
     */
    public function testGetComicSeriesReturnsEntity(): void
    {
        $comicSeries = new ComicSeries();
        $comicSeries->setTitle('Naruto');

        $event = new ComicSeriesCreatedEvent($comicSeries);

        self::assertSame($comicSeries, $event->getComicSeries());
    }
}

<?php

declare(strict_types=1);

namespace App\Tests\Unit\Event;

use App\Event\ComicSeriesDeletedEvent;
use PHPUnit\Framework\TestCase;

/**
 * Tests unitaires pour ComicSeriesDeletedEvent.
 */
final class ComicSeriesDeletedEventTest extends TestCase
{
    /**
     * Teste que l'événement retourne l'identifiant passé au constructeur.
     */
    public function testGetIdReturnsId(): void
    {
        $event = new ComicSeriesDeletedEvent(42, 'Naruto');

        self::assertSame(42, $event->getId());
    }

    /**
     * Teste que l'événement retourne le titre passé au constructeur.
     */
    public function testGetTitleReturnsTitle(): void
    {
        $event = new ComicSeriesDeletedEvent(42, 'Naruto');

        self::assertSame('Naruto', $event->getTitle());
    }
}

<?php

declare(strict_types=1);

namespace App\Event;

use App\Entity\ComicSeries;

/**
 * Événement dispatché lorsqu'une série est créée.
 */
final readonly class ComicSeriesCreatedEvent
{
    public function __construct(
        private ComicSeries $comicSeries,
    ) {
    }

    public function getComicSeries(): ComicSeries
    {
        return $this->comicSeries;
    }
}

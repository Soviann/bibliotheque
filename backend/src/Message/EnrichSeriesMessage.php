<?php

declare(strict_types=1);

namespace App\Message;

/**
 * Message pour l'enrichissement asynchrone d'une série.
 */
final readonly class EnrichSeriesMessage
{
    public function __construct(
        public int $seriesId,
        public ?string $triggeredBy = null,
    ) {
    }
}

<?php

declare(strict_types=1);

namespace App\DTO;

/**
 * Données d'une série extraite du NAS.
 */
final readonly class NasSeriesData
{
    public function __construct(
        public bool $isComplete,
        public ?int $lastOnNas,
        public ?int $readUpTo,
        public bool $readComplete,
        public string $title,
        public ?string $publisher = null,
    ) {
    }
}

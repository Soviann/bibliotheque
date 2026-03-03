<?php

declare(strict_types=1);

namespace App\DTO;

/**
 * Valeur entiere parsee depuis une cellule Excel, pouvant etre "fini" (complete).
 */
readonly class ParsedIntegerValue
{
    public function __construct(
        public bool $isComplete,
        public ?int $value,
    ) {
    }
}

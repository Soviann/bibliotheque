<?php

declare(strict_types=1);

namespace App\DTO;

/**
 * Valeur entiere parsee depuis une cellule Excel, pouvant etre "fini" (complete).
 */
final readonly class ParsedIntegerValue
{
    /**
     * @param list<int>|null $specificValues Liste de tomes spécifiques (format CSV "2, 5, 8")
     */
    public function __construct(
        public ?int $hsCount,
        public bool $isComplete,
        public ?array $specificValues,
        public ?int $value,
    ) {
    }
}

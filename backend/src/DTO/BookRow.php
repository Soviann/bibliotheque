<?php

declare(strict_types=1);

namespace App\DTO;

/**
 * Ligne d'import : titre original, données brutes et numéro de tome.
 */
readonly class BookRow
{
    /**
     * @param array<int, mixed> $row
     */
    public function __construct(
        public string $originalTitle,
        public array $row,
        public ?int $tomeNumber,
    ) {
    }
}

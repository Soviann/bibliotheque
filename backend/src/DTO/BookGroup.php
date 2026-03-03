<?php

declare(strict_types=1);

namespace App\DTO;

/**
 * Groupe de lignes d'import partageant la même série.
 */
readonly class BookGroup
{
    /**
     * @param list<BookRow> $rows
     */
    public function __construct(
        public array $rows,
        public string $seriesName,
    ) {
    }
}

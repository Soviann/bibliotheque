<?php

declare(strict_types=1);

namespace App\DTO;

use App\Entity\ComicSeries;

/**
 * Resultat de l'import d'une ligne Excel.
 */
final readonly class ImportResult
{
    public function __construct(
        public bool $isUpdate,
        public ComicSeries $series,
        public int $tomesCount,
    ) {
    }
}

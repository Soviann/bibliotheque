<?php

declare(strict_types=1);

namespace App\DTO;

use App\Entity\ComicSeries;

/**
 * Résultat de l'import d'une ligne Excel.
 */
final readonly class RowImportResult
{
    public function __construct(
        public bool $isUpdate,
        public bool $metadataApplied,
        public ComicSeries $series,
        public int $tomesCount,
    ) {
    }
}

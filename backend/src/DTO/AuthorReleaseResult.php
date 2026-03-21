<?php

declare(strict_types=1);

namespace App\DTO;

use App\Enum\ComicType;

/**
 * Résultat de détection d'une nouvelle série d'un auteur suivi.
 */
final readonly class AuthorReleaseResult
{
    public function __construct(
        public string $authorName,
        public string $newSeriesTitle,
        public ComicType $type,
    ) {
    }
}

<?php

declare(strict_types=1);

namespace App\DTO;

use App\Enum\ComicStatus;
use App\Enum\ComicType;

/**
 * Filtres pour la recherche de series.
 */
final readonly class ComicSeriesFilter
{
    public function __construct(
        public ?bool $isWishlist = null,
        public ?bool $onNas = null,
        public ?string $reading = null,
        public ?string $search = null,
        public string $sort = 'title_asc',
        public ?ComicStatus $status = null,
        public ?ComicType $type = null,
    ) {
    }
}

<?php

declare(strict_types=1);

namespace App\DTO;

/**
 * Informations extraites d'un titre : nom de série et numéro de tome.
 */
readonly class SeriesInfo
{
    public function __construct(
        public string $name,
        public ?int $tomeNumber,
    ) {
    }
}

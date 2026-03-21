<?php

declare(strict_types=1);

namespace App\DTO;

/**
 * Résultat de détection de tomes manquants pour une série.
 */
final readonly class MissingTomeResult
{
    /**
     * @param list<int> $missingNumbers
     */
    public function __construct(
        public array $missingNumbers,
        public int $seriesId,
        public string $seriesTitle,
    ) {
    }
}

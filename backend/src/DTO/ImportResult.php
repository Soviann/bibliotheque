<?php

declare(strict_types=1);

namespace App\DTO;

/**
 * Résultat global de l'import.
 */
final readonly class ImportResult implements \JsonSerializable
{
    /**
     * @param array<string, array{created: int, enriched: int, tomes: int, updated: int}> $sheetDetails
     */
    public function __construct(
        public array $sheetDetails,
        public int $totalCreated,
        public int $totalEnriched,
        public int $totalTomes,
        public int $totalUpdated,
    ) {
    }

    /**
     * @return array{sheetDetails: array<string, array{created: int, enriched: int, tomes: int, updated: int}>, totalCreated: int, totalEnriched: int, totalTomes: int, totalUpdated: int}
     */
    public function jsonSerialize(): array
    {
        return [
            'sheetDetails' => $this->sheetDetails,
            'totalCreated' => $this->totalCreated,
            'totalEnriched' => $this->totalEnriched,
            'totalTomes' => $this->totalTomes,
            'totalUpdated' => $this->totalUpdated,
        ];
    }
}

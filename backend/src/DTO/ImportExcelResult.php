<?php

declare(strict_types=1);

namespace App\DTO;

/**
 * Résultat de l'import depuis un fichier Excel de suivi.
 */
final readonly class ImportExcelResult implements \JsonSerializable
{
    /**
     * @param array<string, array{created: int, tomes: int, updated: int}> $sheetDetails
     */
    public function __construct(
        public array $sheetDetails,
        public int $totalCreated,
        public int $totalTomes,
        public int $totalUpdated,
    ) {
    }

    /**
     * @return array{sheetDetails: array<string, array{created: int, tomes: int, updated: int}>, totalCreated: int, totalTomes: int, totalUpdated: int}
     */
    public function jsonSerialize(): array
    {
        return [
            'sheetDetails' => $this->sheetDetails,
            'totalCreated' => $this->totalCreated,
            'totalTomes' => $this->totalTomes,
            'totalUpdated' => $this->totalUpdated,
        ];
    }
}

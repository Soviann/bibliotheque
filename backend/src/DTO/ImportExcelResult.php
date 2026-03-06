<?php

declare(strict_types=1);

namespace App\DTO;

/**
 * Résultat de l'import depuis un fichier Excel de suivi.
 */
final readonly class ImportExcelResult implements \JsonSerializable
{
    /**
     * @param array<string, array{series: int, tomes: int}> $sheetDetails
     */
    public function __construct(
        public array $sheetDetails,
        public int $totalSeries,
        public int $totalTomes,
    ) {
    }

    /**
     * @return array{sheetDetails: array<string, array{series: int, tomes: int}>, totalSeries: int, totalTomes: int}
     */
    public function jsonSerialize(): array
    {
        return [
            'sheetDetails' => $this->sheetDetails,
            'totalSeries' => $this->totalSeries,
            'totalTomes' => $this->totalTomes,
        ];
    }
}

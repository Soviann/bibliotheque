<?php

declare(strict_types=1);

namespace App\DTO;

/**
 * Résultat de l'import depuis un fichier Excel de livres.
 */
final readonly class ImportBooksResult implements \JsonSerializable
{
    public function __construct(
        public int $created,
        public int $enriched,
        public int $groupCount,
    ) {
    }

    /**
     * @return array{created: int, enriched: int, groupCount: int}
     */
    public function jsonSerialize(): array
    {
        return [
            'created' => $this->created,
            'enriched' => $this->enriched,
            'groupCount' => $this->groupCount,
        ];
    }
}

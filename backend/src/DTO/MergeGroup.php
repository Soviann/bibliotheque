<?php

declare(strict_types=1);

namespace App\DTO;

/**
 * Groupe de séries détectées comme tomes d'une même série.
 */
readonly class MergeGroup implements \JsonSerializable
{
    /**
     * @param list<MergeGroupEntry> $entries
     */
    public function __construct(
        public array $entries,
        public string $suggestedTitle,
    ) {
    }

    /**
     * @return array{entries: list<MergeGroupEntry>, suggestedTitle: string}
     */
    public function jsonSerialize(): array
    {
        return [
            'entries' => $this->entries,
            'suggestedTitle' => $this->suggestedTitle,
        ];
    }
}

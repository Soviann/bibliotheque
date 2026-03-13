<?php

declare(strict_types=1);

namespace App\DTO;

/**
 * Entrée d'un groupe de fusion : une série avec son numéro de tome suggéré.
 */
final readonly class MergeGroupEntry implements \JsonSerializable
{
    public function __construct(
        public string $originalTitle,
        public int $seriesId,
        public ?int $suggestedTomeNumber,
    ) {
    }

    /**
     * @return array{originalTitle: string, seriesId: int, suggestedTomeNumber: ?int}
     */
    public function jsonSerialize(): array
    {
        return [
            'originalTitle' => $this->originalTitle,
            'seriesId' => $this->seriesId,
            'suggestedTomeNumber' => $this->suggestedTomeNumber,
        ];
    }
}

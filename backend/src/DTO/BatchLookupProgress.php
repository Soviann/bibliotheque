<?php

declare(strict_types=1);

namespace App\DTO;

/**
 * Progression d'un lookup batch pour une série.
 */
readonly class BatchLookupProgress implements \JsonSerializable
{
    public function __construct(
        public int $current,
        public string $seriesTitle,
        public string $status,
        public int $total,
        /** @var list<string> */
        public array $updatedFields = [],
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return [
            'current' => $this->current,
            'seriesTitle' => $this->seriesTitle,
            'status' => $this->status,
            'total' => $this->total,
            'updatedFields' => $this->updatedFields,
        ];
    }
}

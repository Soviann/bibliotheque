<?php

declare(strict_types=1);

namespace App\DTO;

/**
 * Résumé final d'un lookup batch.
 */
readonly class BatchLookupSummary implements \JsonSerializable
{
    public function __construct(
        public int $failed,
        public int $processed,
        public int $skipped,
        public int $updated,
    ) {
    }

    /**
     * @return array<string, int>
     */
    public function jsonSerialize(): array
    {
        return [
            'failed' => $this->failed,
            'processed' => $this->processed,
            'skipped' => $this->skipped,
            'updated' => $this->updated,
        ];
    }
}

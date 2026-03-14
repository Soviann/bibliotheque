<?php

declare(strict_types=1);

namespace App\DTO;

use App\Enum\BatchLookupStatus;

/**
 * Progression de la vérification de nouvelles parutions pour une série.
 */
final readonly class NewReleaseProgress implements \JsonSerializable
{
    public function __construct(
        public int $current,
        public ?int $newLatestIssue,
        public ?int $previousLatestIssue,
        public string $seriesTitle,
        public BatchLookupStatus $status,
        public bool $stoppedByRateLimit,
        public int $total,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return [
            'current' => $this->current,
            'newLatestIssue' => $this->newLatestIssue,
            'previousLatestIssue' => $this->previousLatestIssue,
            'seriesTitle' => $this->seriesTitle,
            'status' => $this->status->value,
            'stoppedByRateLimit' => $this->stoppedByRateLimit,
            'total' => $this->total,
        ];
    }
}

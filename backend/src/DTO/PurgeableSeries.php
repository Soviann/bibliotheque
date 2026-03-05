<?php

declare(strict_types=1);

namespace App\DTO;

/**
 * Série éligible à la purge.
 */
final readonly class PurgeableSeries implements \JsonSerializable
{
    public function __construct(
        public \DateTimeImmutable $deletedAt,
        public int $id,
        public string $title,
    ) {
    }

    /**
     * @return array{deletedAt: string, id: int, title: string}
     */
    public function jsonSerialize(): array
    {
        return [
            'deletedAt' => $this->deletedAt->format(\DateTimeInterface::ATOM),
            'id' => $this->id,
            'title' => $this->title,
        ];
    }
}

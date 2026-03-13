<?php

declare(strict_types=1);

namespace App\Service\Lookup;

/**
 * DTO representant un message de statut d'appel API.
 */
final readonly class ApiMessage implements \JsonSerializable
{
    public function __construct(
        public string $message,
        public string $status,
    ) {
    }

    /**
     * @return array{message: string, status: string}
     */
    public function jsonSerialize(): array
    {
        return ['message' => $this->message, 'status' => $this->status];
    }
}

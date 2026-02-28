<?php

declare(strict_types=1);

namespace App\Service\Lookup;

use App\Enum\ApiLookupStatus;

/**
 * Classe abstraite fournissant la gestion commune des messages API pour les providers de lookup.
 */
abstract class AbstractLookupProvider implements LookupProviderInterface
{
    /** @var array{status: string, message: string}|null */
    private ?array $lastApiMessage = null;

    public function getLastApiMessage(): ?array
    {
        return $this->lastApiMessage;
    }

    protected function recordApiMessage(ApiLookupStatus $status, string $message): void
    {
        $this->lastApiMessage = ['message' => $message, 'status' => $status->value];
    }

    protected function resetApiMessage(): void
    {
        $this->lastApiMessage = null;
    }
}

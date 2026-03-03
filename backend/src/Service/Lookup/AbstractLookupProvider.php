<?php

declare(strict_types=1);

namespace App\Service\Lookup;

use App\Enum\ApiLookupStatus;

/**
 * Classe abstraite fournissant la gestion commune des messages API pour les providers de lookup.
 */
abstract class AbstractLookupProvider implements LookupProviderInterface
{
    private ?ApiMessage $lastApiMessage = null;

    public function getLastApiMessage(): ?ApiMessage
    {
        return $this->lastApiMessage;
    }

    protected function recordApiMessage(ApiLookupStatus $status, string $message): void
    {
        $this->lastApiMessage = new ApiMessage(message: $message, status: $status->value);
    }

    protected function resetApiMessage(): void
    {
        $this->lastApiMessage = null;
    }
}

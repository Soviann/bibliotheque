<?php

declare(strict_types=1);

namespace App\Service\Lookup\Provider;

use App\Enum\ApiLookupStatus;
use App\Service\Lookup\Contract\ApiMessage;
use App\Service\Lookup\Contract\LookupProviderInterface;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;

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

    /**
     * Gère une exception HTTP (rate limit 429 ou autre erreur).
     */
    protected function handleHttpException(ClientExceptionInterface|RedirectionExceptionInterface|ServerExceptionInterface $e): void
    {
        $code = $e->getResponse()->getStatusCode();
        if (429 === $code) {
            $this->recordApiMessage(ApiLookupStatus::RATE_LIMITED, 'Quota dépassé (429)');
        } else {
            $this->recordApiMessage(ApiLookupStatus::ERROR, \sprintf('Erreur HTTP (%d)', $code));
        }
        $this->getLogger()->warning('Erreur HTTP {provider} : {error}', [
            'error' => $e->getMessage(),
            'provider' => $this->getName(),
        ]);
    }

    abstract protected function getLogger(): LoggerInterface;

    protected function recordApiMessage(ApiLookupStatus $status, string $message): void
    {
        $this->lastApiMessage = new ApiMessage(message: $message, status: $status->value);
    }

    protected function resetApiMessage(): void
    {
        $this->lastApiMessage = null;
    }
}

<?php

declare(strict_types=1);

namespace App\Service\Lookup;

use Gemini\Exceptions\ErrorException;
use Psr\Log\LoggerInterface;

/**
 * Pool de clients Gemini avec rotation clés × modèles sur erreur 400/401/403/429.
 *
 * Itère modèles (outer) × clés (inner) : épuise toutes les clés sur le meilleur modèle d'abord.
 * Le suivi d'épuisement est en mémoire (suffisant pour les batchs et réinitialisé par requête web).
 */
class GeminiClientPool
{
    /** @var list<string> */
    private readonly array $apiKeys;

    /** @var array<string, true> */
    private array $exhausted = [];

    /** @var list<string> */
    private readonly array $models;

    /**
     * @param string $apiKeys Clés API séparées par des virgules
     * @param string $models  Modèles séparés par des virgules (ordre = priorité décroissante)
     */
    public function __construct(
        string $apiKeys,
        private readonly LoggerInterface $logger,
        string $models,
    ) {
        $this->apiKeys = \array_values(\array_filter(\array_map(\trim(...), \explode(',', $apiKeys))));
        $this->models = \array_values(\array_filter(\array_map(\trim(...), \explode(',', $models))));

        if ([] === $this->apiKeys) {
            throw new \RuntimeException('GEMINI_API_KEYS ne doit pas être vide.');
        }

        if ([] === $this->models) {
            throw new \RuntimeException('GEMINI_MODELS ne doit pas être vide.');
        }
    }

    /**
     * Exécute le callable avec rotation clés × modèles sur 400/401/403/429.
     *
     * @template T
     *
     * @param callable(\Gemini\Contracts\ClientContract, string): T $callback
     *
     * @return T
     *
     * @throws ErrorException si toutes les combinaisons sont épuisées (dernière 429)
     */
    public function executeWithRetry(callable $callback): mixed
    {
        $lastException = null;

        foreach ($this->models as $model) {
            foreach ($this->apiKeys as $keyIndex => $apiKey) {
                $comboKey = $keyIndex.':'.$model;

                if (isset($this->exhausted[$comboKey])) {
                    continue;
                }

                try {
                    $client = \Gemini::client($apiKey);

                    return $callback($client, $model);
                } catch (ErrorException $e) {
                    $code = $e->getErrorCode();

                    if (!\in_array($code, [400, 401, 403, 429], true)) {
                        throw $e;
                    }

                    $this->exhausted[$comboKey] = true;
                    $lastException = $e;
                    $this->logger->warning('Gemini {code} sur clé {keyIndex} / modèle {model}, rotation…', [
                        'code' => $code,
                        'keyIndex' => $keyIndex,
                        'model' => $model,
                    ]);
                }
            }
        }

        throw $lastException ?? new \RuntimeException('Aucune combinaison clé/modèle disponible.');
    }
}

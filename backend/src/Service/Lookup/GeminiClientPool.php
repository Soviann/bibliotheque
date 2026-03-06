<?php

declare(strict_types=1);

namespace App\Service\Lookup;

use Gemini\Exceptions\ErrorException;
use Psr\Log\LoggerInterface;

/**
 * Pool de clients Gemini avec rotation clés × modèles sur erreur 429.
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
        string $models,
        private readonly LoggerInterface $logger,
    ) {
        $this->apiKeys = \array_values(\array_filter(\array_map(\trim(...), \explode(',', $apiKeys))));
        $this->models = \array_values(\array_filter(\array_map(\trim(...), \explode(',', $models))));

        if ([] === $this->apiKeys) {
            throw new \RuntimeException('GEMINI_API_KEYS ne doit pas être vide.');
        }
    }

    /**
     * Exécute le callable avec rotation clés × modèles sur 429.
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
                    if (429 !== $e->getErrorCode()) {
                        throw $e;
                    }

                    $this->exhausted[$comboKey] = true;
                    $lastException = $e;
                    $this->logger->warning('Gemini 429 sur clé {keyIndex} / modèle {model}, rotation…', [
                        'keyIndex' => $keyIndex,
                        'model' => $model,
                    ]);
                }
            }
        }

        throw $lastException ?? new \RuntimeException('Aucune combinaison clé/modèle disponible.');
    }
}

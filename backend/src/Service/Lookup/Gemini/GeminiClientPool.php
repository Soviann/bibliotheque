<?php

declare(strict_types=1);

namespace App\Service\Lookup\Gemini;

use Gemini;
use Gemini\Contracts\ClientContract;
use Gemini\Exceptions\ErrorException;
use Psr\Log\LoggerInterface;

/**
 * Pool de clients Gemini avec rotation clés × modèles sur erreur 400/401/403/404/429/500/503.
 *
 * Itère modèles (outer) × clés (inner) : épuise toutes les clés sur le meilleur modèle d'abord.
 * Les 500/503 (erreurs serveur Gemini transitoires, fréquentes avec le grounding Google Search)
 * déclenchent aussi une rotation au lieu d'avorter immédiatement.
 *
 * Le suivi d'épuisement est en mémoire et borné dans le temps ({@see self::EXHAUSTION_COOLDOWN_SECONDS}) :
 * une combinaison en échec est ignorée brièvement puis réessayée, ce qui évite qu'un worker
 * longue durée reste dégradé indéfiniment après une rafale d'erreurs.
 *
 * NB : cette rotation clé×modèle est la raison pour laquelle on n'a PAS migré vers Symfony AI
 * (son FailoverPlatform bascule entre plateformes, pas entre N clés d'une même plateforme).
 * Voir docs/decisions/0001-pas-de-migration-vers-symfony-ai.md.
 */
class GeminiClientPool
{
    /** Codes HTTP déclenchant une rotation clé/modèle (le reste est relancé immédiatement). */
    private const array RETRYABLE_CODES = [400, 401, 403, 404, 429, 500, 503];

    /** Durée pendant laquelle une combinaison en échec est ignorée (secondes). */
    private const float EXHAUSTION_COOLDOWN_SECONDS = 90.0;

    /** @var list<string> */
    private readonly array $apiKeys;

    /** @var array<string, array{at: float, rateLimited: bool}> */
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
     * @param callable(ClientContract, string):T $callback
     *
     * @return T
     *
     * @throws ErrorException                  pour un code non retryable (relancé immédiatement)
     * @throws GeminiAllKeysExhaustedException si toutes les combinaisons ont échoué
     */
    public function executeWithRetry(callable $callback): mixed
    {
        $lastException = null;
        $sawRateLimit = false;
        $now = \microtime(true);

        foreach ($this->models as $model) {
            foreach ($this->apiKeys as $keyIndex => $apiKey) {
                $comboKey = $keyIndex.':'.$model;

                $failure = $this->exhausted[$comboKey] ?? null;
                if (null !== $failure && ($now - $failure['at']) < self::EXHAUSTION_COOLDOWN_SECONDS) {
                    $sawRateLimit = $sawRateLimit || $failure['rateLimited'];

                    continue;
                }

                try {
                    $client = \Gemini::client($apiKey);
                    $result = $callback($client, $model);
                    unset($this->exhausted[$comboKey]);

                    return $result;
                } catch (ErrorException $e) {
                    $code = $e->getErrorCode();

                    if (!\in_array($code, self::RETRYABLE_CODES, true)) {
                        throw $e;
                    }

                    $isRateLimit = 429 === $code;
                    $sawRateLimit = $sawRateLimit || $isRateLimit;
                    $this->exhausted[$comboKey] = ['at' => \microtime(true), 'rateLimited' => $isRateLimit];
                    $lastException = $e;
                    $this->logger->warning('Gemini {code} sur clé {keyIndex} / modèle {model}, rotation…', [
                        'code' => $code,
                        'keyIndex' => $keyIndex,
                        'model' => $model,
                    ]);
                }
            }
        }

        throw new GeminiAllKeysExhaustedException(rateLimited: $sawRateLimit, previous: $lastException);
    }
}

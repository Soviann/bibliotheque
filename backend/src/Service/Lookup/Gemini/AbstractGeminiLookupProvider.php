<?php

declare(strict_types=1);

namespace App\Service\Lookup\Gemini;

use App\Enum\ApiLookupStatus;
use App\Service\Lookup\Contract\LookupResult;
use App\Service\Lookup\Provider\AbstractLookupProvider;
use Gemini\Data\GoogleSearch;
use Gemini\Data\SafetySetting;
use Gemini\Data\Tool;
use Gemini\Enums\HarmBlockThreshold;
use Gemini\Enums\HarmCategory;
use Gemini\Exceptions\ErrorException;
use Psr\Log\LoggerInterface;
use Symfony\Component\Cache\Adapter\AdapterInterface;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;

/**
 * Classe abstraite pour les providers de lookup utilisant l'API Gemini avec Google Search grounding.
 *
 * Fournit les méthodes communes : appel Gemini, cache, rate limiting, parsing JSON.
 * Les sous-classes définissent le prompt, les champs extraits et la source.
 */
abstract class AbstractGeminiLookupProvider extends AbstractLookupProvider
{
    private const int CACHE_TTL = 2592000; // 30 jours

    public function __construct(
        protected readonly AdapterInterface $cache,
        protected readonly GeminiClientPool $geminiClientPool,
        protected readonly RateLimiterFactoryInterface $limiterFactory,
        protected readonly LoggerInterface $logger,
    ) {
    }

    public function resolveLookup(mixed $state): ?LookupResult
    {
        if ($state instanceof LookupResult) {
            return $state;
        }

        if (null === $state) {
            return null;
        }

        /** @var array{cacheKey: string, prompt: string} $state */
        $result = $this->callGemini($state['prompt']);

        if ($result instanceof LookupResult) {
            $item = $this->cache->getItem($state['cacheKey']);
            $item->set($result);
            $item->expiresAfter(self::CACHE_TTL);
            $this->cache->save($item);
        }

        return $result;
    }

    /**
     * Construit un LookupResult à partir des données JSON parsées.
     *
     * @param array<string, mixed> $data
     */
    abstract protected function buildResult(array $data): LookupResult;

    /**
     * Retourne les champs à vérifier pour déterminer si des données utiles ont été trouvées.
     *
     * @return list<string>
     */
    abstract protected function getUsefulDataFields(): array;

    /**
     * Retourne le nom affiché dans les logs pour ce provider.
     */
    abstract protected function getLogName(): string;

    /**
     * Retourne le message de succès pour ce provider.
     */
    abstract protected function getSuccessMessage(): string;

    /**
     * Retourne le message "non trouvé" pour ce provider.
     */
    abstract protected function getNotFoundMessage(): string;

    /**
     * Vérifie le cache et le rate limit, retourne LookupResult|array|null.
     */
    protected function prepareWithCache(string $cacheKey, callable $buildPrompt): mixed
    {
        $item = $this->cache->getItem($cacheKey);

        if ($item->isHit()) {
            $cached = $item->get();
            if ($cached instanceof LookupResult) {
                $this->recordApiMessage(ApiLookupStatus::SUCCESS, 'Résultat depuis le cache');

                return $cached;
            }
        }

        if (!$this->consumeRateLimit()) {
            return null;
        }

        return ['cacheKey' => $cacheKey, 'prompt' => $buildPrompt()];
    }

    protected function getLogger(): LoggerInterface
    {
        return $this->logger;
    }

    protected function consumeRateLimit(): bool
    {
        $limiter = $this->limiterFactory->create('gemini_global');

        if (!$limiter->consume()->isAccepted()) {
            $this->recordApiMessage(ApiLookupStatus::RATE_LIMITED, 'Quota dépassé');

            return false;
        }

        return true;
    }

    private function callGemini(string $prompt): ?LookupResult
    {
        $logName = $this->getLogName();

        try {
            return $this->geminiClientPool->executeWithRetry(function ($client, \BackedEnum|string $model) use ($prompt, $logName): ?LookupResult {
                $response = $client
                    ->generativeModel(model: $model)
                    ->withTool(new Tool(googleSearch: GoogleSearch::from()))
                    ->withSafetySetting(new SafetySetting(
                        category: HarmCategory::HARM_CATEGORY_DANGEROUS_CONTENT,
                        threshold: HarmBlockThreshold::BLOCK_ONLY_HIGH,
                    ))
                    ->withSafetySetting(new SafetySetting(
                        category: HarmCategory::HARM_CATEGORY_HARASSMENT,
                        threshold: HarmBlockThreshold::BLOCK_ONLY_HIGH,
                    ))
                    ->withSafetySetting(new SafetySetting(
                        category: HarmCategory::HARM_CATEGORY_HATE_SPEECH,
                        threshold: HarmBlockThreshold::BLOCK_ONLY_HIGH,
                    ))
                    ->withSafetySetting(new SafetySetting(
                        category: HarmCategory::HARM_CATEGORY_SEXUALLY_EXPLICIT,
                        threshold: HarmBlockThreshold::BLOCK_ONLY_HIGH,
                    ))
                    ->generateContent($prompt);

                if (empty($response->candidates)) {
                    $blockReason = $response->promptFeedback?->blockReason?->value;
                    $this->logger->warning("Gemini ({$logName}) : aucun candidat retourné", [
                        'blockReason' => $blockReason,
                    ]);
                    $message = $blockReason
                        ? "Prompt bloqué par Gemini ({$blockReason})"
                        : 'Aucun résultat (réponse vide)';
                    $this->recordApiMessage(ApiLookupStatus::NOT_FOUND, $message);

                    return null;
                }

                $text = $response->text();
                $data = GeminiJsonParser::parseJsonFromText($text);

                if (null === $data) {
                    $this->recordApiMessage(ApiLookupStatus::ERROR, 'Réponse JSON invalide');

                    return null;
                }

                $hasData = false;
                foreach ($this->getUsefulDataFields() as $field) {
                    if (!empty($data[$field])) {
                        $hasData = true;
                        break;
                    }
                }

                if (!$hasData) {
                    $this->recordApiMessage(ApiLookupStatus::NOT_FOUND, $this->getNotFoundMessage());

                    return null;
                }

                $this->recordApiMessage(ApiLookupStatus::SUCCESS, $this->getSuccessMessage());

                return $this->buildResult($data);
            });
        } catch (ErrorException $e) {
            $this->logger->error("Erreur Gemini API ({$logName}) : {error}", ['code' => $e->getErrorCode(), 'error' => $e->getMessage()]);

            $code = $e->getErrorCode();
            if (\in_array($code, [400, 401, 403, 429], true)) {
                $this->recordApiMessage(ApiLookupStatus::RATE_LIMITED, 'Toutes les clés API épuisées (dernière erreur : '.$code.')');
            } else {
                $this->recordApiMessage(ApiLookupStatus::ERROR, $e->getErrorMessage());
            }

            return null;
        } catch (\Throwable $e) {
            $this->logger->error("Erreur Gemini ({$logName}) : {error}", ['error' => $e->getMessage()]);
            $this->recordApiMessage(ApiLookupStatus::ERROR, 'Erreur de connexion');

            return null;
        }
    }
}

<?php

declare(strict_types=1);

namespace App\Service\Lookup;

use App\Enum\ApiLookupStatus;
use Gemini\Contracts\ClientContract as GeminiClient;
use Gemini\Data\GoogleSearch;
use Gemini\Data\Tool;
use Gemini\Exceptions\ErrorException;
use Psr\Log\LoggerInterface;
use Symfony\Component\Cache\Adapter\AdapterInterface;
use Symfony\Component\RateLimiter\RateLimiterFactory;

/**
 * Classe abstraite pour les providers de lookup utilisant l'API Gemini avec Google Search grounding.
 *
 * Fournit les méthodes communes : appel Gemini, cache, rate limiting, parsing JSON.
 * Les sous-classes définissent le prompt, les champs extraits et la source.
 */
abstract class AbstractGeminiLookupProvider extends AbstractLookupProvider
{
    private const string MODEL = 'gemini-2.5-flash';

    public function __construct(
        protected readonly AdapterInterface $cache,
        protected readonly GeminiClient $geminiClient,
        protected readonly RateLimiterFactory $limiterFactory,
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
            $item->expiresAfter(2592000); // 30 jours
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

    protected function consumeRateLimit(): bool
    {
        $limiter = $this->limiterFactory->create('gemini_global');

        if (!$limiter->consume()->isAccepted()) {
            $this->recordApiMessage(ApiLookupStatus::RATE_LIMITED, 'Quota dépassé');

            return false;
        }

        return true;
    }

    /**
     * Parse le JSON depuis la réponse texte de Gemini (avec ou sans bloc markdown).
     *
     * @return array<string, mixed>|null
     */
    protected function parseJsonFromText(string $text): ?array
    {
        $cleaned = \preg_replace('/^```(?:json)?\s*\n?(.*?)\n?```$/s', '$1', \trim($text));

        $data = \json_decode($cleaned ?? $text, true);

        if (!\is_array($data)) {
            return null;
        }

        return $data; // @phpstan-ignore return.type
    }

    private function callGemini(string $prompt): ?LookupResult
    {
        $logName = $this->getLogName();

        try {
            $response = $this->geminiClient
                ->generativeModel(model: self::MODEL)
                ->withTool(new Tool(googleSearch: GoogleSearch::from()))
                ->generateContent($prompt);

            $text = $response->text();
            $data = $this->parseJsonFromText($text);

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
        } catch (ErrorException $e) {
            $this->logger->error("Erreur Gemini API ({$logName}) : {error}", ['code' => $e->getErrorCode(), 'error' => $e->getMessage()]);

            if (429 === $e->getErrorCode()) {
                $this->recordApiMessage(ApiLookupStatus::RATE_LIMITED, 'Quota API dépassé');
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

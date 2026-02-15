<?php

declare(strict_types=1);

namespace App\Service\Lookup;

use App\Enum\ApiLookupStatus;
use App\Enum\ComicType;
use Gemini\Contracts\ClientContract as GeminiClient;
use Gemini\Data\GoogleSearch;
use Gemini\Data\Tool;
use Gemini\Exceptions\ErrorException;
use Psr\Log\LoggerInterface;
use Symfony\Component\Cache\Adapter\AdapterInterface;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\RateLimiter\RateLimiterFactory;

/**
 * Provider de recherche via l'API Google Gemini avec Google Search grounding.
 */
#[AutoconfigureTag('app.lookup_provider', ['priority' => 40])]
class GeminiLookup implements EnrichableLookupProviderInterface
{
    private const string JSON_INSTRUCTIONS = <<<'TEXT'
        Réponds UNIQUEMENT avec un objet JSON (sans bloc markdown) contenant ces champs :
        - "title" (string|null) : titre de la série
        - "authors" (string|null) : auteur(s) séparés par des virgules
        - "publisher" (string|null) : éditeur français
        - "publishedDate" (string|null) : date au format YYYY-MM-DD ou YYYY
        - "description" (string|null) : synopsis de la série
        - "thumbnail" (string|null) : URL image de couverture
        - "isOneShot" (boolean|null) : true = tome unique, false = série multi-tomes
        - "latestPublishedIssue" (integer|null) : nombre de tomes parus
        TEXT;

    private const string MODEL = 'gemini-2.5-flash';

    /** @var array{status: string, message: string}|null */
    private ?array $lastApiMessage = null;

    public function __construct(
        #[Autowire(service: 'gemini.cache')]
        private readonly AdapterInterface $cache,
        private readonly GeminiClient $geminiClient,
        #[Autowire(service: 'limiter.gemini_api')]
        private readonly RateLimiterFactory $limiterFactory,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function enrich(LookupResult $partial, ?ComicType $type): ?LookupResult
    {
        $this->lastApiMessage = null;

        if (null === $partial->title || '' === $partial->title) {
            return null;
        }

        $cacheKey = 'gemini_enrich_'.\md5(\json_encode($partial->jsonSerialize()).($type?->value ?? ''));

        return $this->cachedCall($cacheKey, fn () => $this->doEnrich($partial, $type));
    }

    public function getLastApiMessage(): ?array
    {
        return $this->lastApiMessage;
    }

    public function getName(): string
    {
        return 'gemini';
    }

    public function lookup(string $query, ?ComicType $type, string $mode = 'title'): ?LookupResult
    {
        $this->lastApiMessage = null;

        $cacheKey = 'gemini_'.\md5($query.$mode.($type?->value ?? ''));

        return $this->cachedCall($cacheKey, fn () => $this->doLookup($query, $type, $mode));
    }

    public function supports(string $mode, ?ComicType $type): bool
    {
        return \in_array($mode, ['isbn', 'title'], true);
    }

    private function buildEnrichPrompt(LookupResult $partial, ?ComicType $type): string
    {
        $typeLabel = $type?->value ?? 'bande dessinée/comics/manga';
        $existingData = \json_encode(\array_filter($partial->jsonSerialize(), static fn ($v) => null !== $v));

        return <<<PROMPT
            Tu es un assistant spécialisé en bandes dessinées, comics et mangas.
            J'ai les informations partielles suivantes sur une série ({$typeLabel}) :
            {$existingData}

            Complète les champs manquants en utilisant Google Search.
            Retourne UNIQUEMENT les informations que tu trouves avec certitude.
            Si tu n'es pas sûr d'une information, laisse le champ à null.

            PROMPT.self::JSON_INSTRUCTIONS;
    }

    private function buildLookupPrompt(string $query, ?ComicType $type, string $mode): string
    {
        $typeLabel = $type?->value ?? 'bande dessinée/comics/manga';
        $searchBy = 'isbn' === $mode ? "l'ISBN {$query}" : "le titre \"{$query}\"";

        return <<<PROMPT
            Tu es un assistant spécialisé en bandes dessinées, comics et mangas.
            Recherche les informations sur la série identifiée par {$searchBy} (type: {$typeLabel}).

            Utilise Google Search pour trouver les informations les plus précises et à jour.

            Retourne UNIQUEMENT les informations que tu trouves avec certitude.
            Si tu n'es pas sûr d'une information, laisse le champ à null.
            Pour le titre, retourne le titre de la SÉRIE (pas du tome individuel).

            PROMPT.self::JSON_INSTRUCTIONS;
    }

    /**
     * Exécute un appel Gemini avec cache.
     */
    private function cachedCall(string $cacheKey, callable $callback): ?LookupResult
    {
        $item = $this->cache->getItem($cacheKey);

        if ($item->isHit()) {
            $cached = $item->get();
            if ($cached instanceof LookupResult) {
                $this->recordApiMessage(ApiLookupStatus::SUCCESS, 'Résultat depuis le cache');

                return $cached;
            }
        }

        $result = $callback();

        if (null !== $result) {
            $item->set($result);
            $item->expiresAfter(2592000); // 30 jours
            $this->cache->save($item);
        }

        return $result;
    }

    private function callGemini(string $prompt): ?LookupResult
    {
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

            // Vérifie qu'au moins un champ utile est non-null/non-vide
            $hasData = false;
            foreach (['authors', 'description', 'publishedDate', 'publisher', 'thumbnail', 'title'] as $field) {
                if (!empty($data[$field])) {
                    $hasData = true;
                    break;
                }
            }

            if (!$hasData) {
                $this->recordApiMessage(ApiLookupStatus::NOT_FOUND, 'Aucun résultat');

                return null;
            }

            $this->recordApiMessage(ApiLookupStatus::SUCCESS, 'Données trouvées via IA');

            return new LookupResult(
                authors: \is_string($data['authors'] ?? null) ? $data['authors'] : null,
                description: \is_string($data['description'] ?? null) ? $data['description'] : null,
                isOneShot: \is_bool($data['isOneShot'] ?? null) ? $data['isOneShot'] : null,
                latestPublishedIssue: \is_int($data['latestPublishedIssue'] ?? null) ? $data['latestPublishedIssue'] : null,
                publishedDate: \is_string($data['publishedDate'] ?? null) ? $data['publishedDate'] : null,
                publisher: \is_string($data['publisher'] ?? null) ? $data['publisher'] : null,
                source: 'gemini',
                thumbnail: \is_string($data['thumbnail'] ?? null) ? $data['thumbnail'] : null,
                title: \is_string($data['title'] ?? null) ? $data['title'] : null,
            );
        } catch (ErrorException $e) {
            $this->logger->error('Erreur Gemini API : {error}', ['code' => $e->getErrorCode(), 'error' => $e->getMessage()]);

            if (429 === $e->getErrorCode()) {
                $this->recordApiMessage(ApiLookupStatus::RATE_LIMITED, 'Quota API dépassé');
            } else {
                $this->recordApiMessage(ApiLookupStatus::ERROR, $e->getErrorMessage());
            }

            return null;
        } catch (\Throwable $e) {
            $this->logger->error('Erreur Gemini : {error}', ['error' => $e->getMessage()]);
            $this->recordApiMessage(ApiLookupStatus::ERROR, 'Erreur de connexion');

            return null;
        }
    }

    private function consumeRateLimit(): bool
    {
        $limiter = $this->limiterFactory->create('gemini_global');

        if (!$limiter->consume()->isAccepted()) {
            $this->recordApiMessage(ApiLookupStatus::RATE_LIMITED, 'Quota dépassé');

            return false;
        }

        return true;
    }

    private function doEnrich(LookupResult $partial, ?ComicType $type): ?LookupResult
    {
        if (!$this->consumeRateLimit()) {
            return null;
        }

        $prompt = $this->buildEnrichPrompt($partial, $type);

        return $this->callGemini($prompt);
    }

    private function doLookup(string $query, ?ComicType $type, string $mode): ?LookupResult
    {
        if (!$this->consumeRateLimit()) {
            return null;
        }

        $prompt = $this->buildLookupPrompt($query, $type, $mode);

        return $this->callGemini($prompt);
    }

    /**
     * Parse le JSON depuis la réponse texte de Gemini (avec ou sans bloc markdown).
     *
     * @return array<string, mixed>|null
     */
    private function parseJsonFromText(string $text): ?array
    {
        // Supprime le bloc markdown ```json ... ``` si présent
        $cleaned = \preg_replace('/^```(?:json)?\s*\n?(.*?)\n?```$/s', '$1', \trim($text));

        $data = \json_decode($cleaned ?? $text, true);

        return \is_array($data) ? $data : null;
    }

    private function recordApiMessage(ApiLookupStatus $status, string $message): void
    {
        $this->lastApiMessage = ['message' => $message, 'status' => $status->value];
    }
}

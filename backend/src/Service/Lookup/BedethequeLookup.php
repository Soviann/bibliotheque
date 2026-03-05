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
 * Provider de recherche via Gemini avec Google Search grounding ciblant bedetheque.com.
 *
 * Bedetheque est la référence francophone pour les BD, mangas et comics.
 * Ce provider utilise Gemini avec grounding Google Search pour extraire
 * les données structurées depuis site:bedetheque.com.
 */
#[AutoconfigureTag('app.lookup_provider', ['priority' => 35])]
class BedethequeLookup extends AbstractLookupProvider
{
    private const string JSON_INSTRUCTIONS = <<<'TEXT'
        Réponds UNIQUEMENT avec un objet JSON (sans bloc markdown) contenant ces champs :
        - "title" (string|null) : titre de la série
        - "authors" (string|null) : auteur(s) séparés par des virgules (scénariste, dessinateur)
        - "publisher" (string|null) : éditeur français
        - "description" (string|null) : synopsis/résumé de la série
        - "thumbnail" (string|null) : URL image de couverture
        - "isOneShot" (boolean|null) : true = tome unique, false = série multi-tomes
        - "latestPublishedIssue" (integer|null) : nombre de tomes parus
        TEXT;

    private const string MODEL = 'gemini-2.5-flash';

    public function __construct(
        #[Autowire(service: 'gemini.cache')]
        private readonly AdapterInterface $cache,
        private readonly GeminiClient $geminiClient,
        #[Autowire(service: 'limiter.gemini_api')]
        private readonly RateLimiterFactory $limiterFactory,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function getFieldPriority(string $field, ?ComicType $type = null): int
    {
        if ('thumbnail' === $field) {
            return 50;
        }

        if (ComicType::BD === $type) {
            return 150;
        }

        return 110;
    }

    public function getName(): string
    {
        return 'bedetheque';
    }

    public function prepareLookup(string $query, ?ComicType $type, string $mode = 'title'): mixed
    {
        $this->resetApiMessage();

        $cacheKey = 'bedetheque_'.\md5($query.$mode.($type instanceof ComicType ? $type->value : ''));

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

        return ['cacheKey' => $cacheKey, 'prompt' => $this->buildPrompt($query, $type, $mode)];
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

    public function supports(string $mode, ?ComicType $type): bool
    {
        return \in_array($mode, ['isbn', 'title'], true);
    }

    private function buildPrompt(string $query, ?ComicType $type, string $mode): string
    {
        $typeLabel = $type instanceof ComicType ? $type->value : 'bande dessinée/comics/manga';
        $searchBy = 'isbn' === $mode
            ? "l'ISBN {$query}"
            : "le titre \"{$query}\"";
        $searchQuery = 'isbn' === $mode
            ? "site:bedetheque.com {$query}"
            : "site:bedetheque.com \"{$query}\"";

        return <<<PROMPT
            Tu es un assistant spécialisé en bandes dessinées, comics et mangas.
            Recherche les informations sur la série identifiée par {$searchBy} (type: {$typeLabel})
            EXCLUSIVEMENT sur le site bedetheque.com.

            Utilise Google Search avec la requête : {$searchQuery}

            Extrais les informations de la fiche série sur bedetheque.com.
            Si aucune fiche n'est trouvée sur bedetheque.com, retourne tous les champs à null.
            Ne complète PAS avec des informations provenant d'autres sites.

            PROMPT.self::JSON_INSTRUCTIONS;
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

            $hasData = false;
            foreach (['authors', 'description', 'publisher', 'thumbnail', 'title'] as $field) {
                if (!empty($data[$field])) {
                    $hasData = true;
                    break;
                }
            }

            if (!$hasData) {
                $this->recordApiMessage(ApiLookupStatus::NOT_FOUND, 'Aucun résultat sur bedetheque.com');

                return null;
            }

            $this->recordApiMessage(ApiLookupStatus::SUCCESS, 'Données trouvées via bedetheque.com');

            return new LookupResult(
                authors: \is_string($data['authors'] ?? null) ? $data['authors'] : null,
                description: \is_string($data['description'] ?? null) ? $data['description'] : null,
                isOneShot: \is_bool($data['isOneShot'] ?? null) ? $data['isOneShot'] : null,
                latestPublishedIssue: \is_int($data['latestPublishedIssue'] ?? null) ? $data['latestPublishedIssue'] : null,
                publisher: \is_string($data['publisher'] ?? null) ? $data['publisher'] : null,
                source: 'bedetheque',
                thumbnail: \is_string($data['thumbnail'] ?? null) ? $data['thumbnail'] : null,
                title: \is_string($data['title'] ?? null) ? $data['title'] : null,
            );
        } catch (ErrorException $e) {
            $this->logger->error('Erreur Gemini API (Bedetheque) : {error}', ['code' => $e->getErrorCode(), 'error' => $e->getMessage()]);

            if (429 === $e->getErrorCode()) {
                $this->recordApiMessage(ApiLookupStatus::RATE_LIMITED, 'Quota API dépassé');
            } else {
                $this->recordApiMessage(ApiLookupStatus::ERROR, $e->getErrorMessage());
            }

            return null;
        } catch (\Throwable $e) {
            $this->logger->error('Erreur Gemini (Bedetheque) : {error}', ['error' => $e->getMessage()]);
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

    /**
     * @return array<string, mixed>|null
     */
    private function parseJsonFromText(string $text): ?array
    {
        $cleaned = \preg_replace('/^```(?:json)?\s*\n?(.*?)\n?```$/s', '$1', \trim($text));

        $data = \json_decode($cleaned ?? $text, true);

        if (!\is_array($data)) {
            return null;
        }

        return $data; // @phpstan-ignore return.type
    }
}

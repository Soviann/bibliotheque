<?php

declare(strict_types=1);

namespace App\Service\Lookup;

use App\Enum\ComicType;
use Gemini\Contracts\ClientContract as GeminiClient;
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
#[AutoconfigureTag('app.lookup_provider', ['priority' => 45])]
class BedethequeLookup extends AbstractGeminiLookupProvider
{
    private const string JSON_INSTRUCTIONS = <<<'TEXT'
        Réponds UNIQUEMENT avec un objet JSON (sans bloc markdown) contenant ces champs :
        - "title" (string|null) : titre de la série
        - "authors" (string|null) : auteur(s) séparés par des virgules (scénariste, dessinateur)
        - "publisher" (string|null) : éditeur français
        - "publishedDate" (string|null) : date de première publication au format YYYY-MM-DD ou YYYY
        - "description" (string|null) : synopsis/résumé de la série
        - "thumbnail" (string|null) : URL image de couverture
        - "isOneShot" (boolean|null) : true = tome unique, false = série multi-tomes
        - "latestPublishedIssue" (integer|null) : nombre de tomes parus
        - "tomeNumber" (integer|null) : si cet ISBN correspond à un tome précis, son numéro. null si inconnu.
        - "tomeEnd" (integer|null) : uniquement pour les intégrales/omnibus, le dernier numéro couvert. null si tome simple.
        TEXT;

    public function __construct(
        #[Autowire(service: 'gemini.cache')]
        AdapterInterface $cache,
        GeminiClient $geminiClient,
        #[Autowire(service: 'limiter.gemini_api')]
        RateLimiterFactory $limiterFactory,
        LoggerInterface $logger,
    ) {
        parent::__construct($cache, $geminiClient, $limiterFactory, $logger);
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

        return $this->prepareWithCache($cacheKey, fn (): string => $this->buildPrompt($query, $type, $mode));
    }

    public function supports(string $mode, ?ComicType $type): bool
    {
        return \in_array($mode, ['isbn', 'title'], true);
    }

    protected function buildResult(array $data): LookupResult
    {
        return new LookupResult(
            authors: \is_string($data['authors'] ?? null) ? $data['authors'] : null,
            description: \is_string($data['description'] ?? null) ? $data['description'] : null,
            isOneShot: \is_bool($data['isOneShot'] ?? null) ? $data['isOneShot'] : null,
            latestPublishedIssue: \is_int($data['latestPublishedIssue'] ?? null) ? $data['latestPublishedIssue'] : null,
            publishedDate: \is_string($data['publishedDate'] ?? null) ? $data['publishedDate'] : null,
            publisher: \is_string($data['publisher'] ?? null) ? $data['publisher'] : null,
            source: 'bedetheque',
            thumbnail: \is_string($data['thumbnail'] ?? null) ? $data['thumbnail'] : null,
            title: \is_string($data['title'] ?? null) ? $data['title'] : null,
            tomeEnd: \is_int($data['tomeEnd'] ?? null) ? $data['tomeEnd'] : null,
            tomeNumber: \is_int($data['tomeNumber'] ?? null) ? $data['tomeNumber'] : null,
        );
    }

    protected function getLogName(): string
    {
        return 'Bedetheque';
    }

    protected function getNotFoundMessage(): string
    {
        return 'Aucun résultat sur bedetheque.com';
    }

    protected function getSuccessMessage(): string
    {
        return 'Données trouvées via bedetheque.com';
    }

    protected function getUsefulDataFields(): array
    {
        return ['authors', 'description', 'publishedDate', 'publisher', 'thumbnail', 'title'];
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
}

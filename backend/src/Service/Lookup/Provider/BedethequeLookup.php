<?php

declare(strict_types=1);

namespace App\Service\Lookup\Provider;

use App\Enum\ComicType;
use App\Enum\LookupMode;
use App\Service\Lookup\Contract\LookupResult;
use App\Service\Lookup\Gemini\AbstractGeminiLookupProvider;
use App\Service\Lookup\Gemini\GeminiClientPool;
use Psr\Log\LoggerInterface;
use Symfony\Component\Cache\Adapter\AdapterInterface;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;

/**
 * Provider de recherche via Gemini avec Google Search grounding ciblant bedetheque.com.
 *
 * Bedetheque est la référence francophone pour les BD, mangas et comics.
 * Ce provider utilise Gemini avec grounding Google Search pour extraire
 * les données structurées depuis site:bedetheque.com.
 */
#[AutoconfigureTag('app.lookup_provider', ['priority' => 45])]
final class BedethequeLookup extends AbstractGeminiLookupProvider
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
        GeminiClientPool $geminiClientPool,
        #[Autowire(service: 'limiter.gemini_api')]
        RateLimiterFactoryInterface $limiterFactory,
        LoggerInterface $logger,
    ) {
        parent::__construct($cache, $geminiClientPool, $limiterFactory, $logger);
    }

    public function getFieldPriority(string $field, ?ComicType $type = null): int
    {
        if (ComicType::BD === $type) {
            return 150;
        }

        if ('thumbnail' === $field) {
            return 50;
        }

        return 110;
    }

    public function getName(): string
    {
        return 'bedetheque';
    }

    public function prepareLookup(string $query, ?ComicType $type, LookupMode $mode = LookupMode::TITLE): mixed
    {
        $this->resetApiMessage();

        $cacheKey = 'bedetheque_'.\md5($query.$mode->value.($type instanceof ComicType ? $type->value : ''));

        return $this->prepareWithCache($cacheKey, fn (): string => $this->buildPrompt($query, $type, $mode));
    }

    public function supports(LookupMode $mode, ?ComicType $type): bool
    {
        return true;
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

    private function buildPrompt(string $query, ?ComicType $type, LookupMode $mode): string
    {
        $typeLabel = $type instanceof ComicType ? $type->value : 'bande dessinée/comics/manga';
        $searchBy = LookupMode::ISBN === $mode
            ? "l'ISBN {$query}"
            : "le titre \"{$query}\"";

        return <<<PROMPT
            Tu es un assistant spécialisé en bandes dessinées, comics et mangas.
            Recherche les informations sur la série identifiée par {$searchBy} (type: {$typeLabel}).

            Cherche en priorité sur les bases de données francophones de BD comme BDGest/Bedetheque.
            Extrais les informations les plus précises et complètes possibles.
            Si tu ne trouves aucune information fiable, retourne tous les champs à null.

            PROMPT.self::JSON_INSTRUCTIONS;
    }
}

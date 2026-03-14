<?php

declare(strict_types=1);

namespace App\Service\Lookup;

use App\Enum\ComicType;
use Psr\Log\LoggerInterface;
use Symfony\Component\Cache\Adapter\AdapterInterface;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\RateLimiter\RateLimiterFactory;

/**
 * Provider de recherche via l'API Google Gemini avec Google Search grounding.
 */
#[AutoconfigureTag('app.lookup_provider', ['priority' => 40])]
final class GeminiLookup extends AbstractGeminiLookupProvider implements EnrichableLookupProviderInterface
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
        - "tomeNumber" (integer|null) : si cet ISBN correspond à un tome précis, son numéro (ex : 4 pour le tome 4). Pour une intégrale/omnibus, le premier numéro couvert (ex : 4 pour « tomes 4-6 »). null si inconnu.
        - "tomeEnd" (integer|null) : uniquement pour les intégrales/omnibus couvrant plusieurs tomes, le dernier numéro couvert (ex : 6 pour « tomes 4-6 »). null si c'est un tome simple.
        - "amazonUrl" (string|null) : URL Amazon.fr de la page de la série (pas d'un tome spécifique). De préférence un lien vers la page regroupant tous les tomes.
        TEXT;

    public function __construct(
        #[Autowire(service: 'gemini.cache')]
        AdapterInterface $cache,
        GeminiClientPool $geminiClientPool,
        #[Autowire(service: 'limiter.gemini_api')]
        RateLimiterFactory $limiterFactory,
        LoggerInterface $logger,
    ) {
        parent::__construct($cache, $geminiClientPool, $limiterFactory, $logger);
    }

    public function getFieldPriority(string $field, ?ComicType $type = null): int
    {
        return 40;
    }

    public function getName(): string
    {
        return 'gemini';
    }

    public function prepareEnrich(LookupResult $partial, ?ComicType $type): mixed
    {
        $this->resetApiMessage();

        if (null === $partial->title || '' === $partial->title) {
            return null;
        }

        $cacheKey = 'gemini_enrich_'.\md5(\json_encode($partial->jsonSerialize()).($type instanceof ComicType ? $type->value : ''));

        return $this->prepareWithCache($cacheKey, fn (): string => $this->buildEnrichPrompt($partial, $type));
    }

    public function prepareLookup(string $query, ?ComicType $type, string $mode = 'title'): mixed
    {
        $this->resetApiMessage();

        $cacheKey = 'gemini_'.\md5($query.$mode.($type instanceof ComicType ? $type->value : ''));

        return $this->prepareWithCache($cacheKey, fn (): string => $this->buildLookupPrompt($query, $type, $mode));
    }

    public function resolveEnrich(mixed $state): ?LookupResult
    {
        return $this->resolveLookup($state);
    }

    public function supports(string $mode, ?ComicType $type): bool
    {
        return \in_array($mode, ['isbn', 'title'], true);
    }

    protected function buildResult(array $data): LookupResult
    {
        return new LookupResult(
            amazonUrl: \is_string($data['amazonUrl'] ?? null) ? $data['amazonUrl'] : null,
            authors: \is_string($data['authors'] ?? null) ? $data['authors'] : null,
            description: \is_string($data['description'] ?? null) ? $data['description'] : null,
            isOneShot: \is_bool($data['isOneShot'] ?? null) ? $data['isOneShot'] : null,
            latestPublishedIssue: \is_int($data['latestPublishedIssue'] ?? null) ? $data['latestPublishedIssue'] : null,
            publishedDate: \is_string($data['publishedDate'] ?? null) ? $data['publishedDate'] : null,
            publisher: \is_string($data['publisher'] ?? null) ? $data['publisher'] : null,
            source: 'gemini',
            thumbnail: \is_string($data['thumbnail'] ?? null) ? $data['thumbnail'] : null,
            title: \is_string($data['title'] ?? null) ? $data['title'] : null,
            tomeEnd: \is_int($data['tomeEnd'] ?? null) ? $data['tomeEnd'] : null,
            tomeNumber: \is_int($data['tomeNumber'] ?? null) ? $data['tomeNumber'] : null,
        );
    }

    protected function getLogName(): string
    {
        return 'Gemini';
    }

    protected function getNotFoundMessage(): string
    {
        return 'Aucun résultat';
    }

    protected function getSuccessMessage(): string
    {
        return 'Données trouvées via IA';
    }

    protected function getUsefulDataFields(): array
    {
        return ['authors', 'description', 'publishedDate', 'publisher', 'thumbnail', 'title'];
    }

    private function buildEnrichPrompt(LookupResult $partial, ?ComicType $type): string
    {
        $typeLabel = $type instanceof ComicType ? $type->value : 'bande dessinée/comics/manga';
        $existingData = \json_encode(\array_filter($partial->jsonSerialize(), static fn (bool|int|string|null $v): bool => null !== $v));

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
        $typeLabel = $type instanceof ComicType ? $type->value : 'bande dessinée/comics/manga';
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
}

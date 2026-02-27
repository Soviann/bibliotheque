<?php

declare(strict_types=1);

namespace App\Service\Lookup;

use App\Enum\ApiLookupStatus;
use App\Enum\ComicType;
use Psr\Log\LoggerInterface;
use Symfony\Component\Cache\Adapter\AdapterInterface;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\DecodingExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Provider de recherche via Wikidata + Wikipedia FR.
 *
 * Recherche par titre via wbsearchentities, par ISBN via SPARQL.
 * Extraction des métadonnées depuis les claims Wikidata, synopsis depuis Wikipedia FR.
 */
#[AutoconfigureTag('app.lookup_provider', ['priority' => 120])]
class WikipediaLookup implements EnrichableLookupProviderInterface
{
    private const int CACHE_TTL = 604800; // 7 jours

    /** @var list<string> Types P31 indiquant un one-shot */
    private const array ONE_SHOT_TYPES = [
        'Q725377',  // graphic novel
        'Q8274',    // manga (œuvre individuelle)
        'Q7725634', // literary work
    ];

    /** @var list<string> Types P31 pertinents pour les séries */
    private const array RELEVANT_P31 = [
        'Q1004',      // bande dessinée (concept)
        'Q8274',      // manga (individuel)
        'Q14406742',  // série de bande dessinée / comic book series
        'Q21198342',  // manga series
        'Q104213567', // manhwa series
        'Q725377',    // graphic novel
        'Q838795',    // comic strip
        'Q1667921',   // novel series
        'Q7725634',   // literary work
    ];

    /** @var list<string> Types P31 indiquant une série (pas un one-shot) */
    private const array SERIES_TYPES = [
        'Q1004',      // bande dessinée (concept)
        'Q14406742',  // série de bande dessinée / comic book series
        'Q21198342',  // manga series
        'Q104213567', // manhwa series
        'Q838795',    // comic strip
        'Q1667921',   // novel series
    ];

    private const string USER_AGENT = 'BibliothequeApp/1.0 (https://github.com/Soviann/bibliotheque)';

    private const string WIKIDATA_API = 'https://www.wikidata.org/w/api.php';

    private const string WIKIDATA_SPARQL = 'https://query.wikidata.org/sparql';

    private const string WIKIPEDIA_FR_API = 'https://fr.wikipedia.org/api/rest_v1/page/summary';

    /** @var array{status: string, message: string}|null */
    private ?array $lastApiMessage = null;

    public function __construct(
        #[Autowire(service: 'wikipedia.cache')]
        private readonly AdapterInterface $cache,
        private readonly HttpClientInterface $httpClient,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function getLastApiMessage(): ?array
    {
        return $this->lastApiMessage;
    }

    public function getFieldPriority(string $field, ?ComicType $type = null): int
    {
        if ('description' === $field) {
            return 10;
        }

        return 120;
    }

    public function getName(): string
    {
        return 'wikipedia';
    }

    public function prepareEnrich(LookupResult $partial, ?ComicType $type): mixed
    {
        $this->lastApiMessage = null;

        if (null === $partial->title || '' === $partial->title) {
            return null;
        }

        return $this->prepareLookup($partial->title, $type, 'title');
    }

    public function prepareLookup(string $query, ?ComicType $type, string $mode = 'title'): mixed
    {
        $this->lastApiMessage = null;

        $cacheKey = 'wikipedia_lookup_'.\md5($query.$mode.(null !== $type ? $type->value : ''));

        $item = $this->cache->getItem($cacheKey);

        if ($item->isHit()) {
            $cached = $item->get();
            if ($cached instanceof LookupResult) {
                $this->recordApiMessage(ApiLookupStatus::SUCCESS, 'Résultat depuis le cache');

                return $cached;
            }
        }

        if ('isbn' === $mode) {
            $isbnProp = 13 === \strlen(\str_replace('-', '', $query)) ? 'P212' : 'P957';

            $sparql = \sprintf(
                'SELECT ?item WHERE { ?item wdt:%s "%s" . } LIMIT 1',
                $isbnProp,
                \addslashes($query),
            );

            $response = $this->httpClient->request('GET', self::WIKIDATA_SPARQL, [
                'headers' => [
                    'Accept' => 'application/sparql-results+json',
                    'User-Agent' => self::USER_AGENT,
                ],
                'query' => ['query' => $sparql],
                'timeout' => 15,
            ]);
        } else {
            $response = $this->httpClient->request('GET', self::WIKIDATA_API, [
                'headers' => ['User-Agent' => self::USER_AGENT],
                'query' => [
                    'action' => 'wbsearchentities',
                    'format' => 'json',
                    'language' => 'fr',
                    'limit' => 5,
                    'search' => $query,
                    'type' => 'item',
                ],
                'timeout' => 10,
            ]);
        }

        return ['cacheKey' => $cacheKey, 'mode' => $mode, 'response' => $response];
    }

    public function resolveEnrich(mixed $state): ?LookupResult
    {
        if (null === $state) {
            return null;
        }

        return $this->resolveLookup($state);
    }

    public function resolveLookup(mixed $state): ?LookupResult
    {
        if ($state instanceof LookupResult) {
            return $state;
        }

        /* @var array{cacheKey: string, mode: string, response: \Symfony\Contracts\HttpClient\ResponseInterface} $state */
        try {
            $result = 'isbn' === $state['mode']
                ? $this->resolveIsbnLookup($state['response'])
                : $this->resolveTitleLookup($state['response']);
        } catch (ClientExceptionInterface|ServerExceptionInterface|RedirectionExceptionInterface $e) {
            $code = $e->getResponse()->getStatusCode();
            if (429 === $code) {
                $this->recordApiMessage(ApiLookupStatus::RATE_LIMITED, 'Quota dépassé (429)');
            } else {
                $this->recordApiMessage(ApiLookupStatus::ERROR, \sprintf('Erreur HTTP (%d)', $code));
            }
            $this->logger->warning('Erreur HTTP Wikipedia/Wikidata : {error}', ['error' => $e->getMessage()]);

            return null;
        } catch (TransportExceptionInterface $e) {
            $this->logger->error('Erreur réseau Wikipedia/Wikidata : {error}', ['error' => $e->getMessage()]);
            $this->recordApiMessage(ApiLookupStatus::ERROR, 'Erreur de connexion');

            return null;
        } catch (DecodingExceptionInterface $e) {
            $this->logger->error('Réponse JSON invalide Wikipedia/Wikidata : {error}', ['error' => $e->getMessage()]);
            $this->recordApiMessage(ApiLookupStatus::ERROR, 'Réponse invalide');

            return null;
        }

        if (null === $result) {
            return null;
        }

        $item = $this->cache->getItem($state['cacheKey']);
        $item->set($result);
        $item->expiresAfter(self::CACHE_TTL);
        $this->cache->save($item);

        return $result;
    }

    public function supports(string $mode, ?ComicType $type): bool
    {
        return \in_array($mode, ['isbn', 'title'], true);
    }

    /**
     * Construit l'URL de thumbnail depuis le nom de fichier Wikimedia Commons.
     */
    private function buildThumbnailUrl(string $filename): string
    {
        $filename = \str_replace(' ', '_', $filename);
        $md5 = \md5($filename);

        return \sprintf(
            'https://upload.wikimedia.org/wikipedia/commons/thumb/%s/%s/%s/300px-%s',
            $md5[0],
            $md5[0].$md5[1],
            $filename,
            $filename,
        );
    }

    /**
     * Extrait l'ID d'entité depuis une claim Wikidata.
     *
     * @param array<mixed, mixed> $claim
     */
    private function extractEntityId(array $claim): ?string
    {
        $mainsnak = \is_array($claim['mainsnak'] ?? null) ? $claim['mainsnak'] : [];
        $datavalue = \is_array($mainsnak['datavalue'] ?? null) ? $mainsnak['datavalue'] : [];
        $value = \is_array($datavalue['value'] ?? null) ? $datavalue['value'] : [];
        $id = $value['id'] ?? null;

        return \is_string($id) ? $id : null;
    }

    /**
     * Extrait les données depuis une entité Wikidata résolue.
     *
     * @param array<string, mixed> $entity
     * @param array<string, mixed> $allEntities Toutes les entités (pour résoudre les labels)
     */
    private function extractFromEntity(array $entity, array $allEntities): ?LookupResult
    {
        $claims = \is_array($entity['claims'] ?? null) ? $entity['claims'] : [];

        // Vérifier P31 pour pertinence (exiger au moins un P31 reconnu)
        $p31Ids = $this->getP31Ids($claims);

        if (empty($p31Ids) || 0 === \count(\array_intersect($p31Ids, self::RELEVANT_P31))) {
            return null;
        }

        // Détecter one-shot
        $isSeries = \count(\array_intersect($p31Ids, self::SERIES_TYPES)) > 0;
        $isOneShotType = \count(\array_intersect($p31Ids, self::ONE_SHOT_TYPES)) > 0;
        $isOneShot = !$isSeries && $isOneShotType ? true : ($isSeries ? false : null);

        // Titre
        $labels = \is_array($entity['labels'] ?? null) ? $entity['labels'] : [];
        $frLabel = \is_array($labels['fr'] ?? null) ? $labels['fr'] : [];
        $title = \is_string($frLabel['value'] ?? null) ? $frLabel['value'] : null;

        // Auteur (P50)
        $authors = $this->resolveEntityLabel($claims, 'P50', $allEntities);

        // Éditeur (P123)
        $publisher = $this->resolveEntityLabel($claims, 'P123', $allEntities);

        // Date de publication (P577)
        $publishedDate = $this->extractPublishedDate($claims);

        // Thumbnail (P18)
        $thumbnail = null;
        $p18Value = $this->extractP18Value($claims);
        if (null !== $p18Value) {
            $thumbnail = $this->buildThumbnailUrl($p18Value);
        }

        // Description depuis Wikipedia FR
        $description = null;
        $sitelinks = \is_array($entity['sitelinks'] ?? null) ? $entity['sitelinks'] : [];
        $frWiki = \is_array($sitelinks['frwiki'] ?? null) ? $sitelinks['frwiki'] : [];
        $frWikiTitle = \is_string($frWiki['title'] ?? null) ? $frWiki['title'] : null;
        if (null !== $frWikiTitle) {
            $description = $this->fetchWikipediaSummary($frWikiTitle);
        }

        $this->recordApiMessage(ApiLookupStatus::SUCCESS, 'Données trouvées');

        return new LookupResult(
            authors: $authors,
            description: $description,
            isOneShot: $isOneShot,
            publishedDate: $publishedDate,
            publisher: $publisher,
            source: 'wikipedia',
            thumbnail: $thumbnail,
            title: $title,
        );
    }

    /**
     * Extrait la valeur du fichier image depuis P18.
     *
     * @param array<string, mixed> $claims
     */
    private function extractP18Value(array $claims): ?string
    {
        if (!isset($claims['P18']) || !\is_array($claims['P18'])) {
            return null;
        }

        $firstClaim = $claims['P18'][0] ?? null;
        if (!\is_array($firstClaim)) {
            return null;
        }

        $mainsnak = \is_array($firstClaim['mainsnak'] ?? null) ? $firstClaim['mainsnak'] : [];
        $datavalue = \is_array($mainsnak['datavalue'] ?? null) ? $mainsnak['datavalue'] : [];
        $value = $datavalue['value'] ?? null;

        return \is_string($value) ? $value : null;
    }

    /**
     * Extrait la date de publication depuis P577.
     *
     * @param array<string, mixed> $claims
     */
    private function extractPublishedDate(array $claims): ?string
    {
        if (!isset($claims['P577']) || !\is_array($claims['P577'])) {
            return null;
        }

        $firstClaim = $claims['P577'][0] ?? null;
        if (!\is_array($firstClaim)) {
            return null;
        }

        $mainsnak = \is_array($firstClaim['mainsnak'] ?? null) ? $firstClaim['mainsnak'] : [];
        $datavalue = \is_array($mainsnak['datavalue'] ?? null) ? $mainsnak['datavalue'] : [];
        $value = \is_array($datavalue['value'] ?? null) ? $datavalue['value'] : [];
        $time = $value['time'] ?? null;

        if (!\is_string($time)) {
            return null;
        }

        // Format Wikidata : "+1997-07-22T00:00:00Z"
        $cleaned = \ltrim($time, '+');

        if (\preg_match('/^(\d{4}-\d{2}-\d{2})/', $cleaned, $matches)) {
            return $matches[1];
        }

        return null;
    }

    /**
     * Récupère le résumé depuis Wikipedia FR REST API.
     */
    private function fetchWikipediaSummary(string $title): ?string
    {
        try {
            $response = $this->httpClient->request('GET', self::WIKIPEDIA_FR_API.'/'.\rawurlencode($title), [
                'headers' => ['User-Agent' => self::USER_AGENT],
                'timeout' => 10,
            ]);

            $data = $response->toArray();

            $extract = $data['extract'] ?? null;

            return \is_string($extract) ? $extract : null;
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Récupère les entités Wikidata par leurs IDs.
     *
     * @param list<string> $ids
     *
     * @return array<string, mixed>
     */
    private function fetchWikidataEntities(array $ids): array
    {
        $response = $this->httpClient->request('GET', self::WIKIDATA_API, [
            'headers' => ['User-Agent' => self::USER_AGENT],
            'query' => [
                'action' => 'wbgetentities',
                'format' => 'json',
                'ids' => \implode('|', $ids),
                'languages' => 'fr',
                'props' => 'claims|labels|sitelinks',
            ],
            'timeout' => 10,
        ]);

        $data = $response->toArray();
        $entities = $data['entities'] ?? [];

        return \is_array($entities) ? $entities : [];
    }

    /**
     * Extrait les IDs P31 (instance of) depuis les claims.
     *
     * @param array<string, mixed> $claims
     *
     * @return list<string>
     */
    private function getP31Ids(array $claims): array
    {
        $ids = [];
        $p31Claims = $claims['P31'] ?? [];

        if (!\is_array($p31Claims)) {
            return [];
        }

        foreach ($p31Claims as $claim) {
            if (!\is_array($claim)) {
                continue;
            }

            $id = $this->extractEntityId($claim);
            if (null !== $id) {
                $ids[] = $id;
            }
        }

        return $ids;
    }

    /**
     * Traite la réponse SPARQL pour résoudre l'entité Wikidata.
     */
    /**
     * @param \Symfony\Contracts\HttpClient\ResponseInterface $response
     */
    private function resolveIsbnLookup(mixed $response): ?LookupResult
    {
        $data = $response->toArray();
        $bindings = $data['results']['bindings'] ?? [];

        if (!\is_array($bindings) || empty($bindings)) {
            $this->recordApiMessage(ApiLookupStatus::NOT_FOUND, 'Aucun résultat');

            return null;
        }

        $firstBinding = $bindings[0] ?? [];
        $item = \is_array($firstBinding) && \is_array($firstBinding['item'] ?? null) ? $firstBinding['item'] : [];
        $entityUri = \is_string($item['value'] ?? null) ? $item['value'] : '';
        $entityId = \basename($entityUri);

        if ('' === $entityId) {
            $this->recordApiMessage(ApiLookupStatus::NOT_FOUND, 'Aucun résultat');

            return null;
        }

        return $this->resolveEntity($entityId);
    }

    /**
     * Traite la réponse wbsearchentities pour résoudre l'entité Wikidata.
     */
    /**
     * @param \Symfony\Contracts\HttpClient\ResponseInterface $response
     */
    private function resolveTitleLookup(mixed $response): ?LookupResult
    {
        $data = $response->toArray();
        $searchResults = $data['search'] ?? [];

        if (!\is_array($searchResults) || empty($searchResults)) {
            $this->recordApiMessage(ApiLookupStatus::NOT_FOUND, 'Aucun résultat');

            return null;
        }

        foreach ($searchResults as $candidate) {
            if (!\is_array($candidate)) {
                continue;
            }

            $entityId = $candidate['id'] ?? null;
            if (!\is_string($entityId)) {
                continue;
            }

            $result = $this->resolveEntity($entityId);
            if (null !== $result) {
                return $result;
            }
        }

        $this->recordApiMessage(ApiLookupStatus::NOT_FOUND, 'Aucun résultat pertinent');

        return null;
    }

    private function recordApiMessage(ApiLookupStatus $status, string $message): void
    {
        $this->lastApiMessage = ['message' => $message, 'status' => $status->value];
    }

    /**
     * Résout une entité Wikidata : gère les éditions (P629) et extrait les données.
     */
    private function resolveEntity(string $entityId): ?LookupResult
    {
        $entities = $this->fetchWikidataEntities([$entityId]);
        $entity = $entities[$entityId] ?? null;

        if (!\is_array($entity)) {
            return null;
        }

        $claims = \is_array($entity['claims'] ?? null) ? $entity['claims'] : [];

        // Si c'est une édition (P31 = Q3331189), remonter à l'œuvre via P629
        $p31Ids = $this->getP31Ids($claims);
        if (\in_array('Q3331189', $p31Ids, true)) {
            $p629Claims = \is_array($claims['P629'] ?? null) ? $claims['P629'] : [];
            $workId = null;
            foreach ($p629Claims as $claim) {
                if (!\is_array($claim)) {
                    continue;
                }
                $workId = $this->extractEntityId($claim);
                if (null !== $workId) {
                    break;
                }
            }

            if (null !== $workId) {
                return $this->resolveEntity($workId);
            }
        }

        // Collecter les IDs d'entités à résoudre (auteur, éditeur)
        $relatedIds = [];
        foreach (['P50', 'P123'] as $prop) {
            $propClaims = \is_array($claims[$prop] ?? null) ? $claims[$prop] : [];
            foreach ($propClaims as $claim) {
                if (!\is_array($claim)) {
                    continue;
                }
                $id = $this->extractEntityId($claim);
                if (null !== $id) {
                    $relatedIds[] = $id;
                }
            }
        }

        // Récupérer les entités liées en un seul appel
        $allEntities = $entities;
        if (!empty($relatedIds)) {
            $missingIds = \array_diff($relatedIds, \array_keys($allEntities));
            if (!empty($missingIds)) {
                $relatedEntities = $this->fetchWikidataEntities(\array_values($missingIds));
                $allEntities = \array_merge($allEntities, $relatedEntities);
            }
        }

        return $this->extractFromEntity($entity, $allEntities);
    }

    /**
     * Résout le label d'une entité référencée par une claim.
     *
     * @param array<string, mixed> $claims
     * @param array<string, mixed> $allEntities
     */
    private function resolveEntityLabel(array $claims, string $property, array $allEntities): ?string
    {
        $propClaims = \is_array($claims[$property] ?? null) ? $claims[$property] : [];
        $entityId = null;

        foreach ($propClaims as $claim) {
            if (!\is_array($claim)) {
                continue;
            }
            $entityId = $this->extractEntityId($claim);
            if (null !== $entityId) {
                break;
            }
        }

        if (null === $entityId) {
            return null;
        }

        $relatedEntity = $allEntities[$entityId] ?? null;
        if (!\is_array($relatedEntity)) {
            return null;
        }

        $labels = \is_array($relatedEntity['labels'] ?? null) ? $relatedEntity['labels'] : [];
        $frLabel = \is_array($labels['fr'] ?? null) ? $labels['fr'] : [];
        $value = $frLabel['value'] ?? null;

        return \is_string($value) ? $value : null;
    }
}

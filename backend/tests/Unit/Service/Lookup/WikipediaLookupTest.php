<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Lookup;

use App\Enum\ComicType;
use App\Service\Lookup\LookupResult;
use App\Service\Lookup\WikipediaLookup;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Cache\Adapter\AdapterInterface;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Cache\CacheItem;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * Tests unitaires pour WikipediaLookup.
 */
final class WikipediaLookupTest extends TestCase
{
    private AdapterInterface&MockObject $cache;
    private HttpClientInterface&MockObject $httpClient;
    private LoggerInterface&MockObject $logger;
    private WikipediaLookup $provider;

    protected function setUp(): void
    {
        $this->cache = $this->createMock(AdapterInterface::class);
        $this->httpClient = $this->createMock(HttpClientInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->provider = new WikipediaLookup(
            $this->cache,
            $this->httpClient,
            $this->logger,
        );
    }

    /**
     * Teste que getFieldPriority retourne 10 pour description.
     */
    public function testGetFieldPriorityReturns10ForDescription(): void
    {
        self::assertSame(10, $this->provider->getFieldPriority('description'));
        self::assertSame(10, $this->provider->getFieldPriority('description', ComicType::MANGA));
    }

    /**
     * Teste que getFieldPriority retourne 120 pour les autres champs.
     */
    public function testGetFieldPriorityReturns120ForOtherFields(): void
    {
        self::assertSame(120, $this->provider->getFieldPriority('title'));
        self::assertSame(120, $this->provider->getFieldPriority('authors'));
        self::assertSame(120, $this->provider->getFieldPriority('thumbnail'));
        self::assertSame(120, $this->provider->getFieldPriority('publisher', ComicType::BD));
    }

    /**
     * Teste que getName retourne 'wikipedia'.
     */
    public function testGetNameReturnsWikipedia(): void
    {
        self::assertSame('wikipedia', $this->provider->getName());
    }

    /**
     * Teste que supports retourne true pour isbn et title.
     */
    public function testSupportsIsbnAndTitle(): void
    {
        self::assertTrue($this->provider->supports('isbn', null));
        self::assertTrue($this->provider->supports('title', null));
        self::assertTrue($this->provider->supports('isbn', ComicType::MANGA));
        self::assertTrue($this->provider->supports('title', ComicType::BD));
    }

    /**
     * Teste que supports retourne false pour les modes non supportes.
     */
    public function testDoesNotSupportOtherModes(): void
    {
        self::assertFalse($this->provider->supports('author', null));
    }

    /**
     * Teste que prepareLookup retourne le resultat depuis le cache quand disponible.
     */
    public function testPrepareLookupReturnsCachedResult(): void
    {
        $cachedResult = new LookupResult(title: 'One Piece', source: 'wikipedia');

        $cacheItem = $this->createCacheItem('test_key', $cachedResult, true);
        $this->cache->method('getItem')->willReturn($cacheItem);

        $this->httpClient->expects(self::never())->method('request');

        $state = $this->provider->prepareLookup('One Piece', ComicType::MANGA, 'title');

        self::assertInstanceOf(LookupResult::class, $state);
        self::assertSame('One Piece', $state->title);
    }

    /**
     * Teste que resolveLookup retourne directement un LookupResult passe en etat.
     */
    public function testResolveLookupReturnsCachedResultDirectly(): void
    {
        $cachedResult = new LookupResult(title: 'Cached', source: 'wikipedia');

        $result = $this->provider->resolveLookup($cachedResult);

        self::assertSame($cachedResult, $result);
    }

    /**
     * Teste que prepareLookup en mode isbn envoie une requete SPARQL.
     */
    public function testPrepareLookupIsbnModeSendsSparqlQuery(): void
    {
        $cacheItem = $this->createCacheItem('test_key', null, false);
        $this->cache->method('getItem')->willReturn($cacheItem);

        $response = $this->createMock(ResponseInterface::class);

        $this->httpClient->expects(self::once())
            ->method('request')
            ->with(
                'GET',
                'https://query.wikidata.org/sparql',
                self::callback(static function (array $options): bool {
                    return \str_contains($options['query']['query'], 'P212')
                        && \str_contains($options['query']['query'], '9782723489003')
                        && 'application/sparql-results+json' === $options['headers']['Accept'];
                }),
            )
            ->willReturn($response);

        $state = $this->provider->prepareLookup('9782723489003', null, 'isbn');

        self::assertIsArray($state);
        self::assertSame('isbn', $state['mode']);
        self::assertArrayHasKey('cacheKey', $state);
        self::assertSame($response, $state['response']);
    }

    /**
     * Teste que prepareLookup en mode isbn utilise P957 pour ISBN-10.
     */
    public function testPrepareLookupIsbnModeUsesP957ForIsbn10(): void
    {
        $cacheItem = $this->createCacheItem('test_key', null, false);
        $this->cache->method('getItem')->willReturn($cacheItem);

        $response = $this->createMock(ResponseInterface::class);

        $this->httpClient->expects(self::once())
            ->method('request')
            ->with(
                'GET',
                'https://query.wikidata.org/sparql',
                self::callback(static function (array $options): bool {
                    return \str_contains($options['query']['query'], 'P957');
                }),
            )
            ->willReturn($response);

        $this->provider->prepareLookup('2723489000', null, 'isbn');
    }

    /**
     * Teste que prepareLookup en mode title envoie une requete wbsearchentities.
     */
    public function testPrepareLookupTitleModeSendsSearchRequest(): void
    {
        $cacheItem = $this->createCacheItem('test_key', null, false);
        $this->cache->method('getItem')->willReturn($cacheItem);

        $response = $this->createMock(ResponseInterface::class);

        $this->httpClient->expects(self::once())
            ->method('request')
            ->with(
                'GET',
                'https://www.wikidata.org/w/api.php',
                self::callback(static function (array $options): bool {
                    return 'wbsearchentities' === $options['query']['action']
                        && 'One Piece' === $options['query']['search']
                        && 'fr' === $options['query']['language']
                        && 'json' === $options['query']['format'];
                }),
            )
            ->willReturn($response);

        $state = $this->provider->prepareLookup('One Piece', ComicType::MANGA, 'title');

        self::assertIsArray($state);
        self::assertSame('title', $state['mode']);
    }

    /**
     * Teste resolveLookup en cas d'erreur de transport.
     */
    public function testResolveLookupTransportException(): void
    {
        $exception = new class ('Connection error') extends \RuntimeException implements TransportExceptionInterface {};

        $response = $this->createMock(ResponseInterface::class);
        $response->method('toArray')->willThrowException($exception);

        $state = [
            'cacheKey' => 'test_key',
            'mode' => 'title',
            'response' => $response,
        ];

        $this->logger->expects(self::once())->method('error');

        $result = $this->provider->resolveLookup($state);

        self::assertNull($result);

        $apiMessage = $this->provider->getLastApiMessage();
        self::assertSame('error', $apiMessage['status']);
    }

    /**
     * Teste resolveLookup en cas d'erreur HTTP 429.
     */
    public function testResolveLookupRateLimited(): void
    {
        $innerResponse = $this->createMock(ResponseInterface::class);
        $innerResponse->method('getStatusCode')->willReturn(429);

        $exception = new class ('Rate limited', $innerResponse) extends \RuntimeException implements ClientExceptionInterface {
            public function __construct(
                string $message,
                private readonly ResponseInterface $response,
            ) {
                parent::__construct($message);
            }

            public function getResponse(): ResponseInterface
            {
                return $this->response;
            }
        };

        $response = $this->createMock(ResponseInterface::class);
        $response->method('toArray')->willThrowException($exception);

        $state = [
            'cacheKey' => 'test_key',
            'mode' => 'title',
            'response' => $response,
        ];

        $result = $this->provider->resolveLookup($state);

        self::assertNull($result);

        $apiMessage = $this->provider->getLastApiMessage();
        self::assertSame('rate_limited', $apiMessage['status']);
    }

    /**
     * Teste resolveLookup en mode title quand aucune entite trouvee.
     */
    public function testResolveLookupTitleModeNoResults(): void
    {
        $response = $this->createMock(ResponseInterface::class);
        $response->method('toArray')->willReturn([
            'search' => [],
        ]);

        $state = [
            'cacheKey' => 'test_key',
            'mode' => 'title',
            'response' => $response,
        ];

        $result = $this->provider->resolveLookup($state);

        self::assertNull($result);

        $apiMessage = $this->provider->getLastApiMessage();
        self::assertSame('not_found', $apiMessage['status']);
    }

    /**
     * Teste resolveLookup en mode isbn quand aucun binding SPARQL.
     */
    public function testResolveLookupIsbnModeNoBindings(): void
    {
        $response = $this->createMock(ResponseInterface::class);
        $response->method('toArray')->willReturn([
            'results' => [
                'bindings' => [],
            ],
        ]);

        $state = [
            'cacheKey' => 'test_key',
            'mode' => 'isbn',
            'response' => $response,
        ];

        $result = $this->provider->resolveLookup($state);

        self::assertNull($result);

        $apiMessage = $this->provider->getLastApiMessage();
        self::assertSame('not_found', $apiMessage['status']);
    }

    /**
     * Teste resolveLookup met en cache le resultat en cas de succes.
     */
    public function testResolveLookupCachesSuccessfulResult(): void
    {
        // Premiere requete : wbsearchentities
        $searchResponse = $this->createMock(ResponseInterface::class);
        $searchResponse->method('toArray')->willReturn([
            'search' => [
                ['id' => 'Q634523'],
            ],
        ]);

        // Deuxieme requete : wbgetentities pour l'entite
        $entityResponse = $this->createMock(ResponseInterface::class);
        $entityResponse->method('toArray')->willReturn([
            'entities' => [
                'Q634523' => [
                    'claims' => [
                        'P31' => [
                            [
                                'mainsnak' => [
                                    'datavalue' => [
                                        'value' => ['id' => 'Q21198342'],
                                    ],
                                ],
                            ],
                        ],
                    ],
                    'labels' => [
                        'fr' => ['value' => 'One Piece'],
                    ],
                    'sitelinks' => [],
                ],
            ],
        ]);

        $this->httpClient->method('request')->willReturn($entityResponse);

        $realCache = new ArrayAdapter();
        $cacheItem = $realCache->getItem('test_key');
        $this->cache->method('getItem')->willReturn($cacheItem);
        $this->cache->expects(self::once())->method('save');

        $state = [
            'cacheKey' => 'test_key',
            'mode' => 'title',
            'response' => $searchResponse,
        ];

        $result = $this->provider->resolveLookup($state);

        self::assertNotNull($result);
        self::assertSame('One Piece', $result->title);
        self::assertSame('wikipedia', $result->source);
    }

    /**
     * Cree un CacheItem avec les valeurs souhaitees.
     */
    private function createCacheItem(string $key, mixed $value, bool $isHit): CacheItem
    {
        $realCache = new ArrayAdapter();

        if ($isHit && null !== $value) {
            $realCache->get($key, static fn () => $value);
        }

        return $realCache->getItem($key);
    }
}

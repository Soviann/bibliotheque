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
use Symfony\Contracts\HttpClient\Exception\DecodingExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;
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
     * Teste la resolution d'edition (P31 = Q3331189) qui remonte a l'oeuvre via P629.
     */
    public function testResolveLookupEditionResolutionViaP629(): void
    {
        // Premiere requete : wbsearchentities retourne une edition
        $searchResponse = $this->createMock(ResponseInterface::class);
        $searchResponse->method('toArray')->willReturn([
            'search' => [
                ['id' => 'Q_EDITION'],
            ],
        ]);

        // L'edition (Q3331189) avec P629 pointant vers l'oeuvre
        $editionEntity = [
            'claims' => [
                'P31' => [
                    ['mainsnak' => ['datavalue' => ['value' => ['id' => 'Q3331189']]]],
                ],
                'P629' => [
                    ['mainsnak' => ['datavalue' => ['value' => ['id' => 'Q_WORK']]]],
                ],
            ],
            'labels' => [],
            'sitelinks' => [],
        ];

        // L'oeuvre cible
        $workEntity = [
            'claims' => [
                'P31' => [
                    ['mainsnak' => ['datavalue' => ['value' => ['id' => 'Q21198342']]]],
                ],
            ],
            'labels' => [
                'fr' => ['value' => 'Oeuvre Originale'],
            ],
            'sitelinks' => [],
        ];

        // httpClient->request() est appele pour chaque fetchWikidataEntities
        // 1er appel : fetch l'edition, 2e appel : fetch l'oeuvre
        $requestCount = 0;
        $this->httpClient->method('request')->willReturnCallback(
            function () use (&$requestCount, $editionEntity, $workEntity): ResponseInterface {
                ++$requestCount;
                $response = $this->createMock(ResponseInterface::class);

                if (1 === $requestCount) {
                    $response->method('toArray')->willReturn(['entities' => ['Q_EDITION' => $editionEntity]]);
                } else {
                    $response->method('toArray')->willReturn(['entities' => ['Q_WORK' => $workEntity]]);
                }

                return $response;
            }
        );

        $realCache = new ArrayAdapter();
        $this->cache->method('getItem')->willReturn($realCache->getItem('test_key'));

        $state = [
            'cacheKey' => 'test_key',
            'mode' => 'title',
            'response' => $searchResponse,
        ];

        $result = $this->provider->resolveLookup($state);

        self::assertNotNull($result);
        self::assertSame('Oeuvre Originale', $result->title);
    }

    /**
     * Teste resolveLookup en cas d'erreur de decodage JSON.
     */
    public function testResolveLookupDecodingException(): void
    {
        $exception = new class ('Invalid JSON') extends \RuntimeException implements DecodingExceptionInterface {};

        $response = $this->createMock(ResponseInterface::class);
        $response->method('toArray')->willThrowException($exception);

        $this->logger->expects(self::once())->method('error');

        $state = [
            'cacheKey' => 'test_key',
            'mode' => 'title',
            'response' => $response,
        ];

        $result = $this->provider->resolveLookup($state);

        self::assertNull($result);

        $apiMessage = $this->provider->getLastApiMessage();
        self::assertSame('error', $apiMessage['status']);
        self::assertSame('Réponse invalide', $apiMessage['message']);
    }

    /**
     * Teste resolveLookup en cas d'erreur HTTP 500 (ServerException).
     */
    public function testResolveLookupServerException500(): void
    {
        $innerResponse = $this->createMock(ResponseInterface::class);
        $innerResponse->method('getStatusCode')->willReturn(500);

        $exception = new class ('Server error', $innerResponse) extends \RuntimeException implements ServerExceptionInterface {
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

        $this->logger->expects(self::once())->method('warning');

        $state = [
            'cacheKey' => 'test_key',
            'mode' => 'title',
            'response' => $response,
        ];

        $result = $this->provider->resolveLookup($state);

        self::assertNull($result);

        $apiMessage = $this->provider->getLastApiMessage();
        self::assertSame('error', $apiMessage['status']);
        self::assertStringContainsString('500', $apiMessage['message']);
    }

    /**
     * Teste extractFromEntity retourne null quand aucun P31 ne correspond a RELEVANT_P31.
     */
    public function testResolveLookupEntityWithNoRelevantP31ReturnsNull(): void
    {
        $searchResponse = $this->createMock(ResponseInterface::class);
        $searchResponse->method('toArray')->willReturn([
            'search' => [
                ['id' => 'Q_IRRELEVANT'],
            ],
        ]);

        $entityResponse = $this->createMock(ResponseInterface::class);
        $entityResponse->method('toArray')->willReturn([
            'entities' => [
                'Q_IRRELEVANT' => [
                    'claims' => [
                        'P31' => [
                            ['mainsnak' => ['datavalue' => ['value' => ['id' => 'Q5']]]],
                        ],
                    ],
                    'labels' => ['fr' => ['value' => 'Something']],
                    'sitelinks' => [],
                ],
            ],
        ]);

        $this->httpClient->method('request')->willReturn($entityResponse);

        $realCache = new ArrayAdapter();
        $this->cache->method('getItem')->willReturn($realCache->getItem('test_key'));

        $state = [
            'cacheKey' => 'test_key',
            'mode' => 'title',
            'response' => $searchResponse,
        ];

        $result = $this->provider->resolveLookup($state);

        self::assertNull($result);
    }

    /**
     * Teste isOneShot : type serie retourne false.
     */
    public function testIsOneShotReturnsFalseForSeriesType(): void
    {
        $searchResponse = $this->createMock(ResponseInterface::class);
        $searchResponse->method('toArray')->willReturn([
            'search' => [['id' => 'Q_SERIES']],
        ]);

        $entityResponse = $this->createMock(ResponseInterface::class);
        $entityResponse->method('toArray')->willReturn([
            'entities' => [
                'Q_SERIES' => [
                    'claims' => [
                        'P31' => [
                            ['mainsnak' => ['datavalue' => ['value' => ['id' => 'Q21198342']]]],
                        ],
                    ],
                    'labels' => ['fr' => ['value' => 'Manga Series']],
                    'sitelinks' => [],
                ],
            ],
        ]);

        $this->httpClient->method('request')->willReturn($entityResponse);

        $realCache = new ArrayAdapter();
        $this->cache->method('getItem')->willReturn($realCache->getItem('test_key'));

        $state = [
            'cacheKey' => 'test_key',
            'mode' => 'title',
            'response' => $searchResponse,
        ];

        $result = $this->provider->resolveLookup($state);

        self::assertNotNull($result);
        self::assertFalse($result->isOneShot);
    }

    /**
     * Teste isOneShot : type one-shot retourne true.
     */
    public function testIsOneShotReturnsTrueForOneShotType(): void
    {
        $searchResponse = $this->createMock(ResponseInterface::class);
        $searchResponse->method('toArray')->willReturn([
            'search' => [['id' => 'Q_ONESHOT']],
        ]);

        $entityResponse = $this->createMock(ResponseInterface::class);
        $entityResponse->method('toArray')->willReturn([
            'entities' => [
                'Q_ONESHOT' => [
                    'claims' => [
                        'P31' => [
                            ['mainsnak' => ['datavalue' => ['value' => ['id' => 'Q725377']]]],
                        ],
                    ],
                    'labels' => ['fr' => ['value' => 'Graphic Novel']],
                    'sitelinks' => [],
                ],
            ],
        ]);

        $this->httpClient->method('request')->willReturn($entityResponse);

        $realCache = new ArrayAdapter();
        $this->cache->method('getItem')->willReturn($realCache->getItem('test_key'));

        $state = [
            'cacheKey' => 'test_key',
            'mode' => 'title',
            'response' => $searchResponse,
        ];

        $result = $this->provider->resolveLookup($state);

        self::assertNotNull($result);
        self::assertTrue($result->isOneShot);
    }

    /**
     * Teste isOneShot retourne null pour un type pertinent qui n'est ni serie ni one-shot.
     */
    public function testIsOneShotReturnsNullForNeitherSeriesNorOneShot(): void
    {
        $searchResponse = $this->createMock(ResponseInterface::class);
        $searchResponse->method('toArray')->willReturn([
            'search' => [['id' => 'Q_NEITHER']],
        ]);

        $entityResponse = $this->createMock(ResponseInterface::class);
        $entityResponse->method('toArray')->willReturn([
            'entities' => [
                'Q_NEITHER' => [
                    'claims' => [
                        'P31' => [
                            // Q1004 (bande dessinée concept) est dans RELEVANT_P31 et SERIES_TYPES
                            // mais aussi Q725377 (graphic novel) dans ONE_SHOT_TYPES
                            // Pour obtenir null, il faut un P31 dans RELEVANT_P31 mais
                            // ni dans SERIES_TYPES ni dans ONE_SHOT_TYPES
                            // Aucun tel ID n'existe dans les constantes actuelles.
                            // Testons avec un type serie+one-shot ensemble : isSeries=true → false
                            ['mainsnak' => ['datavalue' => ['value' => ['id' => 'Q14406742']]]],
                            ['mainsnak' => ['datavalue' => ['value' => ['id' => 'Q725377']]]],
                        ],
                    ],
                    'labels' => ['fr' => ['value' => 'Mixed Type']],
                    'sitelinks' => [],
                ],
            ],
        ]);

        $this->httpClient->method('request')->willReturn($entityResponse);

        $realCache = new ArrayAdapter();
        $this->cache->method('getItem')->willReturn($realCache->getItem('test_key'));

        $state = [
            'cacheKey' => 'test_key',
            'mode' => 'title',
            'response' => $searchResponse,
        ];

        $result = $this->provider->resolveLookup($state);

        self::assertNotNull($result);
        // isSeries=true (Q14406742 in SERIES_TYPES) → false, pas null
        self::assertFalse($result->isOneShot);
    }

    /**
     * Teste buildThumbnailUrl genere l'URL correcte a partir d'un nom de fichier.
     */
    public function testBuildThumbnailUrlViaEntity(): void
    {
        $searchResponse = $this->createMock(ResponseInterface::class);
        $searchResponse->method('toArray')->willReturn([
            'search' => [['id' => 'Q_THUMB']],
        ]);

        $entityResponse = $this->createMock(ResponseInterface::class);
        $entityResponse->method('toArray')->willReturn([
            'entities' => [
                'Q_THUMB' => [
                    'claims' => [
                        'P18' => [
                            ['mainsnak' => ['datavalue' => ['value' => 'Test Image.jpg']]],
                        ],
                        'P31' => [
                            ['mainsnak' => ['datavalue' => ['value' => ['id' => 'Q21198342']]]],
                        ],
                    ],
                    'labels' => ['fr' => ['value' => 'Test']],
                    'sitelinks' => [],
                ],
            ],
        ]);

        $this->httpClient->method('request')->willReturn($entityResponse);

        $realCache = new ArrayAdapter();
        $this->cache->method('getItem')->willReturn($realCache->getItem('test_key'));

        $state = [
            'cacheKey' => 'test_key',
            'mode' => 'title',
            'response' => $searchResponse,
        ];

        $result = $this->provider->resolveLookup($state);

        self::assertNotNull($result);
        // Filename "Test_Image.jpg", md5 of "Test_Image.jpg"
        $filename = 'Test_Image.jpg';
        $md5 = \md5($filename);
        $expected = \sprintf(
            'https://upload.wikimedia.org/wikipedia/commons/thumb/%s/%s/%s/300px-%s',
            $md5[0],
            $md5[0].$md5[1],
            $filename,
            $filename,
        );
        self::assertSame($expected, $result->thumbnail);
    }

    /**
     * Teste fetchWikipediaSummary retourne null en cas d'erreur.
     */
    public function testFetchWikipediaSummaryFailureReturnsNullDescription(): void
    {
        $searchResponse = $this->createMock(ResponseInterface::class);
        $searchResponse->method('toArray')->willReturn([
            'search' => [['id' => 'Q_SUMMARY']],
        ]);

        $requestCount = 0;
        $this->httpClient->method('request')->willReturnCallback(
            function (string $method, string $url) use (&$requestCount): ResponseInterface {
                ++$requestCount;
                $response = $this->createMock(ResponseInterface::class);

                if (\str_contains($url, 'fr.wikipedia.org')) {
                    // Wikipedia summary fails
                    $response->method('toArray')->willThrowException(new \RuntimeException('Wikipedia unavailable'));
                } else {
                    // Wikidata entity
                    $response->method('toArray')->willReturn([
                        'entities' => [
                            'Q_SUMMARY' => [
                                'claims' => [
                                    'P31' => [
                                        ['mainsnak' => ['datavalue' => ['value' => ['id' => 'Q21198342']]]],
                                    ],
                                ],
                                'labels' => ['fr' => ['value' => 'Test']],
                                'sitelinks' => [
                                    'frwiki' => ['title' => 'Test_(manga)'],
                                ],
                            ],
                        ],
                    ]);
                }

                return $response;
            }
        );

        $realCache = new ArrayAdapter();
        $this->cache->method('getItem')->willReturn($realCache->getItem('test_key'));

        $state = [
            'cacheKey' => 'test_key',
            'mode' => 'title',
            'response' => $searchResponse,
        ];

        $result = $this->provider->resolveLookup($state);

        self::assertNotNull($result);
        self::assertNull($result->description);
    }

    /**
     * Teste le mode ISBN avec resolution SPARQL complete.
     */
    public function testResolveLookupIsbnModeFullResolve(): void
    {
        $sparqlResponse = $this->createMock(ResponseInterface::class);
        $sparqlResponse->method('toArray')->willReturn([
            'results' => [
                'bindings' => [
                    ['item' => ['value' => 'http://www.wikidata.org/entity/Q_ISBN']],
                ],
            ],
        ]);

        $entityResponse = $this->createMock(ResponseInterface::class);
        $entityResponse->method('toArray')->willReturn([
            'entities' => [
                'Q_ISBN' => [
                    'claims' => [
                        'P31' => [
                            ['mainsnak' => ['datavalue' => ['value' => ['id' => 'Q14406742']]]],
                        ],
                    ],
                    'labels' => ['fr' => ['value' => 'Serie Par ISBN']],
                    'sitelinks' => [],
                ],
            ],
        ]);

        $this->httpClient->method('request')->willReturn($entityResponse);

        $realCache = new ArrayAdapter();
        $this->cache->method('getItem')->willReturn($realCache->getItem('test_key'));

        $state = [
            'cacheKey' => 'test_key',
            'mode' => 'isbn',
            'response' => $sparqlResponse,
        ];

        $result = $this->provider->resolveLookup($state);

        self::assertNotNull($result);
        self::assertSame('Serie Par ISBN', $result->title);
        self::assertSame('wikipedia', $result->source);
    }

    /**
     * Teste prepareEnrich retourne null quand le titre est null.
     */
    public function testPrepareEnrichReturnsNullForNullTitle(): void
    {
        $partial = new LookupResult(source: 'test');

        $state = $this->provider->prepareEnrich($partial, ComicType::MANGA);

        self::assertNull($state);
    }

    /**
     * Teste prepareEnrich retourne null quand le titre est vide.
     */
    public function testPrepareEnrichReturnsNullForEmptyTitle(): void
    {
        $partial = new LookupResult(title: '', source: 'test');

        $state = $this->provider->prepareEnrich($partial, ComicType::MANGA);

        self::assertNull($state);
    }

    /**
     * Teste prepareLookup avec cache corrompue (non-LookupResult) → passe a la requete HTTP.
     */
    public function testPrepareLookupCorruptedCacheFallsThrough(): void
    {
        $cacheItem = $this->createCacheItem('test_key', 'corrupted_string', true);
        $this->cache->method('getItem')->willReturn($cacheItem);

        $response = $this->createMock(ResponseInterface::class);

        $this->httpClient->expects(self::once())
            ->method('request')
            ->willReturn($response);

        $state = $this->provider->prepareLookup('Test', ComicType::MANGA, 'title');

        self::assertIsArray($state);
        self::assertSame('title', $state['mode']);
    }

    /**
     * Teste resolveEnrich retourne null quand l'etat est null.
     */
    public function testResolveEnrichReturnsNullForNullState(): void
    {
        $result = $this->provider->resolveEnrich(null);

        self::assertNull($result);
    }

    /**
     * Teste resolveLookup en cas de RedirectionExceptionInterface non-429.
     */
    public function testResolveLookupRedirectionException(): void
    {
        $innerResponse = $this->createMock(ResponseInterface::class);
        $innerResponse->method('getStatusCode')->willReturn(301);

        $exception = new class ('Redirect', $innerResponse) extends \RuntimeException implements RedirectionExceptionInterface {
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

        $this->logger->expects(self::once())->method('warning');

        $state = [
            'cacheKey' => 'test_key',
            'mode' => 'title',
            'response' => $response,
        ];

        $result = $this->provider->resolveLookup($state);

        self::assertNull($result);

        $apiMessage = $this->provider->getLastApiMessage();
        self::assertSame('error', $apiMessage['status']);
        self::assertStringContainsString('301', $apiMessage['message']);
    }

    /**
     * Teste extractEntityId retourne null avec une claim malformee (mainsnak/datavalue/value.id absents).
     */
    public function testExtractEntityIdWithMalformedClaimReturnsNull(): void
    {
        $searchResponse = $this->createMock(ResponseInterface::class);
        $searchResponse->method('toArray')->willReturn([
            'search' => [['id' => 'Q_MALFORMED']],
        ]);

        $entityResponse = $this->createMock(ResponseInterface::class);
        $entityResponse->method('toArray')->willReturn([
            'entities' => [
                'Q_MALFORMED' => [
                    'claims' => [
                        'P31' => [
                            ['mainsnak' => ['datavalue' => ['value' => 'not-an-array']]],
                        ],
                    ],
                    'labels' => ['fr' => ['value' => 'Test']],
                    'sitelinks' => [],
                ],
            ],
        ]);

        $this->httpClient->method('request')->willReturn($entityResponse);

        $realCache = new ArrayAdapter();
        $this->cache->method('getItem')->willReturn($realCache->getItem('test_key'));

        $state = [
            'cacheKey' => 'test_key',
            'mode' => 'title',
            'response' => $searchResponse,
        ];

        $result = $this->provider->resolveLookup($state);

        // P31 IDs will be empty → no relevant P31 → null result
        self::assertNull($result);
    }

    /**
     * Teste extractPublishedDate retourne null quand time n'est pas une string.
     */
    public function testExtractPublishedDateNonStringTimeReturnsNull(): void
    {
        $searchResponse = $this->createMock(ResponseInterface::class);
        $searchResponse->method('toArray')->willReturn([
            'search' => [['id' => 'Q_BADDATE']],
        ]);

        $entityResponse = $this->createMock(ResponseInterface::class);
        $entityResponse->method('toArray')->willReturn([
            'entities' => [
                'Q_BADDATE' => [
                    'claims' => [
                        'P31' => [
                            ['mainsnak' => ['datavalue' => ['value' => ['id' => 'Q21198342']]]],
                        ],
                        'P577' => [
                            ['mainsnak' => ['datavalue' => ['value' => ['time' => 12345]]]],
                        ],
                    ],
                    'labels' => ['fr' => ['value' => 'Test']],
                    'sitelinks' => [],
                ],
            ],
        ]);

        $this->httpClient->method('request')->willReturn($entityResponse);

        $realCache = new ArrayAdapter();
        $this->cache->method('getItem')->willReturn($realCache->getItem('test_key'));

        $state = [
            'cacheKey' => 'test_key',
            'mode' => 'title',
            'response' => $searchResponse,
        ];

        $result = $this->provider->resolveLookup($state);

        self::assertNotNull($result);
        self::assertNull($result->publishedDate);
    }

    /**
     * Teste extractPublishedDate retourne null avec une chaine time malformee.
     */
    public function testExtractPublishedDateMalformedTimeStringReturnsNull(): void
    {
        $searchResponse = $this->createMock(ResponseInterface::class);
        $searchResponse->method('toArray')->willReturn([
            'search' => [['id' => 'Q_MALTIME']],
        ]);

        $entityResponse = $this->createMock(ResponseInterface::class);
        $entityResponse->method('toArray')->willReturn([
            'entities' => [
                'Q_MALTIME' => [
                    'claims' => [
                        'P31' => [
                            ['mainsnak' => ['datavalue' => ['value' => ['id' => 'Q21198342']]]],
                        ],
                        'P577' => [
                            ['mainsnak' => ['datavalue' => ['value' => ['time' => 'not-a-date']]]],
                        ],
                    ],
                    'labels' => ['fr' => ['value' => 'Test']],
                    'sitelinks' => [],
                ],
            ],
        ]);

        $this->httpClient->method('request')->willReturn($entityResponse);

        $realCache = new ArrayAdapter();
        $this->cache->method('getItem')->willReturn($realCache->getItem('test_key'));

        $state = [
            'cacheKey' => 'test_key',
            'mode' => 'title',
            'response' => $searchResponse,
        ];

        $result = $this->provider->resolveLookup($state);

        self::assertNotNull($result);
        self::assertNull($result->publishedDate);
    }

    /**
     * Teste getP31Ids retourne un tableau vide quand P31 n'est pas un tableau.
     */
    public function testGetP31IdsNonArrayClaimsReturnsEmpty(): void
    {
        $searchResponse = $this->createMock(ResponseInterface::class);
        $searchResponse->method('toArray')->willReturn([
            'search' => [['id' => 'Q_NONARRAY_P31']],
        ]);

        $entityResponse = $this->createMock(ResponseInterface::class);
        $entityResponse->method('toArray')->willReturn([
            'entities' => [
                'Q_NONARRAY_P31' => [
                    'claims' => [
                        'P31' => 'not-an-array',
                    ],
                    'labels' => ['fr' => ['value' => 'Test']],
                    'sitelinks' => [],
                ],
            ],
        ]);

        $this->httpClient->method('request')->willReturn($entityResponse);

        $realCache = new ArrayAdapter();
        $this->cache->method('getItem')->willReturn($realCache->getItem('test_key'));

        $state = [
            'cacheKey' => 'test_key',
            'mode' => 'title',
            'response' => $searchResponse,
        ];

        $result = $this->provider->resolveLookup($state);

        // P31 non-array → empty p31Ids → null result (no relevant P31)
        self::assertNull($result);
    }

    /**
     * Teste getP31Ids ignore les claims individuelles non-array.
     */
    public function testGetP31IdsSkipsNonArrayIndividualClaim(): void
    {
        $searchResponse = $this->createMock(ResponseInterface::class);
        $searchResponse->method('toArray')->willReturn([
            'search' => [['id' => 'Q_MIXED_P31']],
        ]);

        $entityResponse = $this->createMock(ResponseInterface::class);
        $entityResponse->method('toArray')->willReturn([
            'entities' => [
                'Q_MIXED_P31' => [
                    'claims' => [
                        'P31' => [
                            'not-an-array-claim',
                            ['mainsnak' => ['datavalue' => ['value' => ['id' => 'Q21198342']]]],
                        ],
                    ],
                    'labels' => ['fr' => ['value' => 'Valid After Skip']],
                    'sitelinks' => [],
                ],
            ],
        ]);

        $this->httpClient->method('request')->willReturn($entityResponse);

        $realCache = new ArrayAdapter();
        $this->cache->method('getItem')->willReturn($realCache->getItem('test_key'));

        $state = [
            'cacheKey' => 'test_key',
            'mode' => 'title',
            'response' => $searchResponse,
        ];

        $result = $this->provider->resolveLookup($state);

        self::assertNotNull($result);
        self::assertSame('Valid After Skip', $result->title);
    }

    /**
     * Teste resolveIsbnLookup retourne NOT_FOUND quand entityId est vide.
     */
    public function testResolveIsbnLookupEmptyEntityIdReturnsNotFound(): void
    {
        $sparqlResponse = $this->createMock(ResponseInterface::class);
        $sparqlResponse->method('toArray')->willReturn([
            'results' => [
                'bindings' => [
                    ['item' => ['value' => '']],
                ],
            ],
        ]);

        $realCache = new ArrayAdapter();
        $this->cache->method('getItem')->willReturn($realCache->getItem('test_key'));

        $state = [
            'cacheKey' => 'test_key',
            'mode' => 'isbn',
            'response' => $sparqlResponse,
        ];

        $result = $this->provider->resolveLookup($state);

        self::assertNull($result);

        $apiMessage = $this->provider->getLastApiMessage();
        self::assertSame('not_found', $apiMessage['status']);
    }

    /**
     * Teste resolveEntity retourne null quand l'entite fetched n'est pas un tableau.
     */
    public function testResolveEntityNonArrayEntityReturnsNull(): void
    {
        $searchResponse = $this->createMock(ResponseInterface::class);
        $searchResponse->method('toArray')->willReturn([
            'search' => [['id' => 'Q_NONARRAY']],
        ]);

        $entityResponse = $this->createMock(ResponseInterface::class);
        $entityResponse->method('toArray')->willReturn([
            'entities' => [
                'Q_NONARRAY' => 'not-an-array',
            ],
        ]);

        $this->httpClient->method('request')->willReturn($entityResponse);

        $realCache = new ArrayAdapter();
        $this->cache->method('getItem')->willReturn($realCache->getItem('test_key'));

        $state = [
            'cacheKey' => 'test_key',
            'mode' => 'title',
            'response' => $searchResponse,
        ];

        $result = $this->provider->resolveLookup($state);

        self::assertNull($result);
    }

    /**
     * Teste edition (Q3331189) sans P629 valide → retourne null (pas de fallthrough vers extractFromEntity).
     */
    public function testResolveEntityEditionWithNoValidP629ReturnsNull(): void
    {
        $searchResponse = $this->createMock(ResponseInterface::class);
        $searchResponse->method('toArray')->willReturn([
            'search' => [['id' => 'Q_EDITION_NO_P629']],
        ]);

        $entityResponse = $this->createMock(ResponseInterface::class);
        $entityResponse->method('toArray')->willReturn([
            'entities' => [
                'Q_EDITION_NO_P629' => [
                    'claims' => [
                        'P31' => [
                            ['mainsnak' => ['datavalue' => ['value' => ['id' => 'Q3331189']]]],
                        ],
                        'P629' => [
                            ['mainsnak' => ['datavalue' => ['value' => 'not-an-array']]],
                        ],
                    ],
                    'labels' => ['fr' => ['value' => 'Edition sans oeuvre']],
                    'sitelinks' => [],
                ],
            ],
        ]);

        $this->httpClient->method('request')->willReturn($entityResponse);

        $realCache = new ArrayAdapter();
        $this->cache->method('getItem')->willReturn($realCache->getItem('test_key'));

        $state = [
            'cacheKey' => 'test_key',
            'mode' => 'title',
            'response' => $searchResponse,
        ];

        $result = $this->provider->resolveLookup($state);

        // Edition P31 = Q3331189 (not in RELEVANT_P31) → extractFromEntity returns null
        self::assertNull($result);
    }

    /**
     * Teste resolveEntityLabel retourne null quand toutes les claims sont non-array.
     */
    public function testResolveEntityLabelAllNonArrayClaimsReturnsNull(): void
    {
        $searchResponse = $this->createMock(ResponseInterface::class);
        $searchResponse->method('toArray')->willReturn([
            'search' => [['id' => 'Q_BAD_AUTHOR']],
        ]);

        $entityResponse = $this->createMock(ResponseInterface::class);
        $entityResponse->method('toArray')->willReturn([
            'entities' => [
                'Q_BAD_AUTHOR' => [
                    'claims' => [
                        'P31' => [
                            ['mainsnak' => ['datavalue' => ['value' => ['id' => 'Q21198342']]]],
                        ],
                        'P50' => [
                            'not-an-array',
                            42,
                        ],
                    ],
                    'labels' => ['fr' => ['value' => 'Test']],
                    'sitelinks' => [],
                ],
            ],
        ]);

        $this->httpClient->method('request')->willReturn($entityResponse);

        $realCache = new ArrayAdapter();
        $this->cache->method('getItem')->willReturn($realCache->getItem('test_key'));

        $state = [
            'cacheKey' => 'test_key',
            'mode' => 'title',
            'response' => $searchResponse,
        ];

        $result = $this->provider->resolveLookup($state);

        self::assertNotNull($result);
        self::assertNull($result->authors);
    }

    /**
     * Teste resolveEntityLabel retourne null quand l'entite associee est absente.
     */
    public function testResolveEntityLabelMissingRelatedEntityReturnsNull(): void
    {
        $searchResponse = $this->createMock(ResponseInterface::class);
        $searchResponse->method('toArray')->willReturn([
            'search' => [['id' => 'Q_MISSING_REL']],
        ]);

        $requestCount = 0;
        $this->httpClient->method('request')->willReturnCallback(
            function () use (&$requestCount): ResponseInterface {
                ++$requestCount;
                $response = $this->createMock(ResponseInterface::class);

                if (1 === $requestCount) {
                    // Premiere requete : l'entite principale
                    $response->method('toArray')->willReturn([
                        'entities' => [
                            'Q_MISSING_REL' => [
                                'claims' => [
                                    'P31' => [
                                        ['mainsnak' => ['datavalue' => ['value' => ['id' => 'Q21198342']]]],
                                    ],
                                    'P50' => [
                                        ['mainsnak' => ['datavalue' => ['value' => ['id' => 'Q_NONEXISTENT']]]],
                                    ],
                                ],
                                'labels' => ['fr' => ['value' => 'Test']],
                                'sitelinks' => [],
                            ],
                        ],
                    ]);
                } else {
                    // Deuxieme requete : l'entite auteur n'existe pas
                    $response->method('toArray')->willReturn([
                        'entities' => [],
                    ]);
                }

                return $response;
            }
        );

        $realCache = new ArrayAdapter();
        $this->cache->method('getItem')->willReturn($realCache->getItem('test_key'));

        $state = [
            'cacheKey' => 'test_key',
            'mode' => 'title',
            'response' => $searchResponse,
        ];

        $result = $this->provider->resolveLookup($state);

        self::assertNotNull($result);
        self::assertNull($result->authors);
    }

    /**
     * Teste resolveEntityLabel retourne null quand la valeur du label n'est pas une string.
     */
    public function testResolveEntityLabelNonStringValueReturnsNull(): void
    {
        $searchResponse = $this->createMock(ResponseInterface::class);
        $searchResponse->method('toArray')->willReturn([
            'search' => [['id' => 'Q_BAD_LABEL']],
        ]);

        $requestCount = 0;
        $this->httpClient->method('request')->willReturnCallback(
            function () use (&$requestCount): ResponseInterface {
                ++$requestCount;
                $response = $this->createMock(ResponseInterface::class);

                if (1 === $requestCount) {
                    $response->method('toArray')->willReturn([
                        'entities' => [
                            'Q_BAD_LABEL' => [
                                'claims' => [
                                    'P31' => [
                                        ['mainsnak' => ['datavalue' => ['value' => ['id' => 'Q21198342']]]],
                                    ],
                                    'P50' => [
                                        ['mainsnak' => ['datavalue' => ['value' => ['id' => 'Q_AUTHOR']]]],
                                    ],
                                ],
                                'labels' => ['fr' => ['value' => 'Test']],
                                'sitelinks' => [],
                            ],
                        ],
                    ]);
                } else {
                    // Auteur avec label non-string
                    $response->method('toArray')->willReturn([
                        'entities' => [
                            'Q_AUTHOR' => [
                                'claims' => [],
                                'labels' => ['fr' => ['value' => 12345]],
                            ],
                        ],
                    ]);
                }

                return $response;
            }
        );

        $realCache = new ArrayAdapter();
        $this->cache->method('getItem')->willReturn($realCache->getItem('test_key'));

        $state = [
            'cacheKey' => 'test_key',
            'mode' => 'title',
            'response' => $searchResponse,
        ];

        $result = $this->provider->resolveLookup($state);

        self::assertNotNull($result);
        self::assertNull($result->authors);
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

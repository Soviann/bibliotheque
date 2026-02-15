<?php

declare(strict_types=1);

namespace App\Tests\Service\Lookup;

use App\Enum\ComicType;
use App\Service\Lookup\LookupResult;
use App\Service\Lookup\WikipediaLookup;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

class WikipediaLookupTest extends TestCase
{
    public function testGetName(): void
    {
        $provider = $this->createProvider(new MockHttpClient());

        self::assertSame('wikipedia', $provider->getName());
    }

    public function testGetFieldPriorityReturnsLowForDescription(): void
    {
        $provider = $this->createProvider(new MockHttpClient());

        self::assertSame(10, $provider->getFieldPriority('description'));
        self::assertSame(10, $provider->getFieldPriority('description', ComicType::MANGA));
    }

    public function testGetFieldPriorityReturnsHighForOtherFields(): void
    {
        $provider = $this->createProvider(new MockHttpClient());

        self::assertSame(120, $provider->getFieldPriority('title'));
        self::assertSame(120, $provider->getFieldPriority('authors'));
        self::assertSame(120, $provider->getFieldPriority('publisher'));
        self::assertSame(120, $provider->getFieldPriority('thumbnail'));
        self::assertSame(120, $provider->getFieldPriority('isOneShot'));
        self::assertSame(120, $provider->getFieldPriority('publishedDate'));
    }

    public function testSupportsAllModesAndTypes(): void
    {
        $provider = $this->createProvider(new MockHttpClient());

        self::assertTrue($provider->supports('isbn', null));
        self::assertTrue($provider->supports('title', null));
        self::assertTrue($provider->supports('isbn', ComicType::MANGA));
        self::assertTrue($provider->supports('title', ComicType::BD));
        self::assertTrue($provider->supports('title', ComicType::COMICS));
        self::assertTrue($provider->supports('title', ComicType::LIVRE));
        self::assertFalse($provider->supports('unknown', null));
    }

    public function testLookupByTitleReturnsCompleteResult(): void
    {
        $mockClient = new MockHttpClient($this->createTitleLookupResponder());

        $provider = $this->createProvider($mockClient);
        $result = $this->doLookup($provider, 'One Piece', ComicType::MANGA, 'title');

        self::assertInstanceOf(LookupResult::class, $result);
        self::assertSame('One Piece', $result->title);
        self::assertSame('Eiichiro Oda', $result->authors);
        self::assertSame('Shūeisha', $result->publisher);
        self::assertSame('1997-07-22', $result->publishedDate);
        self::assertNotNull($result->description);
        self::assertStringContainsString('One Piece est un manga', $result->description);
        self::assertNotNull($result->thumbnail);
        self::assertStringContainsString('upload.wikimedia.org', $result->thumbnail);
        self::assertFalse($result->isOneShot);
        self::assertSame('wikipedia', $result->source);
    }

    public function testLookupByIsbnViaSparql(): void
    {
        $mockClient = new MockHttpClient($this->createIsbnLookupResponder());

        $provider = $this->createProvider($mockClient);
        $result = $this->doLookup($provider, '9782723428262', null, 'isbn');

        self::assertInstanceOf(LookupResult::class, $result);
        self::assertSame('Astérix', $result->title);
        self::assertSame('René Goscinny', $result->authors);
    }

    public function testLookupByIsbnEditionRemountsToWork(): void
    {
        $mockClient = new MockHttpClient(function (string $method, string $url): MockResponse {
            // 1. SPARQL → returns edition entity
            if (\str_contains($url, 'query.wikidata.org/sparql')) {
                return $this->jsonResponse([
                    'results' => ['bindings' => [['item' => ['value' => 'http://www.wikidata.org/entity/Q999']]]],
                ]);
            }

            // 2. wbgetentities for the edition
            if (\str_contains($url, 'wbgetentities') && \str_contains($url, 'Q999')) {
                return $this->jsonResponse([
                    'entities' => [
                        'Q999' => [
                            'claims' => [
                                'P31' => [$this->claimEntityValue('Q3331189')], // edition
                                'P629' => [$this->claimEntityValue('Q100')], // edition of → work Q100
                            ],
                            'labels' => ['fr' => ['value' => 'Astérix tome 1']],
                            'sitelinks' => [],
                        ],
                    ],
                ]);
            }

            // 3. wbgetentities for the work (Q100)
            if (\str_contains($url, 'wbgetentities') && \str_contains($url, 'Q100')) {
                return $this->jsonResponse([
                    'entities' => [
                        'Q100' => [
                            'claims' => [
                                'P31' => [$this->claimEntityValue('Q838795')], // comic book series
                                'P50' => [$this->claimEntityValue('Q42')],
                                'P123' => [$this->claimEntityValue('Q43')],
                                'P577' => [$this->claimTimeValue('+1959-10-29T00:00:00Z')],
                            ],
                            'labels' => ['fr' => ['value' => 'Astérix']],
                            'sitelinks' => ['frwiki' => ['title' => 'Astérix']],
                        ],
                        'Q42' => ['labels' => ['fr' => ['value' => 'René Goscinny']]],
                        'Q43' => ['labels' => ['fr' => ['value' => 'Dargaud']]],
                    ],
                ]);
            }

            // 4. Wikipedia summary
            if (\str_contains($url, 'fr.wikipedia.org')) {
                return $this->jsonResponse(['extract' => 'Astérix est une série de BD.']);
            }

            return new MockResponse('{}');
        });

        $provider = $this->createProvider($mockClient);
        $result = $this->doLookup($provider, '9782012101340', null, 'isbn');

        self::assertInstanceOf(LookupResult::class, $result);
        self::assertSame('Astérix', $result->title);
        self::assertSame('René Goscinny', $result->authors);
        self::assertSame('Dargaud', $result->publisher);
    }

    public function testLookupReturnsNullWhenNoSearchResults(): void
    {
        $mockClient = new MockHttpClient(static function (string $method, string $url): MockResponse {
            if (\str_contains($url, 'wbsearchentities')) {
                return new MockResponse((string) \json_encode(['search' => []]));
            }

            return new MockResponse('{}');
        });

        $provider = $this->createProvider($mockClient);
        $result = $this->doLookup($provider, 'ZZZZZZ', null, 'title');

        self::assertNull($result);
        self::assertNotNull($provider->getLastApiMessage());
        self::assertSame('not_found', $provider->getLastApiMessage()['status']);
    }

    public function testLookupByIsbnReturnsNullWhenSparqlEmpty(): void
    {
        $mockClient = new MockHttpClient(static function (string $method, string $url): MockResponse {
            if (\str_contains($url, 'query.wikidata.org/sparql')) {
                return new MockResponse((string) \json_encode([
                    'results' => ['bindings' => []],
                ]));
            }

            return new MockResponse('{}');
        });

        $provider = $this->createProvider($mockClient);
        $result = $this->doLookup($provider, '0000000000000', null, 'isbn');

        self::assertNull($result);
        self::assertNotNull($provider->getLastApiMessage());
        self::assertSame('not_found', $provider->getLastApiMessage()['status']);
    }

    public function testLookupHandlesHttpTransportError(): void
    {
        $mockClient = new MockHttpClient(static fn (): MockResponse => new MockResponse('', ['error' => 'Connection failed']));

        $provider = $this->createProvider($mockClient);
        $result = $this->doLookup($provider, 'One Piece', null, 'title');

        self::assertNull($result);
        self::assertNotNull($provider->getLastApiMessage());
        self::assertSame('error', $provider->getLastApiMessage()['status']);
    }

    public function testLookupHandlesRateLimiting(): void
    {
        $mockClient = new MockHttpClient(static fn (): MockResponse => new MockResponse('Too Many Requests', ['http_code' => 429]));

        $provider = $this->createProvider($mockClient);
        $result = $this->doLookup($provider, 'One Piece', null, 'title');

        self::assertNull($result);
        self::assertNotNull($provider->getLastApiMessage());
        self::assertSame('rate_limited', $provider->getLastApiMessage()['status']);
    }

    public function testLookupHandlesServerError(): void
    {
        $mockClient = new MockHttpClient(static fn (): MockResponse => new MockResponse('Internal Server Error', ['http_code' => 500]));

        $provider = $this->createProvider($mockClient);
        $result = $this->doLookup($provider, 'One Piece', null, 'title');

        self::assertNull($result);
        self::assertNotNull($provider->getLastApiMessage());
        self::assertSame('error', $provider->getLastApiMessage()['status']);
    }

    public function testEnrichDoesLookupByTitle(): void
    {
        $mockClient = new MockHttpClient($this->createTitleLookupResponder());

        $partial = new LookupResult(
            source: 'google_books',
            title: 'One Piece',
        );

        $provider = $this->createProvider($mockClient);
        $result = $this->doEnrich($provider, $partial, ComicType::MANGA);

        self::assertInstanceOf(LookupResult::class, $result);
        self::assertSame('One Piece', $result->title);
        self::assertSame('Eiichiro Oda', $result->authors);
    }

    public function testEnrichReturnsNullWithoutTitle(): void
    {
        $provider = $this->createProvider(new MockHttpClient());

        $partial = new LookupResult(source: 'google_books');
        $result = $this->doEnrich($provider, $partial, null);

        self::assertNull($result);
    }

    public function testCacheHitDoesNotMakeHttpRequests(): void
    {
        $callCount = 0;
        $mockClient = new MockHttpClient(function (string $method, string $url) use (&$callCount): MockResponse {
            ++$callCount;

            return ($this->createTitleLookupResponder())($method, $url);
        });

        $cache = new ArrayAdapter();
        $provider = $this->createProvider($mockClient, $cache);

        // Premier appel → requêtes HTTP
        $result1 = $this->doLookup($provider, 'One Piece', ComicType::MANGA, 'title');
        $firstCallCount = $callCount;
        self::assertNotNull($result1);
        self::assertGreaterThan(0, $firstCallCount);

        // Deuxième appel → depuis le cache, pas de nouvelles requêtes
        $result2 = $this->doLookup($provider, 'One Piece', ComicType::MANGA, 'title');
        self::assertNotNull($result2);
        self::assertSame($firstCallCount, $callCount);
        self::assertSame($result1->title, $result2->title);
    }

    public function testFiltersIrrelevantP31Entities(): void
    {
        $mockClient = new MockHttpClient(function (string $method, string $url): MockResponse {
            if (\str_contains($url, 'wbsearchentities')) {
                return $this->jsonResponse([
                    'search' => [
                        ['id' => 'Q111', 'label' => 'One Piece (film)'],
                        ['id' => 'Q222', 'label' => 'One Piece (manga)'],
                    ],
                ]);
            }

            if (\str_contains($url, 'wbgetentities') && \str_contains($url, 'Q111')) {
                return $this->jsonResponse([
                    'entities' => [
                        'Q111' => [
                            'claims' => [
                                'P31' => [
                                    ['mainsnak' => ['datavalue' => ['value' => ['id' => 'Q11424']]]],  // video game
                                ],
                            ],
                            'labels' => ['fr' => ['value' => 'One Piece (film)']],
                            'sitelinks' => [],
                        ],
                    ],
                ]);
            }

            if (\str_contains($url, 'wbgetentities') && \str_contains($url, 'Q222')) {
                return $this->jsonResponse([
                    'entities' => [
                        'Q222' => [
                            'claims' => [
                                'P31' => [
                                    ['mainsnak' => ['datavalue' => ['value' => ['id' => 'Q21198342']]]],  // manga series
                                ],
                                'P50' => [
                                    ['mainsnak' => ['datavalue' => ['value' => ['id' => 'Q42']]]],
                                ],
                            ],
                            'labels' => ['fr' => ['value' => 'One Piece']],
                            'sitelinks' => ['frwiki' => ['title' => 'One Piece']],
                        ],
                        'Q42' => ['labels' => ['fr' => ['value' => 'Eiichiro Oda']]],
                    ],
                ]);
            }

            if (\str_contains($url, 'fr.wikipedia.org')) {
                return $this->jsonResponse(['extract' => 'Description manga']);
            }

            return new MockResponse('{}');
        });

        $provider = $this->createProvider($mockClient);
        $result = $this->doLookup($provider, 'One Piece', ComicType::MANGA, 'title');

        self::assertInstanceOf(LookupResult::class, $result);
        // Doit avoir pris Q222 (manga series), pas Q111 (film)
        self::assertSame('One Piece', $result->title);
    }

    public function testDetectsOneShotFromP31(): void
    {
        $mockClient = new MockHttpClient(function (string $method, string $url): MockResponse {
            if (\str_contains($url, 'wbsearchentities')) {
                return $this->jsonResponse([
                    'search' => [['id' => 'Q333']],
                ]);
            }

            if (\str_contains($url, 'wbgetentities')) {
                return $this->jsonResponse([
                    'entities' => [
                        'Q333' => [
                            'claims' => [
                                'P31' => [
                                    ['mainsnak' => ['datavalue' => ['value' => ['id' => 'Q725377']]]],  // graphic novel (one-shot)
                                ],
                                'P50' => [['mainsnak' => ['datavalue' => ['value' => ['id' => 'Q42']]]]],
                            ],
                            'labels' => ['fr' => ['value' => 'Maus']],
                            'sitelinks' => ['frwiki' => ['title' => 'Maus']],
                        ],
                        'Q42' => ['labels' => ['fr' => ['value' => 'Art Spiegelman']]],
                    ],
                ]);
            }

            if (\str_contains($url, 'fr.wikipedia.org')) {
                return $this->jsonResponse(['extract' => 'Maus est un roman graphique.']);
            }

            return new MockResponse('{}');
        });

        $provider = $this->createProvider($mockClient);
        $result = $this->doLookup($provider, 'Maus', ComicType::BD, 'title');

        self::assertInstanceOf(LookupResult::class, $result);
        self::assertTrue($result->isOneShot);
    }

    public function testExtractsThumbnailFromP18(): void
    {
        $mockClient = new MockHttpClient(function (string $method, string $url): MockResponse {
            if (\str_contains($url, 'wbsearchentities')) {
                return $this->jsonResponse([
                    'search' => [['id' => 'Q444']],
                ]);
            }

            if (\str_contains($url, 'wbgetentities')) {
                return $this->jsonResponse([
                    'entities' => [
                        'Q444' => [
                            'claims' => [
                                'P18' => [['mainsnak' => ['datavalue' => ['value' => 'Cover Image.jpg']]]],
                                'P31' => [['mainsnak' => ['datavalue' => ['value' => ['id' => 'Q21198342']]]]],
                            ],
                            'labels' => ['fr' => ['value' => 'Test']],
                            'sitelinks' => [],
                        ],
                    ],
                ]);
            }

            return new MockResponse('{}');
        });

        $provider = $this->createProvider($mockClient);
        $result = $this->doLookup($provider, 'Test', null, 'title');

        self::assertNotNull($result);
        self::assertNotNull($result->thumbnail);
        // Vérifie la structure de l'URL (le hash MD5 détermine le chemin)
        self::assertStringStartsWith('https://upload.wikimedia.org/wikipedia/commons/thumb/', $result->thumbnail);
        self::assertStringEndsWith('/300px-Cover_Image.jpg', $result->thumbnail);
    }

    /**
     * @return array{mainsnak: array{datavalue: array{value: array{id: string}}}}
     */
    private function claimEntityValue(string $entityId): array
    {
        return ['mainsnak' => ['datavalue' => ['value' => ['id' => $entityId]]]];
    }

    /**
     * @return array{mainsnak: array{datavalue: array{value: array{time: string}}}}
     */
    private function claimTimeValue(string $time): array
    {
        return ['mainsnak' => ['datavalue' => ['value' => ['time' => $time]]]];
    }

    private function doEnrich(WikipediaLookup $provider, LookupResult $partial, ?ComicType $type): ?LookupResult
    {
        $state = $provider->prepareEnrich($partial, $type);

        return $provider->resolveEnrich($state);
    }

    private function doLookup(WikipediaLookup $provider, string $query, ?ComicType $type, string $mode = 'title'): ?LookupResult
    {
        $state = $provider->prepareLookup($query, $type, $mode);

        return $provider->resolveLookup($state);
    }

    private function createIsbnLookupResponder(): \Closure
    {
        return function (string $method, string $url): MockResponse {
            // 1. SPARQL query
            if (\str_contains($url, 'query.wikidata.org/sparql')) {
                return $this->jsonResponse([
                    'results' => ['bindings' => [['item' => ['value' => 'http://www.wikidata.org/entity/Q200']]]],
                ]);
            }

            // 2. wbgetentities for the work
            if (\str_contains($url, 'wbgetentities')) {
                return $this->jsonResponse([
                    'entities' => [
                        'Q200' => [
                            'claims' => [
                                'P31' => [$this->claimEntityValue('Q838795')], // comic book series
                                'P50' => [$this->claimEntityValue('Q42')],
                                'P123' => [$this->claimEntityValue('Q43')],
                                'P577' => [$this->claimTimeValue('+1959-10-29T00:00:00Z')],
                            ],
                            'labels' => ['fr' => ['value' => 'Astérix']],
                            'sitelinks' => ['frwiki' => ['title' => 'Astérix']],
                        ],
                        'Q42' => ['labels' => ['fr' => ['value' => 'René Goscinny']]],
                        'Q43' => ['labels' => ['fr' => ['value' => 'Hachette']]],
                    ],
                ]);
            }

            // 3. Wikipedia summary
            if (\str_contains($url, 'fr.wikipedia.org')) {
                return $this->jsonResponse(['extract' => 'Astérix est une série de BD.']);
            }

            return new MockResponse('{}');
        };
    }

    private function createProvider(MockHttpClient $httpClient, ?ArrayAdapter $cache = null): WikipediaLookup
    {
        return new WikipediaLookup(
            $cache ?? new ArrayAdapter(),
            $httpClient,
            new NullLogger(),
        );
    }

    private function createTitleLookupResponder(): \Closure
    {
        return function (string $method, string $url): MockResponse {
            // 1. wbsearchentities
            if (\str_contains($url, 'wbsearchentities')) {
                return $this->jsonResponse([
                    'search' => [
                        ['id' => 'Q634700', 'label' => 'One Piece'],
                    ],
                ]);
            }

            // 2. wbgetentities — entity + author/publisher labels
            if (\str_contains($url, 'wbgetentities')) {
                return $this->jsonResponse([
                    'entities' => [
                        'Q634700' => [
                            'claims' => [
                                'P18' => [['mainsnak' => ['datavalue' => ['value' => 'One_Piece_Vol_1.jpg']]]],
                                'P31' => [$this->claimEntityValue('Q21198342')], // manga series
                                'P50' => [$this->claimEntityValue('Q217532')],   // author
                                'P123' => [$this->claimEntityValue('Q190363')],  // publisher
                                'P577' => [$this->claimTimeValue('+1997-07-22T00:00:00Z')],
                            ],
                            'labels' => ['fr' => ['value' => 'One Piece']],
                            'sitelinks' => ['frwiki' => ['title' => 'One Piece']],
                        ],
                        'Q217532' => ['labels' => ['fr' => ['value' => 'Eiichiro Oda']]],
                        'Q190363' => ['labels' => ['fr' => ['value' => 'Shūeisha']]],
                    ],
                ]);
            }

            // 3. Wikipedia FR summary
            if (\str_contains($url, 'fr.wikipedia.org')) {
                return $this->jsonResponse([
                    'extract' => 'One Piece est un manga écrit et dessiné par Eiichiro Oda.',
                ]);
            }

            return new MockResponse('{}');
        };
    }

    /**
     * @param array<string, mixed> $data
     */
    private function jsonResponse(array $data): MockResponse
    {
        return new MockResponse((string) \json_encode($data));
    }
}

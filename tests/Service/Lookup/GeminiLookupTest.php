<?php

declare(strict_types=1);

namespace App\Tests\Service\Lookup;

use App\Enum\ComicType;
use App\Service\Lookup\GeminiLookup;
use App\Service\Lookup\LookupResult;
use Gemini\Responses\GenerativeModel\GenerateContentResponse;
use Gemini\Testing\ClientFake;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Cache\Psr16Cache;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\RateLimiter\Storage\InMemoryStorage;

class GeminiLookupTest extends TestCase
{
    public function testGetName(): void
    {
        $lookup = $this->createLookup();

        self::assertSame('gemini', $lookup->getName());
    }

    public function testSupportsIsbnAndTitle(): void
    {
        $lookup = $this->createLookup();

        self::assertTrue($lookup->supports('isbn', null));
        self::assertTrue($lookup->supports('title', null));
        self::assertTrue($lookup->supports('isbn', ComicType::MANGA));
        self::assertTrue($lookup->supports('title', ComicType::BD));
        self::assertFalse($lookup->supports('unknown', null));
    }

    public function testLookupByIsbnReturnsData(): void
    {
        $geminiClient = new ClientFake([
            GenerateContentResponse::fake([
                'candidates' => [
                    [
                        'content' => [
                            'parts' => [
                                [
                                    'text' => \json_encode([
                                        'authors' => 'Hajime Isayama',
                                        'description' => 'Un manga épique sur les titans',
                                        'isOneShot' => false,
                                        'publishedDate' => '2012-06-13',
                                        'publisher' => 'Pika Édition',
                                        'thumbnail' => 'https://example.com/cover.jpg',
                                        'title' => "L'Attaque des Titans",
                                    ]),
                                ],
                            ],
                        ],
                    ],
                ],
            ]),
        ]);

        $lookup = $this->createLookup(geminiClient: $geminiClient);
        $result = $lookup->lookup('9782811607418', ComicType::MANGA, 'isbn');

        self::assertInstanceOf(LookupResult::class, $result);
        self::assertSame('Hajime Isayama', $result->authors);
        self::assertSame('Un manga épique sur les titans', $result->description);
        self::assertFalse($result->isOneShot);
        self::assertSame('2012-06-13', $result->publishedDate);
        self::assertSame('Pika Édition', $result->publisher);
        self::assertSame('https://example.com/cover.jpg', $result->thumbnail);
        self::assertSame("L'Attaque des Titans", $result->title);
        self::assertSame('gemini', $result->source);
    }

    public function testLookupByTitleReturnsData(): void
    {
        $geminiClient = new ClientFake([
            GenerateContentResponse::fake([
                'candidates' => [
                    [
                        'content' => [
                            'parts' => [
                                [
                                    'text' => \json_encode([
                                        'authors' => 'Eiichiro Oda',
                                        'description' => 'Aventure pirate',
                                        'title' => 'One Piece',
                                    ]),
                                ],
                            ],
                        ],
                    ],
                ],
            ]),
        ]);

        $lookup = $this->createLookup(geminiClient: $geminiClient);
        $result = $lookup->lookup('One Piece', ComicType::MANGA, 'title');

        self::assertInstanceOf(LookupResult::class, $result);
        self::assertSame('Eiichiro Oda', $result->authors);
        self::assertSame('One Piece', $result->title);
    }

    public function testEnrichCompletesPartialData(): void
    {
        $geminiClient = new ClientFake([
            GenerateContentResponse::fake([
                'candidates' => [
                    [
                        'content' => [
                            'parts' => [
                                [
                                    'text' => \json_encode([
                                        'authors' => 'Hajime Isayama',
                                        'description' => 'Description enrichie par Gemini',
                                        'publisher' => 'Pika Édition',
                                    ]),
                                ],
                            ],
                        ],
                    ],
                ],
            ]),
        ]);

        $partial = new LookupResult(
            source: 'google_books',
            thumbnail: 'https://example.com/cover.jpg',
            title: "L'Attaque des Titans",
        );

        $lookup = $this->createLookup(geminiClient: $geminiClient);
        $result = $lookup->enrich($partial, ComicType::MANGA);

        self::assertInstanceOf(LookupResult::class, $result);
        self::assertSame('Hajime Isayama', $result->authors);
        self::assertSame('Description enrichie par Gemini', $result->description);
        self::assertSame('Pika Édition', $result->publisher);
        self::assertSame('gemini', $result->source);
    }

    public function testEnrichReturnsNullWhenNoTitle(): void
    {
        $lookup = $this->createLookup();
        $partial = new LookupResult(source: 'google_books');

        $result = $lookup->enrich($partial, null);

        self::assertNull($result);
    }

    public function testLookupReturnsNullOnApiError(): void
    {
        $geminiClient = new ClientFake([
            new \Exception('API connection failed'),
        ]);

        $lookup = $this->createLookup(geminiClient: $geminiClient);
        $result = $lookup->lookup('9781234567890', null, 'isbn');

        self::assertNull($result);
        self::assertNotNull($lookup->getLastApiMessage());
        self::assertSame('error', $lookup->getLastApiMessage()['status']);
    }

    public function testLookupReturnsNullOnRateLimit(): void
    {
        $limiterFactory = new RateLimiterFactory(
            ['id' => 'gemini_api', 'interval' => '1 minute', 'limit' => 1, 'policy' => 'sliding_window'],
            new InMemoryStorage(),
        );

        $geminiClient = new ClientFake([
            GenerateContentResponse::fake([
                'candidates' => [
                    [
                        'content' => [
                            'parts' => [['text' => \json_encode(['title' => 'Test'])]],
                        ],
                    ],
                ],
            ]),
            GenerateContentResponse::fake([
                'candidates' => [
                    [
                        'content' => [
                            'parts' => [['text' => \json_encode(['title' => 'Test 2'])]],
                        ],
                    ],
                ],
            ]),
        ]);

        $lookup = $this->createLookup(geminiClient: $geminiClient, limiterFactory: $limiterFactory);

        // Premier appel : consomme le quota (limit=1)
        $lookup->lookup('isbn1', null, 'isbn');

        // Deuxième appel : rate limited
        $result = $lookup->lookup('isbn2', null, 'isbn');

        self::assertNull($result);
        self::assertNotNull($lookup->getLastApiMessage());
        self::assertSame('rate_limited', $lookup->getLastApiMessage()['status']);
    }

    public function testLookupUsesCacheForSameQuery(): void
    {
        $geminiClient = new ClientFake([
            GenerateContentResponse::fake([
                'candidates' => [
                    [
                        'content' => [
                            'parts' => [
                                [
                                    'text' => \json_encode([
                                        'authors' => 'Author',
                                        'title' => 'Cached Book',
                                    ]),
                                ],
                            ],
                        ],
                    ],
                ],
            ]),
        ]);

        $lookup = $this->createLookup(geminiClient: $geminiClient);

        // Premier appel
        $result1 = $lookup->lookup('9781234567890', null, 'isbn');
        // Deuxième appel (même query) — doit venir du cache
        $result2 = $lookup->lookup('9781234567890', null, 'isbn');

        self::assertInstanceOf(LookupResult::class, $result1);
        self::assertInstanceOf(LookupResult::class, $result2);
        self::assertSame('Cached Book', $result2->title);

        // Un seul appel API
        $geminiClient->generativeModel(model: 'gemini-2.0-flash')->assertSent(1);
    }

    public function testLookupReturnsNullWhenGeminiReturnsEmptyData(): void
    {
        $geminiClient = new ClientFake([
            GenerateContentResponse::fake([
                'candidates' => [
                    [
                        'content' => [
                            'parts' => [
                                ['text' => \json_encode([])],
                            ],
                        ],
                    ],
                ],
            ]),
        ]);

        $lookup = $this->createLookup(geminiClient: $geminiClient);
        $result = $lookup->lookup('9781234567890', null, 'isbn');

        self::assertNull($result);
        self::assertNotNull($lookup->getLastApiMessage());
        self::assertSame('not_found', $lookup->getLastApiMessage()['status']);
    }

    private function createLookup(
        ?ClientFake $geminiClient = null,
        ?RateLimiterFactory $limiterFactory = null,
    ): GeminiLookup {
        $geminiClient ??= new ClientFake([]);
        $cache = new ArrayAdapter();
        $limiterFactory ??= new RateLimiterFactory(
            ['id' => 'gemini_api', 'interval' => '1 minute', 'limit' => 100, 'policy' => 'sliding_window'],
            new InMemoryStorage(),
        );

        return new GeminiLookup(
            cache: $cache,
            geminiClient: $geminiClient,
            limiterFactory: $limiterFactory,
            logger: new NullLogger(),
        );
    }
}

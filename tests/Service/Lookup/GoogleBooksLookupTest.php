<?php

declare(strict_types=1);

namespace App\Tests\Service\Lookup;

use App\Enum\ComicType;
use App\Service\Lookup\GoogleBooksLookup;
use App\Service\Lookup\LookupResult;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

class GoogleBooksLookupTest extends TestCase
{
    public function testGetName(): void
    {
        $provider = new GoogleBooksLookup(new MockHttpClient(), new NullLogger());

        self::assertSame('google_books', $provider->getName());
    }

    public function testGetFieldPriorityReturnsDefaultForAllFields(): void
    {
        $provider = new GoogleBooksLookup(new MockHttpClient(), new NullLogger());

        self::assertSame(100, $provider->getFieldPriority('title'));
        self::assertSame(100, $provider->getFieldPriority('description'));
        self::assertSame(100, $provider->getFieldPriority('authors'));
        self::assertSame(100, $provider->getFieldPriority('thumbnail', ComicType::MANGA));
    }

    public function testSupportsIsbnAndTitle(): void
    {
        $provider = new GoogleBooksLookup(new MockHttpClient(), new NullLogger());

        self::assertTrue($provider->supports('isbn', null));
        self::assertTrue($provider->supports('isbn', ComicType::MANGA));
        self::assertTrue($provider->supports('title', null));
        self::assertTrue($provider->supports('title', ComicType::BD));
        self::assertFalse($provider->supports('unknown', null));
    }

    public function testLookupByIsbnReturnsData(): void
    {
        $response = new MockResponse(\json_encode([
            'items' => [
                [
                    'volumeInfo' => [
                        'authors' => ['John Doe'],
                        'description' => 'A great book',
                        'imageLinks' => ['thumbnail' => 'https://example.com/cover.jpg'],
                        'publishedDate' => '2020-01-01',
                        'publisher' => 'Great Publisher',
                        'title' => 'Test Book',
                    ],
                ],
            ],
        ]));

        $provider = new GoogleBooksLookup(new MockHttpClient([$response]), new NullLogger());
        $result = $this->doLookup($provider, '9781234567890', null, 'isbn');

        self::assertInstanceOf(LookupResult::class, $result);
        self::assertSame('Test Book', $result->title);
        self::assertSame('John Doe', $result->authors);
        self::assertSame('Great Publisher', $result->publisher);
        self::assertSame('2020-01-01', $result->publishedDate);
        self::assertSame('A great book', $result->description);
        self::assertSame('https://example.com/cover.jpg', $result->thumbnail);
        self::assertSame('google_books', $result->source);
    }

    public function testLookupByIsbnMergesMultipleResults(): void
    {
        $response = new MockResponse(\json_encode([
            'items' => [
                [
                    'volumeInfo' => [
                        'description' => 'Description du premier résultat',
                        'publishedDate' => '2010',
                        'title' => 'L\'Agent 212',
                    ],
                ],
                [
                    'volumeInfo' => [
                        'authors' => ['Raoul Cauvin', 'Daniel Kox'],
                        'imageLinks' => ['thumbnail' => 'https://example.com/agent212.jpg'],
                        'publishedDate' => '2010-05',
                        'publisher' => 'Dupuis',
                        'title' => 'L\'Agent 212, tome 23',
                    ],
                ],
            ],
        ]));

        $provider = new GoogleBooksLookup(new MockHttpClient([$response]), new NullLogger());
        $result = $this->doLookup($provider, '2800152850', null, 'isbn');

        self::assertNotNull($result);
        self::assertSame('L\'Agent 212', $result->title);
        self::assertSame('Raoul Cauvin, Daniel Kox', $result->authors);
        self::assertSame('Dupuis', $result->publisher);
        self::assertSame('Description du premier résultat', $result->description);
    }

    public function testLookupByIsbnSelectsBestThumbnail(): void
    {
        $response = new MockResponse(\json_encode([
            'items' => [
                [
                    'volumeInfo' => [
                        'imageLinks' => [
                            'smallThumbnail' => 'https://example.com/small.jpg',
                            'thumbnail' => 'https://example.com/large.jpg',
                        ],
                        'title' => 'Book with images',
                    ],
                ],
            ],
        ]));

        $provider = new GoogleBooksLookup(new MockHttpClient([$response]), new NullLogger());
        $result = $this->doLookup($provider, '1234567890', null, 'isbn');

        self::assertSame('https://example.com/large.jpg', $result->thumbnail);
    }

    public function testLookupByIsbnFallsBackToSmallThumbnail(): void
    {
        $response = new MockResponse(\json_encode([
            'items' => [
                [
                    'volumeInfo' => [
                        'imageLinks' => [
                            'smallThumbnail' => 'https://example.com/small.jpg',
                        ],
                        'title' => 'Book with small image',
                    ],
                ],
            ],
        ]));

        $provider = new GoogleBooksLookup(new MockHttpClient([$response]), new NullLogger());
        $result = $this->doLookup($provider, '1234567890', null, 'isbn');

        self::assertSame('https://example.com/small.jpg', $result->thumbnail);
    }

    public function testThumbnailUrlIsOptimizedForLargerResolution(): void
    {
        $response = new MockResponse(\json_encode([
            'items' => [
                [
                    'volumeInfo' => [
                        'imageLinks' => [
                            'thumbnail' => 'http://books.google.com/books/content?id=ABC123&printsec=frontcover&img=1&zoom=1&edge=curl&source=gbs_api',
                        ],
                        'title' => 'Book with Google Books URL',
                    ],
                ],
            ],
        ]));

        $provider = new GoogleBooksLookup(new MockHttpClient([$response]), new NullLogger());
        $result = $this->doLookup($provider, '1234567890', null, 'isbn');

        self::assertSame(
            'https://books.google.com/books/content?id=ABC123&printsec=frontcover&img=1&zoom=0&source=gbs_api',
            $result->thumbnail,
        );
    }

    public function testThumbnailUrlReplacesHttpWithHttps(): void
    {
        $response = new MockResponse(\json_encode([
            'items' => [
                [
                    'volumeInfo' => [
                        'imageLinks' => [
                            'thumbnail' => 'http://books.google.com/books/content?id=XYZ&img=1&zoom=1&source=gbs_api',
                        ],
                        'title' => 'HTTP URL book',
                    ],
                ],
            ],
        ]));

        $provider = new GoogleBooksLookup(new MockHttpClient([$response]), new NullLogger());
        $result = $this->doLookup($provider, '1234567890', null, 'isbn');

        self::assertStringStartsWith('https://', $result->thumbnail);
    }

    public function testThumbnailUrlRemovesEdgeCurl(): void
    {
        $response = new MockResponse(\json_encode([
            'items' => [
                [
                    'volumeInfo' => [
                        'imageLinks' => [
                            'thumbnail' => 'https://books.google.com/books/content?id=ABC&img=1&zoom=1&edge=curl&source=gbs_api',
                        ],
                        'title' => 'Book with edge curl',
                    ],
                ],
            ],
        ]));

        $provider = new GoogleBooksLookup(new MockHttpClient([$response]), new NullLogger());
        $result = $this->doLookup($provider, '1234567890', null, 'isbn');

        self::assertStringNotContainsString('edge=curl', $result->thumbnail);
    }

    public function testThumbnailUrlLeavesNonGoogleUrlsUntouched(): void
    {
        $response = new MockResponse(\json_encode([
            'items' => [
                [
                    'volumeInfo' => [
                        'imageLinks' => [
                            'thumbnail' => 'https://example.com/cover.jpg',
                        ],
                        'title' => 'Book with external URL',
                    ],
                ],
            ],
        ]));

        $provider = new GoogleBooksLookup(new MockHttpClient([$response]), new NullLogger());
        $result = $this->doLookup($provider, '1234567890', null, 'isbn');

        self::assertSame('https://example.com/cover.jpg', $result->thumbnail);
    }

    public function testLookupByIsbnExtractsIsbn13(): void
    {
        $response = new MockResponse(\json_encode([
            'items' => [
                [
                    'volumeInfo' => [
                        'industryIdentifiers' => [
                            ['identifier' => '1234567890', 'type' => 'ISBN_10'],
                            ['identifier' => '9781234567890', 'type' => 'ISBN_13'],
                        ],
                        'title' => 'Book with ISBN',
                    ],
                ],
            ],
        ]));

        $provider = new GoogleBooksLookup(new MockHttpClient([$response]), new NullLogger());
        $result = $this->doLookup($provider, '1234567890', null, 'isbn');

        self::assertSame('9781234567890', $result->isbn);
    }

    public function testLookupByIsbnReturnsNullOneShotWhenNoSeriesInfo(): void
    {
        $response = new MockResponse(\json_encode([
            'items' => [
                [
                    'volumeInfo' => [
                        'title' => 'Aquablue',
                    ],
                ],
            ],
        ]));

        $provider = new GoogleBooksLookup(new MockHttpClient([$response]), new NullLogger());
        $result = $this->doLookup($provider, '1234567890', null, 'isbn');

        self::assertNull($result->isOneShot);
    }

    public function testLookupByIsbnDetectsSeriesAsNotOneShot(): void
    {
        $response = new MockResponse(\json_encode([
            'items' => [
                [
                    'volumeInfo' => [
                        'seriesInfo' => ['bookDisplayNumber' => '2'],
                        'title' => 'Series Book',
                    ],
                ],
            ],
        ]));

        $provider = new GoogleBooksLookup(new MockHttpClient([$response]), new NullLogger());
        $result = $this->doLookup($provider, '1234567890', null, 'isbn');

        self::assertFalse($result->isOneShot);
    }

    public function testLookupByTitleReturnsData(): void
    {
        $response = new MockResponse(\json_encode([
            'items' => [
                [
                    'volumeInfo' => [
                        'authors' => ['Author'],
                        'title' => 'Found by Title',
                    ],
                ],
            ],
        ]));

        $provider = new GoogleBooksLookup(new MockHttpClient([$response]), new NullLogger());
        $result = $this->doLookup($provider, 'Found by Title', null, 'title');

        self::assertNotNull($result);
        self::assertSame('Found by Title', $result->title);
        self::assertSame('Author', $result->authors);
    }

    public function testLookupByIsbnUsesIsbnPrefix(): void
    {
        $requestedUrls = [];
        $response = new MockResponse(\json_encode(['items' => []]));

        $mockClient = new MockHttpClient(static function (string $method, string $url) use (&$requestedUrls, $response): MockResponse {
            $requestedUrls[] = $url;

            return $response;
        });

        $provider = new GoogleBooksLookup($mockClient, new NullLogger());
        $this->doLookup($provider, '9781234567890', null, 'isbn');

        self::assertCount(1, $requestedUrls);
        self::assertStringContainsString('isbn:9781234567890', $requestedUrls[0]);
    }

    public function testLookupByTitleDoesNotUseIsbnPrefix(): void
    {
        $requestedUrls = [];
        $response = new MockResponse(\json_encode(['items' => []]));

        $mockClient = new MockHttpClient(static function (string $method, string $url) use (&$requestedUrls, $response): MockResponse {
            $requestedUrls[] = $url;

            return $response;
        });

        $provider = new GoogleBooksLookup($mockClient, new NullLogger());
        $this->doLookup($provider, 'My Book Title', null, 'title');

        self::assertCount(1, $requestedUrls);
        self::assertStringNotContainsString('isbn:', $requestedUrls[0]);
        self::assertStringContainsString('My%20Book%20Title', $requestedUrls[0]);
    }

    public function testLookupReturnsNullWhenNoResults(): void
    {
        $response = new MockResponse(\json_encode(['items' => []]));

        $provider = new GoogleBooksLookup(new MockHttpClient([$response]), new NullLogger());
        $result = $this->doLookup($provider, '0000000000', null, 'isbn');

        self::assertNull($result);
        self::assertNotNull($provider->getLastApiMessage());
        self::assertSame('not_found', $provider->getLastApiMessage()['status']);
    }

    public function testLookupHandlesNetworkErrors(): void
    {
        $response = new MockResponse('', ['error' => 'Connection failed']);

        $provider = new GoogleBooksLookup(new MockHttpClient([$response]), new NullLogger());
        $result = $this->doLookup($provider, '1234567890', null, 'isbn');

        self::assertNull($result);
        self::assertSame('error', $provider->getLastApiMessage()['status']);
    }

    public function testLookupHandlesRateLimiting(): void
    {
        $response = new MockResponse('Rate limit exceeded', ['http_code' => 429]);

        $provider = new GoogleBooksLookup(new MockHttpClient([$response]), new NullLogger());
        $result = $this->doLookup($provider, '1234567890', null, 'isbn');

        self::assertNull($result);
        self::assertSame('rate_limited', $provider->getLastApiMessage()['status']);
    }

    public function testLookupHandlesServerErrors(): void
    {
        $response = new MockResponse('Internal Server Error', ['http_code' => 500]);

        $provider = new GoogleBooksLookup(new MockHttpClient([$response]), new NullLogger());
        $result = $this->doLookup($provider, '1234567890', null, 'isbn');

        self::assertNull($result);
        self::assertSame('error', $provider->getLastApiMessage()['status']);
    }

    public function testLookupHandlesInvalidJson(): void
    {
        $response = new MockResponse('not json', ['http_code' => 200]);

        $provider = new GoogleBooksLookup(new MockHttpClient([$response]), new NullLogger());
        $result = $this->doLookup($provider, '1234567890', null, 'isbn');

        self::assertNull($result);
        self::assertSame('error', $provider->getLastApiMessage()['status']);
    }

    private function doLookup(GoogleBooksLookup $provider, string $query, ?ComicType $type, string $mode = 'title'): ?LookupResult
    {
        $state = $provider->prepareLookup($query, $type, $mode);

        return $provider->resolveLookup($state);
    }
}

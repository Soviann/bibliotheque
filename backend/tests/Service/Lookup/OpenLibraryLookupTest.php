<?php

declare(strict_types=1);

namespace App\Tests\Service\Lookup;

use App\Enum\ComicType;
use App\Service\Lookup\LookupResult;
use App\Service\Lookup\OpenLibraryLookup;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

class OpenLibraryLookupTest extends TestCase
{
    public function testGetName(): void
    {
        $provider = new OpenLibraryLookup(new MockHttpClient(), new NullLogger());

        self::assertSame('open_library', $provider->getName());
    }

    public function testGetFieldPriorityReturnsDefaultForAllFields(): void
    {
        $provider = new OpenLibraryLookup(new MockHttpClient(), new NullLogger());

        self::assertSame(80, $provider->getFieldPriority('title'));
        self::assertSame(80, $provider->getFieldPriority('description'));
        self::assertSame(80, $provider->getFieldPriority('publisher'));
    }

    public function testSupportsIsbnOnly(): void
    {
        $provider = new OpenLibraryLookup(new MockHttpClient(), new NullLogger());

        self::assertTrue($provider->supports('isbn', null));
        self::assertTrue($provider->supports('isbn', ComicType::MANGA));
        self::assertFalse($provider->supports('title', null));
        self::assertFalse($provider->supports('title', ComicType::BD));
    }

    public function testLookupByIsbnReturnsData(): void
    {
        $response = new MockResponse((string) \json_encode([
            'covers' => [12345],
            'publish_date' => 'January 2021',
            'publishers' => ['Open Publisher'],
            'title' => 'Open Library Book',
        ]));

        $provider = new OpenLibraryLookup(new MockHttpClient([$response]), new NullLogger());
        $result = $this->doLookup($provider, '1234567890', null, 'isbn');

        self::assertInstanceOf(LookupResult::class, $result);
        self::assertSame('Open Library Book', $result->title);
        self::assertSame('Open Publisher', $result->publisher);
        self::assertSame('January 2021', $result->publishedDate);
        self::assertSame('https://covers.openlibrary.org/b/id/12345-M.jpg', $result->thumbnail);
        self::assertSame('open_library', $result->source);
        self::assertNull($result->description);
    }

    public function testLookupByIsbnFetchesAuthorsInParallel(): void
    {
        $bookResponse = new MockResponse((string) \json_encode([
            'authors' => [
                ['key' => '/authors/OL1A'],
                ['key' => '/authors/OL2A'],
            ],
            'title' => 'Multi Author Book',
        ]));

        $authorResponse1 = new MockResponse((string) \json_encode(['name' => 'Author One']));
        $authorResponse2 = new MockResponse((string) \json_encode(['name' => 'Author Two']));

        $provider = new OpenLibraryLookup(
            new MockHttpClient([$bookResponse, $authorResponse1, $authorResponse2]),
            new NullLogger(),
        );
        $result = $this->doLookup($provider, '1234567890', null, 'isbn');

        self::assertNotNull($result);
        self::assertSame('Author One, Author Two', $result->authors);
    }

    public function testLookupReturnsNullWhenNotFound(): void
    {
        $response = new MockResponse('', ['http_code' => 404]);

        $provider = new OpenLibraryLookup(new MockHttpClient([$response]), new NullLogger());
        $result = $this->doLookup($provider, '0000000000', null, 'isbn');

        self::assertNull($result);
        self::assertNotNull($provider->getLastApiMessage());
        self::assertSame('not_found', $provider->getLastApiMessage()['status']);
    }

    public function testLookupReturnsNullWhenTitleMissing(): void
    {
        $response = new MockResponse((string) \json_encode([
            'publishers' => ['Some Publisher'],
        ]));

        $provider = new OpenLibraryLookup(new MockHttpClient([$response]), new NullLogger());
        $result = $this->doLookup($provider, '1234567890', null, 'isbn');

        self::assertNull($result);
        self::assertNotNull($provider->getLastApiMessage());
        self::assertSame('not_found', $provider->getLastApiMessage()['status']);
    }

    public function testLookupHandlesNetworkErrors(): void
    {
        $response = new MockResponse('', ['error' => 'Connection failed']);

        $provider = new OpenLibraryLookup(new MockHttpClient([$response]), new NullLogger());
        $result = $this->doLookup($provider, '1234567890', null, 'isbn');

        self::assertNull($result);
        self::assertNotNull($provider->getLastApiMessage());
        self::assertSame('error', $provider->getLastApiMessage()['status']);
    }

    public function testLookupHandlesRateLimiting(): void
    {
        $response = new MockResponse('Rate limit', ['http_code' => 429]);

        $provider = new OpenLibraryLookup(new MockHttpClient([$response]), new NullLogger());
        $result = $this->doLookup($provider, '1234567890', null, 'isbn');

        self::assertNull($result);
        self::assertNotNull($provider->getLastApiMessage());
        self::assertSame('rate_limited', $provider->getLastApiMessage()['status']);
    }

    private function doLookup(OpenLibraryLookup $provider, string $query, ?ComicType $type, string $mode = 'title'): ?LookupResult
    {
        $state = $provider->prepareLookup($query, $type, $mode);

        return $provider->resolveLookup($state);
    }
}

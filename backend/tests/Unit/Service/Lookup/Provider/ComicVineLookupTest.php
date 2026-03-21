<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Lookup\Provider;

use App\Enum\ComicType;
use App\Enum\LookupMode;
use App\Service\Lookup\Provider\ComicVineLookup;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * Tests unitaires pour ComicVineLookup.
 */
final class ComicVineLookupTest extends TestCase
{
    private HttpClientInterface $httpClient;
    private LoggerInterface $logger;
    private ComicVineLookup $provider;

    protected function setUp(): void
    {
        $this->httpClient = $this->createStub(HttpClientInterface::class);
        $this->logger = $this->createStub(LoggerInterface::class);

        $this->provider = new ComicVineLookup(
            'test-api-key',
            $this->httpClient,
            $this->logger,
        );
    }

    public function testGetFieldPriorityReturns100ForBdPublisher(): void
    {
        self::assertSame(100, $this->provider->getFieldPriority('publisher', ComicType::BD));
        self::assertSame(100, $this->provider->getFieldPriority('publisher', ComicType::COMICS));
    }

    public function testGetFieldPriorityReturns55Otherwise(): void
    {
        self::assertSame(55, $this->provider->getFieldPriority('title', ComicType::BD));
        self::assertSame(55, $this->provider->getFieldPriority('publisher', ComicType::MANGA));
        self::assertSame(55, $this->provider->getFieldPriority('description'));
    }

    public function testGetNameReturnsComicvine(): void
    {
        self::assertSame('comicvine', $this->provider->getName());
    }

    public function testSupportsBdAndComicsTitleMode(): void
    {
        self::assertTrue($this->provider->supports(LookupMode::TITLE, ComicType::BD));
        self::assertTrue($this->provider->supports(LookupMode::TITLE, ComicType::COMICS));
        self::assertFalse($this->provider->supports(LookupMode::TITLE, ComicType::MANGA));
        self::assertFalse($this->provider->supports(LookupMode::ISBN, ComicType::BD));
        self::assertFalse($this->provider->supports(LookupMode::TITLE, null));
    }

    public function testPrepareLookupSendsGetRequestWithApiKey(): void
    {
        $response = $this->createStub(ResponseInterface::class);
        $httpClient = $this->createHttpClientMock();

        $httpClient->expects(self::once())
            ->method('request')
            ->with(
                'GET',
                'https://comicvine.gamespot.com/api/search/',
                self::callback(static fn (array $options): bool => 'test-api-key' === $options['query']['api_key']
                    && 'json' === $options['query']['format']
                    && 'volume' === $options['query']['resources']
                    && 'Batman' === $options['query']['query']
                    && 1 === $options['query']['limit']),
            )
            ->willReturn($response);

        $this->provider->prepareLookup('Batman', ComicType::BD);
    }

    public function testPrepareLookupReturnsNullWhenApiKeyEmpty(): void
    {
        $provider = new ComicVineLookup('', $this->httpClient, $this->logger);

        $result = $provider->prepareLookup('Batman', ComicType::BD);

        self::assertNull($result);

        $apiMessage = $provider->getLastApiMessage();
        self::assertNotNull($apiMessage);
        self::assertSame('error', $apiMessage->status);
        self::assertStringContainsString('Clé API', $apiMessage->message);
    }

    public function testResolveLookupSuccess(): void
    {
        $response = $this->createStub(ResponseInterface::class);
        $response->method('toArray')->willReturn([
            'results' => [
                [
                    'count_of_issues' => 52,
                    'description' => '<p>Batman is a <b>superhero</b> comic.</p>',
                    'image' => [
                        'medium_url' => 'https://comicvine.gamespot.com/img/medium.jpg',
                        'original_url' => 'https://comicvine.gamespot.com/img/original.jpg',
                    ],
                    'name' => 'Batman',
                    'publisher' => ['name' => 'DC Comics'],
                    'start_year' => '2016',
                ],
            ],
        ]);

        $result = $this->provider->resolveLookup($response);

        self::assertNotNull($result);
        self::assertSame('Batman', $result->title);
        self::assertSame('https://comicvine.gamespot.com/img/original.jpg', $result->thumbnail);
        self::assertSame('DC Comics', $result->publisher);
        self::assertSame(52, $result->latestPublishedIssue);
        self::assertSame('2016', $result->publishedDate);
        self::assertSame('comicvine', $result->source);
        self::assertNotNull($result->description);
        self::assertStringNotContainsString('<p>', $result->description);
        self::assertStringNotContainsString('<b>', $result->description);
        self::assertStringContainsString('Batman is a superhero comic.', $result->description);
    }

    public function testResolveLookupWithNullState(): void
    {
        $result = $this->provider->resolveLookup(null);

        self::assertNull($result);
    }

    public function testResolveLookupNoResults(): void
    {
        $response = $this->createStub(ResponseInterface::class);
        $response->method('toArray')->willReturn(['results' => []]);

        $result = $this->provider->resolveLookup($response);

        self::assertNull($result);

        $apiMessage = $this->provider->getLastApiMessage();
        self::assertNotNull($apiMessage);
        self::assertSame('not_found', $apiMessage->status);
    }

    public function testResolveLookupTransportException(): void
    {
        $exception = new class('Timeout') extends \RuntimeException implements TransportExceptionInterface {};
        $response = $this->createStub(ResponseInterface::class);
        $response->method('toArray')->willThrowException($exception);

        $result = $this->provider->resolveLookup($response);

        self::assertNull($result);

        $apiMessage = $this->provider->getLastApiMessage();
        self::assertNotNull($apiMessage);
        self::assertSame('error', $apiMessage->status);
    }

    public function testResolveLookupHtmlStripping(): void
    {
        $response = $this->createStub(ResponseInterface::class);
        $response->method('toArray')->willReturn([
            'results' => [
                [
                    'count_of_issues' => null,
                    'description' => '<h2>Overview</h2><p>A &quot;great&quot; story &amp; adventure.</p>',
                    'image' => null,
                    'name' => 'Test',
                    'publisher' => null,
                    'start_year' => null,
                ],
            ],
        ]);

        $result = $this->provider->resolveLookup($response);

        self::assertNotNull($result);
        self::assertNotNull($result->description);
        self::assertStringNotContainsString('<h2>', $result->description);
        self::assertStringNotContainsString('&quot;', $result->description);
        self::assertStringContainsString('"great"', $result->description);
        self::assertStringContainsString('&', $result->description);
    }

    public function testResolveMultipleLookupReturnsMultipleResults(): void
    {
        $response = $this->createStub(ResponseInterface::class);
        $response->method('toArray')->willReturn([
            'results' => [
                [
                    'count_of_issues' => 100,
                    'description' => '<p>Story A</p>',
                    'image' => ['original_url' => 'https://img1.jpg'],
                    'name' => 'Series A',
                    'publisher' => ['name' => 'Marvel'],
                    'start_year' => '2020',
                ],
                [
                    'count_of_issues' => 50,
                    'description' => null,
                    'image' => null,
                    'name' => 'Series B',
                    'publisher' => null,
                    'start_year' => null,
                ],
            ],
        ]);

        $results = $this->provider->resolveMultipleLookup($response);

        self::assertCount(2, $results);
        self::assertSame('Series A', $results[0]->title);
        self::assertSame('Marvel', $results[0]->publisher);
        self::assertSame('Series B', $results[1]->title);
    }

    public function testResolveMultipleLookupWithNullState(): void
    {
        $results = $this->provider->resolveMultipleLookup(null);

        self::assertSame([], $results);
    }

    public function testPrepareMultipleLookupUsesLimit(): void
    {
        $response = $this->createStub(ResponseInterface::class);
        $httpClient = $this->createHttpClientMock();

        $httpClient->expects(self::once())
            ->method('request')
            ->with(
                'GET',
                self::anything(),
                self::callback(static fn (array $options): bool => 5 === $options['query']['limit']),
            )
            ->willReturn($response);

        $this->provider->prepareMultipleLookup('Spider-Man', ComicType::COMICS, 5);
    }

    public function testPrepareMultipleLookupReturnsNullWhenApiKeyEmpty(): void
    {
        $provider = new ComicVineLookup('', $this->httpClient, $this->logger);

        $result = $provider->prepareMultipleLookup('Batman', ComicType::BD, 5);

        self::assertNull($result);
    }

    private function createHttpClientMock(): HttpClientInterface&MockObject
    {
        $mock = $this->createMock(HttpClientInterface::class);
        $this->httpClient = $mock;
        $this->provider = new ComicVineLookup('test-api-key', $this->httpClient, $this->logger);

        return $mock;
    }
}

<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Lookup;

use App\Enum\ComicType;
use App\Enum\LookupMode;
use App\Service\Lookup\KitsuLookup;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * Tests unitaires pour KitsuLookup.
 */
final class KitsuLookupTest extends TestCase
{
    private HttpClientInterface $httpClient;
    private LoggerInterface $logger;
    private KitsuLookup $provider;

    protected function setUp(): void
    {
        $this->httpClient = $this->createStub(HttpClientInterface::class);
        $this->logger = $this->createStub(LoggerInterface::class);

        $this->provider = new KitsuLookup(
            $this->httpClient,
            $this->logger,
        );
    }

    public function testGetFieldPriorityReturns55ForMangaThumbnail(): void
    {
        self::assertSame(55, $this->provider->getFieldPriority('thumbnail', ComicType::MANGA));
    }

    public function testGetFieldPriorityReturns45Otherwise(): void
    {
        self::assertSame(45, $this->provider->getFieldPriority('title', ComicType::MANGA));
        self::assertSame(45, $this->provider->getFieldPriority('thumbnail', ComicType::BD));
        self::assertSame(45, $this->provider->getFieldPriority('description'));
    }

    public function testGetNameReturnsKitsu(): void
    {
        self::assertSame('kitsu', $this->provider->getName());
    }

    public function testSupportsOnlyTitleModeWithMangaType(): void
    {
        self::assertTrue($this->provider->supports(LookupMode::TITLE, ComicType::MANGA));
        self::assertFalse($this->provider->supports(LookupMode::ISBN, ComicType::MANGA));
        self::assertFalse($this->provider->supports(LookupMode::TITLE, ComicType::BD));
        self::assertFalse($this->provider->supports(LookupMode::TITLE, null));
    }

    public function testPrepareLookupSendsGetRequestWithJsonApiHeader(): void
    {
        $response = $this->createStub(ResponseInterface::class);
        $httpClient = $this->createHttpClientMock();

        $httpClient->expects(self::once())
            ->method('request')
            ->with(
                'GET',
                'https://kitsu.app/api/edge/manga',
                self::callback(static fn (array $options): bool => 'application/vnd.api+json' === $options['headers']['Accept']
                    && 'One Piece' === $options['query']['filter[text]']
                    && 1 === $options['query']['page[limit]']),
            )
            ->willReturn($response);

        $this->provider->prepareLookup('One Piece - Tome 5', ComicType::MANGA);
    }

    public function testResolveLookupSuccess(): void
    {
        $response = $this->createStub(ResponseInterface::class);
        $response->method('toArray')->willReturn([
            'data' => [
                [
                    'attributes' => [
                        'canonicalTitle' => 'One Piece',
                        'posterImage' => [
                            'large' => 'https://media.kitsu.app/manga/large.jpg',
                            'medium' => 'https://media.kitsu.app/manga/medium.jpg',
                            'original' => 'https://media.kitsu.app/manga/original.jpg',
                        ],
                        'startDate' => '1997-07-22',
                        'status' => 'current',
                        'subtype' => 'manga',
                        'synopsis' => 'A pirate adventure.',
                        'titles' => [
                            'en' => 'One Piece',
                            'en_jp' => 'One Piece',
                        ],
                        'volumeCount' => null,
                    ],
                ],
            ],
        ]);

        $result = $this->provider->resolveLookup($response);

        self::assertNotNull($result);
        self::assertSame('One Piece', $result->title);
        self::assertSame('https://media.kitsu.app/manga/original.jpg', $result->thumbnail);
        self::assertSame('1997-07-22', $result->publishedDate);
        self::assertSame('A pirate adventure.', $result->description);
        self::assertFalse($result->isOneShot);
        self::assertSame('kitsu', $result->source);
    }

    public function testResolveLookupOneShot(): void
    {
        $response = $this->createStub(ResponseInterface::class);
        $response->method('toArray')->willReturn([
            'data' => [
                [
                    'attributes' => [
                        'canonicalTitle' => 'Test',
                        'posterImage' => null,
                        'startDate' => null,
                        'status' => 'finished',
                        'subtype' => 'oneshot',
                        'synopsis' => null,
                        'titles' => [],
                        'volumeCount' => 1,
                    ],
                ],
            ],
        ]);

        $result = $this->provider->resolveLookup($response);

        self::assertNotNull($result);
        self::assertTrue($result->isOneShot);
    }

    public function testResolveLookupTitleFallbackToEnJp(): void
    {
        $response = $this->createStub(ResponseInterface::class);
        $response->method('toArray')->willReturn([
            'data' => [
                [
                    'attributes' => [
                        'canonicalTitle' => 'Canonical Title',
                        'posterImage' => null,
                        'startDate' => null,
                        'status' => 'current',
                        'subtype' => 'manga',
                        'synopsis' => null,
                        'titles' => [
                            'en_jp' => 'Romaji Title',
                        ],
                        'volumeCount' => null,
                    ],
                ],
            ],
        ]);

        $result = $this->provider->resolveLookup($response);

        self::assertNotNull($result);
        self::assertSame('Romaji Title', $result->title);
    }

    public function testResolveLookupTitleFallbackToCanonical(): void
    {
        $response = $this->createStub(ResponseInterface::class);
        $response->method('toArray')->willReturn([
            'data' => [
                [
                    'attributes' => [
                        'canonicalTitle' => 'Canonical Title',
                        'posterImage' => null,
                        'startDate' => null,
                        'status' => 'current',
                        'subtype' => 'manga',
                        'synopsis' => null,
                        'titles' => [],
                        'volumeCount' => null,
                    ],
                ],
            ],
        ]);

        $result = $this->provider->resolveLookup($response);

        self::assertNotNull($result);
        self::assertSame('Canonical Title', $result->title);
    }

    public function testResolveLookupNoResults(): void
    {
        $response = $this->createStub(ResponseInterface::class);
        $response->method('toArray')->willReturn(['data' => []]);

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

    public function testResolveMultipleLookupReturnsMultipleResults(): void
    {
        $response = $this->createStub(ResponseInterface::class);
        $response->method('toArray')->willReturn([
            'data' => [
                [
                    'attributes' => [
                        'canonicalTitle' => 'Manga A',
                        'posterImage' => ['original' => 'https://img1.jpg'],
                        'startDate' => '2020-01-01',
                        'status' => 'current',
                        'subtype' => 'manga',
                        'synopsis' => 'Story A',
                        'titles' => ['en' => 'Manga A'],
                        'volumeCount' => 10,
                    ],
                ],
                [
                    'attributes' => [
                        'canonicalTitle' => 'Manga B',
                        'posterImage' => null,
                        'startDate' => null,
                        'status' => 'finished',
                        'subtype' => 'manga',
                        'synopsis' => null,
                        'titles' => ['en' => 'Manga B'],
                        'volumeCount' => 5,
                    ],
                ],
            ],
        ]);

        $results = $this->provider->resolveMultipleLookup($response);

        self::assertCount(2, $results);
        self::assertSame('Manga A', $results[0]->title);
        self::assertSame('Manga B', $results[1]->title);
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
                self::callback(static fn (array $options): bool => 5 === $options['query']['page[limit]']),
            )
            ->willReturn($response);

        $this->provider->prepareMultipleLookup('Naruto', ComicType::MANGA, 5);
    }

    private function createHttpClientMock(): HttpClientInterface&MockObject
    {
        $mock = $this->createMock(HttpClientInterface::class);
        $this->httpClient = $mock;
        $this->provider = new KitsuLookup($this->httpClient, $this->logger);

        return $mock;
    }
}

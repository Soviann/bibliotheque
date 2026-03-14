<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Lookup;

use App\Enum\ComicType;
use App\Service\Lookup\MangaDexLookup;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * Tests unitaires pour MangaDexLookup.
 */
final class MangaDexLookupTest extends TestCase
{
    private HttpClientInterface $httpClient;
    private LoggerInterface $logger;
    private MangaDexLookup $provider;

    protected function setUp(): void
    {
        $this->httpClient = $this->createStub(HttpClientInterface::class);
        $this->logger = $this->createStub(LoggerInterface::class);

        $this->provider = new MangaDexLookup(
            $this->httpClient,
            $this->logger,
        );
    }

    public function testGetFieldPriorityReturns55ForMangaAuthors(): void
    {
        self::assertSame(55, $this->provider->getFieldPriority('authors', ComicType::MANGA));
    }

    public function testGetFieldPriorityReturns40Otherwise(): void
    {
        self::assertSame(40, $this->provider->getFieldPriority('title', ComicType::MANGA));
        self::assertSame(40, $this->provider->getFieldPriority('authors', ComicType::BD));
        self::assertSame(40, $this->provider->getFieldPriority('description'));
    }

    public function testGetNameReturnsMangadex(): void
    {
        self::assertSame('mangadex', $this->provider->getName());
    }

    public function testSupportsOnlyTitleModeWithMangaType(): void
    {
        self::assertTrue($this->provider->supports('title', ComicType::MANGA));
        self::assertFalse($this->provider->supports('isbn', ComicType::MANGA));
        self::assertFalse($this->provider->supports('title', ComicType::BD));
        self::assertFalse($this->provider->supports('title', null));
    }

    public function testPrepareLookupSendsGetRequest(): void
    {
        $response = $this->createStub(ResponseInterface::class);
        $httpClient = $this->createHttpClientMock();

        $httpClient->expects(self::once())
            ->method('request')
            ->with(
                'GET',
                'https://api.mangadex.org/manga',
                self::callback(static function (array $options): bool {
                    return 'One Piece' === $options['query']['title']
                        && 1 === $options['query']['limit']
                        && ['cover_art', 'author', 'artist'] === $options['query']['includes[]'];
                }),
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
                        'description' => ['en' => 'A pirate adventure.'],
                        'lastVolume' => '107',
                        'status' => 'ongoing',
                        'title' => ['en' => 'One Piece'],
                        'year' => 1997,
                    ],
                    'id' => 'abc-123',
                    'relationships' => [
                        [
                            'attributes' => ['name' => 'Oda Eiichiro'],
                            'type' => 'author',
                        ],
                        [
                            'attributes' => ['name' => 'Oda Eiichiro'],
                            'type' => 'artist',
                        ],
                        [
                            'attributes' => ['fileName' => 'cover.jpg'],
                            'type' => 'cover_art',
                        ],
                    ],
                ],
            ],
        ]);

        $result = $this->provider->resolveLookup($response);

        self::assertNotNull($result);
        self::assertSame('One Piece', $result->title);
        self::assertSame('Oda Eiichiro', $result->authors);
        self::assertSame('https://uploads.mangadex.org/covers/abc-123/cover.jpg', $result->thumbnail);
        self::assertSame('1997', $result->publishedDate);
        self::assertSame('A pirate adventure.', $result->description);
        self::assertSame(107, $result->latestPublishedIssue);
        self::assertFalse($result->isOneShot);
        self::assertSame('mangadex', $result->source);
    }

    public function testResolveLookupAuthorDeduplication(): void
    {
        $response = $this->createStub(ResponseInterface::class);
        $response->method('toArray')->willReturn([
            'data' => [
                [
                    'attributes' => [
                        'description' => [],
                        'lastVolume' => null,
                        'status' => 'ongoing',
                        'title' => ['en' => 'Test'],
                        'year' => null,
                    ],
                    'id' => 'def-456',
                    'relationships' => [
                        [
                            'attributes' => ['name' => 'Author A'],
                            'type' => 'author',
                        ],
                        [
                            'attributes' => ['name' => 'Author A'],
                            'type' => 'artist',
                        ],
                        [
                            'attributes' => ['name' => 'Author B'],
                            'type' => 'author',
                        ],
                    ],
                ],
            ],
        ]);

        $result = $this->provider->resolveLookup($response);

        self::assertNotNull($result);
        self::assertSame('Author A, Author B', $result->authors);
    }

    public function testResolveLookupTitleFallbackToJaRo(): void
    {
        $response = $this->createStub(ResponseInterface::class);
        $response->method('toArray')->willReturn([
            'data' => [
                [
                    'attributes' => [
                        'description' => [],
                        'lastVolume' => null,
                        'status' => 'ongoing',
                        'title' => ['ja-ro' => 'Wan Piisu'],
                        'year' => null,
                    ],
                    'id' => 'ghi-789',
                    'relationships' => [],
                ],
            ],
        ]);

        $result = $this->provider->resolveLookup($response);

        self::assertNotNull($result);
        self::assertSame('Wan Piisu', $result->title);
    }

    public function testResolveLookupDescriptionFallbackToFr(): void
    {
        $response = $this->createStub(ResponseInterface::class);
        $response->method('toArray')->willReturn([
            'data' => [
                [
                    'attributes' => [
                        'description' => ['fr' => 'Une aventure de pirate.'],
                        'lastVolume' => null,
                        'status' => 'ongoing',
                        'title' => ['en' => 'Test'],
                        'year' => null,
                    ],
                    'id' => 'jkl-012',
                    'relationships' => [],
                ],
            ],
        ]);

        $result = $this->provider->resolveLookup($response);

        self::assertNotNull($result);
        self::assertSame('Une aventure de pirate.', $result->description);
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
                        'description' => ['en' => 'Story A'],
                        'lastVolume' => '10',
                        'status' => 'ongoing',
                        'title' => ['en' => 'Manga A'],
                        'year' => 2020,
                    ],
                    'id' => 'id-1',
                    'relationships' => [
                        ['attributes' => ['name' => 'Author A'], 'type' => 'author'],
                    ],
                ],
                [
                    'attributes' => [
                        'description' => [],
                        'lastVolume' => null,
                        'status' => 'completed',
                        'title' => ['en' => 'Manga B'],
                        'year' => null,
                    ],
                    'id' => 'id-2',
                    'relationships' => [],
                ],
            ],
        ]);

        $results = $this->provider->resolveMultipleLookup($response);

        self::assertCount(2, $results);
        self::assertSame('Manga A', $results[0]->title);
        self::assertSame('Author A', $results[0]->authors);
        self::assertSame('Manga B', $results[1]->title);
    }

    public function testResolveLookupCoverUrlAssembly(): void
    {
        $response = $this->createStub(ResponseInterface::class);
        $response->method('toArray')->willReturn([
            'data' => [
                [
                    'attributes' => [
                        'description' => [],
                        'lastVolume' => null,
                        'status' => 'ongoing',
                        'title' => ['en' => 'Test'],
                        'year' => null,
                    ],
                    'id' => 'manga-uuid-123',
                    'relationships' => [
                        [
                            'attributes' => ['fileName' => 'my-cover-file.png'],
                            'type' => 'cover_art',
                        ],
                    ],
                ],
            ],
        ]);

        $result = $this->provider->resolveLookup($response);

        self::assertNotNull($result);
        self::assertSame(
            'https://uploads.mangadex.org/covers/manga-uuid-123/my-cover-file.png',
            $result->thumbnail,
        );
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
                self::callback(static function (array $options): bool {
                    return 5 === $options['query']['limit'];
                }),
            )
            ->willReturn($response);

        $this->provider->prepareMultipleLookup('Naruto', ComicType::MANGA, 5);
    }

    private function createHttpClientMock(): HttpClientInterface&MockObject
    {
        $mock = $this->createMock(HttpClientInterface::class);
        $this->httpClient = $mock;
        $this->provider = new MangaDexLookup($this->httpClient, $this->logger);

        return $mock;
    }
}

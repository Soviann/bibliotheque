<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Lookup\Provider;

use App\Enum\ComicType;
use App\Enum\LookupMode;
use App\Service\Lookup\Provider\JikanLookup;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * Tests unitaires pour JikanLookup.
 */
final class JikanLookupTest extends TestCase
{
    private HttpClientInterface $httpClient;
    private LoggerInterface $logger;
    private JikanLookup $provider;

    protected function setUp(): void
    {
        $this->httpClient = $this->createStub(HttpClientInterface::class);
        $this->logger = $this->createStub(LoggerInterface::class);

        $this->provider = new JikanLookup(
            $this->httpClient,
            $this->logger,
        );
    }

    public function testGetFieldPriorityReturns65ForMangaDescriptionAndLatestPublishedIssue(): void
    {
        self::assertSame(65, $this->provider->getFieldPriority('description', ComicType::MANGA));
        self::assertSame(65, $this->provider->getFieldPriority('latestPublishedIssue', ComicType::MANGA));
    }

    public function testGetFieldPriorityReturns50Otherwise(): void
    {
        self::assertSame(50, $this->provider->getFieldPriority('title', ComicType::MANGA));
        self::assertSame(50, $this->provider->getFieldPriority('description', ComicType::BD));
        self::assertSame(50, $this->provider->getFieldPriority('thumbnail'));
    }

    public function testGetNameReturnsJikan(): void
    {
        self::assertSame('jikan', $this->provider->getName());
    }

    public function testSupportsOnlyTitleModeWithMangaType(): void
    {
        self::assertTrue($this->provider->supports(LookupMode::TITLE, ComicType::MANGA));
        self::assertFalse($this->provider->supports(LookupMode::ISBN, ComicType::MANGA));
        self::assertFalse($this->provider->supports(LookupMode::TITLE, ComicType::BD));
        self::assertFalse($this->provider->supports(LookupMode::TITLE, null));
    }

    public function testPrepareLookupSendsGetRequest(): void
    {
        $response = $this->createStub(ResponseInterface::class);
        $httpClient = $this->createHttpClientMock();

        $httpClient->expects(self::once())
            ->method('request')
            ->with(
                'GET',
                'https://api.jikan.moe/v4/manga',
                self::callback(static fn (array $options): bool => 'One Piece' === $options['query']['q']
                    && 'manga' === $options['query']['type']
                    && 1 === $options['query']['limit']),
            )
            ->willReturn($response);

        $this->provider->prepareLookup('One Piece - Tome 42', ComicType::MANGA);
    }

    public function testResolveLookupSuccess(): void
    {
        $response = $this->createStub(ResponseInterface::class);
        $response->method('toArray')->willReturn([
            'data' => [
                [
                    'authors' => [
                        ['name' => 'Oda, Eiichiro'],
                    ],
                    'images' => [
                        'jpg' => [
                            'image_url' => 'https://cdn.myanimelist.net/images/manga/small.jpg',
                            'large_image_url' => 'https://cdn.myanimelist.net/images/manga/large.jpg',
                        ],
                    ],
                    'published' => [
                        'from' => '1997-07-22T00:00:00+00:00',
                    ],
                    'status' => 'Publishing',
                    'synopsis' => 'A pirate adventure story.',
                    'title' => 'One Piece',
                    'title_english' => 'One Piece',
                    'type' => 'Manga',
                    'volumes' => null,
                ],
            ],
        ]);

        $result = $this->provider->resolveLookup($response);

        self::assertNotNull($result);
        self::assertSame('One Piece', $result->title);
        self::assertSame('Oda, Eiichiro', $result->authors);
        self::assertSame('https://cdn.myanimelist.net/images/manga/large.jpg', $result->thumbnail);
        self::assertSame('1997-07-22', $result->publishedDate);
        self::assertSame('A pirate adventure story.', $result->description);
        self::assertFalse($result->isOneShot);
        self::assertNull($result->latestPublishedIssue);
        self::assertSame('jikan', $result->source);
    }

    public function testResolveLookupOneShot(): void
    {
        $response = $this->createStub(ResponseInterface::class);
        $response->method('toArray')->willReturn([
            'data' => [
                [
                    'authors' => [],
                    'images' => ['jpg' => []],
                    'published' => [],
                    'status' => 'Finished',
                    'synopsis' => null,
                    'title' => 'Test One Shot',
                    'title_english' => null,
                    'type' => 'One-shot',
                    'volumes' => 1,
                ],
            ],
        ]);

        $result = $this->provider->resolveLookup($response);

        self::assertNotNull($result);
        self::assertTrue($result->isOneShot);
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

    public function testResolveLookupTitleFallbackToTitle(): void
    {
        $response = $this->createStub(ResponseInterface::class);
        $response->method('toArray')->willReturn([
            'data' => [
                [
                    'authors' => [],
                    'images' => ['jpg' => []],
                    'published' => [],
                    'status' => 'Publishing',
                    'synopsis' => null,
                    'title' => 'ワンピース',
                    'title_english' => null,
                    'type' => 'Manga',
                    'volumes' => null,
                ],
            ],
        ]);

        $result = $this->provider->resolveLookup($response);

        self::assertNotNull($result);
        self::assertSame('ワンピース', $result->title);
    }

    public function testResolveMultipleLookupReturnsMultipleResults(): void
    {
        $response = $this->createStub(ResponseInterface::class);
        $response->method('toArray')->willReturn([
            'data' => [
                [
                    'authors' => [['name' => 'Author A']],
                    'images' => ['jpg' => ['large_image_url' => 'https://img1.jpg']],
                    'published' => ['from' => '2020-01-01T00:00:00+00:00'],
                    'status' => 'Publishing',
                    'synopsis' => 'Story A',
                    'title' => 'Manga A',
                    'title_english' => 'Manga A',
                    'type' => 'Manga',
                    'volumes' => 10,
                ],
                [
                    'authors' => [],
                    'images' => ['jpg' => []],
                    'published' => [],
                    'status' => 'Finished',
                    'synopsis' => null,
                    'title' => 'Manga B',
                    'title_english' => null,
                    'type' => 'Manga',
                    'volumes' => 5,
                ],
            ],
        ]);

        $results = $this->provider->resolveMultipleLookup($response);

        self::assertCount(2, $results);
        self::assertSame('Manga A', $results[0]->title);
        self::assertSame(10, $results[0]->latestPublishedIssue);
        self::assertSame('Manga B', $results[1]->title);
    }

    public function testResolveMultipleLookupNoResults(): void
    {
        $response = $this->createStub(ResponseInterface::class);
        $response->method('toArray')->willReturn(['data' => []]);

        $results = $this->provider->resolveMultipleLookup($response);

        self::assertSame([], $results);

        $apiMessage = $this->provider->getLastApiMessage();
        self::assertNotNull($apiMessage);
        self::assertSame('not_found', $apiMessage->status);
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
                self::callback(static fn (array $options): bool => 5 === $options['query']['limit']
                    && 'Naruto' === $options['query']['q']),
            )
            ->willReturn($response);

        $this->provider->prepareMultipleLookup('Naruto', ComicType::MANGA, 5);
    }

    private function createHttpClientMock(): HttpClientInterface&MockObject
    {
        $mock = $this->createMock(HttpClientInterface::class);
        $this->httpClient = $mock;
        $this->provider = new JikanLookup($this->httpClient, $this->logger);

        return $mock;
    }
}

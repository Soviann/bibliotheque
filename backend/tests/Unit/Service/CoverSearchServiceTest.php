<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\DTO\CoverSearchResult;
use App\Enum\ComicType;
use App\Service\CoverSearchService;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * Tests unitaires pour CoverSearchService.
 */
final class CoverSearchServiceTest extends TestCase
{
    private HttpClientInterface $httpClient;
    private LoggerInterface $logger;

    protected function setUp(): void
    {
        $this->httpClient = $this->createStub(HttpClientInterface::class);
        $this->logger = $this->createStub(LoggerInterface::class);
    }

    private function createService(string $googleBooksApiKey = 'test-books-key', string $serperApiKey = 'test-serper-key'): CoverSearchService
    {
        return new CoverSearchService(
            $googleBooksApiKey,
            $this->httpClient,
            $this->logger,
            $serperApiKey,
        );
    }

    public function testSearchCombinesGoogleBooksAndSerperResults(): void
    {
        $googleBooksResponse = $this->createStub(ResponseInterface::class);
        $googleBooksResponse->method('toArray')->willReturn([
            'items' => [
                [
                    'volumeInfo' => [
                        'imageLinks' => ['thumbnail' => 'https://books.google.com/thumb1?zoom=1'],
                        'title' => 'Naruto Vol. 1',
                    ],
                ],
            ],
        ]);

        $serperResponse = $this->createStub(ResponseInterface::class);
        $serperResponse->method('toArray')->willReturn([
            'images' => [
                [
                    'imageHeight' => 800,
                    'imageUrl' => 'https://example.com/naruto.jpg',
                    'imageWidth' => 600,
                    'thumbnailUrl' => 'https://example.com/naruto_thumb.jpg',
                    'title' => 'Naruto Cover',
                ],
            ],
        ]);

        $this->httpClient->method('request')
            ->willReturnCallback(static function (string $method) use ($googleBooksResponse, $serperResponse): ResponseInterface {
                return 'GET' === $method ? $googleBooksResponse : $serperResponse;
            });

        $results = $this->createService()->search('Naruto', ComicType::MANGA);

        self::assertCount(2, $results);

        // Google Books en premier
        self::assertSame('Naruto Vol. 1', $results[0]->title);
        self::assertStringContainsString('zoom=0', $results[0]->url);

        // Serper ensuite
        self::assertSame('https://example.com/naruto.jpg', $results[1]->url);
        self::assertSame('https://example.com/naruto_thumb.jpg', $results[1]->thumbnail);
        self::assertSame(800, $results[1]->height);
        self::assertSame(600, $results[1]->width);
    }

    public function testSearchSerperCallsWithCorrectQueryForManga(): void
    {
        $emptyResponse = $this->createStub(ResponseInterface::class);
        $emptyResponse->method('toArray')->willReturn([]);

        $this->httpClient = $this->createMock(HttpClientInterface::class);
        $this->httpClient
            ->expects(self::exactly(2))
            ->method('request')
            ->willReturnCallback(static function (string $method, string $url, array $options) use ($emptyResponse): ResponseInterface {
                if ('POST' === $method) {
                    self::assertSame('https://google.serper.dev/images', $url);
                    self::assertSame('Naruto cover', $options['json']['q']);
                    self::assertSame('test-serper-key', $options['headers']['X-API-KEY']);
                }

                return $emptyResponse;
            });

        $this->createService()->search('Naruto', ComicType::MANGA);
    }

    public function testSearchSerperCallsWithCouvertureForBd(): void
    {
        $emptyResponse = $this->createStub(ResponseInterface::class);
        $emptyResponse->method('toArray')->willReturn([]);

        $this->httpClient = $this->createMock(HttpClientInterface::class);
        $this->httpClient
            ->expects(self::exactly(2))
            ->method('request')
            ->willReturnCallback(static function (string $method, string $url, array $options) use ($emptyResponse): ResponseInterface {
                if ('POST' === $method) {
                    self::assertSame('Astérix couverture', $options['json']['q']);
                }

                return $emptyResponse;
            });

        $this->createService()->search('Astérix', ComicType::BD);
    }

    public function testSearchReturnsEmptyArrayWhenBothSourcesFail(): void
    {
        $this->httpClient->method('request')->willThrowException(
            new \RuntimeException('API error'),
        );

        $results = $this->createService()->search('Test');

        self::assertSame([], $results);
    }

    public function testSearchSkipsGoogleBooksWithoutThumbnail(): void
    {
        $googleBooksResponse = $this->createStub(ResponseInterface::class);
        $googleBooksResponse->method('toArray')->willReturn([
            'items' => [
                ['volumeInfo' => ['title' => 'No Cover Book']],
            ],
        ]);

        $serperResponse = $this->createStub(ResponseInterface::class);
        $serperResponse->method('toArray')->willReturn(['images' => []]);

        $this->httpClient->method('request')
            ->willReturnCallback(static function (string $method) use ($googleBooksResponse, $serperResponse): ResponseInterface {
                return 'GET' === $method ? $googleBooksResponse : $serperResponse;
            });

        $results = $this->createService()->search('Test');

        self::assertSame([], $results);
    }

    public function testSearchSkipsSourcesWithEmptyApiKey(): void
    {
        $results = $this->createService('', '')->search('Test');

        self::assertSame([], $results);
    }

    public function testSearchGoogleBooksOptimizesThumbnailUrl(): void
    {
        $googleBooksResponse = $this->createStub(ResponseInterface::class);
        $googleBooksResponse->method('toArray')->willReturn([
            'items' => [
                [
                    'volumeInfo' => [
                        'imageLinks' => ['thumbnail' => 'http://books.google.com/thumb?zoom=1&edge=curl'],
                        'title' => 'Test',
                    ],
                ],
            ],
        ]);

        $serperResponse = $this->createStub(ResponseInterface::class);
        $serperResponse->method('toArray')->willReturn(['images' => []]);

        $this->httpClient->method('request')
            ->willReturnCallback(static function (string $method) use ($googleBooksResponse, $serperResponse): ResponseInterface {
                return 'GET' === $method ? $googleBooksResponse : $serperResponse;
            });

        $results = $this->createService()->search('Test');

        self::assertCount(1, $results);
        self::assertStringStartsWith('https://', $results[0]->url);
        self::assertStringContainsString('zoom=0', $results[0]->url);
        self::assertStringNotContainsString('edge=curl', $results[0]->url);
    }
}

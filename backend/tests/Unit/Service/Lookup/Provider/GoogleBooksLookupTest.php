<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Lookup\Provider;

use App\Enum\ComicType;
use App\Enum\LookupMode;
use App\Service\Lookup\Provider\GoogleBooksLookup;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\DecodingExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * Tests unitaires pour GoogleBooksLookup.
 */
final class GoogleBooksLookupTest extends TestCase
{
    private HttpClientInterface $httpClient;
    private LoggerInterface $logger;
    private GoogleBooksLookup $provider;

    protected function setUp(): void
    {
        $this->httpClient = $this->createStub(HttpClientInterface::class);
        $this->logger = $this->createStub(LoggerInterface::class);

        $this->provider = new GoogleBooksLookup(
            'test-api-key',
            $this->httpClient,
            $this->logger,
        );
    }

    /**
     * Teste que getFieldPriority retourne toujours 100.
     */
    public function testGetFieldPriorityReturns100(): void
    {
        self::assertSame(100, $this->provider->getFieldPriority('title'));
        self::assertSame(100, $this->provider->getFieldPriority('description'));
        self::assertSame(100, $this->provider->getFieldPriority('thumbnail', ComicType::MANGA));
    }

    /**
     * Teste que getName retourne 'google_books'.
     */
    public function testGetNameReturnsGoogleBooks(): void
    {
        self::assertSame('google_books', $this->provider->getName());
    }

    /**
     * Teste que supports retourne true pour isbn et title.
     */
    public function testSupportsIsbnAndTitle(): void
    {
        self::assertTrue($this->provider->supports(LookupMode::ISBN, null));
        self::assertTrue($this->provider->supports(LookupMode::TITLE, null));
        self::assertTrue($this->provider->supports(LookupMode::ISBN, ComicType::MANGA));
    }

    /**
     * Teste que prepareLookup en mode isbn envoie la bonne requete.
     */
    public function testPrepareLookupIsbnMode(): void
    {
        $response = $this->createStub(ResponseInterface::class);
        $httpClient = $this->createHttpClientMock();

        $httpClient->expects(self::once())
            ->method('request')
            ->with(
                'GET',
                'https://www.googleapis.com/books/v1/volumes',
                self::callback(static fn (array $options): bool => 'isbn:9782723489' === $options['query']['q']
                    && 10 === $options['query']['maxResults']
                    && 'test-api-key' === $options['query']['key']
                    && 10 === $options['timeout']),
            )
            ->willReturn($response);

        $result = $this->provider->prepareLookup('9782723489', null, LookupMode::ISBN);

        self::assertSame($response, $result);
    }

    /**
     * Teste que prepareLookup en mode title envoie la bonne requete.
     */
    public function testPrepareLookupTitleMode(): void
    {
        $response = $this->createStub(ResponseInterface::class);
        $httpClient = $this->createHttpClientMock();

        $httpClient->expects(self::once())
            ->method('request')
            ->with(
                'GET',
                'https://www.googleapis.com/books/v1/volumes',
                self::callback(static fn (array $options): bool => 'One Piece' === $options['query']['q']
                    && 10 === $options['query']['maxResults']),
            )
            ->willReturn($response);

        $this->provider->prepareLookup('One Piece', ComicType::MANGA, LookupMode::TITLE);
    }

    /**
     * Teste que prepareLookup sans cle API n'inclut pas le parametre key.
     */
    public function testPrepareLookupWithoutApiKey(): void
    {
        $httpClient = $this->createMock(HttpClientInterface::class);
        $provider = new GoogleBooksLookup('', $httpClient, $this->logger);
        $response = $this->createStub(ResponseInterface::class);

        $httpClient->expects(self::once())
            ->method('request')
            ->with(
                'GET',
                self::anything(),
                self::callback(static fn (array $options): bool => !isset($options['query']['key'])),
            )
            ->willReturn($response);

        $provider->prepareLookup('test', null, LookupMode::ISBN);
    }

    /**
     * Teste resolveLookup avec des donnees valides.
     */
    public function testResolveLookupSuccess(): void
    {
        $response = $this->createStub(ResponseInterface::class);
        $response->method('toArray')->willReturn([
            'items' => [
                [
                    'volumeInfo' => [
                        'authors' => ['Eiichiro Oda'],
                        'description' => 'A pirate story',
                        'imageLinks' => [
                            'thumbnail' => 'http://books.google.com/image?zoom=1&edge=curl',
                        ],
                        'publishedDate' => '1997',
                        'publisher' => 'Glenat',
                        'title' => 'One Piece',
                    ],
                ],
            ],
        ]);

        $result = $this->provider->resolveLookup($response);

        self::assertNotNull($result);
        self::assertSame('Eiichiro Oda', $result->authors);
        self::assertSame('A pirate story', $result->description);
        self::assertSame('1997', $result->publishedDate);
        self::assertSame('Glenat', $result->publisher);
        self::assertSame('One Piece', $result->title);
        self::assertSame('google_books', $result->source);
    }

    /**
     * Teste que l'URL thumbnail est optimisee : http->https, zoom=1->zoom=0, edge=curl supprime.
     */
    public function testResolveLookupOptimizesThumbnailUrl(): void
    {
        $response = $this->createStub(ResponseInterface::class);
        $response->method('toArray')->willReturn([
            'items' => [
                [
                    'volumeInfo' => [
                        'imageLinks' => [
                            'thumbnail' => 'http://books.google.com/books/content?id=abc&zoom=1&edge=curl',
                        ],
                        'title' => 'Test',
                    ],
                ],
            ],
        ]);

        $result = $this->provider->resolveLookup($response);

        self::assertNotNull($result);
        self::assertStringStartsWith('https://', $result->thumbnail);
        self::assertStringContainsString('zoom=0', $result->thumbnail);
        self::assertStringNotContainsString('edge=curl', $result->thumbnail);
        self::assertStringNotContainsString('http://', $result->thumbnail);
    }

    /**
     * Teste resolveLookup retourne null quand aucun item.
     */
    public function testResolveLookupNoItems(): void
    {
        $response = $this->createStub(ResponseInterface::class);
        $response->method('toArray')->willReturn(['items' => []]);

        $result = $this->provider->resolveLookup($response);

        self::assertNull($result);

        $apiMessage = $this->provider->getLastApiMessage();
        self::assertNotNull($apiMessage);
        self::assertSame('not_found', $apiMessage->status);
    }

    /**
     * Teste resolveLookup retourne null quand le champ items est absent.
     */
    public function testResolveLookupNoItemsKey(): void
    {
        $response = $this->createStub(ResponseInterface::class);
        $response->method('toArray')->willReturn(['totalItems' => 0]);

        $result = $this->provider->resolveLookup($response);

        self::assertNull($result);

        $apiMessage = $this->provider->getLastApiMessage();
        self::assertSame('not_found', $apiMessage->status);
    }

    /**
     * Teste resolveLookup en cas d'erreur de transport.
     */
    public function testResolveLookupTransportException(): void
    {
        $response = $this->createStub(ResponseInterface::class);
        $exception = new class('Connection timeout') extends \RuntimeException implements TransportExceptionInterface {};
        $response->method('toArray')->willThrowException($exception);

        $this->createLoggerMock()->expects(self::once())->method('error');

        $result = $this->provider->resolveLookup($response);

        self::assertNull($result);

        $apiMessage = $this->provider->getLastApiMessage();
        self::assertSame('error', $apiMessage->status);
        self::assertSame('Erreur de connexion', $apiMessage->message);
    }

    /**
     * Teste resolveLookup en cas d'erreur HTTP 429.
     */
    public function testResolveLookupRateLimited429(): void
    {
        $innerResponse = $this->createStub(ResponseInterface::class);
        $innerResponse->method('getStatusCode')->willReturn(429);

        $exception = new class('Rate limited', $innerResponse) extends \RuntimeException implements ClientExceptionInterface {
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

        $response = $this->createStub(ResponseInterface::class);
        $response->method('toArray')->willThrowException($exception);

        $result = $this->provider->resolveLookup($response);

        self::assertNull($result);

        $apiMessage = $this->provider->getLastApiMessage();
        self::assertSame('rate_limited', $apiMessage->status);
    }

    /**
     * Teste resolveLookup en cas d'erreur HTTP autre que 429.
     */
    public function testResolveLookupOtherHttpError(): void
    {
        $innerResponse = $this->createStub(ResponseInterface::class);
        $innerResponse->method('getStatusCode')->willReturn(500);

        $exception = new class('Server error', $innerResponse) extends \RuntimeException implements ClientExceptionInterface {
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

        $response = $this->createStub(ResponseInterface::class);
        $response->method('toArray')->willThrowException($exception);

        $result = $this->provider->resolveLookup($response);

        self::assertNull($result);

        $apiMessage = $this->provider->getLastApiMessage();
        self::assertSame('error', $apiMessage->status);
        self::assertStringContainsString('500', $apiMessage->message);
    }

    /**
     * Teste resolveLookup en cas d'erreur de decodage JSON.
     */
    public function testResolveLookupDecodingException(): void
    {
        $exception = new class('Invalid JSON') extends \RuntimeException implements DecodingExceptionInterface {};

        $response = $this->createStub(ResponseInterface::class);
        $response->method('toArray')->willThrowException($exception);

        $this->createLoggerMock()->expects(self::once())->method('error');

        $result = $this->provider->resolveLookup($response);

        self::assertNull($result);

        $apiMessage = $this->provider->getLastApiMessage();
        self::assertSame('error', $apiMessage->status);
        self::assertSame('Réponse invalide', $apiMessage->message);
    }

    /**
     * Teste que resolveLookup fusionne les donnees de plusieurs items.
     */
    public function testResolveLookupMergesMultipleItems(): void
    {
        $response = $this->createStub(ResponseInterface::class);
        $response->method('toArray')->willReturn([
            'items' => [
                [
                    'volumeInfo' => [
                        'authors' => ['Oda'],
                        'title' => 'One Piece T1',
                    ],
                ],
                [
                    'volumeInfo' => [
                        'description' => 'Des pirates',
                        'publishedDate' => '1997',
                        'publisher' => 'Glenat',
                    ],
                ],
            ],
        ]);

        $result = $this->provider->resolveLookup($response);

        self::assertNotNull($result);
        self::assertSame('Oda', $result->authors);
        self::assertSame('One Piece T1', $result->title);
        self::assertSame('Des pirates', $result->description);
        self::assertSame('1997', $result->publishedDate);
        self::assertSame('Glenat', $result->publisher);
    }

    /**
     * Teste que optimizeThumbnailUrl retourne l'URL inchangee pour un domaine non-Google Books.
     */
    public function testOptimizeThumbnailUrlNonGoogleBooksUrlReturnedUnchanged(): void
    {
        $response = $this->createStub(ResponseInterface::class);
        $response->method('toArray')->willReturn([
            'items' => [
                [
                    'volumeInfo' => [
                        'imageLinks' => [
                            'thumbnail' => 'https://example.com/image.jpg?zoom=1&edge=curl',
                        ],
                        'title' => 'Test',
                    ],
                ],
            ],
        ]);

        $result = $this->provider->resolveLookup($response);

        self::assertNotNull($result);
        self::assertSame('https://example.com/image.jpg?zoom=1&edge=curl', $result->thumbnail);
    }

    /**
     * Teste le fallback quand seul smallThumbnail est present.
     */
    public function testResolveLookupSmallThumbnailFallback(): void
    {
        $response = $this->createStub(ResponseInterface::class);
        $response->method('toArray')->willReturn([
            'items' => [
                [
                    'volumeInfo' => [
                        'imageLinks' => [
                            'smallThumbnail' => 'http://books.google.com/small?zoom=1',
                        ],
                        'title' => 'Test',
                    ],
                ],
            ],
        ]);

        $result = $this->provider->resolveLookup($response);

        self::assertNotNull($result);
        self::assertNotNull($result->thumbnail);
        self::assertStringContainsString('zoom=0', $result->thumbnail);
    }

    /**
     * Teste l'extraction de l'ISBN quand seul ISBN_10 est present.
     */
    public function testResolveLookupExtractsIsbn10Only(): void
    {
        $response = $this->createStub(ResponseInterface::class);
        $response->method('toArray')->willReturn([
            'items' => [
                [
                    'volumeInfo' => [
                        'industryIdentifiers' => [
                            ['identifier' => '2723489000', 'type' => 'ISBN_10'],
                        ],
                        'title' => 'Test',
                    ],
                ],
            ],
        ]);

        $result = $this->provider->resolveLookup($response);

        self::assertNotNull($result);
        self::assertSame('2723489000', $result->isbn);
    }

    /**
     * Teste isOneShot=false quand seriesInfo est present.
     */
    public function testResolveLookupIsOneShotViaSeriesInfo(): void
    {
        $response = $this->createStub(ResponseInterface::class);
        $response->method('toArray')->willReturn([
            'items' => [
                [
                    'volumeInfo' => [
                        'seriesInfo' => ['volumeSeries' => []],
                        'title' => 'Test Series',
                    ],
                ],
            ],
        ]);

        $result = $this->provider->resolveLookup($response);

        self::assertNotNull($result);
        self::assertFalse($result->isOneShot);
    }

    /**
     * Teste extractIsbn ignore les identifiants qui ne sont pas des tableaux.
     */
    public function testResolveLookupExtractIsbnSkipsNonArrayIdentifier(): void
    {
        $response = $this->createStub(ResponseInterface::class);
        $response->method('toArray')->willReturn([
            'items' => [
                [
                    'volumeInfo' => [
                        'industryIdentifiers' => [
                            'not-an-array',
                            42,
                            null,
                        ],
                        'title' => 'Test',
                    ],
                ],
            ],
        ]);

        $result = $this->provider->resolveLookup($response);

        self::assertNotNull($result);
        self::assertNull($result->isbn);
    }

    /**
     * Teste extractIsbn retourne null quand aucun identifiant n'est ISBN_13 ni ISBN_10.
     */
    public function testResolveLookupExtractIsbnNoIsbnTypeReturnsNull(): void
    {
        $response = $this->createStub(ResponseInterface::class);
        $response->method('toArray')->willReturn([
            'items' => [
                [
                    'volumeInfo' => [
                        'industryIdentifiers' => [
                            ['identifier' => 'SOME_ID', 'type' => 'OTHER'],
                            ['identifier' => 'ANOTHER', 'type' => 'ISSN'],
                        ],
                        'title' => 'Test',
                    ],
                ],
            ],
        ]);

        $result = $this->provider->resolveLookup($response);

        self::assertNotNull($result);
        self::assertNull($result->isbn);
    }

    /**
     * Teste mergeItems ignore les items dont volumeInfo n'est pas un tableau.
     */
    public function testResolveLookupMergeItemsSkipsNonArrayVolumeInfo(): void
    {
        $response = $this->createStub(ResponseInterface::class);
        $response->method('toArray')->willReturn([
            'items' => [
                [
                    'volumeInfo' => 'not-an-array',
                ],
                [
                    'volumeInfo' => [
                        'title' => 'Valid Item',
                    ],
                ],
            ],
        ]);

        $result = $this->provider->resolveLookup($response);

        self::assertNotNull($result);
        self::assertSame('Valid Item', $result->title);
    }

    /**
     * Teste mergeItems retourne thumbnail null quand imageLinks est null.
     */
    public function testResolveLookupImageLinksNullReturnsThumbnailNull(): void
    {
        $response = $this->createStub(ResponseInterface::class);
        $response->method('toArray')->willReturn([
            'items' => [
                [
                    'volumeInfo' => [
                        'imageLinks' => null,
                        'title' => 'Test',
                    ],
                ],
            ],
        ]);

        $result = $this->provider->resolveLookup($response);

        self::assertNotNull($result);
        self::assertNull($result->thumbnail);
    }

    /**
     * Teste mergeItems arrete l'iteration quand tous les champs principaux sont remplis.
     */
    public function testResolveLookupMergeItemsBreaksWhenAllFieldsComplete(): void
    {
        $response = $this->createStub(ResponseInterface::class);
        $response->method('toArray')->willReturn([
            'items' => [
                [
                    'volumeInfo' => [
                        'authors' => ['Author'],
                        'description' => 'Desc',
                        'imageLinks' => ['thumbnail' => 'https://example.com/img.jpg'],
                        'publishedDate' => '2020',
                        'publisher' => 'Publisher',
                        'title' => 'Complete',
                    ],
                ],
                [
                    'volumeInfo' => [
                        'title' => 'Should Not Override',
                    ],
                ],
            ],
        ]);

        $result = $this->provider->resolveLookup($response);

        self::assertNotNull($result);
        self::assertSame('Complete', $result->title);
        self::assertSame('Author', $result->authors);
        self::assertSame('Desc', $result->description);
        self::assertSame('2020', $result->publishedDate);
        self::assertSame('Publisher', $result->publisher);
    }

    /**
     * Teste l'extraction de l'ISBN depuis les identifiants.
     */
    public function testResolveLookupExtractsIsbn(): void
    {
        $response = $this->createStub(ResponseInterface::class);
        $response->method('toArray')->willReturn([
            'items' => [
                [
                    'volumeInfo' => [
                        'industryIdentifiers' => [
                            ['identifier' => '2723489000', 'type' => 'ISBN_10'],
                            ['identifier' => '9782723489003', 'type' => 'ISBN_13'],
                        ],
                        'title' => 'Test',
                    ],
                ],
            ],
        ]);

        $result = $this->provider->resolveLookup($response);

        self::assertNotNull($result);
        self::assertSame('9782723489003', $result->isbn);
    }

    /**
     * Teste que prepareMultipleLookup envoie la meme requete que prepareLookup.
     */
    public function testPrepareMultipleLookupSendsRequest(): void
    {
        $response = $this->createStub(ResponseInterface::class);
        $httpClient = $this->createHttpClientMock();

        $httpClient->expects(self::once())
            ->method('request')
            ->with(
                'GET',
                'https://www.googleapis.com/books/v1/volumes',
                self::callback(static fn (array $options): bool => 'Naruto' === $options['query']['q']
                    && 40 === $options['query']['maxResults']),
            )
            ->willReturn($response);

        $result = $this->provider->prepareMultipleLookup('Naruto', ComicType::MANGA, 5);

        self::assertSame($response, $result);
    }

    /**
     * Teste resolveMultipleLookup regroupe les items par titre distinct.
     */
    public function testResolveMultipleLookupGroupsByTitle(): void
    {
        $response = $this->createStub(ResponseInterface::class);
        $response->method('toArray')->willReturn([
            'items' => [
                [
                    'volumeInfo' => [
                        'authors' => ['Oda'],
                        'description' => 'Pirates',
                        'title' => 'One Piece',
                    ],
                ],
                [
                    'volumeInfo' => [
                        'authors' => ['Oda'],
                        'publishedDate' => '1997',
                        'title' => 'One Piece',
                    ],
                ],
                [
                    'volumeInfo' => [
                        'authors' => ['Kishimoto'],
                        'description' => 'Ninjas',
                        'title' => 'Naruto',
                    ],
                ],
            ],
        ]);

        $results = $this->provider->resolveMultipleLookup($response);

        self::assertCount(2, $results);
        self::assertSame('One Piece', $results[0]->title);
        self::assertSame('Oda', $results[0]->authors);
        self::assertSame('Pirates', $results[0]->description);
        self::assertSame('1997', $results[0]->publishedDate);
        self::assertSame('Naruto', $results[1]->title);
        self::assertSame('Kishimoto', $results[1]->authors);
    }

    /**
     * Teste resolveMultipleLookup retourne un tableau vide quand pas d'items.
     */
    public function testResolveMultipleLookupNoItems(): void
    {
        $response = $this->createStub(ResponseInterface::class);
        $response->method('toArray')->willReturn(['items' => []]);

        $results = $this->provider->resolveMultipleLookup($response);

        self::assertSame([], $results);
    }

    /**
     * Teste resolveMultipleLookup gere les erreurs de transport.
     */
    public function testResolveMultipleLookupTransportException(): void
    {
        $response = $this->createStub(ResponseInterface::class);
        $exception = new class('Timeout') extends \RuntimeException implements TransportExceptionInterface {};
        $response->method('toArray')->willThrowException($exception);

        $this->createLoggerMock();

        $results = $this->provider->resolveMultipleLookup($response);

        self::assertSame([], $results);

        $apiMessage = $this->provider->getLastApiMessage();
        self::assertSame('error', $apiMessage->status);
    }

    /**
     * Recree le provider avec un mock httpClient pour les tests d'attente.
     */
    private function createHttpClientMock(): HttpClientInterface&MockObject
    {
        $mock = $this->createMock(HttpClientInterface::class);
        $this->httpClient = $mock;
        $this->provider = new GoogleBooksLookup('test-api-key', $this->httpClient, $this->logger);

        return $mock;
    }

    /**
     * Recree le provider avec un mock logger pour les tests d'attente.
     */
    private function createLoggerMock(): LoggerInterface&MockObject
    {
        $mock = $this->createMock(LoggerInterface::class);
        $this->logger = $mock;
        $this->provider = new GoogleBooksLookup('test-api-key', $this->httpClient, $this->logger);

        return $mock;
    }
}

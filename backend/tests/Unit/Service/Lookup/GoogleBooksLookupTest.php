<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Lookup;

use App\Enum\ComicType;
use App\Service\Lookup\GoogleBooksLookup;
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
    private HttpClientInterface&MockObject $httpClient;
    private LoggerInterface&MockObject $logger;
    private GoogleBooksLookup $provider;

    protected function setUp(): void
    {
        $this->httpClient = $this->createMock(HttpClientInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);

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
        self::assertTrue($this->provider->supports('isbn', null));
        self::assertTrue($this->provider->supports('title', null));
        self::assertTrue($this->provider->supports('isbn', ComicType::MANGA));
    }

    /**
     * Teste que supports retourne false pour les modes non supportes.
     */
    public function testDoesNotSupportOtherModes(): void
    {
        self::assertFalse($this->provider->supports('author', null));
        self::assertFalse($this->provider->supports('publisher', null));
    }

    /**
     * Teste que prepareLookup en mode isbn envoie la bonne requete.
     */
    public function testPrepareLookupIsbnMode(): void
    {
        $response = $this->createMock(ResponseInterface::class);

        $this->httpClient->expects(self::once())
            ->method('request')
            ->with(
                'GET',
                'https://www.googleapis.com/books/v1/volumes',
                self::callback(static function (array $options): bool {
                    return 'isbn:9782723489' === $options['query']['q']
                        && 10 === $options['query']['maxResults']
                        && 'test-api-key' === $options['query']['key']
                        && 10 === $options['timeout'];
                }),
            )
            ->willReturn($response);

        $result = $this->provider->prepareLookup('9782723489', null, 'isbn');

        self::assertSame($response, $result);
    }

    /**
     * Teste que prepareLookup en mode title envoie la bonne requete.
     */
    public function testPrepareLookupTitleMode(): void
    {
        $response = $this->createMock(ResponseInterface::class);

        $this->httpClient->expects(self::once())
            ->method('request')
            ->with(
                'GET',
                'https://www.googleapis.com/books/v1/volumes',
                self::callback(static function (array $options): bool {
                    return 'One Piece' === $options['query']['q']
                        && 10 === $options['query']['maxResults'];
                }),
            )
            ->willReturn($response);

        $this->provider->prepareLookup('One Piece', ComicType::MANGA, 'title');
    }

    /**
     * Teste que prepareLookup sans cle API n'inclut pas le parametre key.
     */
    public function testPrepareLookupWithoutApiKey(): void
    {
        $provider = new GoogleBooksLookup('', $this->httpClient, $this->logger);
        $response = $this->createMock(ResponseInterface::class);

        $this->httpClient->expects(self::once())
            ->method('request')
            ->with(
                'GET',
                self::anything(),
                self::callback(static function (array $options): bool {
                    return !isset($options['query']['key']);
                }),
            )
            ->willReturn($response);

        $provider->prepareLookup('test', null, 'isbn');
    }

    /**
     * Teste resolveLookup avec des donnees valides.
     */
    public function testResolveLookupSuccess(): void
    {
        $response = $this->createMock(ResponseInterface::class);
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
        $response = $this->createMock(ResponseInterface::class);
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
        $response = $this->createMock(ResponseInterface::class);
        $response->method('toArray')->willReturn(['items' => []]);

        $result = $this->provider->resolveLookup($response);

        self::assertNull($result);

        $apiMessage = $this->provider->getLastApiMessage();
        self::assertNotNull($apiMessage);
        self::assertSame('not_found', $apiMessage['status']);
    }

    /**
     * Teste resolveLookup retourne null quand le champ items est absent.
     */
    public function testResolveLookupNoItemsKey(): void
    {
        $response = $this->createMock(ResponseInterface::class);
        $response->method('toArray')->willReturn(['totalItems' => 0]);

        $result = $this->provider->resolveLookup($response);

        self::assertNull($result);

        $apiMessage = $this->provider->getLastApiMessage();
        self::assertSame('not_found', $apiMessage['status']);
    }

    /**
     * Teste resolveLookup en cas d'erreur de transport.
     */
    public function testResolveLookupTransportException(): void
    {
        $response = $this->createMock(ResponseInterface::class);
        $exception = new class ('Connection timeout') extends \RuntimeException implements TransportExceptionInterface {};
        $response->method('toArray')->willThrowException($exception);

        $this->logger->expects(self::once())->method('error');

        $result = $this->provider->resolveLookup($response);

        self::assertNull($result);

        $apiMessage = $this->provider->getLastApiMessage();
        self::assertSame('error', $apiMessage['status']);
        self::assertSame('Erreur de connexion', $apiMessage['message']);
    }

    /**
     * Teste resolveLookup en cas d'erreur HTTP 429.
     */
    public function testResolveLookupRateLimited429(): void
    {
        $innerResponse = $this->createMock(ResponseInterface::class);
        $innerResponse->method('getStatusCode')->willReturn(429);

        $exception = new class ('Rate limited', $innerResponse) extends \RuntimeException implements ClientExceptionInterface {
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

        $response = $this->createMock(ResponseInterface::class);
        $response->method('toArray')->willThrowException($exception);

        $result = $this->provider->resolveLookup($response);

        self::assertNull($result);

        $apiMessage = $this->provider->getLastApiMessage();
        self::assertSame('rate_limited', $apiMessage['status']);
    }

    /**
     * Teste resolveLookup en cas d'erreur HTTP autre que 429.
     */
    public function testResolveLookupOtherHttpError(): void
    {
        $innerResponse = $this->createMock(ResponseInterface::class);
        $innerResponse->method('getStatusCode')->willReturn(500);

        $exception = new class ('Server error', $innerResponse) extends \RuntimeException implements ClientExceptionInterface {
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

        $response = $this->createMock(ResponseInterface::class);
        $response->method('toArray')->willThrowException($exception);

        $result = $this->provider->resolveLookup($response);

        self::assertNull($result);

        $apiMessage = $this->provider->getLastApiMessage();
        self::assertSame('error', $apiMessage['status']);
        self::assertStringContainsString('500', $apiMessage['message']);
    }

    /**
     * Teste resolveLookup en cas d'erreur de decodage JSON.
     */
    public function testResolveLookupDecodingException(): void
    {
        $exception = new class ('Invalid JSON') extends \RuntimeException implements DecodingExceptionInterface {};

        $response = $this->createMock(ResponseInterface::class);
        $response->method('toArray')->willThrowException($exception);

        $this->logger->expects(self::once())->method('error');

        $result = $this->provider->resolveLookup($response);

        self::assertNull($result);

        $apiMessage = $this->provider->getLastApiMessage();
        self::assertSame('error', $apiMessage['status']);
        self::assertSame('Réponse invalide', $apiMessage['message']);
    }

    /**
     * Teste que resolveLookup fusionne les donnees de plusieurs items.
     */
    public function testResolveLookupMergesMultipleItems(): void
    {
        $response = $this->createMock(ResponseInterface::class);
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
     * Teste l'extraction de l'ISBN depuis les identifiants.
     */
    public function testResolveLookupExtractsIsbn(): void
    {
        $response = $this->createMock(ResponseInterface::class);
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
}

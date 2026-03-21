<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Lookup\Provider;

use App\Enum\ComicType;
use App\Enum\LookupMode;
use App\Service\Lookup\Provider\OpenLibraryLookup;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\DecodingExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * Tests unitaires pour OpenLibraryLookup.
 */
final class OpenLibraryLookupTest extends TestCase
{
    private HttpClientInterface $httpClient;
    private LoggerInterface $logger;
    private OpenLibraryLookup $provider;

    protected function setUp(): void
    {
        $this->httpClient = $this->createStub(HttpClientInterface::class);
        $this->logger = $this->createStub(LoggerInterface::class);

        $this->provider = new OpenLibraryLookup(
            $this->httpClient,
            $this->logger,
        );
    }

    /**
     * Teste que getFieldPriority retourne toujours 80.
     */
    public function testGetFieldPriorityReturns80(): void
    {
        self::assertSame(80, $this->provider->getFieldPriority('title'));
        self::assertSame(80, $this->provider->getFieldPriority('authors'));
        self::assertSame(80, $this->provider->getFieldPriority('thumbnail', ComicType::BD));
    }

    /**
     * Teste que getName retourne 'open_library'.
     */
    public function testGetNameReturnsOpenLibrary(): void
    {
        self::assertSame('open_library', $this->provider->getName());
    }

    /**
     * Teste que supports retourne true uniquement pour isbn.
     */
    public function testSupportsOnlyIsbn(): void
    {
        self::assertTrue($this->provider->supports(LookupMode::ISBN, null));
        self::assertTrue($this->provider->supports(LookupMode::ISBN, ComicType::MANGA));
    }

    /**
     * Teste que supports retourne false pour title.
     */
    public function testDoesNotSupportTitle(): void
    {
        self::assertFalse($this->provider->supports(LookupMode::TITLE, null));
    }

    /**
     * Teste que prepareLookup envoie la bonne requete.
     */
    public function testPrepareLookupSendsCorrectRequest(): void
    {
        $response = $this->createStub(ResponseInterface::class);
        $httpClient = $this->createHttpClientMock();

        $httpClient->expects(self::once())
            ->method('request')
            ->with(
                'GET',
                'https://openlibrary.org/isbn/9782723489.json',
                self::callback(static fn (array $options): bool => 10 === $options['timeout']),
            )
            ->willReturn($response);

        $result = $this->provider->prepareLookup('9782723489', null, LookupMode::ISBN);

        self::assertSame($response, $result);
    }

    /**
     * Teste resolveLookup avec des donnees valides et des auteurs.
     */
    public function testResolveLookupSuccessWithAuthors(): void
    {
        $mainResponse = $this->createStub(ResponseInterface::class);
        $mainResponse->method('getStatusCode')->willReturn(200);
        $mainResponse->method('toArray')->willReturn([
            'authors' => [
                ['key' => '/authors/OL1234A'],
            ],
            'covers' => [12345],
            'publish_date' => '1997',
            'publishers' => ['Glenat'],
            'title' => 'One Piece',
        ]);

        $authorResponse = $this->createStub(ResponseInterface::class);
        $authorResponse->method('toArray')->willReturn([
            'name' => 'Eiichiro Oda',
        ]);

        $this->createHttpClientMock()->expects(self::once())
            ->method('request')
            ->with(
                'GET',
                'https://openlibrary.org/authors/OL1234A.json',
                self::anything(),
            )
            ->willReturn($authorResponse);

        $result = $this->provider->resolveLookup($mainResponse);

        self::assertNotNull($result);
        self::assertSame('One Piece', $result->title);
        self::assertSame('Eiichiro Oda', $result->authors);
        self::assertSame('Glenat', $result->publisher);
        self::assertSame('1997', $result->publishedDate);
        self::assertSame('https://covers.openlibrary.org/b/id/12345-M.jpg', $result->thumbnail);
        self::assertSame('open_library', $result->source);
    }

    /**
     * Teste resolveLookup avec donnees sans auteurs ni couverture.
     */
    public function testResolveLookupSuccessWithoutAuthorsOrCover(): void
    {
        $response = $this->createStub(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(200);
        $response->method('toArray')->willReturn([
            'publish_date' => '2000',
            'publishers' => ['Kana'],
            'title' => 'Naruto',
        ]);

        $result = $this->provider->resolveLookup($response);

        self::assertNotNull($result);
        self::assertSame('Naruto', $result->title);
        self::assertNull($result->authors);
        self::assertNull($result->thumbnail);
        self::assertSame('Kana', $result->publisher);
    }

    /**
     * Teste resolveLookup retourne null avec code 429.
     */
    public function testResolveLookupRateLimited429(): void
    {
        $response = $this->createStub(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(429);

        $result = $this->provider->resolveLookup($response);

        self::assertNull($result);

        $apiMessage = $this->provider->getLastApiMessage();
        self::assertSame('rate_limited', $apiMessage->status);
    }

    /**
     * Teste resolveLookup retourne null avec code 404.
     */
    public function testResolveLookupNotFound404(): void
    {
        $response = $this->createStub(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(404);

        $result = $this->provider->resolveLookup($response);

        self::assertNull($result);

        $apiMessage = $this->provider->getLastApiMessage();
        self::assertSame('not_found', $apiMessage->status);
    }

    /**
     * Teste resolveLookup retourne null quand le titre est absent.
     */
    public function testResolveLookupNoTitle(): void
    {
        $response = $this->createStub(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(200);
        $response->method('toArray')->willReturn([
            'publishers' => ['Kana'],
        ]);

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
        $exception = new class('Connection failed') extends \RuntimeException implements TransportExceptionInterface {};

        $response = $this->createStub(ResponseInterface::class);
        $response->method('getStatusCode')->willThrowException($exception);

        $this->createLoggerMock()->expects(self::once())->method('error');

        $result = $this->provider->resolveLookup($response);

        self::assertNull($result);

        $apiMessage = $this->provider->getLastApiMessage();
        self::assertSame('error', $apiMessage->status);
        self::assertSame('Erreur de connexion', $apiMessage->message);
    }

    /**
     * Teste resolveLookup en cas de ClientExceptionInterface avec statut 429 (rate limited).
     */
    public function testResolveLookupClientExceptionRateLimited(): void
    {
        $innerResponse = $this->createStub(ResponseInterface::class);
        $innerResponse->method('getStatusCode')->willReturn(429);

        $exception = new class('Too Many Requests', $innerResponse) extends \RuntimeException implements ClientExceptionInterface {
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
        $response->method('getStatusCode')->willReturn(200);
        $response->method('toArray')->willThrowException($exception);

        $this->createLoggerMock()->expects(self::once())->method('warning');

        $result = $this->provider->resolveLookup($response);

        self::assertNull($result);

        $apiMessage = $this->provider->getLastApiMessage();
        self::assertSame('rate_limited', $apiMessage->status);
    }

    /**
     * Teste resolveLookup en cas de ClientExceptionInterface.
     */
    public function testResolveLookupClientException(): void
    {
        $innerResponse = $this->createStub(ResponseInterface::class);
        $innerResponse->method('getStatusCode')->willReturn(403);

        $exception = new class('Forbidden', $innerResponse) extends \RuntimeException implements ClientExceptionInterface {
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
        $response->method('getStatusCode')->willReturn(200);
        $response->method('toArray')->willThrowException($exception);

        $this->createLoggerMock()->expects(self::once())->method('warning');

        $result = $this->provider->resolveLookup($response);

        self::assertNull($result);

        $apiMessage = $this->provider->getLastApiMessage();
        self::assertSame('error', $apiMessage->status);
        self::assertStringContainsString('403', $apiMessage->message);
    }

    /**
     * Teste resolveLookup en cas de DecodingExceptionInterface.
     */
    public function testResolveLookupDecodingException(): void
    {
        $exception = new class('Invalid JSON') extends \RuntimeException implements DecodingExceptionInterface {};

        $response = $this->createStub(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(200);
        $response->method('toArray')->willThrowException($exception);

        $this->createLoggerMock()->expects(self::once())->method('error');

        $result = $this->provider->resolveLookup($response);

        self::assertNull($result);

        $apiMessage = $this->provider->getLastApiMessage();
        self::assertSame('error', $apiMessage->status);
        self::assertSame('Réponse invalide', $apiMessage->message);
    }

    /**
     * Teste que l'echec d'une sous-requete auteur est silencieusement ignore.
     */
    public function testResolveLookupAuthorSubRequestFailureSkipsSilently(): void
    {
        $mainResponse = $this->createStub(ResponseInterface::class);
        $mainResponse->method('getStatusCode')->willReturn(200);
        $mainResponse->method('toArray')->willReturn([
            'authors' => [
                ['key' => '/authors/OL_FAIL'],
            ],
            'title' => 'Test Book',
        ]);

        $exception = new class('Author network error') extends \RuntimeException implements TransportExceptionInterface {};

        $authorResponse = $this->createStub(ResponseInterface::class);
        $authorResponse->method('toArray')->willThrowException($exception);

        $httpClient = $this->createMock(HttpClientInterface::class);
        $httpClient->expects(self::once())
            ->method('request')
            ->willReturn($authorResponse);

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('debug');

        $this->provider = new OpenLibraryLookup($httpClient, $logger);

        $result = $this->provider->resolveLookup($mainResponse);

        self::assertNotNull($result);
        self::assertSame('Test Book', $result->title);
        self::assertNull($result->authors);
    }

    /**
     * Teste resolveLookup retourne not_found pour un status HTTP non-200/non-404/non-429 (ex: 500).
     */
    public function testResolveLookupNon200Non404Non429ReturnsNotFound(): void
    {
        $response = $this->createStub(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(500);

        $result = $this->provider->resolveLookup($response);

        self::assertNull($result);

        $apiMessage = $this->provider->getLastApiMessage();
        self::assertSame('not_found', $apiMessage->status);
    }

    /**
     * Teste que seul le premier editeur est utilise quand il y en a plusieurs.
     */
    public function testResolveLookupMultiplePublishersUsesFirst(): void
    {
        $response = $this->createStub(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(200);
        $response->method('toArray')->willReturn([
            'publishers' => ['Glenat', 'Kana', 'Delcourt'],
            'title' => 'Test',
        ]);

        $result = $this->provider->resolveLookup($response);

        self::assertNotNull($result);
        self::assertSame('Glenat', $result->publisher);
    }

    /**
     * Teste que seule la premiere couverture est utilisee quand il y en a plusieurs.
     */
    public function testResolveLookupMultipleCoversUsesFirst(): void
    {
        $response = $this->createStub(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(200);
        $response->method('toArray')->willReturn([
            'covers' => [11111, 22222, 33333],
            'title' => 'Test',
        ]);

        $result = $this->provider->resolveLookup($response);

        self::assertNotNull($result);
        self::assertSame('https://covers.openlibrary.org/b/id/11111-M.jpg', $result->thumbnail);
    }

    /**
     * Teste publishedDate null quand publish_date est absent.
     */
    public function testResolveLookupMissingPublishDateReturnsNull(): void
    {
        $response = $this->createStub(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(200);
        $response->method('toArray')->willReturn([
            'publishers' => ['Kana'],
            'title' => 'Test',
        ]);

        $result = $this->provider->resolveLookup($response);

        self::assertNotNull($result);
        self::assertNull($result->publishedDate);
    }

    /**
     * Teste resolveLookup avec plusieurs auteurs en parallele.
     */
    public function testResolveLookupMultipleAuthorsParallel(): void
    {
        $mainResponse = $this->createStub(ResponseInterface::class);
        $mainResponse->method('getStatusCode')->willReturn(200);
        $mainResponse->method('toArray')->willReturn([
            'authors' => [
                ['key' => '/authors/OL1A'],
                ['key' => '/authors/OL2A'],
            ],
            'title' => 'Collaboration',
        ]);

        $authorResponse1 = $this->createStub(ResponseInterface::class);
        $authorResponse1->method('toArray')->willReturn(['name' => 'Auteur Un']);

        $authorResponse2 = $this->createStub(ResponseInterface::class);
        $authorResponse2->method('toArray')->willReturn(['name' => 'Auteur Deux']);

        $this->createHttpClientMock()->expects(self::exactly(2))
            ->method('request')
            ->willReturnCallback(static function (string $method, string $url) use ($authorResponse1, $authorResponse2): ResponseInterface {
                if (\str_contains($url, 'OL1A')) {
                    return $authorResponse1;
                }

                return $authorResponse2;
            });

        $result = $this->provider->resolveLookup($mainResponse);

        self::assertNotNull($result);
        self::assertSame('Auteur Un, Auteur Deux', $result->authors);
    }

    /**
     * Recree le provider avec un mock httpClient pour les tests d'attente.
     */
    private function createHttpClientMock(): HttpClientInterface&MockObject
    {
        $mock = $this->createMock(HttpClientInterface::class);
        $this->httpClient = $mock;
        $this->provider = new OpenLibraryLookup($this->httpClient, $this->logger);

        return $mock;
    }

    /**
     * Recree le provider avec un mock logger pour les tests d'attente.
     */
    private function createLoggerMock(): LoggerInterface&MockObject
    {
        $mock = $this->createMock(LoggerInterface::class);
        $this->logger = $mock;
        $this->provider = new OpenLibraryLookup($this->httpClient, $this->logger);

        return $mock;
    }
}

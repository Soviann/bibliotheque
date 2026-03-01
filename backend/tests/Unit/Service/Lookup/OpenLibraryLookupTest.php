<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Lookup;

use App\Enum\ComicType;
use App\Service\Lookup\OpenLibraryLookup;
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
    private HttpClientInterface&MockObject $httpClient;
    private LoggerInterface&MockObject $logger;
    private OpenLibraryLookup $provider;

    protected function setUp(): void
    {
        $this->httpClient = $this->createMock(HttpClientInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);

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
        self::assertTrue($this->provider->supports('isbn', null));
        self::assertTrue($this->provider->supports('isbn', ComicType::MANGA));
    }

    /**
     * Teste que supports retourne false pour title et autres modes.
     */
    public function testDoesNotSupportTitleOrOtherModes(): void
    {
        self::assertFalse($this->provider->supports('title', null));
        self::assertFalse($this->provider->supports('author', null));
    }

    /**
     * Teste que prepareLookup envoie la bonne requete.
     */
    public function testPrepareLookupSendsCorrectRequest(): void
    {
        $response = $this->createMock(ResponseInterface::class);

        $this->httpClient->expects(self::once())
            ->method('request')
            ->with(
                'GET',
                'https://openlibrary.org/isbn/9782723489.json',
                self::callback(static function (array $options): bool {
                    return 10 === $options['timeout'];
                }),
            )
            ->willReturn($response);

        $result = $this->provider->prepareLookup('9782723489', null, 'isbn');

        self::assertSame($response, $result);
    }

    /**
     * Teste resolveLookup avec des donnees valides et des auteurs.
     */
    public function testResolveLookupSuccessWithAuthors(): void
    {
        $mainResponse = $this->createMock(ResponseInterface::class);
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

        $authorResponse = $this->createMock(ResponseInterface::class);
        $authorResponse->method('toArray')->willReturn([
            'name' => 'Eiichiro Oda',
        ]);

        $this->httpClient->expects(self::once())
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
        $response = $this->createMock(ResponseInterface::class);
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
        $response = $this->createMock(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(429);

        $result = $this->provider->resolveLookup($response);

        self::assertNull($result);

        $apiMessage = $this->provider->getLastApiMessage();
        self::assertSame('rate_limited', $apiMessage['status']);
    }

    /**
     * Teste resolveLookup retourne null avec code 404.
     */
    public function testResolveLookupNotFound404(): void
    {
        $response = $this->createMock(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(404);

        $result = $this->provider->resolveLookup($response);

        self::assertNull($result);

        $apiMessage = $this->provider->getLastApiMessage();
        self::assertSame('not_found', $apiMessage['status']);
    }

    /**
     * Teste resolveLookup retourne null quand le titre est absent.
     */
    public function testResolveLookupNoTitle(): void
    {
        $response = $this->createMock(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(200);
        $response->method('toArray')->willReturn([
            'publishers' => ['Kana'],
        ]);

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
        $exception = new class ('Connection failed') extends \RuntimeException implements TransportExceptionInterface {};

        $response = $this->createMock(ResponseInterface::class);
        $response->method('getStatusCode')->willThrowException($exception);

        $this->logger->expects(self::once())->method('error');

        $result = $this->provider->resolveLookup($response);

        self::assertNull($result);

        $apiMessage = $this->provider->getLastApiMessage();
        self::assertSame('error', $apiMessage['status']);
        self::assertSame('Erreur de connexion', $apiMessage['message']);
    }

    /**
     * Teste resolveLookup en cas de ClientExceptionInterface.
     */
    public function testResolveLookupClientException(): void
    {
        $innerResponse = $this->createMock(ResponseInterface::class);
        $innerResponse->method('getStatusCode')->willReturn(403);

        $exception = new class ('Forbidden', $innerResponse) extends \RuntimeException implements ClientExceptionInterface {
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
        $response->method('getStatusCode')->willReturn(200);
        $response->method('toArray')->willThrowException($exception);

        $this->logger->expects(self::once())->method('warning');

        $result = $this->provider->resolveLookup($response);

        self::assertNull($result);

        $apiMessage = $this->provider->getLastApiMessage();
        self::assertSame('error', $apiMessage['status']);
        self::assertStringContainsString('403', $apiMessage['message']);
    }

    /**
     * Teste resolveLookup en cas de DecodingExceptionInterface.
     */
    public function testResolveLookupDecodingException(): void
    {
        $exception = new class ('Invalid JSON') extends \RuntimeException implements DecodingExceptionInterface {};

        $response = $this->createMock(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(200);
        $response->method('toArray')->willThrowException($exception);

        $this->logger->expects(self::once())->method('error');

        $result = $this->provider->resolveLookup($response);

        self::assertNull($result);

        $apiMessage = $this->provider->getLastApiMessage();
        self::assertSame('error', $apiMessage['status']);
        self::assertSame('Réponse invalide', $apiMessage['message']);
    }

    /**
     * Teste que l'echec d'une sous-requete auteur est silencieusement ignore.
     */
    public function testResolveLookupAuthorSubRequestFailureSkipsSilently(): void
    {
        $mainResponse = $this->createMock(ResponseInterface::class);
        $mainResponse->method('getStatusCode')->willReturn(200);
        $mainResponse->method('toArray')->willReturn([
            'authors' => [
                ['key' => '/authors/OL_FAIL'],
            ],
            'title' => 'Test Book',
        ]);

        $exception = new class ('Author network error') extends \RuntimeException implements TransportExceptionInterface {};

        $authorResponse = $this->createMock(ResponseInterface::class);
        $authorResponse->method('toArray')->willThrowException($exception);

        $this->httpClient->expects(self::once())
            ->method('request')
            ->willReturn($authorResponse);

        $this->logger->expects(self::once())->method('debug');

        $result = $this->provider->resolveLookup($mainResponse);

        self::assertNotNull($result);
        self::assertSame('Test Book', $result->title);
        self::assertNull($result->authors);
    }

    /**
     * Teste resolveLookup avec plusieurs auteurs en parallele.
     */
    public function testResolveLookupMultipleAuthorsParallel(): void
    {
        $mainResponse = $this->createMock(ResponseInterface::class);
        $mainResponse->method('getStatusCode')->willReturn(200);
        $mainResponse->method('toArray')->willReturn([
            'authors' => [
                ['key' => '/authors/OL1A'],
                ['key' => '/authors/OL2A'],
            ],
            'title' => 'Collaboration',
        ]);

        $authorResponse1 = $this->createMock(ResponseInterface::class);
        $authorResponse1->method('toArray')->willReturn(['name' => 'Auteur Un']);

        $authorResponse2 = $this->createMock(ResponseInterface::class);
        $authorResponse2->method('toArray')->willReturn(['name' => 'Auteur Deux']);

        $this->httpClient->expects(self::exactly(2))
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
}

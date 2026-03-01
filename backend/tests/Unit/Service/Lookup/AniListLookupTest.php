<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Lookup;

use App\Enum\ComicType;
use App\Service\Lookup\AniListLookup;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\DecodingExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * Tests unitaires pour AniListLookup.
 */
final class AniListLookupTest extends TestCase
{
    private HttpClientInterface&MockObject $httpClient;
    private LoggerInterface&MockObject $logger;
    private AniListLookup $provider;

    protected function setUp(): void
    {
        $this->httpClient = $this->createMock(HttpClientInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->provider = new AniListLookup(
            $this->httpClient,
            $this->logger,
        );
    }

    /**
     * Teste que getFieldPriority retourne 200 pour isOneShot et thumbnail quand type=MANGA.
     */
    public function testGetFieldPriorityReturns200ForMangaSpecificFields(): void
    {
        self::assertSame(200, $this->provider->getFieldPriority('isOneShot', ComicType::MANGA));
        self::assertSame(200, $this->provider->getFieldPriority('thumbnail', ComicType::MANGA));
    }

    /**
     * Teste que getFieldPriority retourne 60 pour les autres cas.
     */
    public function testGetFieldPriorityReturns60Otherwise(): void
    {
        self::assertSame(60, $this->provider->getFieldPriority('title', ComicType::MANGA));
        self::assertSame(60, $this->provider->getFieldPriority('authors', ComicType::MANGA));
        self::assertSame(60, $this->provider->getFieldPriority('isOneShot', ComicType::BD));
        self::assertSame(60, $this->provider->getFieldPriority('thumbnail', null));
        self::assertSame(60, $this->provider->getFieldPriority('description'));
    }

    /**
     * Teste que getName retourne 'anilist'.
     */
    public function testGetNameReturnsAnilist(): void
    {
        self::assertSame('anilist', $this->provider->getName());
    }

    /**
     * Teste que supports retourne true uniquement pour mode=title et type=MANGA.
     */
    public function testSupportsOnlyTitleModeWithMangaType(): void
    {
        self::assertTrue($this->provider->supports('title', ComicType::MANGA));
    }

    /**
     * Teste que supports retourne false pour les cas non supportes.
     */
    public function testDoesNotSupportOtherCombinations(): void
    {
        self::assertFalse($this->provider->supports('isbn', ComicType::MANGA));
        self::assertFalse($this->provider->supports('title', ComicType::BD));
        self::assertFalse($this->provider->supports('title', ComicType::COMICS));
        self::assertFalse($this->provider->supports('title', null));
        self::assertFalse($this->provider->supports('isbn', null));
    }

    /**
     * Teste que prepareLookup envoie une requete POST GraphQL.
     */
    public function testPrepareLookupSendsGraphqlPost(): void
    {
        $response = $this->createMock(ResponseInterface::class);

        $this->httpClient->expects(self::once())
            ->method('request')
            ->with(
                'POST',
                'https://graphql.anilist.co',
                self::callback(static function (array $options): bool {
                    return 'application/json' === $options['headers']['Content-Type']
                        && 'application/json' === $options['headers']['Accept']
                        && isset($options['json']['query'])
                        && 'One Piece' === $options['json']['variables']['search']
                        && 10 === $options['timeout'];
                }),
            )
            ->willReturn($response);

        $this->provider->prepareLookup('One Piece', ComicType::MANGA, 'title');
    }

    /**
     * Teste que prepareLookup nettoie le titre (suppression des suffixes de tome).
     */
    public function testPrepareLookupCleansTitle(): void
    {
        $response = $this->createMock(ResponseInterface::class);

        $this->httpClient->expects(self::once())
            ->method('request')
            ->with(
                'POST',
                self::anything(),
                self::callback(static function (array $options): bool {
                    return 'One Piece' === $options['json']['variables']['search'];
                }),
            )
            ->willReturn($response);

        $this->provider->prepareLookup('One Piece - Tome 42', ComicType::MANGA, 'title');
    }

    /**
     * Teste resolveLookup avec des donnees valides.
     */
    public function testResolveLookupSuccess(): void
    {
        $response = $this->createMock(ResponseInterface::class);
        $response->method('toArray')->willReturn([
            'data' => [
                'Media' => [
                    'coverImage' => [
                        'extraLarge' => 'https://anilist.co/img/cover-xl.jpg',
                        'large' => 'https://anilist.co/img/cover-l.jpg',
                    ],
                    'description' => '<p>A pirate story</p>',
                    'format' => 'MANGA',
                    'staff' => [
                        'edges' => [
                            [
                                'node' => ['name' => ['full' => 'Eiichiro Oda']],
                                'role' => 'Story & Art',
                            ],
                        ],
                    ],
                    'startDate' => ['day' => 22, 'month' => 7, 'year' => 1997],
                    'status' => 'RELEASING',
                    'title' => [
                        'english' => 'One Piece',
                        'native' => 'ONE PIECE',
                        'romaji' => 'ONE PIECE',
                    ],
                    'volumes' => null,
                ],
            ],
        ]);

        $result = $this->provider->resolveLookup($response);

        self::assertNotNull($result);
        self::assertSame('One Piece', $result->title);
        self::assertSame('Eiichiro Oda', $result->authors);
        self::assertSame('https://anilist.co/img/cover-xl.jpg', $result->thumbnail);
        self::assertSame('1997-07-22', $result->publishedDate);
        self::assertSame('A pirate story', $result->description);
        self::assertFalse($result->isOneShot);
        self::assertSame('anilist', $result->source);
    }

    /**
     * Teste le fallback du titre : english -> romaji -> native.
     */
    public function testResolveLookupTitleFallbackToRomaji(): void
    {
        $response = $this->createMock(ResponseInterface::class);
        $response->method('toArray')->willReturn([
            'data' => [
                'Media' => [
                    'coverImage' => [],
                    'description' => null,
                    'format' => 'MANGA',
                    'staff' => ['edges' => []],
                    'startDate' => ['day' => null, 'month' => null, 'year' => null],
                    'status' => 'FINISHED',
                    'title' => [
                        'english' => null,
                        'native' => 'Kimetsu no Yaiba JP',
                        'romaji' => 'Kimetsu no Yaiba',
                    ],
                    'volumes' => 23,
                ],
            ],
        ]);

        $result = $this->provider->resolveLookup($response);

        self::assertNotNull($result);
        self::assertSame('Kimetsu no Yaiba', $result->title);
    }

    /**
     * Teste isOneShot=true quand format=ONE_SHOT.
     */
    public function testResolveLookupOneShotByFormat(): void
    {
        $response = $this->createMock(ResponseInterface::class);
        $response->method('toArray')->willReturn([
            'data' => [
                'Media' => [
                    'coverImage' => [],
                    'description' => null,
                    'format' => 'ONE_SHOT',
                    'staff' => ['edges' => []],
                    'startDate' => [],
                    'status' => 'FINISHED',
                    'title' => ['english' => 'Test One Shot', 'native' => null, 'romaji' => null],
                    'volumes' => 1,
                ],
            ],
        ]);

        $result = $this->provider->resolveLookup($response);

        self::assertNotNull($result);
        self::assertTrue($result->isOneShot);
    }

    /**
     * Teste isOneShot=true quand volumes=1 et status=FINISHED.
     */
    public function testResolveLookupOneShotByVolumesAndStatus(): void
    {
        $response = $this->createMock(ResponseInterface::class);
        $response->method('toArray')->willReturn([
            'data' => [
                'Media' => [
                    'coverImage' => [],
                    'description' => null,
                    'format' => 'MANGA',
                    'staff' => ['edges' => []],
                    'startDate' => [],
                    'status' => 'FINISHED',
                    'title' => ['english' => 'Single Volume', 'native' => null, 'romaji' => null],
                    'volumes' => 1,
                ],
            ],
        ]);

        $result = $this->provider->resolveLookup($response);

        self::assertNotNull($result);
        self::assertTrue($result->isOneShot);
    }

    /**
     * Teste que les auteurs sont filtres par role et dedupliques.
     */
    public function testResolveLookupFiltersAuthorsByRole(): void
    {
        $response = $this->createMock(ResponseInterface::class);
        $response->method('toArray')->willReturn([
            'data' => [
                'Media' => [
                    'coverImage' => [],
                    'description' => null,
                    'format' => 'MANGA',
                    'staff' => [
                        'edges' => [
                            [
                                'node' => ['name' => ['full' => 'Author A']],
                                'role' => 'Story',
                            ],
                            [
                                'node' => ['name' => ['full' => 'Author B']],
                                'role' => 'Art',
                            ],
                            [
                                'node' => ['name' => ['full' => 'Editor C']],
                                'role' => 'Editor',
                            ],
                            [
                                'node' => ['name' => ['full' => 'Author A']],
                                'role' => 'Original Creator',
                            ],
                        ],
                    ],
                    'startDate' => [],
                    'status' => 'RELEASING',
                    'title' => ['english' => 'Test', 'native' => null, 'romaji' => null],
                    'volumes' => null,
                ],
            ],
        ]);

        $result = $this->provider->resolveLookup($response);

        self::assertNotNull($result);
        self::assertSame('Author A, Author B', $result->authors);
    }

    /**
     * Teste resolveLookup retourne null quand Media est absent.
     */
    public function testResolveLookupNoMedia(): void
    {
        $response = $this->createMock(ResponseInterface::class);
        $response->method('toArray')->willReturn([
            'data' => [
                'Media' => null,
            ],
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
        $exception = new class ('Connection timeout') extends \RuntimeException implements TransportExceptionInterface {};

        $response = $this->createMock(ResponseInterface::class);
        $response->method('toArray')->willThrowException($exception);

        $this->logger->expects(self::once())->method('error');

        $result = $this->provider->resolveLookup($response);

        self::assertNull($result);

        $apiMessage = $this->provider->getLastApiMessage();
        self::assertSame('error', $apiMessage['status']);
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
    }

    /**
     * Teste le format de date avec annee seulement.
     */
    public function testResolveLookupDateFormatYearOnly(): void
    {
        $response = $this->createMock(ResponseInterface::class);
        $response->method('toArray')->willReturn([
            'data' => [
                'Media' => [
                    'coverImage' => [],
                    'description' => null,
                    'format' => 'MANGA',
                    'staff' => ['edges' => []],
                    'startDate' => ['day' => null, 'month' => null, 'year' => 2020],
                    'status' => 'RELEASING',
                    'title' => ['english' => 'Test', 'native' => null, 'romaji' => null],
                    'volumes' => null,
                ],
            ],
        ]);

        $result = $this->provider->resolveLookup($response);

        self::assertNotNull($result);
        self::assertSame('2020', $result->publishedDate);
    }

    /**
     * Teste le format de date avec annee et mois.
     */
    public function testResolveLookupDateFormatYearMonth(): void
    {
        $response = $this->createMock(ResponseInterface::class);
        $response->method('toArray')->willReturn([
            'data' => [
                'Media' => [
                    'coverImage' => [],
                    'description' => null,
                    'format' => 'MANGA',
                    'staff' => ['edges' => []],
                    'startDate' => ['day' => null, 'month' => 3, 'year' => 2020],
                    'status' => 'RELEASING',
                    'title' => ['english' => 'Test', 'native' => null, 'romaji' => null],
                    'volumes' => null,
                ],
            ],
        ]);

        $result = $this->provider->resolveLookup($response);

        self::assertNotNull($result);
        self::assertSame('2020-03', $result->publishedDate);
    }

    /**
     * Teste que latestPublishedIssue est extrait du nombre de volumes.
     */
    public function testResolveLookupExtractsLatestPublishedIssue(): void
    {
        $response = $this->createMock(ResponseInterface::class);
        $response->method('toArray')->willReturn([
            'data' => [
                'Media' => [
                    'coverImage' => [],
                    'description' => null,
                    'format' => 'MANGA',
                    'staff' => ['edges' => []],
                    'startDate' => [],
                    'status' => 'RELEASING',
                    'title' => ['english' => 'Test', 'native' => null, 'romaji' => null],
                    'volumes' => 25,
                ],
            ],
        ]);

        $result = $this->provider->resolveLookup($response);

        self::assertNotNull($result);
        self::assertSame(25, $result->latestPublishedIssue);
    }
}

<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Lookup;

use App\Enum\ComicType;
use App\Enum\LookupMode;
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
    private HttpClientInterface $httpClient;
    private LoggerInterface $logger;
    private AniListLookup $provider;

    protected function setUp(): void
    {
        $this->httpClient = $this->createStub(HttpClientInterface::class);
        $this->logger = $this->createStub(LoggerInterface::class);

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
        self::assertSame(60, $this->provider->getFieldPriority('thumbnail'));
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
        self::assertTrue($this->provider->supports(LookupMode::TITLE, ComicType::MANGA));
    }

    /**
     * Teste que supports retourne false pour les cas non supportes.
     */
    public function testDoesNotSupportOtherCombinations(): void
    {
        self::assertFalse($this->provider->supports(LookupMode::ISBN, ComicType::MANGA));
        self::assertFalse($this->provider->supports(LookupMode::TITLE, ComicType::BD));
        self::assertFalse($this->provider->supports(LookupMode::TITLE, ComicType::COMICS));
        self::assertFalse($this->provider->supports(LookupMode::TITLE, null));
        self::assertFalse($this->provider->supports(LookupMode::ISBN, null));
    }

    /**
     * Teste que prepareLookup envoie une requete POST GraphQL.
     */
    public function testPrepareLookupSendsGraphqlPost(): void
    {
        $response = $this->createStub(ResponseInterface::class);
        $httpClient = $this->createHttpClientMock();

        $httpClient->expects(self::once())
            ->method('request')
            ->with(
                'POST',
                'https://graphql.anilist.co',
                self::callback(static fn (array $options): bool => 'application/json' === $options['headers']['Content-Type']
                    && 'application/json' === $options['headers']['Accept']
                    && isset($options['json']['query'])
                    && 'One Piece' === $options['json']['variables']['search']
                    && 10 === $options['timeout']),
            )
            ->willReturn($response);

        $this->provider->prepareLookup('One Piece', ComicType::MANGA, LookupMode::TITLE);
    }

    /**
     * Teste que prepareLookup nettoie le titre (suppression des suffixes de tome).
     */
    public function testPrepareLookupCleansTitle(): void
    {
        $response = $this->createStub(ResponseInterface::class);
        $httpClient = $this->createHttpClientMock();

        $httpClient->expects(self::once())
            ->method('request')
            ->with(
                'POST',
                self::anything(),
                self::callback(static fn (array $options): bool => 'One Piece' === $options['json']['variables']['search']),
            )
            ->willReturn($response);

        $this->provider->prepareLookup('One Piece - Tome 42', ComicType::MANGA, LookupMode::TITLE);
    }

    /**
     * Teste resolveLookup avec des donnees valides.
     */
    public function testResolveLookupSuccess(): void
    {
        $response = $this->createStub(ResponseInterface::class);
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
        $response = $this->createStub(ResponseInterface::class);
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
        $response = $this->createStub(ResponseInterface::class);
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
        $response = $this->createStub(ResponseInterface::class);
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
        $response = $this->createStub(ResponseInterface::class);
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
        $response = $this->createStub(ResponseInterface::class);
        $response->method('toArray')->willReturn([
            'data' => [
                'Media' => null,
            ],
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
        $exception = new class('Connection timeout') extends \RuntimeException implements TransportExceptionInterface {};

        $response = $this->createStub(ResponseInterface::class);
        $response->method('toArray')->willThrowException($exception);

        $this->createLoggerMock()->expects(self::once())->method('error');

        $result = $this->provider->resolveLookup($response);

        self::assertNull($result);

        $apiMessage = $this->provider->getLastApiMessage();
        self::assertSame('error', $apiMessage->status);
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
    }

    /**
     * Teste le format de date avec annee seulement.
     */
    public function testResolveLookupDateFormatYearOnly(): void
    {
        $response = $this->createStub(ResponseInterface::class);
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
        $response = $this->createStub(ResponseInterface::class);
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
     * Teste cleanTitle avec suffixe "#N".
     */
    public function testPrepareLookupCleansHashSuffix(): void
    {
        $response = $this->createStub(ResponseInterface::class);
        $httpClient = $this->createHttpClientMock();

        $httpClient->expects(self::once())
            ->method('request')
            ->with(
                'POST',
                self::anything(),
                self::callback(static fn (array $options): bool => 'My Hero Academia' === $options['json']['variables']['search']),
            )
            ->willReturn($response);

        $this->provider->prepareLookup('My Hero Academia #25', ComicType::MANGA, LookupMode::TITLE);
    }

    /**
     * Teste cleanTitle avec suffixe "(N)".
     */
    public function testPrepareLookupCleansParenthesisNumber(): void
    {
        $response = $this->createStub(ResponseInterface::class);
        $httpClient = $this->createHttpClientMock();

        $httpClient->expects(self::once())
            ->method('request')
            ->with(
                'POST',
                self::anything(),
                self::callback(static fn (array $options): bool => 'Bleach' === $options['json']['variables']['search']),
            )
            ->willReturn($response);

        $this->provider->prepareLookup('Bleach (3)', ComicType::MANGA, LookupMode::TITLE);
    }

    /**
     * Teste cleanTitle avec prefixe "Vol.N".
     */
    public function testPrepareLookupCleansVolPrefix(): void
    {
        $response = $this->createStub(ResponseInterface::class);
        $httpClient = $this->createHttpClientMock();

        $httpClient->expects(self::once())
            ->method('request')
            ->with(
                'POST',
                self::anything(),
                self::callback(static fn (array $options): bool => 'Dragon Ball' === $options['json']['variables']['search']),
            )
            ->willReturn($response);

        $this->provider->prepareLookup('Dragon Ball Vol.5', ComicType::MANGA, LookupMode::TITLE);
    }

    /**
     * Teste cleanTitle avec un numero nu en fin de chaine.
     */
    public function testPrepareLookupCleansTrailingBareNumber(): void
    {
        $response = $this->createStub(ResponseInterface::class);
        $httpClient = $this->createHttpClientMock();

        $httpClient->expects(self::once())
            ->method('request')
            ->with(
                'POST',
                self::anything(),
                self::callback(static fn (array $options): bool => 'Naruto' === $options['json']['variables']['search']),
            )
            ->willReturn($response);

        $this->provider->prepareLookup('Naruto 42', ComicType::MANGA, LookupMode::TITLE);
    }

    /**
     * Teste le fallback thumbnail : extraLarge null → utilise large.
     */
    public function testResolveLookupThumbnailFallbackToLarge(): void
    {
        $response = $this->createStub(ResponseInterface::class);
        $response->method('toArray')->willReturn([
            'data' => [
                'Media' => [
                    'coverImage' => [
                        'extraLarge' => null,
                        'large' => 'https://anilist.co/img/cover-l.jpg',
                    ],
                    'description' => null,
                    'format' => 'MANGA',
                    'staff' => ['edges' => []],
                    'startDate' => [],
                    'status' => 'RELEASING',
                    'title' => ['english' => 'Test', 'native' => null, 'romaji' => null],
                    'volumes' => null,
                ],
            ],
        ]);

        $result = $this->provider->resolveLookup($response);

        self::assertNotNull($result);
        self::assertSame('https://anilist.co/img/cover-l.jpg', $result->thumbnail);
    }

    /**
     * Teste le fallback titre : english=null, romaji=null → utilise native.
     */
    public function testResolveLookupTitleFallbackToNative(): void
    {
        $response = $this->createStub(ResponseInterface::class);
        $response->method('toArray')->willReturn([
            'data' => [
                'Media' => [
                    'coverImage' => [],
                    'description' => null,
                    'format' => 'MANGA',
                    'staff' => ['edges' => []],
                    'startDate' => [],
                    'status' => 'RELEASING',
                    'title' => [
                        'english' => null,
                        'native' => 'ワンピース',
                        'romaji' => null,
                    ],
                    'volumes' => null,
                ],
            ],
        ]);

        $result = $this->provider->resolveLookup($response);

        self::assertNotNull($result);
        self::assertSame('ワンピース', $result->title);
    }

    /**
     * Teste que la description avec entites HTML est correctement decodee.
     */
    public function testResolveLookupDescriptionDecodesHtmlEntities(): void
    {
        $response = $this->createStub(ResponseInterface::class);
        $response->method('toArray')->willReturn([
            'data' => [
                'Media' => [
                    'coverImage' => [],
                    'description' => '<p>A story about &quot;pirates&quot; &amp; treasure&hellip;</p>',
                    'format' => 'MANGA',
                    'staff' => ['edges' => []],
                    'startDate' => [],
                    'status' => 'RELEASING',
                    'title' => ['english' => 'Test', 'native' => null, 'romaji' => null],
                    'volumes' => null,
                ],
            ],
        ]);

        $result = $this->provider->resolveLookup($response);

        self::assertNotNull($result);
        self::assertStringNotContainsString('<p>', $result->description);
        self::assertStringNotContainsString('&quot;', $result->description);
        self::assertStringContainsString('"pirates"', $result->description);
        self::assertStringContainsString('&', $result->description);
    }

    /**
     * Teste le fallback titre : tous les titres sont null.
     */
    public function testResolveLookupTitleAllNullReturnsNull(): void
    {
        $response = $this->createStub(ResponseInterface::class);
        $response->method('toArray')->willReturn([
            'data' => [
                'Media' => [
                    'coverImage' => [],
                    'description' => null,
                    'format' => 'MANGA',
                    'staff' => ['edges' => []],
                    'startDate' => [],
                    'status' => 'RELEASING',
                    'title' => [
                        'english' => null,
                        'native' => null,
                        'romaji' => null,
                    ],
                    'volumes' => null,
                ],
            ],
        ]);

        $result = $this->provider->resolveLookup($response);

        self::assertNotNull($result);
        self::assertNull($result->title);
    }

    /**
     * Teste le fallback thumbnail : les deux null → thumbnail null.
     */
    public function testResolveLookupThumbnailBothNullReturnsNull(): void
    {
        $response = $this->createStub(ResponseInterface::class);
        $response->method('toArray')->willReturn([
            'data' => [
                'Media' => [
                    'coverImage' => [
                        'extraLarge' => null,
                        'large' => null,
                    ],
                    'description' => null,
                    'format' => 'MANGA',
                    'staff' => ['edges' => []],
                    'startDate' => [],
                    'status' => 'RELEASING',
                    'title' => ['english' => 'Test', 'native' => null, 'romaji' => null],
                    'volumes' => null,
                ],
            ],
        ]);

        $result = $this->provider->resolveLookup($response);

        self::assertNotNull($result);
        self::assertNull($result->thumbnail);
    }

    /**
     * Teste isOneShot=false quand multi-volumes et en cours.
     */
    public function testResolveLookupIsOneShotFalseWhenMultiVolumeOngoing(): void
    {
        $response = $this->createStub(ResponseInterface::class);
        $response->method('toArray')->willReturn([
            'data' => [
                'Media' => [
                    'coverImage' => [],
                    'description' => null,
                    'format' => 'MANGA',
                    'staff' => ['edges' => []],
                    'startDate' => [],
                    'status' => 'RELEASING',
                    'title' => ['english' => 'Ongoing Series', 'native' => null, 'romaji' => null],
                    'volumes' => 50,
                ],
            ],
        ]);

        $result = $this->provider->resolveLookup($response);

        self::assertNotNull($result);
        self::assertFalse($result->isOneShot);
    }

    /**
     * Teste latestPublishedIssue null quand volumes est null.
     */
    public function testResolveLookupLatestPublishedIssueNullWhenVolumesNull(): void
    {
        $response = $this->createStub(ResponseInterface::class);
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
                    'volumes' => null,
                ],
            ],
        ]);

        $result = $this->provider->resolveLookup($response);

        self::assertNotNull($result);
        self::assertNull($result->latestPublishedIssue);
    }

    /**
     * Teste extractAuthors avec un node manquant dans les donnees staff.
     */
    public function testResolveLookupExtractAuthorsWithMissingNodeData(): void
    {
        $response = $this->createStub(ResponseInterface::class);
        $response->method('toArray')->willReturn([
            'data' => [
                'Media' => [
                    'coverImage' => [],
                    'description' => null,
                    'format' => 'MANGA',
                    'staff' => [
                        'edges' => [
                            [
                                'node' => null,
                                'role' => 'Story',
                            ],
                            [
                                'node' => ['name' => null],
                                'role' => 'Art',
                            ],
                            [
                                'node' => ['name' => ['full' => null]],
                                'role' => 'Story & Art',
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
        self::assertNull($result->authors);
    }

    /**
     * Teste formatDate retourne null quand year est null.
     */
    public function testResolveLookupDateFormatNullYearReturnsNull(): void
    {
        $response = $this->createStub(ResponseInterface::class);
        $response->method('toArray')->willReturn([
            'data' => [
                'Media' => [
                    'coverImage' => [],
                    'description' => null,
                    'format' => 'MANGA',
                    'staff' => ['edges' => []],
                    'startDate' => ['day' => 1, 'month' => 5, 'year' => null],
                    'status' => 'RELEASING',
                    'title' => ['english' => 'Test', 'native' => null, 'romaji' => null],
                    'volumes' => null,
                ],
            ],
        ]);

        $result = $this->provider->resolveLookup($response);

        self::assertNotNull($result);
        self::assertNull($result->publishedDate);
    }

    /**
     * Teste que latestPublishedIssue est extrait du nombre de volumes.
     */
    public function testResolveLookupExtractsLatestPublishedIssue(): void
    {
        $response = $this->createStub(ResponseInterface::class);
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

    /**
     * Teste que prepareMultipleLookup utilise la query Page avec perPage.
     */
    public function testPrepareMultipleLookupUsesPageQuery(): void
    {
        $response = $this->createStub(ResponseInterface::class);
        $httpClient = $this->createHttpClientMock();

        $httpClient->expects(self::once())
            ->method('request')
            ->with(
                'POST',
                'https://graphql.anilist.co',
                self::callback(static fn (array $options): bool => \str_contains((string) $options['json']['query'], 'Page')
                    && 'Naruto' === $options['json']['variables']['search']
                    && 5 === $options['json']['variables']['perPage']),
            )
            ->willReturn($response);

        $this->provider->prepareMultipleLookup('Naruto', ComicType::MANGA, 5);
    }

    /**
     * Teste resolveMultipleLookup retourne un resultat par media.
     */
    public function testResolveMultipleLookupReturnsMultipleResults(): void
    {
        $response = $this->createStub(ResponseInterface::class);
        $response->method('toArray')->willReturn([
            'data' => [
                'Page' => [
                    'media' => [
                        [
                            'coverImage' => ['extraLarge' => 'https://img1.jpg', 'large' => null],
                            'description' => 'Ninja story',
                            'format' => 'MANGA',
                            'staff' => ['edges' => [['node' => ['name' => ['full' => 'Kishimoto']], 'role' => 'Story & Art']]],
                            'startDate' => ['day' => null, 'month' => null, 'year' => 1999],
                            'status' => 'FINISHED',
                            'title' => ['english' => 'Naruto', 'native' => null, 'romaji' => null],
                            'volumes' => 72,
                        ],
                        [
                            'coverImage' => ['extraLarge' => 'https://img2.jpg', 'large' => null],
                            'description' => 'Boruto story',
                            'format' => 'MANGA',
                            'staff' => ['edges' => []],
                            'startDate' => ['day' => null, 'month' => null, 'year' => 2016],
                            'status' => 'RELEASING',
                            'title' => ['english' => 'Boruto', 'native' => null, 'romaji' => null],
                            'volumes' => null,
                        ],
                    ],
                ],
            ],
        ]);

        $results = $this->provider->resolveMultipleLookup($response);

        self::assertCount(2, $results);
        self::assertSame('Naruto', $results[0]->title);
        self::assertSame('Kishimoto', $results[0]->authors);
        self::assertSame(72, $results[0]->latestPublishedIssue);
        self::assertSame('Boruto', $results[1]->title);
    }

    /**
     * Teste resolveMultipleLookup retourne un tableau vide quand pas de media.
     */
    public function testResolveMultipleLookupNoMedia(): void
    {
        $response = $this->createStub(ResponseInterface::class);
        $response->method('toArray')->willReturn([
            'data' => [
                'Page' => [
                    'media' => [],
                ],
            ],
        ]);

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
    }

    /**
     * Recree le provider avec un mock httpClient pour les tests d'attente.
     */
    private function createHttpClientMock(): HttpClientInterface&MockObject
    {
        $mock = $this->createMock(HttpClientInterface::class);
        $this->httpClient = $mock;
        $this->provider = new AniListLookup($this->httpClient, $this->logger);

        return $mock;
    }

    /**
     * Recree le provider avec un mock logger pour les tests d'attente.
     */
    private function createLoggerMock(): LoggerInterface&MockObject
    {
        $mock = $this->createMock(LoggerInterface::class);
        $this->logger = $mock;
        $this->provider = new AniListLookup($this->httpClient, $this->logger);

        return $mock;
    }
}

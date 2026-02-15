<?php

declare(strict_types=1);

namespace App\Tests\Service\Lookup;

use App\Enum\ComicType;
use App\Service\Lookup\AniListLookup;
use App\Service\Lookup\LookupResult;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

class AniListLookupTest extends TestCase
{
    public function testGetName(): void
    {
        $provider = new AniListLookup(new MockHttpClient(), new NullLogger());

        self::assertSame('anilist', $provider->getName());
    }

    public function testGetFieldPriorityHighForMangaThumbnailAndOneShot(): void
    {
        $provider = new AniListLookup(new MockHttpClient(), new NullLogger());

        self::assertSame(200, $provider->getFieldPriority('thumbnail', ComicType::MANGA));
        self::assertSame(200, $provider->getFieldPriority('isOneShot', ComicType::MANGA));
    }

    public function testGetFieldPriorityDefaultForNonMangaOrOtherFields(): void
    {
        $provider = new AniListLookup(new MockHttpClient(), new NullLogger());

        self::assertSame(60, $provider->getFieldPriority('thumbnail', ComicType::BD));
        self::assertSame(60, $provider->getFieldPriority('isOneShot', ComicType::COMICS));
        self::assertSame(60, $provider->getFieldPriority('title', ComicType::MANGA));
        self::assertSame(60, $provider->getFieldPriority('authors', ComicType::MANGA));
        self::assertSame(60, $provider->getFieldPriority('description'));
    }

    public function testSupportsTitleForMangaOnly(): void
    {
        $provider = new AniListLookup(new MockHttpClient(), new NullLogger());

        self::assertTrue($provider->supports('title', ComicType::MANGA));
        self::assertFalse($provider->supports('title', ComicType::BD));
        self::assertFalse($provider->supports('title', ComicType::COMICS));
        self::assertFalse($provider->supports('title', null));
        self::assertFalse($provider->supports('isbn', ComicType::MANGA));
        self::assertFalse($provider->supports('isbn', null));
    }

    public function testLookupByTitleReturnsData(): void
    {
        $response = new MockResponse(\json_encode([
            'data' => [
                'Media' => [
                    'coverImage' => [
                        'extraLarge' => 'https://anilist.co/cover-xl.jpg',
                        'large' => 'https://anilist.co/cover-l.jpg',
                    ],
                    'description' => 'Un super manga',
                    'staff' => [
                        'edges' => [
                            [
                                'node' => ['name' => ['full' => 'Mangaka Name']],
                                'role' => 'Story & Art',
                            ],
                        ],
                    ],
                    'startDate' => ['day' => 15, 'month' => 6, 'year' => 2020],
                    'title' => ['english' => 'Solo Leveling', 'romaji' => 'Na Honjaman Level Up'],
                ],
            ],
        ]));

        $provider = new AniListLookup(new MockHttpClient([$response]), new NullLogger());
        $result = $this->doLookup($provider, 'Solo Leveling', ComicType::MANGA, 'title');

        self::assertInstanceOf(LookupResult::class, $result);
        self::assertSame('Solo Leveling', $result->title);
        self::assertSame('Mangaka Name', $result->authors);
        self::assertSame('Un super manga', $result->description);
        self::assertSame('https://anilist.co/cover-xl.jpg', $result->thumbnail);
        self::assertSame('2020-06-15', $result->publishedDate);
        self::assertSame('anilist', $result->source);
    }

    public function testLookupExtractsMultipleAuthors(): void
    {
        $response = new MockResponse(\json_encode([
            'data' => [
                'Media' => [
                    'coverImage' => ['large' => 'https://anilist.co/cover.jpg'],
                    'staff' => [
                        'edges' => [
                            [
                                'node' => ['name' => ['full' => 'Writer Name']],
                                'role' => 'Story',
                            ],
                            [
                                'node' => ['name' => ['full' => 'Artist Name']],
                                'role' => 'Art',
                            ],
                        ],
                    ],
                    'title' => ['english' => 'Test Manga'],
                ],
            ],
        ]));

        $provider = new AniListLookup(new MockHttpClient([$response]), new NullLogger());
        $result = $this->doLookup($provider, 'Test Manga', ComicType::MANGA, 'title');

        self::assertSame('Writer Name, Artist Name', $result->authors);
    }

    public function testLookupFiltersNonAuthorRoles(): void
    {
        $response = new MockResponse(\json_encode([
            'data' => [
                'Media' => [
                    'coverImage' => ['large' => 'https://anilist.co/cover.jpg'],
                    'staff' => [
                        'edges' => [
                            [
                                'node' => ['name' => ['full' => 'Author']],
                                'role' => 'Story',
                            ],
                            [
                                'node' => ['name' => ['full' => 'Editor']],
                                'role' => 'Editor',
                            ],
                            [
                                'node' => ['name' => ['full' => 'Director']],
                                'role' => 'Director',
                            ],
                        ],
                    ],
                    'title' => ['english' => 'Test'],
                ],
            ],
        ]));

        $provider = new AniListLookup(new MockHttpClient([$response]), new NullLogger());
        $result = $this->doLookup($provider, 'Test', ComicType::MANGA, 'title');

        self::assertSame('Author', $result->authors);
    }

    public function testLookupFormatsFullDate(): void
    {
        $response = new MockResponse(\json_encode([
            'data' => [
                'Media' => [
                    'coverImage' => ['large' => 'https://cover.jpg'],
                    'startDate' => ['day' => 5, 'month' => 3, 'year' => 2018],
                    'title' => ['romaji' => 'Test'],
                ],
            ],
        ]));

        $provider = new AniListLookup(new MockHttpClient([$response]), new NullLogger());
        $result = $this->doLookup($provider, 'Test', ComicType::MANGA, 'title');

        self::assertSame('2018-03-05', $result->publishedDate);
    }

    public function testLookupFormatsPartialDate(): void
    {
        $response = new MockResponse(\json_encode([
            'data' => [
                'Media' => [
                    'coverImage' => ['large' => 'https://cover.jpg'],
                    'startDate' => ['day' => null, 'month' => 7, 'year' => 2020],
                    'title' => ['romaji' => 'Test'],
                ],
            ],
        ]));

        $provider = new AniListLookup(new MockHttpClient([$response]), new NullLogger());
        $result = $this->doLookup($provider, 'Test', ComicType::MANGA, 'title');

        self::assertSame('2020-07', $result->publishedDate);
    }

    public function testLookupFormatsYearOnlyDate(): void
    {
        $response = new MockResponse(\json_encode([
            'data' => [
                'Media' => [
                    'coverImage' => ['large' => 'https://cover.jpg'],
                    'startDate' => ['day' => null, 'month' => null, 'year' => 2015],
                    'title' => ['romaji' => 'Test'],
                ],
            ],
        ]));

        $provider = new AniListLookup(new MockHttpClient([$response]), new NullLogger());
        $result = $this->doLookup($provider, 'Test', ComicType::MANGA, 'title');

        self::assertSame('2015', $result->publishedDate);
    }

    public function testLookupDetectsOneShotByFormat(): void
    {
        $response = new MockResponse(\json_encode([
            'data' => [
                'Media' => [
                    'coverImage' => ['large' => 'https://cover.jpg'],
                    'format' => 'ONE_SHOT',
                    'title' => ['romaji' => 'Test'],
                ],
            ],
        ]));

        $provider = new AniListLookup(new MockHttpClient([$response]), new NullLogger());
        $result = $this->doLookup($provider, 'Test', ComicType::MANGA, 'title');

        self::assertTrue($result->isOneShot);
    }

    public function testLookupDetectsOneShotByVolumesAndStatus(): void
    {
        $response = new MockResponse(\json_encode([
            'data' => [
                'Media' => [
                    'coverImage' => ['large' => 'https://cover.jpg'],
                    'format' => 'MANGA',
                    'status' => 'FINISHED',
                    'title' => ['romaji' => 'Test'],
                    'volumes' => 1,
                ],
            ],
        ]));

        $provider = new AniListLookup(new MockHttpClient([$response]), new NullLogger());
        $result = $this->doLookup($provider, 'Test', ComicType::MANGA, 'title');

        self::assertTrue($result->isOneShot);
    }

    public function testLookupDetectsMultiVolumeAsNotOneShot(): void
    {
        $response = new MockResponse(\json_encode([
            'data' => [
                'Media' => [
                    'coverImage' => ['large' => 'https://cover.jpg'],
                    'format' => 'MANGA',
                    'status' => 'RELEASING',
                    'title' => ['romaji' => 'Test'],
                    'volumes' => 10,
                ],
            ],
        ]));

        $provider = new AniListLookup(new MockHttpClient([$response]), new NullLogger());
        $result = $this->doLookup($provider, 'Test', ComicType::MANGA, 'title');

        self::assertFalse($result->isOneShot);
    }

    public function testLookupReturnsLatestPublishedIssue(): void
    {
        $response = new MockResponse(\json_encode([
            'data' => [
                'Media' => [
                    'coverImage' => ['large' => 'https://cover.jpg'],
                    'format' => 'MANGA',
                    'status' => 'RELEASING',
                    'title' => ['romaji' => 'One Piece'],
                    'volumes' => 109,
                ],
            ],
        ]));

        $provider = new AniListLookup(new MockHttpClient([$response]), new NullLogger());
        $result = $this->doLookup($provider, 'One Piece', ComicType::MANGA, 'title');

        self::assertSame(109, $result->latestPublishedIssue);
    }

    public function testLookupReturnsNullLatestPublishedIssueWhenNotAvailable(): void
    {
        $response = new MockResponse(\json_encode([
            'data' => [
                'Media' => [
                    'coverImage' => ['large' => 'https://cover.jpg'],
                    'title' => ['romaji' => 'Test'],
                ],
            ],
        ]));

        $provider = new AniListLookup(new MockHttpClient([$response]), new NullLogger());
        $result = $this->doLookup($provider, 'Test', ComicType::MANGA, 'title');

        self::assertNull($result->latestPublishedIssue);
    }

    public function testLookupCleansTitleSuffixes(): void
    {
        $requestedBodies = [];
        $response = new MockResponse(\json_encode(['data' => ['Media' => null]]));

        $mockClient = new MockHttpClient(static function (string $method, string $url, array $options) use (&$requestedBodies, $response): MockResponse {
            $requestedBodies[] = $options['body'] ?? '';

            return $response;
        });

        $provider = new AniListLookup($mockClient, new NullLogger());
        $this->doLookup($provider, 'Solo Leveling Tome 2', ComicType::MANGA, 'title');

        self::assertCount(1, $requestedBodies);
        $body = \json_decode($requestedBodies[0], true);
        self::assertSame('Solo Leveling', $body['variables']['search']);
    }

    public function testLookupReturnsNullWhenNoResults(): void
    {
        $response = new MockResponse(\json_encode(['data' => ['Media' => null]]));

        $provider = new AniListLookup(new MockHttpClient([$response]), new NullLogger());
        $result = $this->doLookup($provider, 'Nonexistent Manga', ComicType::MANGA, 'title');

        self::assertNull($result);
        self::assertSame('not_found', $provider->getLastApiMessage()['status']);
    }

    public function testLookupHandlesNetworkErrors(): void
    {
        $response = new MockResponse('', ['error' => 'Connection failed']);

        $provider = new AniListLookup(new MockHttpClient([$response]), new NullLogger());
        $result = $this->doLookup($provider, 'Test', ComicType::MANGA, 'title');

        self::assertNull($result);
        self::assertSame('error', $provider->getLastApiMessage()['status']);
    }

    public function testLookupHandlesRateLimiting(): void
    {
        $response = new MockResponse('Rate limit', ['http_code' => 429]);

        $provider = new AniListLookup(new MockHttpClient([$response]), new NullLogger());
        $result = $this->doLookup($provider, 'Test', ComicType::MANGA, 'title');

        self::assertNull($result);
        self::assertSame('rate_limited', $provider->getLastApiMessage()['status']);
    }

    public function testLookupPrefersEnglishTitle(): void
    {
        $response = new MockResponse(\json_encode([
            'data' => [
                'Media' => [
                    'coverImage' => ['large' => 'https://cover.jpg'],
                    'title' => [
                        'english' => 'English Title',
                        'native' => 'ネイティブ',
                        'romaji' => 'Romaji Title',
                    ],
                ],
            ],
        ]));

        $provider = new AniListLookup(new MockHttpClient([$response]), new NullLogger());
        $result = $this->doLookup($provider, 'Test', ComicType::MANGA, 'title');

        self::assertSame('English Title', $result->title);
    }

    public function testLookupFallsBackToRomajiTitle(): void
    {
        $response = new MockResponse(\json_encode([
            'data' => [
                'Media' => [
                    'coverImage' => ['large' => 'https://cover.jpg'],
                    'title' => [
                        'english' => null,
                        'native' => 'ネイティブ',
                        'romaji' => 'Romaji Title',
                    ],
                ],
            ],
        ]));

        $provider = new AniListLookup(new MockHttpClient([$response]), new NullLogger());
        $result = $this->doLookup($provider, 'Test', ComicType::MANGA, 'title');

        self::assertSame('Romaji Title', $result->title);
    }

    private function doLookup(AniListLookup $provider, string $query, ?ComicType $type, string $mode = 'title'): ?LookupResult
    {
        $state = $provider->prepareLookup($query, $type, $mode);

        return $provider->resolveLookup($state);
    }
}

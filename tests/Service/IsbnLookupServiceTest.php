<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Enum\ComicType;
use App\Service\IsbnLookupService;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

/**
 * Tests du service de recherche ISBN.
 */
class IsbnLookupServiceTest extends TestCase
{
    /**
     * Teste la recherche avec un ISBN valide sur Google Books.
     */
    public function testLookupReturnsGoogleBooksData(): void
    {
        $googleResponse = new MockResponse(\json_encode([
            'items' => [
                [
                    'volumeInfo' => [
                        'authors' => ['John Doe'],
                        'description' => 'A great book',
                        'imageLinks' => ['thumbnail' => 'https://example.com/cover.jpg'],
                        'publishedDate' => '2020-01-01',
                        'publisher' => 'Great Publisher',
                        'title' => 'Test Book',
                    ],
                ],
            ],
        ]));

        $openLibraryResponse = new MockResponse('', ['http_code' => 404]);

        $mockClient = new MockHttpClient([$googleResponse, $openLibraryResponse]);
        $service = new IsbnLookupService($mockClient, new NullLogger());

        $result = $service->lookup('978-2-505-00123-4');

        self::assertNotNull($result);
        self::assertSame('Test Book', $result['title']);
        self::assertSame('John Doe', $result['authors']);
        self::assertSame('Great Publisher', $result['publisher']);
        self::assertSame('2020-01-01', $result['publishedDate']);
        self::assertSame('A great book', $result['description']);
        self::assertSame('https://example.com/cover.jpg', $result['thumbnail']);
        self::assertContains('google_books', $result['sources']);
    }

    /**
     * Teste que les données de plusieurs résultats Google Books sont fusionnées.
     */
    public function testLookupMergesMultipleGoogleBooksResults(): void
    {
        // Simule le cas de l'ISBN 2800152850 où le premier résultat n'a pas d'auteurs
        $googleResponse = new MockResponse(\json_encode([
            'items' => [
                [
                    'volumeInfo' => [
                        'description' => 'Description du premier résultat',
                        'publishedDate' => '2010',
                        'title' => 'L\'Agent 212',
                    ],
                ],
                [
                    'volumeInfo' => [
                        'authors' => ['Raoul Cauvin', 'Daniel Kox'],
                        'imageLinks' => ['thumbnail' => 'https://example.com/agent212.jpg'],
                        'publishedDate' => '2010-05',
                        'publisher' => 'Dupuis',
                        'title' => 'L\'Agent 212, tome 23',
                    ],
                ],
            ],
        ]));

        $openLibraryResponse = new MockResponse('', ['http_code' => 404]);

        $mockClient = new MockHttpClient([$googleResponse, $openLibraryResponse]);
        $service = new IsbnLookupService($mockClient, new NullLogger());

        $result = $service->lookup('2800152850');

        self::assertNotNull($result);
        // Le titre vient du premier résultat
        self::assertSame('L\'Agent 212', $result['title']);
        // Les auteurs viennent du deuxième résultat
        self::assertSame('Raoul Cauvin, Daniel Kox', $result['authors']);
        // L'éditeur vient du deuxième résultat
        self::assertSame('Dupuis', $result['publisher']);
        // La description vient du premier résultat
        self::assertSame('Description du premier résultat', $result['description']);
    }

    /**
     * Teste la recherche avec fallback sur Open Library.
     */
    public function testLookupFallsBackToOpenLibrary(): void
    {
        $googleResponse = new MockResponse(\json_encode(['items' => []]));

        $openLibraryResponse = new MockResponse(\json_encode([
            'covers' => [12345],
            'publish_date' => 'January 2021',
            'publishers' => ['Open Publisher'],
            'title' => 'Open Library Book',
        ]));

        $mockClient = new MockHttpClient([$googleResponse, $openLibraryResponse]);
        $service = new IsbnLookupService($mockClient, new NullLogger());

        $result = $service->lookup('1234567890');

        self::assertNotNull($result);
        self::assertSame('Open Library Book', $result['title']);
        self::assertSame('Open Publisher', $result['publisher']);
        self::assertSame('January 2021', $result['publishedDate']);
        self::assertSame('https://covers.openlibrary.org/b/id/12345-M.jpg', $result['thumbnail']);
        self::assertContains('open_library', $result['sources']);
    }

    /**
     * Teste que les résultats des deux APIs sont fusionnés.
     */
    public function testLookupMergesBothApis(): void
    {
        // Google Books n'a que le titre et la description
        $googleResponse = new MockResponse(\json_encode([
            'items' => [
                [
                    'volumeInfo' => [
                        'description' => 'Une description de Google',
                        'title' => 'Google Book Title',
                    ],
                ],
            ],
        ]));

        // Open Library a l'éditeur et les auteurs
        $openLibraryResponse = new MockResponse(\json_encode([
            'authors' => [['key' => '/authors/OL123A']],
            'publish_date' => '2022',
            'publishers' => ['OL Publisher'],
            'title' => 'OL Book Title',
        ]));

        // Réponse pour l'auteur Open Library
        $authorResponse = new MockResponse(\json_encode([
            'name' => 'Author Name',
        ]));

        $mockClient = new MockHttpClient([$googleResponse, $openLibraryResponse, $authorResponse]);
        $service = new IsbnLookupService($mockClient, new NullLogger());

        $result = $service->lookup('9999999999');

        self::assertNotNull($result);
        // Google Books est prioritaire pour le titre
        self::assertSame('Google Book Title', $result['title']);
        // Google Books fournit la description
        self::assertSame('Une description de Google', $result['description']);
        // Open Library complète les données manquantes
        self::assertSame('OL Publisher', $result['publisher']);
        self::assertSame('Author Name', $result['authors']);
        self::assertSame('2022', $result['publishedDate']);
        // Les deux sources sont mentionnées
        self::assertContains('google_books', $result['sources']);
        self::assertContains('open_library', $result['sources']);
    }

    /**
     * Teste qu'un ISBN vide retourne null.
     */
    public function testLookupWithEmptyIsbnReturnsNull(): void
    {
        $mockClient = new MockHttpClient([]);
        $service = new IsbnLookupService($mockClient, new NullLogger());

        self::assertNull($service->lookup(''));
        self::assertNull($service->lookup('   '));
    }

    /**
     * Teste que les tirets et espaces sont supprimés de l'ISBN.
     */
    public function testLookupNormalizesIsbn(): void
    {
        $googleResponse = new MockResponse(\json_encode([
            'items' => [
                [
                    'volumeInfo' => [
                        'title' => 'Normalized ISBN Book',
                    ],
                ],
            ],
        ]));

        $openLibraryResponse = new MockResponse('', ['http_code' => 404]);

        $responses = [$googleResponse, $openLibraryResponse];
        $requestedUrls = [];

        $mockClient = new MockHttpClient(static function (string $method, string $url) use (&$responses, &$requestedUrls): ?MockResponse {
            $requestedUrls[] = $url;

            return \array_shift($responses);
        });

        $service = new IsbnLookupService($mockClient, new NullLogger());
        $result = $service->lookup('978-2-505-00123-4');

        self::assertNotNull($result);
        // Vérifie que l'ISBN est normalisé (sans tirets ni espaces)
        self::assertStringContainsString('isbn:9782505001234', $requestedUrls[0]);
    }

    /**
     * Teste que les erreurs API sont gérées gracieusement.
     */
    public function testLookupHandlesApiErrors(): void
    {
        $googleResponse = new MockResponse('', ['error' => 'Connection failed']);
        $openLibraryResponse = new MockResponse('', ['http_code' => 500]);

        $mockClient = new MockHttpClient([$googleResponse, $openLibraryResponse]);
        $service = new IsbnLookupService($mockClient, new NullLogger());

        $result = $service->lookup('1234567890');

        self::assertNull($result);
    }

    /**
     * Teste la recherche quand aucune API ne trouve de résultat.
     */
    public function testLookupReturnsNullWhenNoResults(): void
    {
        $googleResponse = new MockResponse(\json_encode(['items' => []]));
        $openLibraryResponse = new MockResponse('', ['http_code' => 404]);

        $mockClient = new MockHttpClient([$googleResponse, $openLibraryResponse]);
        $service = new IsbnLookupService($mockClient, new NullLogger());

        $result = $service->lookup('0000000000');

        self::assertNull($result);
    }

    /**
     * Teste que la meilleure image est sélectionnée (thumbnail > smallThumbnail).
     */
    public function testLookupSelectsBestThumbnail(): void
    {
        $googleResponse = new MockResponse(\json_encode([
            'items' => [
                [
                    'volumeInfo' => [
                        'imageLinks' => [
                            'smallThumbnail' => 'https://example.com/small.jpg',
                            'thumbnail' => 'https://example.com/large.jpg',
                        ],
                        'title' => 'Book with images',
                    ],
                ],
            ],
        ]));

        $openLibraryResponse = new MockResponse('', ['http_code' => 404]);

        $mockClient = new MockHttpClient([$googleResponse, $openLibraryResponse]);
        $service = new IsbnLookupService($mockClient, new NullLogger());

        $result = $service->lookup('1234567890');

        self::assertSame('https://example.com/large.jpg', $result['thumbnail']);
    }

    /**
     * Teste que smallThumbnail est utilisé si thumbnail n'existe pas.
     */
    public function testLookupFallsBackToSmallThumbnail(): void
    {
        $googleResponse = new MockResponse(\json_encode([
            'items' => [
                [
                    'volumeInfo' => [
                        'imageLinks' => [
                            'smallThumbnail' => 'https://example.com/small.jpg',
                        ],
                        'title' => 'Book with small image',
                    ],
                ],
            ],
        ]));

        $openLibraryResponse = new MockResponse('', ['http_code' => 404]);
        $anilistResponse = new MockResponse(\json_encode(['data' => ['Media' => null]]));

        $mockClient = new MockHttpClient([$googleResponse, $openLibraryResponse, $anilistResponse]);
        $service = new IsbnLookupService($mockClient, new NullLogger());

        $result = $service->lookup('1234567890');

        self::assertSame('https://example.com/small.jpg', $result['thumbnail']);
    }

    /**
     * Teste l'enrichissement des données manga avec AniList.
     * Utilise l'ISBN 2382880309 (Solo Leveling Tome 2).
     */
    public function testLookupEnrichesWithAniList(): void
    {
        // Google Books retourne des données basiques
        $googleResponse = new MockResponse(\json_encode([
            'items' => [
                [
                    'volumeInfo' => [
                        'imageLinks' => ['thumbnail' => 'https://books.google.com/small.jpg'],
                        'publishedDate' => '2021',
                        'publisher' => 'Kbooks',
                        'title' => 'Solo Leveling Tome 2',
                    ],
                ],
            ],
        ]));

        $openLibraryResponse = new MockResponse('', ['http_code' => 404]);

        // AniList retourne des données enrichies
        $anilistResponse = new MockResponse(\json_encode([
            'data' => [
                'Media' => [
                    'coverImage' => [
                        'extraLarge' => 'https://anilist.co/cover-xl.jpg',
                        'large' => 'https://anilist.co/cover-l.jpg',
                    ],
                    'description' => 'Depuis qu\'il s\'est éveillé, Jinwoo a accès au Système...',
                    'staff' => [
                        'edges' => [
                            [
                                'node' => ['name' => ['full' => 'Chugong']],
                                'role' => 'Original Story',
                            ],
                            [
                                'node' => ['name' => ['full' => 'DUBU']],
                                'role' => 'Art',
                            ],
                        ],
                    ],
                    'startDate' => [
                        'day' => 4,
                        'month' => 3,
                        'year' => 2018,
                    ],
                    'title' => [
                        'english' => 'Solo Leveling',
                        'native' => '나 혼자만 레벨업',
                        'romaji' => 'Na Honjaman Level Up',
                    ],
                ],
            ],
        ]));

        $mockClient = new MockHttpClient([$googleResponse, $openLibraryResponse, $anilistResponse]);
        $service = new IsbnLookupService($mockClient, new NullLogger());

        // Le second paramètre ComicType::MANGA déclenche l'appel à AniList
        $result = $service->lookup('2382880309', ComicType::MANGA);

        self::assertNotNull($result);
        // Titre vient de Google Books (prioritaire)
        self::assertSame('Solo Leveling Tome 2', $result['title']);
        // Éditeur vient de Google Books
        self::assertSame('Kbooks', $result['publisher']);
        // Description vient d'AniList (Google Books n'en avait pas)
        self::assertSame('Depuis qu\'il s\'est éveillé, Jinwoo a accès au Système...', $result['description']);
        // Couverture AniList remplace celle de Google (meilleure qualité)
        self::assertSame('https://anilist.co/cover-xl.jpg', $result['thumbnail']);
        // Les trois sources sont mentionnées
        self::assertContains('google_books', $result['sources']);
        self::assertContains('anilist', $result['sources']);
    }

    /**
     * Teste que AniList complète les auteurs manquants.
     */
    public function testLookupAniListCompletesAuthors(): void
    {
        // Google Books sans auteurs
        $googleResponse = new MockResponse(\json_encode([
            'items' => [
                [
                    'volumeInfo' => [
                        'publisher' => 'Kana',
                        'title' => 'My Hero Academia',
                    ],
                ],
            ],
        ]));

        $openLibraryResponse = new MockResponse('', ['http_code' => 404]);

        // AniList avec auteurs
        $anilistResponse = new MockResponse(\json_encode([
            'data' => [
                'Media' => [
                    'coverImage' => ['large' => 'https://anilist.co/mha.jpg'],
                    'staff' => [
                        'edges' => [
                            [
                                'node' => ['name' => ['full' => 'Kouhei Horikoshi']],
                                'role' => 'Story & Art',
                            ],
                        ],
                    ],
                    'startDate' => ['year' => 2014],
                    'title' => ['english' => 'My Hero Academia'],
                ],
            ],
        ]));

        $mockClient = new MockHttpClient([$googleResponse, $openLibraryResponse, $anilistResponse]);
        $service = new IsbnLookupService($mockClient, new NullLogger());

        // Le second paramètre ComicType::MANGA déclenche l'appel à AniList
        $result = $service->lookup('9782505063391', ComicType::MANGA);

        self::assertNotNull($result);
        // Auteur complété par AniList
        self::assertSame('Kouhei Horikoshi', $result['authors']);
        self::assertContains('anilist', $result['sources']);
    }

    /**
     * Teste que AniList ne remplace pas les données existantes (sauf thumbnail).
     */
    public function testLookupAniListDoesNotOverwriteExistingData(): void
    {
        // Google Books avec description
        $googleResponse = new MockResponse(\json_encode([
            'items' => [
                [
                    'volumeInfo' => [
                        'authors' => ['Auteur Google'],
                        'description' => 'Description de Google Books',
                        'publishedDate' => '2020-01-15',
                        'title' => 'Test Manga',
                    ],
                ],
            ],
        ]));

        $openLibraryResponse = new MockResponse('', ['http_code' => 404]);

        // AniList avec des données différentes
        $anilistResponse = new MockResponse(\json_encode([
            'data' => [
                'Media' => [
                    'coverImage' => ['large' => 'https://anilist.co/cover.jpg'],
                    'description' => 'Description AniList différente',
                    'staff' => [
                        'edges' => [
                            [
                                'node' => ['name' => ['full' => 'Auteur AniList']],
                                'role' => 'Story',
                            ],
                        ],
                    ],
                    'startDate' => ['year' => 2019],
                    'title' => ['english' => 'Test Manga'],
                ],
            ],
        ]));

        $mockClient = new MockHttpClient([$googleResponse, $openLibraryResponse, $anilistResponse]);
        $service = new IsbnLookupService($mockClient, new NullLogger());

        // Le second paramètre ComicType::MANGA déclenche l'appel à AniList
        $result = $service->lookup('1234567890', ComicType::MANGA);

        self::assertNotNull($result);
        // Les données Google Books sont conservées (pas remplacées par AniList)
        self::assertSame('Description de Google Books', $result['description']);
        self::assertSame('Auteur Google', $result['authors']);
        self::assertSame('2020-01-15', $result['publishedDate']);
        // Mais la couverture AniList est utilisée (meilleure qualité)
        self::assertSame('https://anilist.co/cover.jpg', $result['thumbnail']);
    }

    /**
     * Teste le formatage des dates AniList.
     */
    public function testLookupAniListDateFormatting(): void
    {
        $googleResponse = new MockResponse(\json_encode([
            'items' => [
                [
                    'volumeInfo' => [
                        'title' => 'Test Manga',
                    ],
                ],
            ],
        ]));

        $openLibraryResponse = new MockResponse('', ['http_code' => 404]);

        // AniList avec date complète
        $anilistResponse = new MockResponse(\json_encode([
            'data' => [
                'Media' => [
                    'coverImage' => ['large' => 'https://anilist.co/cover.jpg'],
                    'startDate' => [
                        'day' => 5,
                        'month' => 3,
                        'year' => 2018,
                    ],
                    'title' => ['romaji' => 'Test Manga'],
                ],
            ],
        ]));

        $mockClient = new MockHttpClient([$googleResponse, $openLibraryResponse, $anilistResponse]);
        $service = new IsbnLookupService($mockClient, new NullLogger());

        // Le second paramètre ComicType::MANGA déclenche l'appel à AniList
        $result = $service->lookup('1234567890', ComicType::MANGA);

        self::assertNotNull($result);
        // Date formatée en YYYY-MM-DD
        self::assertSame('2018-03-05', $result['publishedDate']);
    }

    /**
     * Teste que AniList n'est pas appelé si aucun titre n'est trouvé.
     */
    public function testLookupDoesNotCallAniListWithoutTitle(): void
    {
        $googleResponse = new MockResponse(\json_encode(['items' => []]));
        $openLibraryResponse = new MockResponse('', ['http_code' => 404]);

        $requestCount = 0;
        $mockClient = new MockHttpClient(static function () use (&$requestCount, $googleResponse, $openLibraryResponse): MockResponse {
            ++$requestCount;

            return 1 === $requestCount ? $googleResponse : $openLibraryResponse;
        });

        $service = new IsbnLookupService($mockClient, new NullLogger());
        $result = $service->lookup('0000000000');

        self::assertNull($result);
        // Seulement 2 appels (Google Books + Open Library), pas AniList
        self::assertSame(2, $requestCount);
    }

    /**
     * Teste la gestion des erreurs AniList.
     */
    public function testLookupHandlesAniListErrors(): void
    {
        $googleResponse = new MockResponse(\json_encode([
            'items' => [
                [
                    'volumeInfo' => [
                        'imageLinks' => ['thumbnail' => 'https://google.com/cover.jpg'],
                        'title' => 'Test Manga',
                    ],
                ],
            ],
        ]));

        $openLibraryResponse = new MockResponse('', ['http_code' => 404]);
        $anilistResponse = new MockResponse('', ['http_code' => 500]);

        $mockClient = new MockHttpClient([$googleResponse, $openLibraryResponse, $anilistResponse]);
        $service = new IsbnLookupService($mockClient, new NullLogger());

        $result = $service->lookup('1234567890');

        self::assertNotNull($result);
        // Les données Google Books sont retournées malgré l'erreur AniList
        self::assertSame('Test Manga', $result['title']);
        self::assertSame('https://google.com/cover.jpg', $result['thumbnail']);
        // AniList n'est pas dans les sources
        self::assertNotContains('anilist', $result['sources']);
    }

    /**
     * Teste l'extraction des auteurs AniList avec différents rôles.
     */
    public function testLookupAniListExtractsMultipleAuthors(): void
    {
        $googleResponse = new MockResponse(\json_encode([
            'items' => [
                [
                    'volumeInfo' => [
                        'title' => 'Test Manga',
                    ],
                ],
            ],
        ]));

        $openLibraryResponse = new MockResponse('', ['http_code' => 404]);

        // AniList avec plusieurs auteurs
        $anilistResponse = new MockResponse(\json_encode([
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
                            [
                                'node' => ['name' => ['full' => 'Editor Name']],
                                'role' => 'Editor', // Non-auteur, doit être filtré
                            ],
                        ],
                    ],
                    'title' => ['english' => 'Test Manga'],
                ],
            ],
        ]));

        $mockClient = new MockHttpClient([$googleResponse, $openLibraryResponse, $anilistResponse]);
        $service = new IsbnLookupService($mockClient, new NullLogger());

        // Le second paramètre ComicType::MANGA déclenche l'appel à AniList
        $result = $service->lookup('1234567890', ComicType::MANGA);

        self::assertNotNull($result);
        // Seuls Story et Art sont inclus, pas Editor
        self::assertSame('Writer Name, Artist Name', $result['authors']);
    }
}

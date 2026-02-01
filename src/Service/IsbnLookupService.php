<?php

declare(strict_types=1);

namespace App\Service;

use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\DecodingExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Service de recherche d'informations sur un livre/BD/manga par ISBN ou titre.
 * Interroge Google Books, Open Library et AniList (pour les mangas).
 */
class IsbnLookupService
{
    private const ANILIST_API = 'https://graphql.anilist.co';
    private const GOOGLE_BOOKS_API = 'https://www.googleapis.com/books/v1/volumes';
    private const OPEN_LIBRARY_API = 'https://openlibrary.org/isbn/';

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * Recherche les informations d'un livre par ISBN.
     * Si le type est "manga", utilise AniList en priorité.
     *
     * @param string      $isbn L'ISBN à rechercher
     * @param string|null $type Le type sélectionné (manga, bd, comics, livre)
     *
     * @return array<string, mixed>|null Les données du livre ou null si non trouvé
     */
    public function lookup(string $isbn, ?string $type = null): ?array
    {
        // Nettoie l'ISBN (supprime les tirets et espaces)
        $isbn = \preg_replace('/[\s-]/', '', $isbn) ?? '';

        if ('' === $isbn) {
            return null;
        }

        // Interroge Google Books et Open Library par ISBN
        $googleResult = $this->lookupGoogleBooks($isbn);
        $openLibraryResult = $this->lookupOpenLibrary($isbn);

        // Si aucune API n'a de résultat
        if (null === $googleResult && null === $openLibraryResult) {
            return null;
        }

        // Fusionne les résultats Google Books et Open Library
        $mergedResult = $this->mergeResults($googleResult, $openLibraryResult);

        // Si le type est manga, enrichit avec AniList
        if ('manga' === $type) {
            $title = $mergedResult['title'] ?? null;
            if (\is_string($title) && '' !== $title) {
                $anilistResult = $this->lookupAniList($title);
                if (null !== $anilistResult) {
                    $mergedResult = $this->mergeWithAniList($mergedResult, $anilistResult);
                }
            }
        }

        // Ajoute l'ISBN recherché dans les résultats
        $mergedResult['isbn'] = $isbn;

        return $mergedResult;
    }

    /**
     * Recherche les informations par titre.
     * Si le type est "manga", utilise AniList en priorité.
     *
     * @param string      $title Le titre à rechercher
     * @param string|null $type  Le type sélectionné (manga, bd, comics, livre)
     *
     * @return array<string, mixed>|null Les données ou null si non trouvé
     */
    public function lookupByTitle(string $title, ?string $type = null): ?array
    {
        $title = \trim($title);

        if ('' === $title) {
            return null;
        }

        // Si le type est manga, cherche sur AniList en priorité
        if ('manga' === $type) {
            $anilistResult = $this->lookupAniList($title);
            if (null !== $anilistResult) {
                return [
                    'authors' => $anilistResult['authors'] ?? null,
                    'description' => $anilistResult['description'] ?? null,
                    'isOneShot' => $anilistResult['isOneShot'] ?? false,
                    'publishedDate' => $anilistResult['publishedDate'] ?? null,
                    'sources' => ['anilist'],
                    'thumbnail' => $anilistResult['thumbnail'] ?? null,
                    'title' => $anilistResult['title'] ?? $title,
                ];
            }
        }

        // Sinon, cherche sur Google Books par titre
        $googleResult = $this->lookupGoogleBooksByTitle($title);

        if (null === $googleResult) {
            return null;
        }

        return [
            'authors' => $googleResult['authors'] ?? null,
            'description' => $googleResult['description'] ?? null,
            'isbn' => $googleResult['isbn'] ?? null,
            'isOneShot' => $googleResult['isOneShot'] ?? null,
            'publishedDate' => $googleResult['publishedDate'] ?? null,
            'publisher' => $googleResult['publisher'] ?? null,
            'sources' => ['google_books'],
            'thumbnail' => $googleResult['thumbnail'] ?? null,
            'title' => $googleResult['title'] ?? $title,
        ];
    }

    /**
     * Recherche sur Google Books API par titre.
     *
     * @return array<string, string|null>|null
     */
    private function lookupGoogleBooksByTitle(string $title): ?array
    {
        try {
            $response = $this->httpClient->request('GET', self::GOOGLE_BOOKS_API, [
                'query' => [
                    'q' => $title,
                    'maxResults' => 10,
                ],
                'timeout' => 10,
            ]);

            $data = $response->toArray();

            if (empty($data['items'])) {
                return null;
            }

            return $this->mergeGoogleBooksItems($data['items']);
        } catch (TransportExceptionInterface $e) {
            $this->logger->error('Erreur réseau lors de la recherche Google Books pour le titre "{title}": {error}', [
                'error' => $e->getMessage(),
                'title' => $title,
            ]);

            return null;
        } catch (ClientExceptionInterface|ServerExceptionInterface|RedirectionExceptionInterface $e) {
            $this->logger->warning('Erreur HTTP lors de la recherche Google Books pour le titre "{title}": {error}', [
                'error' => $e->getMessage(),
                'title' => $title,
            ]);

            return null;
        } catch (DecodingExceptionInterface $e) {
            $this->logger->error('Réponse JSON invalide de Google Books pour le titre "{title}": {error}', [
                'error' => $e->getMessage(),
                'title' => $title,
            ]);

            return null;
        }
    }

    /**
     * Fusionne les résultats des deux API.
     * Privilégie Google Books, complète avec Open Library pour les champs manquants.
     *
     * @param array<string, mixed>|null $google
     * @param array<string, mixed>|null $openLibrary
     *
     * @return array<string, mixed>
     */
    private function mergeResults(?array $google, ?array $openLibrary): array
    {
        $sources = [];

        if (null !== $google) {
            $sources[] = 'google_books';
        }
        if (null !== $openLibrary) {
            $sources[] = 'open_library';
        }

        // Champs à fusionner
        $fields = ['authors', 'description', 'publishedDate', 'publisher', 'thumbnail', 'title'];

        $result = ['sources' => $sources];

        foreach ($fields as $field) {
            // Prend la valeur de Google Books si disponible, sinon celle d'Open Library
            $googleValue = $google[$field] ?? null;
            $openLibraryValue = $openLibrary[$field] ?? null;

            $result[$field] = $this->selectBestValue($googleValue, $openLibraryValue);
        }

        // isOneShot vient uniquement de Google Books
        $result['isOneShot'] = $google['isOneShot'] ?? null;

        return $result;
    }

    /**
     * Sélectionne la meilleure valeur entre deux sources.
     * Privilégie la première valeur non vide, sinon la seconde.
     */
    private function selectBestValue(mixed $primary, mixed $secondary): ?string
    {
        if (\is_string($primary) && '' !== $primary) {
            return $primary;
        }

        return \is_string($secondary) ? $secondary : null;
    }

    /**
     * Recherche sur Google Books API.
     * Récupère plusieurs résultats et les fusionne pour obtenir les données les plus complètes.
     */
    private function lookupGoogleBooks(string $isbn): ?array
    {
        try {
            $response = $this->httpClient->request('GET', self::GOOGLE_BOOKS_API, [
                'query' => [
                    'q' => 'isbn:'.$isbn,
                    'maxResults' => 10,
                ],
                'timeout' => 10,
            ]);

            $data = $response->toArray();

            if (empty($data['items'])) {
                return null;
            }

            // Fusionne les informations de tous les résultats pour maximiser les données
            return $this->mergeGoogleBooksItems($data['items']);
        } catch (TransportExceptionInterface $e) {
            $this->logger->error('Erreur réseau lors de la recherche Google Books pour ISBN {isbn}: {error}', [
                'error' => $e->getMessage(),
                'isbn' => $isbn,
            ]);

            return null;
        } catch (ClientExceptionInterface|ServerExceptionInterface|RedirectionExceptionInterface $e) {
            $this->logger->warning('Erreur HTTP lors de la recherche Google Books pour ISBN {isbn}: {error}', [
                'error' => $e->getMessage(),
                'isbn' => $isbn,
            ]);

            return null;
        } catch (DecodingExceptionInterface $e) {
            $this->logger->error('Réponse JSON invalide de Google Books pour ISBN {isbn}: {error}', [
                'error' => $e->getMessage(),
                'isbn' => $isbn,
            ]);

            return null;
        }
    }

    /**
     * Fusionne les données de plusieurs résultats Google Books.
     * Prend la première valeur non vide pour chaque champ.
     *
     * @param array<int, array<string, mixed>> $items
     *
     * @return array<string, string|null>
     */
    private function mergeGoogleBooksItems(array $items): array
    {
        $result = [
            'authors' => null,
            'description' => null,
            'isbn' => null,
            'isOneShot' => null,
            'publishedDate' => null,
            'publisher' => null,
            'source' => 'google_books',
            'thumbnail' => null,
            'title' => null,
        ];

        foreach ($items as $item) {
            $volumeInfo = $item['volumeInfo'] ?? [];

            // Auteurs
            if (null === $result['authors'] && !empty($volumeInfo['authors'])) {
                $result['authors'] = \implode(', ', $volumeInfo['authors']);
            }

            // Description
            if (null === $result['description'] && !empty($volumeInfo['description'])) {
                $result['description'] = $volumeInfo['description'];
            }

            // Date de publication
            if (null === $result['publishedDate'] && !empty($volumeInfo['publishedDate'])) {
                $result['publishedDate'] = $volumeInfo['publishedDate'];
            }

            // Éditeur
            if (null === $result['publisher'] && !empty($volumeInfo['publisher'])) {
                $result['publisher'] = $volumeInfo['publisher'];
            }

            // Thumbnail
            if (null === $result['thumbnail']) {
                $result['thumbnail'] = $volumeInfo['imageLinks']['thumbnail']
                    ?? $volumeInfo['imageLinks']['smallThumbnail']
                    ?? null;
            }

            // Titre
            if (null === $result['title'] && !empty($volumeInfo['title'])) {
                $result['title'] = $volumeInfo['title'];
            }

            // ISBN (préfère ISBN-13)
            if (null === $result['isbn'] && \is_array($volumeInfo) && !empty($volumeInfo['industryIdentifiers']) && \is_array($volumeInfo['industryIdentifiers'])) {
                $result['isbn'] = $this->extractIsbnFromIdentifiers($volumeInfo['industryIdentifiers']);
            }

            // Détection one-shot : si seriesInfo est absent, c'est probablement un one-shot
            if (null === $result['isOneShot'] && \is_array($volumeInfo)) {
                $result['isOneShot'] = !\array_key_exists('seriesInfo', $volumeInfo) || null === $volumeInfo['seriesInfo'];
            }

            // Arrête si toutes les données sont remplies
            if ($this->isComplete($result)) {
                break;
            }
        }

        return $result;
    }

    /**
     * Vérifie si toutes les données sont remplies.
     *
     * @param array<string, string|null> $data
     */
    private function isComplete(array $data): bool
    {
        return null !== $data['authors']
            && null !== $data['description']
            && null !== $data['publishedDate']
            && null !== $data['publisher']
            && null !== $data['thumbnail']
            && null !== $data['title'];
    }

    /**
     * Extrait l'ISBN depuis les identifiants Google Books.
     * Préfère ISBN-13, sinon ISBN-10.
     *
     * @param array<mixed, mixed> $identifiers
     */
    private function extractIsbnFromIdentifiers(array $identifiers): ?string
    {
        $isbn10 = null;
        $isbn13 = null;

        foreach ($identifiers as $identifier) {
            if (!\is_array($identifier)) {
                continue;
            }

            $type = \is_string($identifier['type'] ?? null) ? $identifier['type'] : '';
            $value = \is_string($identifier['identifier'] ?? null) ? $identifier['identifier'] : '';

            if ('ISBN_13' === $type && '' !== $value) {
                $isbn13 = $value;
            } elseif ('ISBN_10' === $type && '' !== $value) {
                $isbn10 = $value;
            }
        }

        return $isbn13 ?? $isbn10;
    }

    /**
     * Recherche sur Open Library API.
     */
    private function lookupOpenLibrary(string $isbn): ?array
    {
        try {
            $response = $this->httpClient->request('GET', self::OPEN_LIBRARY_API.$isbn.'.json', [
                'timeout' => 10,
            ]);

            if (200 !== $response->getStatusCode()) {
                return null;
            }

            $data = $response->toArray();

            if (empty($data['title'])) {
                return null;
            }

            // Récupère les auteurs si disponibles
            $authors = null;
            if (!empty($data['authors'])) {
                $authorNames = [];
                foreach ($data['authors'] as $author) {
                    if (isset($author['key'])) {
                        $authorData = $this->fetchOpenLibraryAuthor($author['key']);
                        if ($authorData) {
                            $authorNames[] = $authorData;
                        }
                    }
                }
                $authors = \implode(', ', $authorNames);
            }

            // Récupère l'éditeur
            $publisher = null;
            if (!empty($data['publishers'])) {
                $publisher = $data['publishers'][0];
            }

            // Récupère la date de publication
            $publishedDate = $data['publish_date'] ?? null;

            // Récupère la couverture
            $thumbnail = null;
            if (!empty($data['covers'][0])) {
                $coverId = $data['covers'][0];
                $thumbnail = "https://covers.openlibrary.org/b/id/{$coverId}-M.jpg";
            }

            return [
                'authors' => $authors,
                'description' => null,
                'publishedDate' => $publishedDate,
                'publisher' => $publisher,
                'source' => 'open_library',
                'thumbnail' => $thumbnail,
                'title' => $data['title'],
            ];
        } catch (TransportExceptionInterface $e) {
            $this->logger->error('Erreur réseau lors de la recherche Open Library pour ISBN {isbn}: {error}', [
                'error' => $e->getMessage(),
                'isbn' => $isbn,
            ]);

            return null;
        } catch (ClientExceptionInterface|ServerExceptionInterface|RedirectionExceptionInterface $e) {
            $this->logger->warning('Erreur HTTP lors de la recherche Open Library pour ISBN {isbn}: {error}', [
                'error' => $e->getMessage(),
                'isbn' => $isbn,
            ]);

            return null;
        } catch (DecodingExceptionInterface $e) {
            $this->logger->error('Réponse JSON invalide de Open Library pour ISBN {isbn}: {error}', [
                'error' => $e->getMessage(),
                'isbn' => $isbn,
            ]);

            return null;
        }
    }

    /**
     * Récupère le nom d'un auteur depuis Open Library.
     */
    private function fetchOpenLibraryAuthor(string $authorKey): ?string
    {
        try {
            $response = $this->httpClient->request('GET', 'https://openlibrary.org'.$authorKey.'.json', [
                'timeout' => 5,
            ]);

            $data = $response->toArray();

            return $data['name'] ?? null;
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Nettoie le titre pour la recherche AniList.
     * Supprime les suffixes de volume/tome courants.
     */
    private function cleanTitleForAniList(string $title): string
    {
        // Supprime les suffixes de volume/tome (Tome 2, Vol. 3, T. 1, Volume 10, etc.)
        $patterns = [
            '/\s*[-–—]\s*(?:T(?:ome)?|Vol(?:ume)?|V)\.?\s*\d+.*$/iu',
            '/\s+(?:T(?:ome)?|Vol(?:ume)?|V)\.?\s*\d+.*$/iu',
            '/\s*#\d+.*$/u',
            '/\s*\(\d+\)\s*$/u',
            '/\s+\d+\s*$/u',
        ];

        $cleaned = $title;
        foreach ($patterns as $pattern) {
            $cleaned = \preg_replace($pattern, '', $cleaned) ?? $cleaned;
        }

        return \trim($cleaned);
    }

    /**
     * Recherche sur AniList API (GraphQL) par titre.
     * Ne retourne que les mangas (pas les animes).
     *
     * @return array<string, mixed>|null
     */
    private function lookupAniList(string $title): ?array
    {
        // Nettoie le titre pour améliorer les chances de match
        $searchTitle = $this->cleanTitleForAniList($title);

        $query = <<<'GRAPHQL'
            query ($search: String) {
                Media(search: $search, type: MANGA) {
                    title {
                        english
                        native
                        romaji
                    }
                    format
                    volumes
                    status
                    description(asHtml: false)
                    coverImage {
                        extraLarge
                        large
                    }
                    startDate {
                        day
                        month
                        year
                    }
                    staff(sort: RELEVANCE, perPage: 10) {
                        edges {
                            role
                            node {
                                name {
                                    full
                                }
                            }
                        }
                    }
                }
            }
            GRAPHQL;

        try {
            $response = $this->httpClient->request('POST', self::ANILIST_API, [
                'headers' => [
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'query' => $query,
                    'variables' => ['search' => $searchTitle],
                ],
                'timeout' => 10,
            ]);

            $data = $response->toArray();
            $media = $data['data']['Media'] ?? null;

            if (null === $media) {
                return null;
            }

            // Extrait les auteurs (mangaka) depuis le staff
            $authors = $this->extractAniListAuthors($media['staff']['edges'] ?? []);

            // Sélectionne le meilleur titre disponible
            $anilistTitle = $media['title']['english']
                ?? $media['title']['romaji']
                ?? $media['title']['native']
                ?? null;

            // Sélectionne la meilleure image de couverture
            $thumbnail = $media['coverImage']['extraLarge']
                ?? $media['coverImage']['large']
                ?? null;

            // Formate la date de publication
            $publishedDate = $this->formatAniListDate($media['startDate'] ?? []);

            // Nettoie la description (supprime les balises HTML résiduelles)
            $description = $media['description'] ?? null;
            if (null !== $description) {
                $description = \strip_tags($description);
                $description = \html_entity_decode($description, \ENT_QUOTES | \ENT_HTML5, 'UTF-8');
            }

            // Détecte si c'est un one-shot via AniList :
            // - format === 'ONE_SHOT'
            // - OU volumes === 1 ET status === 'FINISHED' (tome unique terminé)
            $format = $media['format'] ?? null;
            $volumes = $media['volumes'] ?? null;
            $status = $media['status'] ?? null;
            $isOneShot = 'ONE_SHOT' === $format || (1 === $volumes && 'FINISHED' === $status);

            return [
                'authors' => $authors,
                'description' => $description,
                'isOneShot' => $isOneShot,
                'publishedDate' => $publishedDate,
                'source' => 'anilist',
                'thumbnail' => $thumbnail,
                'title' => $anilistTitle,
            ];
        } catch (TransportExceptionInterface $e) {
            $this->logger->error('Erreur réseau lors de la recherche AniList pour le titre "{title}" (recherche: "{search}"): {error}', [
                'error' => $e->getMessage(),
                'search' => $searchTitle,
                'title' => $title,
            ]);

            return null;
        } catch (ClientExceptionInterface|ServerExceptionInterface|RedirectionExceptionInterface $e) {
            $this->logger->warning('Erreur HTTP lors de la recherche AniList pour le titre "{title}" (recherche: "{search}"): {error}', [
                'error' => $e->getMessage(),
                'search' => $searchTitle,
                'title' => $title,
            ]);

            return null;
        } catch (DecodingExceptionInterface $e) {
            $this->logger->error('Réponse JSON invalide de AniList pour le titre "{title}" (recherche: "{search}"): {error}', [
                'error' => $e->getMessage(),
                'search' => $searchTitle,
                'title' => $title,
            ]);

            return null;
        }
    }

    /**
     * Extrait les noms des auteurs depuis les données staff d'AniList.
     * Filtre pour ne garder que les rôles d'auteur (Story, Art, Story & Art).
     *
     * @param array<int, array<string, mixed>> $staffEdges
     */
    private function extractAniListAuthors(array $staffEdges): ?string
    {
        $authorRoles = ['Art', 'Original Creator', 'Original Story', 'Story', 'Story & Art'];
        $authors = [];

        foreach ($staffEdges as $edge) {
            $role = $edge['role'] ?? '';
            if (\in_array($role, $authorRoles, true)) {
                $node = $edge['node'] ?? null;
                $nameData = \is_array($node) ? ($node['name'] ?? null) : null;
                $name = \is_array($nameData) ? ($nameData['full'] ?? null) : null;
                if (\is_string($name) && !\in_array($name, $authors, true)) {
                    $authors[] = $name;
                }
            }
        }

        return \count($authors) > 0 ? \implode(', ', $authors) : null;
    }

    /**
     * Formate une date AniList en chaîne lisible.
     *
     * @param array{year?: int|null, month?: int|null, day?: int|null} $date
     */
    private function formatAniListDate(array $date): ?string
    {
        $year = $date['year'] ?? null;
        $month = $date['month'] ?? null;
        $day = $date['day'] ?? null;

        if (null === $year) {
            return null;
        }

        if (null !== $month && null !== $day) {
            return \sprintf('%04d-%02d-%02d', $year, $month, $day);
        }

        if (null !== $month) {
            return \sprintf('%04d-%02d', $year, $month);
        }

        return (string) $year;
    }

    /**
     * Fusionne les résultats existants avec les données AniList.
     * AniList complète les champs manquants et peut remplacer la couverture si elle est de meilleure qualité.
     *
     * @param array<string, mixed> $current
     * @param array<string, mixed> $anilist
     *
     * @return array<string, mixed>
     */
    private function mergeWithAniList(array $current, array $anilist): array
    {
        // Ajoute AniList aux sources
        $sources = \is_array($current['sources'] ?? null) ? $current['sources'] : [];
        $sources[] = 'anilist';
        $current['sources'] = $sources;

        // Champs à compléter (AniList ne remplace pas, il complète)
        $fieldsToComplete = ['authors', 'description', 'publishedDate'];

        foreach ($fieldsToComplete as $field) {
            if (empty($current[$field]) && !empty($anilist[$field])) {
                $current[$field] = $anilist[$field];
            }
        }

        // Pour la couverture, AniList a souvent de meilleures images (plus grandes)
        // On remplace si AniList a une image et que l'actuelle vient de Google/OpenLibrary
        if (!empty($anilist['thumbnail'])) {
            $current['thumbnail'] = $anilist['thumbnail'];
        }

        // Pour isOneShot, AniList est plus fiable que Google Books pour les mangas
        if (isset($anilist['isOneShot'])) {
            $current['isOneShot'] = $anilist['isOneShot'];
        }

        return $current;
    }
}

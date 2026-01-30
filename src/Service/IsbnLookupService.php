<?php

declare(strict_types=1);

namespace App\Service;

use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Service de recherche d'informations sur un livre/BD/manga par ISBN.
 * Interroge Google Books et Open Library, puis fusionne les résultats.
 */
class IsbnLookupService
{
    private const GOOGLE_BOOKS_API = 'https://www.googleapis.com/books/v1/volumes';
    private const OPEN_LIBRARY_API = 'https://openlibrary.org/isbn/';

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * Recherche les informations d'un livre par ISBN.
     * Interroge les deux API et fusionne les résultats pour maximiser les données.
     *
     * @return array{title?: string, authors?: string, publisher?: string, publishedDate?: string, description?: string, thumbnail?: string, sources?: array}|null
     */
    public function lookup(string $isbn): ?array
    {
        // Nettoie l'ISBN (supprime les tirets et espaces)
        $isbn = \preg_replace('/[\s-]/', '', $isbn) ?? '';

        if ('' === $isbn) {
            return null;
        }

        // Interroge les deux API
        $googleResult = $this->lookupGoogleBooks($isbn);
        $openLibraryResult = $this->lookupOpenLibrary($isbn);

        // Si aucune API n'a de résultat
        if (null === $googleResult && null === $openLibraryResult) {
            return null;
        }

        // Fusionne les résultats (Google Books prioritaire, Open Library complète)
        return $this->mergeResults($googleResult, $openLibraryResult);
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
        } catch (\Throwable $e) {
            $this->logger->warning('Erreur lors de la recherche Google Books pour ISBN {isbn}: {error}', [
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
        } catch (\Throwable $e) {
            $this->logger->warning('Erreur lors de la recherche Open Library pour ISBN {isbn}: {error}', [
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
}

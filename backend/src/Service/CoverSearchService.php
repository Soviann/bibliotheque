<?php

declare(strict_types=1);

namespace App\Service;

use App\DTO\CoverSearchResult;
use App\Enum\ComicType;
use App\Service\Lookup\GoogleBooksUrlHelper;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * Recherche de couvertures via Google Books + Serper Images.
 */
final class CoverSearchService
{
    private const string GOOGLE_BOOKS_URL = 'https://www.googleapis.com/books/v1/volumes';
    private const string SERPER_URL = 'https://google.serper.dev/images';

    public function __construct(
        #[Autowire('%env(GOOGLE_BOOKS_API_KEY)%')]
        private readonly string $googleBooksApiKey,
        private readonly HttpClientInterface $httpClient,
        private readonly LoggerInterface $logger,
        #[Autowire('%env(SERPER_API_KEY)%')]
        private readonly string $serperApiKey,
    ) {
    }

    /**
     * Recherche des images de couverture pour une série.
     * Combine Google Books (couvertures de livres) et Serper (images web).
     *
     * @return CoverSearchResult[]
     */
    public function search(string $query, ?ComicType $type = null): array
    {
        $serperQuery = $query.' '.$this->getQuerySuffix($type);

        // Lancer les deux requêtes en parallèle
        $googleBooksResponse = $this->requestGoogleBooks($query);
        $serperResponse = $this->requestSerper($serperQuery);

        $googleBooksResults = $this->parseGoogleBooksResponse($googleBooksResponse);
        $serperResults = $this->parseSerperResponse($serperResponse);

        // Google Books en premier (plus pertinents pour les couvertures), puis Serper
        return \array_values(\array_merge($googleBooksResults, $serperResults));
    }

    /**
     * Retourne le suffixe de recherche selon le type.
     */
    private function getQuerySuffix(?ComicType $type): string
    {
        return match ($type) {
            ComicType::MANGA => 'cover',
            default => 'couverture',
        };
    }

    /**
     * @return CoverSearchResult[]
     */
    private function parseGoogleBooksResponse(?ResponseInterface $response): array
    {
        if (!$response instanceof ResponseInterface) {
            return [];
        }

        try {
            $data = $response->toArray();
        } catch (\Throwable $e) {
            $this->logger->error('Erreur Google Books cover search : {message}', [
                'message' => $e->getMessage(),
            ]);

            return [];
        }

        $items = $data['items'] ?? [];
        $results = [];

        foreach ($items as $item) {
            $volumeInfo = $item['volumeInfo'] ?? [];
            if (!\is_array($volumeInfo)) {
                continue;
            }

            $imageLinks = $volumeInfo['imageLinks'] ?? null;
            $rawThumbnail = \is_array($imageLinks)
                ? ($imageLinks['thumbnail'] ?? $imageLinks['smallThumbnail'] ?? null)
                : null;

            if (!\is_string($rawThumbnail)) {
                continue;
            }

            $url = GoogleBooksUrlHelper::optimizeThumbnailUrl($rawThumbnail);
            $title = \is_string($volumeInfo['title'] ?? null) ? $volumeInfo['title'] : '';

            $results[] = new CoverSearchResult(
                height: 0,
                thumbnail: $url,
                title: $title,
                url: $url,
                width: 0,
            );
        }

        return $results;
    }

    /**
     * @return CoverSearchResult[]
     */
    private function parseSerperResponse(?ResponseInterface $response): array
    {
        if (!$response instanceof ResponseInterface) {
            return [];
        }

        try {
            $data = $response->toArray();
        } catch (\Throwable $e) {
            $this->logger->error('Erreur Serper cover search : {message}', [
                'message' => $e->getMessage(),
            ]);

            return [];
        }

        $images = $data['images'] ?? [];

        return \array_map(
            static fn (array $image): CoverSearchResult => new CoverSearchResult(
                height: (int) ($image['imageHeight'] ?? 0),
                thumbnail: $image['thumbnailUrl'] ?? $image['imageUrl'] ?? '',
                title: $image['title'] ?? '',
                url: $image['imageUrl'] ?? '',
                width: (int) ($image['imageWidth'] ?? 0),
            ),
            $images,
        );
    }

    private function requestGoogleBooks(string $query): ?ResponseInterface
    {
        if ('' === $this->googleBooksApiKey) {
            return null;
        }

        try {
            return $this->httpClient->request('GET', self::GOOGLE_BOOKS_URL, [
                'query' => [
                    'key' => $this->googleBooksApiKey,
                    'maxResults' => 5,
                    'q' => $query,
                ],
                'timeout' => 10,
            ]);
        } catch (\Throwable $e) {
            $this->logger->error('Erreur requête Google Books : {message}', [
                'message' => $e->getMessage(),
            ]);

            return null;
        }
    }

    private function requestSerper(string $query): ?ResponseInterface
    {
        if ('' === $this->serperApiKey) {
            return null;
        }

        try {
            return $this->httpClient->request('POST', self::SERPER_URL, [
                'headers' => [
                    'Content-Type' => 'application/json',
                    'X-API-KEY' => $this->serperApiKey,
                ],
                'json' => [
                    'num' => 10,
                    'q' => $query,
                ],
                'timeout' => 10,
            ]);
        } catch (\Throwable $e) {
            $this->logger->error('Erreur requête Serper : {message}', [
                'message' => $e->getMessage(),
            ]);

            return null;
        }
    }
}

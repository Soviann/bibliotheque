<?php

declare(strict_types=1);

namespace App\Service\Lookup;

use App\Enum\ApiLookupStatus;
use App\Enum\ComicType;
use App\Enum\LookupMode;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\DecodingExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * Provider de recherche via l'API Google Books.
 */
#[AutoconfigureTag('app.lookup_provider', ['priority' => 100])]
final class GoogleBooksLookup extends AbstractLookupProvider implements MultiResultLookupProviderInterface
{
    private const string API_URL = 'https://www.googleapis.com/books/v1/volumes';

    public function __construct(
        #[Autowire('%env(GOOGLE_BOOKS_API_KEY)%')]
        private readonly string $apiKey,
        private readonly HttpClientInterface $httpClient,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function getFieldPriority(string $field, ?ComicType $type = null): int
    {
        return 100;
    }

    public function getName(): string
    {
        return 'google_books';
    }

    public function prepareMultipleLookup(string $query, ?ComicType $type, int $limit): mixed
    {
        $this->resetApiMessage();

        $queryParams = [
            'maxResults' => \min($limit * 8, 40),
            'q' => $query,
        ];

        if ('' !== $this->apiKey) {
            $queryParams['key'] = $this->apiKey;
        }

        return $this->httpClient->request('GET', self::API_URL, [
            'query' => $queryParams,
            'timeout' => 10,
        ]);
    }

    public function prepareLookup(string $query, ?ComicType $type, LookupMode $mode = LookupMode::TITLE): mixed
    {
        $this->resetApiMessage();

        $q = LookupMode::ISBN === $mode ? 'isbn:'.$query : $query;

        $query = [
            'maxResults' => 10,
            'q' => $q,
        ];

        if ('' !== $this->apiKey) {
            $query['key'] = $this->apiKey;
        }

        return $this->httpClient->request('GET', self::API_URL, [
            'query' => $query,
            'timeout' => 10,
        ]);
    }

    public function resolveMultipleLookup(mixed $state): array
    {
        \assert($state instanceof ResponseInterface);
        try {
            $data = $state->toArray();

            if (empty($data['items'])) {
                $this->recordApiMessage(ApiLookupStatus::NOT_FOUND, 'Aucun résultat');

                return [];
            }

            $results = $this->groupItemsByTitle($data['items']);
            $this->recordApiMessage(ApiLookupStatus::SUCCESS, \sprintf('%d résultat(s) trouvé(s)', \count($results)));

            return $results;
        } catch (TransportExceptionInterface $e) {
            $this->logger->error('Erreur réseau Google Books : {error}', [
                'error' => $e->getMessage(),
            ]);
            $this->recordApiMessage(ApiLookupStatus::ERROR, 'Erreur de connexion');

            return [];
        } catch (ClientExceptionInterface|ServerExceptionInterface|RedirectionExceptionInterface $e) {
            $code = $e->getResponse()->getStatusCode();
            if (429 === $code) {
                $this->recordApiMessage(ApiLookupStatus::RATE_LIMITED, 'Quota dépassé (429)');
            } else {
                $this->recordApiMessage(ApiLookupStatus::ERROR, \sprintf('Erreur HTTP (%d)', $code));
            }
            $this->logger->warning('Erreur HTTP Google Books : {error}', [
                'error' => $e->getMessage(),
            ]);

            return [];
        } catch (DecodingExceptionInterface $e) {
            $this->logger->error('Réponse JSON invalide de Google Books : {error}', [
                'error' => $e->getMessage(),
            ]);
            $this->recordApiMessage(ApiLookupStatus::ERROR, 'Réponse invalide');

            return [];
        }
    }

    public function resolveLookup(mixed $state): ?LookupResult
    {
        \assert($state instanceof ResponseInterface);
        try {
            $data = $state->toArray();

            if (empty($data['items'])) {
                $this->recordApiMessage(ApiLookupStatus::NOT_FOUND, 'Aucun résultat');

                return null;
            }

            $result = $this->mergeItems($data['items']);
            $this->recordApiMessage(ApiLookupStatus::SUCCESS, 'Données trouvées');

            return $result;
        } catch (TransportExceptionInterface $e) {
            $this->logger->error('Erreur réseau Google Books : {error}', [
                'error' => $e->getMessage(),
            ]);
            $this->recordApiMessage(ApiLookupStatus::ERROR, 'Erreur de connexion');

            return null;
        } catch (ClientExceptionInterface|ServerExceptionInterface|RedirectionExceptionInterface $e) {
            $code = $e->getResponse()->getStatusCode();
            if (429 === $code) {
                $this->recordApiMessage(ApiLookupStatus::RATE_LIMITED, 'Quota dépassé (429)');
            } else {
                $this->recordApiMessage(ApiLookupStatus::ERROR, \sprintf('Erreur HTTP (%d)', $code));
            }
            $this->logger->warning('Erreur HTTP Google Books : {error}', [
                'error' => $e->getMessage(),
            ]);

            return null;
        } catch (DecodingExceptionInterface $e) {
            $this->logger->error('Réponse JSON invalide de Google Books : {error}', [
                'error' => $e->getMessage(),
            ]);
            $this->recordApiMessage(ApiLookupStatus::ERROR, 'Réponse invalide');

            return null;
        }
    }

    public function supports(LookupMode $mode, ?ComicType $type): bool
    {
        return true;
    }

    protected function getLogger(): LoggerInterface
    {
        return $this->logger;
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
     * Regroupe les items par titre distinct et fusionne chaque groupe.
     *
     * @param array<int, array<string, mixed>> $items
     *
     * @return list<LookupResult>
     */
    private function groupItemsByTitle(array $items): array
    {
        /** @var array<string, list<array<string, mixed>>> $groups */
        $groups = [];

        foreach ($items as $item) {
            $volumeInfo = $item['volumeInfo'] ?? [];
            if (!\is_array($volumeInfo)) {
                continue;
            }

            $title = \is_string($volumeInfo['title'] ?? null) ? $volumeInfo['title'] : null;
            if (null === $title) {
                continue;
            }

            $normalizedTitle = $this->normalizeTitle($title);
            $groups[$normalizedTitle][] = $item;
        }

        $results = [];
        foreach ($groups as $groupItems) {
            $results[] = $this->mergeItems($groupItems);
        }

        return $results;
    }

    /**
     * Fusionne les données de plusieurs résultats Google Books.
     *
     * @param array<int, array<string, mixed>> $items
     */
    private function mergeItems(array $items): LookupResult
    {
        $authors = null;
        $description = null;
        $isbn = null;
        $isOneShot = null;
        $publishedDate = null;
        $publisher = null;
        $thumbnail = null;
        $title = null;

        foreach ($items as $item) {
            $volumeInfo = $item['volumeInfo'] ?? [];
            if (!\is_array($volumeInfo)) {
                continue;
            }

            /** @var array<string, mixed> $volumeInfo */
            if (null === $authors && !empty($volumeInfo['authors']) && \is_array($volumeInfo['authors'])) {
                /** @var array<string> $authorList */
                $authorList = $volumeInfo['authors'];
                $authors = \implode(', ', $authorList);
            }

            if (null === $description && !empty($volumeInfo['description']) && \is_string($volumeInfo['description'])) {
                $description = $volumeInfo['description'];
            }

            if (null === $publishedDate && !empty($volumeInfo['publishedDate']) && \is_string($volumeInfo['publishedDate'])) {
                $publishedDate = $volumeInfo['publishedDate'];
            }

            if (null === $publisher && !empty($volumeInfo['publisher']) && \is_string($volumeInfo['publisher'])) {
                $publisher = $volumeInfo['publisher'];
            }

            if (null === $thumbnail) {
                $imageLinks = $volumeInfo['imageLinks'] ?? null;
                $rawThumbnail = \is_array($imageLinks)
                    ? ($imageLinks['thumbnail'] ?? $imageLinks['smallThumbnail'] ?? null)
                    : null;

                if (\is_string($rawThumbnail)) {
                    $thumbnail = GoogleBooksUrlHelper::optimizeThumbnailUrl($rawThumbnail);
                }
            }

            if (null === $title && !empty($volumeInfo['title']) && \is_string($volumeInfo['title'])) {
                $title = $volumeInfo['title'];
            }

            if (null === $isbn && !empty($volumeInfo['industryIdentifiers']) && \is_array($volumeInfo['industryIdentifiers'])) {
                $isbn = $this->extractIsbnFromIdentifiers($volumeInfo['industryIdentifiers']);
            }

            if (null === $isOneShot && \array_key_exists('seriesInfo', $volumeInfo) && null !== $volumeInfo['seriesInfo']) {
                $isOneShot = false;
            }

            if (!\in_array(null, [$authors, $description, $publishedDate, $publisher, $thumbnail, $title], true)) {
                break;
            }
        }

        return new LookupResult(
            authors: $authors,
            description: $description,
            isbn: $isbn,
            isOneShot: $isOneShot,
            publishedDate: $publishedDate,
            publisher: $publisher,
            source: 'google_books',
            thumbnail: $thumbnail,
            title: $title,
        );
    }

    /**
     * Normalise un titre pour le regroupement (supprime suffixes de tome/volume, casse).
     */
    private function normalizeTitle(string $title): string
    {
        $patterns = [
            '/\s*[-–—]\s*(?:T(?:ome)?|Vol(?:ume)?|V)\.?\s*\d+.*$/iu',
            '/\s+(?:T(?:ome)?|Vol(?:ume)?|V)\.?\s*\d+.*$/iu',
            '/\s*#\d+.*$/u',
            '/\s*\(\d+\)\s*$/u',
            '/\s+\d+\s*$/u',
        ];

        $normalized = $title;
        foreach ($patterns as $pattern) {
            $normalized = \preg_replace($pattern, '', $normalized) ?? $normalized;
        }

        return \mb_strtolower(\trim($normalized));
    }
}

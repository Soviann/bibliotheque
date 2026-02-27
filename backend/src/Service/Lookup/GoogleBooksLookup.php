<?php

declare(strict_types=1);

namespace App\Service\Lookup;

use App\Enum\ApiLookupStatus;
use App\Enum\ComicType;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\DecodingExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Provider de recherche via l'API Google Books.
 */
#[AutoconfigureTag('app.lookup_provider', ['priority' => 100])]
class GoogleBooksLookup implements LookupProviderInterface
{
    private const string API_URL = 'https://www.googleapis.com/books/v1/volumes';

    /** @var array{status: string, message: string}|null */
    private ?array $lastApiMessage = null;

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function getLastApiMessage(): ?array
    {
        return $this->lastApiMessage;
    }

    public function getFieldPriority(string $field, ?ComicType $type = null): int
    {
        return 100;
    }

    public function getName(): string
    {
        return 'google_books';
    }

    public function prepareLookup(string $query, ?ComicType $type, string $mode = 'title'): mixed
    {
        $this->lastApiMessage = null;

        $q = 'isbn' === $mode ? 'isbn:'.$query : $query;

        return $this->httpClient->request('GET', self::API_URL, [
            'query' => [
                'maxResults' => 10,
                'q' => $q,
            ],
            'timeout' => 10,
        ]);
    }

    public function resolveLookup(mixed $state): ?LookupResult
    {
        /* @var \Symfony\Contracts\HttpClient\ResponseInterface $state */
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

    public function supports(string $mode, ?ComicType $type): bool
    {
        return \in_array($mode, ['isbn', 'title'], true);
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

            if (null === $authors && !empty($volumeInfo['authors'])) {
                $authors = \implode(', ', $volumeInfo['authors']);
            }

            if (null === $description && !empty($volumeInfo['description'])) {
                $description = $volumeInfo['description'];
            }

            if (null === $publishedDate && !empty($volumeInfo['publishedDate'])) {
                $publishedDate = $volumeInfo['publishedDate'];
            }

            if (null === $publisher && !empty($volumeInfo['publisher'])) {
                $publisher = $volumeInfo['publisher'];
            }

            if (null === $thumbnail) {
                $rawThumbnail = $volumeInfo['imageLinks']['thumbnail']
                    ?? $volumeInfo['imageLinks']['smallThumbnail']
                    ?? null;

                if (\is_string($rawThumbnail)) {
                    $thumbnail = $this->optimizeThumbnailUrl($rawThumbnail);
                }
            }

            if (null === $title && !empty($volumeInfo['title'])) {
                $title = $volumeInfo['title'];
            }

            if (null === $isbn && \is_array($volumeInfo) && !empty($volumeInfo['industryIdentifiers']) && \is_array($volumeInfo['industryIdentifiers'])) {
                $isbn = $this->extractIsbnFromIdentifiers($volumeInfo['industryIdentifiers']);
            }

            if (null === $isOneShot && \is_array($volumeInfo) && \array_key_exists('seriesInfo', $volumeInfo) && null !== $volumeInfo['seriesInfo']) {
                $isOneShot = false;
            }

            if (null !== $authors && null !== $description && null !== $publishedDate && null !== $publisher && null !== $thumbnail && null !== $title) {
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
     * Optimise l'URL d'une couverture Google Books pour obtenir une meilleure résolution.
     *
     * - Remplace zoom=1 par zoom=0 (plus grande image disponible)
     * - Supprime edge=curl (effet de page cornée)
     * - Force HTTPS
     */
    private function optimizeThumbnailUrl(string $url): string
    {
        if (!\str_contains($url, 'books.google.com/')) {
            return $url;
        }

        $url = (string) \preg_replace('#^http://#', 'https://', $url);
        $url = \str_replace('zoom=1', 'zoom=0', $url);
        $url = (string) \preg_replace('/&?edge=curl&?/', '&', $url);

        return \rtrim($url, '&');
    }

    private function recordApiMessage(ApiLookupStatus $status, string $message): void
    {
        $this->lastApiMessage = ['message' => $message, 'status' => $status->value];
    }
}

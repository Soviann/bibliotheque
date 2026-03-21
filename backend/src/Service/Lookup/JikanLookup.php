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
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * Provider de recherche via l'API Jikan (MyAnimeList, manga uniquement, par titre).
 */
#[AutoconfigureTag('app.lookup_provider', ['priority' => 50])]
final class JikanLookup extends AbstractLookupProvider implements MultiResultLookupProviderInterface
{
    private const string API_URL = 'https://api.jikan.moe/v4/manga';

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function getFieldPriority(string $field, ?ComicType $type = null): int
    {
        if (ComicType::MANGA === $type && \in_array($field, ['description', 'latestPublishedIssue'], true)) {
            return 65;
        }

        return 50;
    }

    public function getName(): string
    {
        return 'jikan';
    }

    public function prepareMultipleLookup(string $query, ?ComicType $type, int $limit): mixed
    {
        $this->resetApiMessage();

        return $this->httpClient->request('GET', self::API_URL, [
            'query' => [
                'limit' => $limit,
                'q' => LookupTitleCleaner::clean($query),
                'type' => 'manga',
            ],
            'timeout' => 10,
        ]);
    }

    public function prepareLookup(string $query, ?ComicType $type, string $mode = 'title'): mixed
    {
        $this->resetApiMessage();

        return $this->httpClient->request('GET', self::API_URL, [
            'query' => [
                'limit' => 1,
                'q' => LookupTitleCleaner::clean($query),
                'type' => 'manga',
            ],
            'timeout' => 10,
        ]);
    }

    public function resolveMultipleLookup(mixed $state): array
    {
        \assert($state instanceof ResponseInterface);

        try {
            $data = $state->toArray();
            $items = $data['data'] ?? [];

            if (0 === \count($items)) {
                $this->recordApiMessage(ApiLookupStatus::NOT_FOUND, 'Aucun résultat');

                return [];
            }

            $results = [];
            foreach ($items as $item) {
                $results[] = $this->buildResultFromItem($item);
            }

            $this->recordApiMessage(ApiLookupStatus::SUCCESS, \sprintf('%d résultat(s) trouvé(s)', \count($results)));

            return $results;
        } catch (TransportExceptionInterface $e) {
            $this->logger->error('Erreur réseau Jikan : {error}', ['error' => $e->getMessage()]);
            $this->recordApiMessage(ApiLookupStatus::ERROR, 'Erreur de connexion');

            return [];
        } catch (ClientExceptionInterface|ServerExceptionInterface|RedirectionExceptionInterface $e) {
            $this->handleHttpException($e);

            return [];
        } catch (DecodingExceptionInterface $e) {
            $this->logger->error('Réponse JSON invalide de Jikan : {error}', ['error' => $e->getMessage()]);
            $this->recordApiMessage(ApiLookupStatus::ERROR, 'Réponse invalide');

            return [];
        }
    }

    public function resolveLookup(mixed $state): ?LookupResult
    {
        \assert($state instanceof ResponseInterface);

        try {
            $data = $state->toArray();
            $items = $data['data'] ?? [];

            if (0 === \count($items)) {
                $this->recordApiMessage(ApiLookupStatus::NOT_FOUND, 'Aucun résultat');

                return null;
            }

            $result = $this->buildResultFromItem($items[0]);
            $this->recordApiMessage(ApiLookupStatus::SUCCESS, 'Données trouvées');

            return $result;
        } catch (TransportExceptionInterface $e) {
            $this->logger->error('Erreur réseau Jikan : {error}', ['error' => $e->getMessage()]);
            $this->recordApiMessage(ApiLookupStatus::ERROR, 'Erreur de connexion');

            return null;
        } catch (ClientExceptionInterface|ServerExceptionInterface|RedirectionExceptionInterface $e) {
            $this->handleHttpException($e);

            return null;
        } catch (DecodingExceptionInterface $e) {
            $this->logger->error('Réponse JSON invalide de Jikan : {error}', ['error' => $e->getMessage()]);
            $this->recordApiMessage(ApiLookupStatus::ERROR, 'Réponse invalide');

            return null;
        }
    }

    public function supports(string $mode, ?ComicType $type): bool
    {
        return 'title' === $mode && ComicType::MANGA === $type;
    }

    /**
     * Construit un LookupResult depuis un item Jikan.
     *
     * @param array<string, mixed> $item
     */
    private function buildResultFromItem(array $item): LookupResult
    {
        $title = $item['title_english'] ?? $item['title'] ?? null;
        $title = \is_string($title) ? $title : null;

        $synopsis = $item['synopsis'] ?? null;
        $synopsis = \is_string($synopsis) ? $synopsis : null;

        $volumes = $item['volumes'] ?? null;
        $volumes = \is_int($volumes) ? $volumes : null;

        $imageUrl = null;
        $images = $item['images'] ?? null;
        if (\is_array($images)) {
            $jpg = $images['jpg'] ?? null;
            if (\is_array($jpg)) {
                $imageUrl = $jpg['large_image_url'] ?? $jpg['image_url'] ?? null;
                $imageUrl = \is_string($imageUrl) ? $imageUrl : null;
            }
        }

        $authors = $this->extractAuthors($item['authors'] ?? null);

        $publishedDate = null;
        $published = $item['published'] ?? null;
        if (\is_array($published)) {
            $from = $published['from'] ?? null;
            if (\is_string($from)) {
                $publishedDate = \substr($from, 0, 10);
            }
        }

        $type = $item['type'] ?? null;
        $status = $item['status'] ?? null;
        $isOneShot = 'One-shot' === $type || (1 === $volumes && 'Finished' === $status);

        return new LookupResult(
            authors: $authors,
            description: $synopsis,
            isOneShot: $isOneShot,
            latestPublishedIssue: $volumes,
            publishedDate: $publishedDate,
            source: 'jikan',
            thumbnail: $imageUrl,
            title: $title,
        );
    }

    /**
     * Extrait les auteurs depuis le tableau Jikan.
     */
    private function extractAuthors(mixed $authorsData): ?string
    {
        if (!\is_array($authorsData) || 0 === \count($authorsData)) {
            return null;
        }

        $names = [];
        foreach ($authorsData as $author) {
            if (\is_array($author) && \is_string($author['name'] ?? null)) {
                $names[] = $author['name'];
            }
        }

        return \count($names) > 0 ? \implode(', ', $names) : null;
    }

    protected function getLogger(): LoggerInterface
    {
        return $this->logger;
    }
}

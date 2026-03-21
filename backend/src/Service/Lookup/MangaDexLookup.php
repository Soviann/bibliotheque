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
 * Provider de recherche via l'API MangaDex (manga uniquement, par titre).
 */
#[AutoconfigureTag('app.lookup_provider', ['priority' => 40])]
final class MangaDexLookup extends AbstractLookupProvider implements MultiResultLookupProviderInterface
{
    private const string API_URL = 'https://api.mangadex.org/manga';
    private const string COVER_BASE_URL = 'https://uploads.mangadex.org/covers';

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function getFieldPriority(string $field, ?ComicType $type = null): int
    {
        if (ComicType::MANGA === $type && 'authors' === $field) {
            return 55;
        }

        return 40;
    }

    public function getName(): string
    {
        return 'mangadex';
    }

    public function prepareMultipleLookup(string $query, ?ComicType $type, int $limit): mixed
    {
        $this->resetApiMessage();

        return $this->httpClient->request('GET', self::API_URL, [
            'query' => [
                'includes[]' => ['cover_art', 'author', 'artist'],
                'limit' => $limit,
                'title' => LookupTitleCleaner::clean($query),
            ],
            'timeout' => 10,
        ]);
    }

    public function prepareLookup(string $query, ?ComicType $type, string $mode = 'title'): mixed
    {
        $this->resetApiMessage();

        return $this->httpClient->request('GET', self::API_URL, [
            'query' => [
                'includes[]' => ['cover_art', 'author', 'artist'],
                'limit' => 1,
                'title' => LookupTitleCleaner::clean($query),
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
            $this->logger->error('Erreur réseau MangaDex : {error}', ['error' => $e->getMessage()]);
            $this->recordApiMessage(ApiLookupStatus::ERROR, 'Erreur de connexion');

            return [];
        } catch (ClientExceptionInterface|ServerExceptionInterface|RedirectionExceptionInterface $e) {
            $this->handleHttpException($e);

            return [];
        } catch (DecodingExceptionInterface $e) {
            $this->logger->error('Réponse JSON invalide de MangaDex : {error}', ['error' => $e->getMessage()]);
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
            $this->logger->error('Erreur réseau MangaDex : {error}', ['error' => $e->getMessage()]);
            $this->recordApiMessage(ApiLookupStatus::ERROR, 'Erreur de connexion');

            return null;
        } catch (ClientExceptionInterface|ServerExceptionInterface|RedirectionExceptionInterface $e) {
            $this->handleHttpException($e);

            return null;
        } catch (DecodingExceptionInterface $e) {
            $this->logger->error('Réponse JSON invalide de MangaDex : {error}', ['error' => $e->getMessage()]);
            $this->recordApiMessage(ApiLookupStatus::ERROR, 'Réponse invalide');

            return null;
        }
    }

    public function supports(string $mode, ?ComicType $type): bool
    {
        return 'title' === $mode && ComicType::MANGA === $type;
    }

    /**
     * Construit un LookupResult depuis un item MangaDex.
     *
     * @param array<string, mixed> $item
     */
    private function buildResultFromItem(array $item): LookupResult
    {
        $mangaId = \is_string($item['id'] ?? null) ? $item['id'] : '';

        /** @var array<string, mixed> $attrs */
        $attrs = \is_array($item['attributes'] ?? null) ? $item['attributes'] : [];

        /** @var array<string, string> $titleMap */
        $titleMap = \is_array($attrs['title'] ?? null) ? $attrs['title'] : [];
        $title = $titleMap['en'] ?? $titleMap['ja-ro'] ?? \reset($titleMap) ?: null;
        $title = \is_string($title) ? $title : null;

        $descMap = \is_array($attrs['description'] ?? null) ? $attrs['description'] : [];
        $description = $descMap['en'] ?? $descMap['fr'] ?? \reset($descMap) ?: null;
        $description = \is_string($description) ? $description : null;

        $lastVolume = $attrs['lastVolume'] ?? null;
        $latestPublishedIssue = null;
        if (\is_string($lastVolume) && \ctype_digit($lastVolume)) {
            $latestPublishedIssue = (int) $lastVolume;
        }

        $year = $attrs['year'] ?? null;
        $publishedDate = \is_int($year) ? (string) $year : null;

        $status = $attrs['status'] ?? null;
        $isOneShot = 1 === $latestPublishedIssue && 'completed' === $status;

        /** @var list<array<string, mixed>> $relationships */
        $relationships = \is_array($item['relationships'] ?? null) ? $item['relationships'] : [];

        $thumbnail = $this->extractCoverUrl($mangaId, $relationships);
        $authors = $this->extractAuthors($relationships);

        return new LookupResult(
            authors: $authors,
            description: $description,
            isOneShot: $isOneShot,
            latestPublishedIssue: $latestPublishedIssue,
            publishedDate: $publishedDate,
            source: 'mangadex',
            thumbnail: $thumbnail,
            title: $title,
        );
    }

    /**
     * Extrait les noms des auteurs et artistes depuis les relations.
     *
     * @param list<array<string, mixed>> $relationships
     */
    private function extractAuthors(array $relationships): ?string
    {
        $names = [];

        foreach ($relationships as $rel) {
            $type = $rel['type'] ?? null;
            if (\in_array($type, ['author', 'artist'], true)) {
                $attrs = $rel['attributes'] ?? null;
                if (\is_array($attrs) && \is_string($attrs['name'] ?? null)) {
                    $name = $attrs['name'];
                    if (!\in_array($name, $names, true)) {
                        $names[] = $name;
                    }
                }
            }
        }

        return \count($names) > 0 ? \implode(', ', $names) : null;
    }

    /**
     * Extrait l'URL de couverture depuis les relations cover_art.
     *
     * @param list<array<string, mixed>> $relationships
     */
    private function extractCoverUrl(string $mangaId, array $relationships): ?string
    {
        foreach ($relationships as $rel) {
            if ('cover_art' === ($rel['type'] ?? null)) {
                $attrs = $rel['attributes'] ?? null;
                if (\is_array($attrs) && \is_string($attrs['fileName'] ?? null)) {
                    return \sprintf('%s/%s/%s', self::COVER_BASE_URL, $mangaId, $attrs['fileName']);
                }
            }
        }

        return null;
    }

    protected function getLogger(): LoggerInterface
    {
        return $this->logger;
    }
}

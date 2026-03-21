<?php

declare(strict_types=1);

namespace App\Service\Lookup\Provider;

use App\Enum\ApiLookupStatus;
use App\Enum\ComicType;
use App\Enum\LookupMode;
use App\Service\Lookup\Contract\LookupResult;
use App\Service\Lookup\Contract\MultiResultLookupProviderInterface;
use App\Service\Lookup\Util\LookupTitleCleaner;
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
 * Provider de recherche via l'API AniList (manga uniquement, par titre).
 */
#[AutoconfigureTag('app.lookup_provider', ['priority' => 60])]
final class AniListLookup extends AbstractLookupProvider implements MultiResultLookupProviderInterface
{
    private const string API_URL = 'https://graphql.anilist.co';

    private const string GRAPHQL_QUERY = <<<'GRAPHQL'
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

    private const string GRAPHQL_QUERY_PAGE = <<<'GRAPHQL'
        query ($search: String, $perPage: Int) {
            Page(perPage: $perPage) {
                media(search: $search, type: MANGA) {
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
        }
        GRAPHQL;

    /** @var list<string> */
    private const array AUTHOR_ROLES = ['Art', 'Original Creator', 'Original Story', 'Story', 'Story & Art'];

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function getFieldPriority(string $field, ?ComicType $type = null): int
    {
        if (ComicType::MANGA === $type && \in_array($field, ['isOneShot', 'thumbnail'], true)) {
            return 200;
        }

        return 60;
    }

    public function getName(): string
    {
        return 'anilist';
    }

    public function prepareMultipleLookup(string $query, ?ComicType $type, int $limit): mixed
    {
        $this->resetApiMessage();

        $searchTitle = LookupTitleCleaner::clean($query);

        return $this->httpClient->request('POST', self::API_URL, [
            'headers' => [
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
            ],
            'json' => [
                'query' => self::GRAPHQL_QUERY_PAGE,
                'variables' => ['perPage' => $limit, 'search' => $searchTitle],
            ],
            'timeout' => 10,
        ]);
    }

    public function prepareLookup(string $query, ?ComicType $type, LookupMode $mode = LookupMode::TITLE): mixed
    {
        $this->resetApiMessage();

        $searchTitle = LookupTitleCleaner::clean($query);

        return $this->httpClient->request('POST', self::API_URL, [
            'headers' => [
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
            ],
            'json' => [
                'query' => self::GRAPHQL_QUERY,
                'variables' => ['search' => $searchTitle],
            ],
            'timeout' => 10,
        ]);
    }

    public function resolveLookup(mixed $state): ?LookupResult
    {
        \assert($state instanceof ResponseInterface);
        try {
            $data = $state->toArray();
            $media = $data['data']['Media'] ?? null;

            if (null === $media) {
                $this->recordApiMessage(ApiLookupStatus::NOT_FOUND, 'Aucun résultat');

                return null;
            }

            $result = $this->buildResultFromMedia($media);
            $this->recordApiMessage(ApiLookupStatus::SUCCESS, 'Données trouvées');

            return $result;
        } catch (TransportExceptionInterface $e) {
            $this->logger->error('Erreur réseau AniList : {error}', [
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
            $this->logger->warning('Erreur HTTP AniList : {error}', [
                'error' => $e->getMessage(),
            ]);

            return null;
        } catch (DecodingExceptionInterface $e) {
            $this->logger->error('Réponse JSON invalide de AniList : {error}', [
                'error' => $e->getMessage(),
            ]);
            $this->recordApiMessage(ApiLookupStatus::ERROR, 'Réponse invalide');

            return null;
        }
    }

    public function resolveMultipleLookup(mixed $state): array
    {
        \assert($state instanceof ResponseInterface);
        try {
            $data = $state->toArray();
            $mediaList = $data['data']['Page']['media'] ?? [];

            if (0 === \count($mediaList)) {
                $this->recordApiMessage(ApiLookupStatus::NOT_FOUND, 'Aucun résultat');

                return [];
            }

            $results = [];
            foreach ($mediaList as $media) {
                $results[] = $this->buildResultFromMedia($media);
            }

            $this->recordApiMessage(ApiLookupStatus::SUCCESS, \sprintf('%d résultat(s) trouvé(s)', \count($results)));

            return $results;
        } catch (TransportExceptionInterface $e) {
            $this->logger->error('Erreur réseau AniList : {error}', [
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
            $this->logger->warning('Erreur HTTP AniList : {error}', [
                'error' => $e->getMessage(),
            ]);

            return [];
        } catch (DecodingExceptionInterface $e) {
            $this->logger->error('Réponse JSON invalide de AniList : {error}', [
                'error' => $e->getMessage(),
            ]);
            $this->recordApiMessage(ApiLookupStatus::ERROR, 'Réponse invalide');

            return [];
        }
    }

    public function supports(LookupMode $mode, ?ComicType $type): bool
    {
        return LookupMode::TITLE === $mode && ComicType::MANGA === $type;
    }

    protected function getLogger(): LoggerInterface
    {
        return $this->logger;
    }

    /**
     * Construit un LookupResult à partir d'un objet media AniList.
     *
     * @param array<string, mixed> $media
     */
    private function buildResultFromMedia(array $media): LookupResult
    {
        /** @var array<string, mixed> $staffData */
        $staffData = \is_array($media['staff'] ?? null) ? $media['staff'] : [];
        /** @var list<array<string, mixed>> $staffEdges */
        $staffEdges = \is_array($staffData['edges'] ?? null) ? $staffData['edges'] : [];
        $authors = $this->extractAuthors($staffEdges);

        /** @var array<string, string|null> $titleData */
        $titleData = \is_array($media['title'] ?? null) ? $media['title'] : [];
        $title = $titleData['english'] ?? $titleData['romaji'] ?? $titleData['native'] ?? null;

        /** @var array<string, string|null> $coverData */
        $coverData = \is_array($media['coverImage'] ?? null) ? $media['coverImage'] : [];
        $thumbnail = $coverData['extraLarge'] ?? $coverData['large'] ?? null;

        /** @var array{year?: int|null, month?: int|null, day?: int|null} $startDate */
        $startDate = \is_array($media['startDate'] ?? null) ? $media['startDate'] : [];
        $publishedDate = $this->formatDate($startDate);

        $description = $media['description'] ?? null;
        if (\is_string($description)) {
            $description = \strip_tags($description);
            $description = \html_entity_decode($description, \ENT_QUOTES | \ENT_HTML5, 'UTF-8');
        } else {
            $description = null;
        }

        $format = $media['format'] ?? null;
        $volumes = $media['volumes'] ?? null;
        $status = $media['status'] ?? null;
        $isOneShot = 'ONE_SHOT' === $format || (1 === $volumes && 'FINISHED' === $status);

        return new LookupResult(
            authors: $authors,
            description: $description,
            isOneShot: $isOneShot,
            latestPublishedIssue: \is_int($volumes) ? $volumes : null,
            publishedDate: $publishedDate,
            source: 'anilist',
            thumbnail: \is_string($thumbnail) ? $thumbnail : null,
            title: \is_string($title) ? $title : null,
        );
    }

    /**
     * Extrait les noms des auteurs depuis les données staff.
     *
     * @param array<int, array<string, mixed>> $staffEdges
     */
    private function extractAuthors(array $staffEdges): ?string
    {
        $authors = [];

        foreach ($staffEdges as $edge) {
            $role = $edge['role'] ?? '';
            if (\in_array($role, self::AUTHOR_ROLES, true)) {
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
     * Formate une date AniList.
     *
     * @param array{year?: int|null, month?: int|null, day?: int|null} $date
     */
    private function formatDate(array $date): ?string
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
}

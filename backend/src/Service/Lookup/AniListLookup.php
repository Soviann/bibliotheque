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
 * Provider de recherche via l'API AniList (manga uniquement, par titre).
 */
#[AutoconfigureTag('app.lookup_provider', ['priority' => 60])]
class AniListLookup extends AbstractLookupProvider
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

    public function prepareLookup(string $query, ?ComicType $type, string $mode = 'title'): mixed
    {
        $this->resetApiMessage();

        $searchTitle = $this->cleanTitle($query);

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

            $authors = $this->extractAuthors($media['staff']['edges'] ?? []);

            $title = $media['title']['english']
                ?? $media['title']['romaji']
                ?? $media['title']['native']
                ?? null;

            $thumbnail = $media['coverImage']['extraLarge']
                ?? $media['coverImage']['large']
                ?? null;

            $publishedDate = $this->formatDate($media['startDate'] ?? []);

            $description = $media['description'] ?? null;
            if (null !== $description) {
                $description = \strip_tags($description);
                $description = \html_entity_decode($description, \ENT_QUOTES | \ENT_HTML5, 'UTF-8');
            }

            $format = $media['format'] ?? null;
            $volumes = $media['volumes'] ?? null;
            $status = $media['status'] ?? null;
            $isOneShot = 'ONE_SHOT' === $format || (1 === $volumes && 'FINISHED' === $status);

            $this->recordApiMessage(ApiLookupStatus::SUCCESS, 'Données trouvées');

            return new LookupResult(
                authors: $authors,
                description: $description,
                isOneShot: $isOneShot,
                latestPublishedIssue: \is_int($volumes) ? $volumes : null,
                publishedDate: $publishedDate,
                source: 'anilist',
                thumbnail: $thumbnail,
                title: $title,
            );
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

    public function supports(string $mode, ?ComicType $type): bool
    {
        return 'title' === $mode && ComicType::MANGA === $type;
    }

    /**
     * Nettoie le titre pour la recherche AniList.
     * Supprime les suffixes de volume/tome courants.
     */
    private function cleanTitle(string $title): string
    {
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

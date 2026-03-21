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
 * Provider de recherche via l'API ComicVine (BD et Comics, par titre).
 */
#[AutoconfigureTag('app.lookup_provider', ['priority' => 55])]
final class ComicVineLookup extends AbstractLookupProvider implements MultiResultLookupProviderInterface
{
    private const string API_URL = 'https://comicvine.gamespot.com/api/search/';

    public function __construct(
        #[Autowire('%env(COMICVINE_API_KEY)%')]
        private readonly string $apiKey,
        private readonly HttpClientInterface $httpClient,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function getFieldPriority(string $field, ?ComicType $type = null): int
    {
        if (\in_array($type, [ComicType::BD, ComicType::COMICS], true) && 'publisher' === $field) {
            return 100;
        }

        return 55;
    }

    public function getName(): string
    {
        return 'comicvine';
    }

    public function prepareMultipleLookup(string $query, ?ComicType $type, int $limit): mixed
    {
        $this->resetApiMessage();

        if ('' === $this->apiKey) {
            $this->recordApiMessage(ApiLookupStatus::ERROR, 'Clé API manquante');

            return null;
        }

        return $this->httpClient->request('GET', self::API_URL, [
            'query' => [
                'api_key' => $this->apiKey,
                'format' => 'json',
                'limit' => $limit,
                'query' => LookupTitleCleaner::clean($query),
                'resources' => 'volume',
            ],
            'timeout' => 10,
        ]);
    }

    public function prepareLookup(string $query, ?ComicType $type, LookupMode $mode = LookupMode::TITLE): mixed
    {
        $this->resetApiMessage();

        if ('' === $this->apiKey) {
            $this->recordApiMessage(ApiLookupStatus::ERROR, 'Clé API manquante');

            return null;
        }

        return $this->httpClient->request('GET', self::API_URL, [
            'query' => [
                'api_key' => $this->apiKey,
                'format' => 'json',
                'limit' => 1,
                'query' => LookupTitleCleaner::clean($query),
                'resources' => 'volume',
            ],
            'timeout' => 10,
        ]);
    }

    public function resolveMultipleLookup(mixed $state): array
    {
        if (null === $state) {
            return [];
        }

        \assert($state instanceof ResponseInterface);

        try {
            $data = $state->toArray();
            $items = $data['results'] ?? [];

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
            $this->logger->error('Erreur réseau ComicVine : {error}', ['error' => $e->getMessage()]);
            $this->recordApiMessage(ApiLookupStatus::ERROR, 'Erreur de connexion');

            return [];
        } catch (ClientExceptionInterface|ServerExceptionInterface|RedirectionExceptionInterface $e) {
            $this->handleHttpException($e);

            return [];
        } catch (DecodingExceptionInterface $e) {
            $this->logger->error('Réponse JSON invalide de ComicVine : {error}', ['error' => $e->getMessage()]);
            $this->recordApiMessage(ApiLookupStatus::ERROR, 'Réponse invalide');

            return [];
        }
    }

    public function resolveLookup(mixed $state): ?LookupResult
    {
        if (null === $state) {
            return null;
        }

        \assert($state instanceof ResponseInterface);

        try {
            $data = $state->toArray();
            $items = $data['results'] ?? [];

            if (0 === \count($items)) {
                $this->recordApiMessage(ApiLookupStatus::NOT_FOUND, 'Aucun résultat');

                return null;
            }

            $result = $this->buildResultFromItem($items[0]);
            $this->recordApiMessage(ApiLookupStatus::SUCCESS, 'Données trouvées');

            return $result;
        } catch (TransportExceptionInterface $e) {
            $this->logger->error('Erreur réseau ComicVine : {error}', ['error' => $e->getMessage()]);
            $this->recordApiMessage(ApiLookupStatus::ERROR, 'Erreur de connexion');

            return null;
        } catch (ClientExceptionInterface|ServerExceptionInterface|RedirectionExceptionInterface $e) {
            $this->handleHttpException($e);

            return null;
        } catch (DecodingExceptionInterface $e) {
            $this->logger->error('Réponse JSON invalide de ComicVine : {error}', ['error' => $e->getMessage()]);
            $this->recordApiMessage(ApiLookupStatus::ERROR, 'Réponse invalide');

            return null;
        }
    }

    public function supports(LookupMode $mode, ?ComicType $type): bool
    {
        return LookupMode::TITLE === $mode && \in_array($type, [ComicType::BD, ComicType::COMICS], true);
    }

    protected function getLogger(): LoggerInterface
    {
        return $this->logger;
    }

    /**
     * Construit un LookupResult depuis un item ComicVine.
     *
     * @param array<string, mixed> $item
     */
    private function buildResultFromItem(array $item): LookupResult
    {
        $title = $item['name'] ?? null;
        $title = \is_string($title) ? $title : null;

        $description = $item['description'] ?? null;
        if (\is_string($description)) {
            $description = \strip_tags($description);
            $description = \html_entity_decode($description, \ENT_QUOTES | \ENT_HTML5, 'UTF-8');
            $description = \trim($description);
        } else {
            $description = null;
        }

        $thumbnail = null;
        $image = $item['image'] ?? null;
        if (\is_array($image)) {
            $thumbnail = $image['original_url'] ?? $image['medium_url'] ?? $image['small_url'] ?? null;
            $thumbnail = \is_string($thumbnail) ? $thumbnail : null;
        }

        $publisher = null;
        $publisherData = $item['publisher'] ?? null;
        if (\is_array($publisherData) && \is_string($publisherData['name'] ?? null)) {
            $publisher = $publisherData['name'];
        }

        $countOfIssues = $item['count_of_issues'] ?? null;
        $countOfIssues = \is_int($countOfIssues) ? $countOfIssues : null;

        $startYear = $item['start_year'] ?? null;
        $publishedDate = \is_string($startYear) ? $startYear : null;

        return new LookupResult(
            description: $description,
            latestPublishedIssue: $countOfIssues,
            publishedDate: $publishedDate,
            publisher: $publisher,
            source: 'comicvine',
            thumbnail: $thumbnail,
            title: $title,
        );
    }
}

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
 * Provider de recherche via l'API Kitsu (manga uniquement, par titre).
 */
#[AutoconfigureTag('app.lookup_provider', ['priority' => 45])]
final class KitsuLookup extends AbstractLookupProvider implements MultiResultLookupProviderInterface
{
    private const string API_URL = 'https://kitsu.app/api/edge/manga';

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function getFieldPriority(string $field, ?ComicType $type = null): int
    {
        if (ComicType::MANGA === $type && 'thumbnail' === $field) {
            return 55;
        }

        return 45;
    }

    public function getName(): string
    {
        return 'kitsu';
    }

    public function prepareMultipleLookup(string $query, ?ComicType $type, int $limit): mixed
    {
        $this->resetApiMessage();

        return $this->httpClient->request('GET', self::API_URL, [
            'headers' => ['Accept' => 'application/vnd.api+json'],
            'query' => [
                'filter[text]' => LookupTitleCleaner::clean($query),
                'page[limit]' => $limit,
            ],
            'timeout' => 10,
        ]);
    }

    public function prepareLookup(string $query, ?ComicType $type, LookupMode $mode = LookupMode::TITLE): mixed
    {
        $this->resetApiMessage();

        return $this->httpClient->request('GET', self::API_URL, [
            'headers' => ['Accept' => 'application/vnd.api+json'],
            'query' => [
                'filter[text]' => LookupTitleCleaner::clean($query),
                'page[limit]' => 1,
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
            $this->logger->error('Erreur réseau Kitsu : {error}', ['error' => $e->getMessage()]);
            $this->recordApiMessage(ApiLookupStatus::ERROR, 'Erreur de connexion');

            return [];
        } catch (ClientExceptionInterface|ServerExceptionInterface|RedirectionExceptionInterface $e) {
            $this->handleHttpException($e);

            return [];
        } catch (DecodingExceptionInterface $e) {
            $this->logger->error('Réponse JSON invalide de Kitsu : {error}', ['error' => $e->getMessage()]);
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
            $this->logger->error('Erreur réseau Kitsu : {error}', ['error' => $e->getMessage()]);
            $this->recordApiMessage(ApiLookupStatus::ERROR, 'Erreur de connexion');

            return null;
        } catch (ClientExceptionInterface|ServerExceptionInterface|RedirectionExceptionInterface $e) {
            $this->handleHttpException($e);

            return null;
        } catch (DecodingExceptionInterface $e) {
            $this->logger->error('Réponse JSON invalide de Kitsu : {error}', ['error' => $e->getMessage()]);
            $this->recordApiMessage(ApiLookupStatus::ERROR, 'Réponse invalide');

            return null;
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
     * Construit un LookupResult depuis un item JSON:API Kitsu.
     *
     * @param array<string, mixed> $item
     */
    private function buildResultFromItem(array $item): LookupResult
    {
        /** @var array<string, mixed> $attrs */
        $attrs = \is_array($item['attributes'] ?? null) ? $item['attributes'] : [];

        /** @var array<string, string> $titles */
        $titles = \is_array($attrs['titles'] ?? null) ? $attrs['titles'] : [];
        $title = $titles['en'] ?? $titles['en_jp'] ?? $attrs['canonicalTitle'] ?? null;
        $title = \is_string($title) ? $title : null;

        $synopsis = $attrs['synopsis'] ?? null;
        $synopsis = \is_string($synopsis) ? $synopsis : null;

        $volumeCount = $attrs['volumeCount'] ?? null;
        $volumeCount = \is_int($volumeCount) ? $volumeCount : null;

        $thumbnail = null;
        $posterImage = $attrs['posterImage'] ?? null;
        if (\is_array($posterImage)) {
            $thumbnail = $posterImage['original'] ?? $posterImage['large'] ?? $posterImage['medium'] ?? null;
            $thumbnail = \is_string($thumbnail) ? $thumbnail : null;
        }

        $publishedDate = $attrs['startDate'] ?? null;
        $publishedDate = \is_string($publishedDate) ? $publishedDate : null;

        $subtype = $attrs['subtype'] ?? null;
        $status = $attrs['status'] ?? null;
        $isOneShot = 'oneshot' === $subtype || (1 === $volumeCount && 'finished' === $status);

        return new LookupResult(
            description: $synopsis,
            isOneShot: $isOneShot,
            latestPublishedIssue: $volumeCount,
            publishedDate: $publishedDate,
            source: 'kitsu',
            thumbnail: $thumbnail,
            title: $title,
        );
    }
}

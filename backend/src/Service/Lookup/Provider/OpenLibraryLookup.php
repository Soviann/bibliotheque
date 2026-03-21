<?php

declare(strict_types=1);

namespace App\Service\Lookup\Provider;

use App\Service\Lookup\Contract\LookupResult;

use App\Enum\ApiLookupStatus;
use App\Enum\ComicType;
use App\Enum\LookupMode;
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
 * Provider de recherche via l'API Open Library (ISBN uniquement).
 */
#[AutoconfigureTag('app.lookup_provider', ['priority' => 80])]
final class OpenLibraryLookup extends AbstractLookupProvider
{
    private const string API_URL = 'https://openlibrary.org/isbn/';

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function getFieldPriority(string $field, ?ComicType $type = null): int
    {
        return 80;
    }

    public function getName(): string
    {
        return 'open_library';
    }

    public function prepareLookup(string $query, ?ComicType $type, LookupMode $mode = LookupMode::TITLE): mixed
    {
        $this->resetApiMessage();

        return $this->httpClient->request('GET', self::API_URL.$query.'.json', [
            'timeout' => 10,
        ]);
    }

    public function resolveLookup(mixed $state): ?LookupResult
    {
        \assert($state instanceof ResponseInterface);
        try {
            $statusCode = $state->getStatusCode();

            if (429 === $statusCode) {
                $this->recordApiMessage(ApiLookupStatus::RATE_LIMITED, 'Quota dépassé (429)');

                return null;
            }

            if (200 !== $statusCode) {
                $this->recordApiMessage(ApiLookupStatus::NOT_FOUND, 'Aucun résultat');

                return null;
            }

            $data = $state->toArray();

            if (empty($data['title'])) {
                $this->recordApiMessage(ApiLookupStatus::NOT_FOUND, 'Aucun résultat');

                return null;
            }

            $authors = null;
            if (!empty($data['authors'])) {
                $authors = $this->fetchAuthorsParallel($data['authors']);
            }

            $publisher = empty($data['publishers']) ? null : $data['publishers'][0];
            $publishedDate = $data['publish_date'] ?? null;

            $thumbnail = null;
            if (!empty($data['covers'][0])) {
                $coverId = $data['covers'][0];
                $thumbnail = "https://covers.openlibrary.org/b/id/{$coverId}-M.jpg";
            }

            $this->recordApiMessage(ApiLookupStatus::SUCCESS, 'Données trouvées');

            return new LookupResult(
                authors: $authors,
                publishedDate: $publishedDate,
                publisher: $publisher,
                source: 'open_library',
                thumbnail: $thumbnail,
                title: $data['title'],
            );
        } catch (TransportExceptionInterface $e) {
            $this->logger->error('Erreur réseau Open Library : {error}', [
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
            $this->logger->warning('Erreur HTTP Open Library : {error}', [
                'error' => $e->getMessage(),
            ]);

            return null;
        } catch (DecodingExceptionInterface $e) {
            $this->logger->error('Réponse JSON invalide de Open Library : {error}', [
                'error' => $e->getMessage(),
            ]);
            $this->recordApiMessage(ApiLookupStatus::ERROR, 'Réponse invalide');

            return null;
        }
    }

    public function supports(LookupMode $mode, ?ComicType $type): bool
    {
        return LookupMode::ISBN === $mode;
    }

    protected function getLogger(): LoggerInterface
    {
        return $this->logger;
    }

    /**
     * Récupère les noms des auteurs en parallèle.
     *
     * @param array<int, array{key?: string}> $authorRefs
     */
    private function fetchAuthorsParallel(array $authorRefs): ?string
    {
        $responses = [];
        foreach ($authorRefs as $author) {
            if (isset($author['key'])) {
                $responses[$author['key']] = $this->httpClient->request('GET', 'https://openlibrary.org'.$author['key'].'.json', [
                    'timeout' => 5,
                ]);
            }
        }

        $authorNames = [];
        foreach ($responses as $authorKey => $response) {
            try {
                $data = $response->toArray();
                if (!empty($data['name'])) {
                    $authorNames[] = $data['name'];
                }
            } catch (TransportExceptionInterface|ClientExceptionInterface|ServerExceptionInterface|RedirectionExceptionInterface|DecodingExceptionInterface $e) {
                $this->logger->debug('Erreur lors de la récupération de l\'auteur Open Library "{key}": {error}', [
                    'error' => $e->getMessage(),
                    'key' => $authorKey,
                ]);
            }
        }

        return \count($authorNames) > 0 ? \implode(', ', $authorNames) : null;
    }
}

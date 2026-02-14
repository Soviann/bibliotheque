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
 * Provider de recherche via l'API Open Library (ISBN uniquement).
 */
#[AutoconfigureTag('app.lookup_provider', ['priority' => 80])]
class OpenLibraryLookup implements LookupProviderInterface
{
    private const string API_URL = 'https://openlibrary.org/isbn/';

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

    public function getName(): string
    {
        return 'open_library';
    }

    public function lookup(string $query, ?ComicType $type, string $mode = 'title'): ?LookupResult
    {
        $this->lastApiMessage = null;

        try {
            $response = $this->httpClient->request('GET', self::API_URL.$query.'.json', [
                'timeout' => 10,
            ]);

            $statusCode = $response->getStatusCode();

            if (429 === $statusCode) {
                $this->recordApiMessage(ApiLookupStatus::RATE_LIMITED, 'Quota dépassé (429)');

                return null;
            }

            if (200 !== $statusCode) {
                $this->recordApiMessage(ApiLookupStatus::NOT_FOUND, 'Aucun résultat');

                return null;
            }

            $data = $response->toArray();

            if (empty($data['title'])) {
                $this->recordApiMessage(ApiLookupStatus::NOT_FOUND, 'Aucun résultat');

                return null;
            }

            $authors = null;
            if (!empty($data['authors'])) {
                $authors = $this->fetchAuthorsParallel($data['authors']);
            }

            $publisher = !empty($data['publishers']) ? $data['publishers'][0] : null;
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
            $this->logger->error('Erreur réseau Open Library pour ISBN {isbn}: {error}', [
                'error' => $e->getMessage(),
                'isbn' => $query,
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
            $this->logger->warning('Erreur HTTP Open Library pour ISBN {isbn}: {error}', [
                'error' => $e->getMessage(),
                'isbn' => $query,
            ]);

            return null;
        } catch (DecodingExceptionInterface $e) {
            $this->logger->error('Réponse JSON invalide de Open Library pour ISBN {isbn}: {error}', [
                'error' => $e->getMessage(),
                'isbn' => $query,
            ]);
            $this->recordApiMessage(ApiLookupStatus::ERROR, 'Réponse invalide');

            return null;
        }
    }

    public function supports(string $mode, ?ComicType $type): bool
    {
        return 'isbn' === $mode;
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

    private function recordApiMessage(ApiLookupStatus $status, string $message): void
    {
        $this->lastApiMessage = ['message' => $message, 'status' => $status->value];
    }
}

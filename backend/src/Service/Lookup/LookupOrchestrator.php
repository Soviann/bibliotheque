<?php

declare(strict_types=1);

namespace App\Service\Lookup;

use App\Enum\ApiLookupStatus;
use App\Enum\ComicType;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

/**
 * Coordonne les providers de lookup en deux phases (prepare/resolve)
 * pour exploiter le multiplexage HTTP natif de Symfony HttpClient.
 */
class LookupOrchestrator
{
    /** @var array<string, ApiMessage> */
    private array $apiMessages = [];

    /** @var list<string> */
    private array $sources = [];

    /**
     * @param float                             $globalTimeout Timeout global en secondes
     * @param iterable<LookupProviderInterface> $providers
     */
    public function __construct(
        #[Autowire('%app.lookup_global_timeout%')]
        private readonly float $globalTimeout,
        private readonly LoggerInterface $logger,
        #[AutowireIterator('app.lookup_provider')]
        private readonly iterable $providers,
    ) {
    }

    /**
     * @return array<string, ApiMessage>
     */
    public function getLastApiMessages(): array
    {
        return $this->apiMessages;
    }

    /**
     * @return list<string>
     */
    public function getLastSources(): array
    {
        return $this->sources;
    }

    /**
     * Recherche par ISBN.
     */
    public function lookup(string $isbn, ?ComicType $type = null): ?LookupResult
    {
        $isbn = \preg_replace('/[\s-]/', '', $isbn) ?? '';

        if ('' === $isbn) {
            return null;
        }

        $result = $this->doLookup($isbn, $type, 'isbn');

        if ($result instanceof LookupResult) {
            $result = $result->withIsbn($isbn);
        }

        return $result;
    }

    /**
     * Recherche par titre avec plusieurs résultats candidats.
     *
     * @return list<LookupResult>
     */
    public function lookupByTitleMultiple(string $title, ?ComicType $type, int $limit): array
    {
        $title = \trim($title);

        if ('' === $title) {
            return [];
        }

        $this->apiMessages = [];
        $this->sources = [];

        $startTime = \microtime(true);

        // Phase 1 : lancer toutes les requêtes (non bloquant)
        /** @var list<array{multi: bool, provider: LookupProviderInterface, state: mixed}> $prepared */
        $prepared = [];

        foreach ($this->providers as $provider) {
            if (!$provider->supports('title', $type)) {
                continue;
            }

            try {
                if ($provider instanceof MultiResultLookupProviderInterface) {
                    $state = $provider->prepareMultipleLookup($title, $type, $limit);
                    $prepared[] = ['multi' => true, 'provider' => $provider, 'state' => $state];
                } else {
                    $state = $provider->prepareLookup($title, $type, 'title');
                    $prepared[] = ['multi' => false, 'provider' => $provider, 'state' => $state];
                }
            } catch (\Throwable $e) {
                $this->logger->error('Erreur prepareLookup {provider} : {error}', [
                    'error' => $e->getMessage(),
                    'provider' => $provider->getName(),
                ]);
                $this->apiMessages[$provider->getName()] = new ApiMessage(
                    message: $e->getMessage(),
                    status: ApiLookupStatus::ERROR->value,
                );
            }
        }

        // Phase 2 : résoudre les réponses
        /** @var list<LookupResult> $allResults */
        $allResults = [];

        foreach ($prepared as ['multi' => $isMulti, 'provider' => $provider, 'state' => $state]) {
            $elapsed = \microtime(true) - $startTime;

            if ($elapsed >= $this->globalTimeout) {
                $this->apiMessages[$provider->getName()] = new ApiMessage(
                    message: 'Timeout global dépassé',
                    status: ApiLookupStatus::TIMEOUT->value,
                );

                continue;
            }

            try {
                if ($isMulti) {
                    \assert($provider instanceof MultiResultLookupProviderInterface);
                    $results = $provider->resolveMultipleLookup($state);
                } else {
                    $single = $provider->resolveLookup($state);
                    $results = null !== $single ? [$single] : [];
                }

                $apiMessage = $provider->getLastApiMessage();

                if (null !== $apiMessage) {
                    $this->apiMessages[$provider->getName()] = $apiMessage;
                }

                if (\count($results) > 0) {
                    \array_push($allResults, ...$results);
                    $this->sources[] = $provider->getName();
                }
            } catch (\Throwable $e) {
                $this->logger->error('Erreur resolveLookup {provider} : {error}', [
                    'error' => $e->getMessage(),
                    'provider' => $provider->getName(),
                ]);
                $this->apiMessages[$provider->getName()] = new ApiMessage(
                    message: $e->getMessage(),
                    status: ApiLookupStatus::ERROR->value,
                );
            }
        }

        // Dédupliquer par titre normalisé, garder le premier trouvé
        $seen = [];
        $deduplicated = [];

        foreach ($allResults as $result) {
            $key = \mb_strtolower(\trim($result->title ?? ''));
            if ('' === $key || isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $deduplicated[] = $result;
        }

        return \array_slice($deduplicated, 0, $limit);
    }

    /**
     * Recherche par titre.
     */
    public function lookupByTitle(string $title, ?ComicType $type = null): ?LookupResult
    {
        $title = \trim($title);

        if ('' === $title) {
            return null;
        }

        return $this->doLookup($title, $type, 'title');
    }

    private function doLookup(string $query, ?ComicType $type, string $mode): ?LookupResult
    {
        $this->apiMessages = [];
        $this->sources = [];

        $startTime = \microtime(true);

        // Phase 1 : lancer toutes les requêtes (non bloquant)
        /** @var list<array{provider: LookupProviderInterface, state: mixed}> $prepared */
        $prepared = [];

        foreach ($this->providers as $provider) {
            if (!$provider->supports($mode, $type)) {
                continue;
            }

            try {
                $state = $provider->prepareLookup($query, $type, $mode);
                $prepared[] = ['provider' => $provider, 'state' => $state];
            } catch (\Throwable $e) {
                $this->logger->error('Erreur prepareLookup {provider} : {error}', [
                    'error' => $e->getMessage(),
                    'provider' => $provider->getName(),
                ]);
                $this->apiMessages[$provider->getName()] = new ApiMessage(
                    message: $e->getMessage(),
                    status: ApiLookupStatus::ERROR->value,
                );
            }
        }

        // Phase 2 : résoudre les réponses (bloquant, avec timeout global)
        /** @var list<array{LookupProviderInterface, LookupResult}> $providerResults */
        $providerResults = [];

        foreach ($prepared as ['provider' => $provider, 'state' => $state]) {
            $elapsed = \microtime(true) - $startTime;

            if ($elapsed >= $this->globalTimeout) {
                $this->apiMessages[$provider->getName()] = new ApiMessage(
                    message: 'Timeout global dépassé',
                    status: ApiLookupStatus::TIMEOUT->value,
                );

                continue;
            }

            try {
                $result = $provider->resolveLookup($state);
                $apiMessage = $provider->getLastApiMessage();

                if (null !== $apiMessage) {
                    $this->apiMessages[$provider->getName()] = $apiMessage;
                }

                if (null !== $result) {
                    $providerResults[] = [$provider, $result];
                    $this->sources[] = $provider->getName();
                }
            } catch (\Throwable $e) {
                $this->logger->error('Erreur resolveLookup {provider} : {error}', [
                    'error' => $e->getMessage(),
                    'provider' => $provider->getName(),
                ]);
                $this->apiMessages[$provider->getName()] = new ApiMessage(
                    message: $e->getMessage(),
                    status: ApiLookupStatus::ERROR->value,
                );
            }
        }

        if (0 === \count($providerResults)) {
            return null;
        }

        $merged = $this->mergeByFieldPriority($providerResults, $type);

        if (!$merged->isComplete()) {
            $merged = $this->tryEnrich($merged, $providerResults, $type);
        }

        return $merged;
    }

    /**
     * Fusionne les résultats en utilisant la priorité par champ de chaque provider.
     *
     * Pour chaque champ, la valeur du provider avec la plus haute priorité l'emporte.
     * À priorité égale, le premier provider (ordre d'exécution) gagne.
     *
     * @param list<array{LookupProviderInterface, LookupResult}> $providerResults
     */
    private function mergeByFieldPriority(array $providerResults, ?ComicType $type): LookupResult
    {
        $fields = ['amazonUrl', 'authors', 'description', 'isbn', 'isOneShot', 'latestPublishedIssue', 'publishedDate', 'publisher', 'thumbnail', 'title', 'tomeEnd', 'tomeNumber'];
        $bestPriorities = \array_fill_keys($fields, -1);
        $bestValues = \array_fill_keys($fields, null);

        foreach ($providerResults as [$provider, $result]) {
            foreach ($fields as $field) {
                if (null === $result->$field) {
                    continue;
                }

                $priority = $provider->getFieldPriority($field, $type);

                if ($priority > $bestPriorities[$field]) {
                    $bestPriorities[$field] = $priority;
                    $bestValues[$field] = $result->$field;
                }
            }
        }

        return new LookupResult(
            amazonUrl: $bestValues['amazonUrl'], // @phpstan-ignore argument.type (accès dynamique aux propriétés typées)
            authors: $bestValues['authors'], // @phpstan-ignore argument.type
            description: $bestValues['description'], // @phpstan-ignore argument.type
            isbn: $bestValues['isbn'], // @phpstan-ignore argument.type
            isOneShot: $bestValues['isOneShot'], // @phpstan-ignore argument.type
            latestPublishedIssue: $bestValues['latestPublishedIssue'], // @phpstan-ignore argument.type
            publishedDate: $bestValues['publishedDate'], // @phpstan-ignore argument.type
            publisher: $bestValues['publisher'], // @phpstan-ignore argument.type
            source: $providerResults[0][1]->source,
            thumbnail: $bestValues['thumbnail'], // @phpstan-ignore argument.type
            title: $bestValues['title'], // @phpstan-ignore argument.type
            tomeEnd: $bestValues['tomeEnd'], // @phpstan-ignore argument.type
            tomeNumber: $bestValues['tomeNumber'], // @phpstan-ignore argument.type
        );
    }

    /**
     * Tente d'enrichir les données via les providers enrichables (deux phases).
     *
     * @param list<array{LookupProviderInterface, LookupResult}> $existingResults
     */
    private function tryEnrich(LookupResult $merged, array $existingResults, ?ComicType $type): LookupResult
    {
        // Phase 1 : préparer les enrichissements
        /** @var list<array{provider: EnrichableLookupProviderInterface, state: mixed}> $prepared */
        $prepared = [];

        foreach ($this->providers as $provider) {
            if (!$provider instanceof EnrichableLookupProviderInterface) {
                continue;
            }

            try {
                $state = $provider->prepareEnrich($merged, $type);
                $prepared[] = ['provider' => $provider, 'state' => $state];
            } catch (\Throwable $e) {
                $this->logger->error('Erreur prepareEnrich {provider} : {error}', [
                    'error' => $e->getMessage(),
                    'provider' => $provider->getName(),
                ]);
                $this->apiMessages[$provider->getName().'.enrich'] = new ApiMessage(
                    message: $e->getMessage(),
                    status: ApiLookupStatus::ERROR->value,
                );
            }
        }

        // Phase 2 : résoudre les enrichissements
        /** @var list<array{LookupProviderInterface, LookupResult}> $enrichResults */
        $enrichResults = [];

        foreach ($prepared as ['provider' => $provider, 'state' => $state]) {
            try {
                $enriched = $provider->resolveEnrich($state);
                $apiMessage = $provider->getLastApiMessage();

                if (null !== $apiMessage) {
                    $this->apiMessages[$provider->getName().'.enrich'] = $apiMessage;
                }

                if (null !== $enriched) {
                    $enrichResults[] = [$provider, $enriched];
                    $this->sources[] = $enriched->source;
                }
            } catch (\Throwable $e) {
                $this->logger->error('Erreur resolveEnrich {provider} : {error}', [
                    'error' => $e->getMessage(),
                    'provider' => $provider->getName(),
                ]);
                $this->apiMessages[$provider->getName().'.enrich'] = new ApiMessage(
                    message: $e->getMessage(),
                    status: ApiLookupStatus::ERROR->value,
                );
            }
        }

        if (0 === \count($enrichResults)) {
            return $merged;
        }

        // Fusionne tous les résultats (lookup + enrichissement) par priorité de champ
        return $this->mergeByFieldPriority(\array_merge($existingResults, $enrichResults), $type);
    }
}

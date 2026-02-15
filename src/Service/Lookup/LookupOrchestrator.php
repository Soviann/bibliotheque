<?php

declare(strict_types=1);

namespace App\Service\Lookup;

use App\Enum\ComicType;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

/**
 * Coordonne les providers de lookup, fusionne les résultats et enrichit si nécessaire.
 */
class LookupOrchestrator
{
    /** @var array<string, array{status: string, message: string}> */
    private array $apiMessages = [];

    /** @var list<string> */
    private array $sources = [];

    /**
     * @param iterable<LookupProviderInterface> $providers
     */
    public function __construct(
        #[AutowireIterator('app.lookup_provider')]
        private readonly iterable $providers,
    ) {
    }

    /**
     * @return array<string, array{status: string, message: string}>
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

        if (null !== $result) {
            $result = $result->withIsbn($isbn);
        }

        return $result;
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

        /** @var list<array{LookupProviderInterface, LookupResult}> $providerResults */
        $providerResults = [];

        foreach ($this->providers as $provider) {
            if (!$provider->supports($mode, $type)) {
                continue;
            }

            $result = $provider->lookup($query, $type, $mode);
            $apiMessage = $provider->getLastApiMessage();

            if (null !== $apiMessage) {
                $this->apiMessages[$provider->getName()] = $apiMessage;
            }

            if (null !== $result) {
                $providerResults[] = [$provider, $result];
                $this->sources[] = $provider->getName();
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
        $fields = ['authors', 'description', 'isbn', 'isOneShot', 'latestPublishedIssue', 'publishedDate', 'publisher', 'thumbnail', 'title'];
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
            authors: $bestValues['authors'], // @phpstan-ignore argument.type (accès dynamique aux propriétés typées)
            description: $bestValues['description'], // @phpstan-ignore argument.type
            isbn: $bestValues['isbn'], // @phpstan-ignore argument.type
            isOneShot: $bestValues['isOneShot'], // @phpstan-ignore argument.type
            latestPublishedIssue: $bestValues['latestPublishedIssue'], // @phpstan-ignore argument.type
            publishedDate: $bestValues['publishedDate'], // @phpstan-ignore argument.type
            publisher: $bestValues['publisher'], // @phpstan-ignore argument.type
            source: $providerResults[0][1]->source,
            thumbnail: $bestValues['thumbnail'], // @phpstan-ignore argument.type
            title: $bestValues['title'], // @phpstan-ignore argument.type
        );
    }

    /**
     * Tente d'enrichir les données via les providers enrichables.
     *
     * Collecte les résultats d'enrichissement puis les fusionne par priorité de champ.
     * Les champs déjà remplis par le lookup principal sont conservés.
     *
     * @param list<array{LookupProviderInterface, LookupResult}> $existingResults
     */
    private function tryEnrich(LookupResult $merged, array $existingResults, ?ComicType $type): LookupResult
    {
        /** @var list<array{LookupProviderInterface, LookupResult}> $enrichResults */
        $enrichResults = [];

        foreach ($this->providers as $provider) {
            if (!$provider instanceof EnrichableLookupProviderInterface) {
                continue;
            }

            $enriched = $provider->enrich($merged, $type);
            $apiMessage = $provider->getLastApiMessage();

            if (null !== $apiMessage) {
                $this->apiMessages[$provider->getName().'.enrich'] = $apiMessage;
            }

            if (null !== $enriched) {
                $enrichResults[] = [$provider, $enriched];
                $this->sources[] = $enriched->source;
            }
        }

        if (0 === \count($enrichResults)) {
            return $merged;
        }

        // Fusionne tous les résultats (lookup + enrichissement) par priorité de champ
        return $this->mergeByFieldPriority(\array_merge($existingResults, $enrichResults), $type);
    }
}

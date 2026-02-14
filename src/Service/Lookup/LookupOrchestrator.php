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
        $results = [];

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
                $results[] = $result;
                $this->sources[] = $provider->getName();
            }
        }

        if (0 === \count($results)) {
            return null;
        }

        $merged = $this->mergeResults($results, $type);

        if (!$merged->isComplete()) {
            $merged = $this->tryEnrich($merged, $type);
        }

        return $merged;
    }

    /**
     * Fusionne les LookupResult avec règles de priorité.
     * L'ordre des providers détermine la priorité (premier = plus prioritaire).
     * AniList a des règles spéciales pour les mangas : remplace thumbnail et isOneShot.
     *
     * @param list<LookupResult> $results
     */
    private function mergeResults(array $results, ?ComicType $type): LookupResult
    {
        $merged = $results[0];

        for ($i = 1, $count = \count($results); $i < $count; ++$i) {
            $current = $results[$i];

            if ('anilist' === $current->source && ComicType::MANGA === $type) {
                $merged = $merged->mergeWith($current, overrideFields: ['isOneShot', 'thumbnail']);
            } else {
                $merged = $merged->mergeWith($current);
            }
        }

        return $merged;
    }

    /**
     * Tente d'enrichir les données via un provider enrichable.
     */
    private function tryEnrich(LookupResult $merged, ?ComicType $type): LookupResult
    {
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
                $this->sources[] = $enriched->source;
                $merged = $merged->mergeWith($enriched);
            }
        }

        return $merged;
    }
}

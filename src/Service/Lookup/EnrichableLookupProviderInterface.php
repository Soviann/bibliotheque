<?php

declare(strict_types=1);

namespace App\Service\Lookup;

use App\Enum\ComicType;

/**
 * Interface pour les providers capables d'enrichir des données existantes.
 */
interface EnrichableLookupProviderInterface extends LookupProviderInterface
{
    /**
     * Enrichit des données partielles avec des informations complémentaires.
     *
     * @param LookupResult   $partial Les données partielles à enrichir
     * @param ComicType|null $type    Le type de série
     */
    public function enrich(LookupResult $partial, ?ComicType $type): ?LookupResult;
}

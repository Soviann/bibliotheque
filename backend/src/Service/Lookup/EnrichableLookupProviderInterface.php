<?php

declare(strict_types=1);

namespace App\Service\Lookup;

use App\Enum\ComicType;

/**
 * Interface pour les providers capables d'enrichir des données existantes.
 *
 * Utilise un modèle deux phases (prepare/resolve) comme LookupProviderInterface.
 */
interface EnrichableLookupProviderInterface extends LookupProviderInterface
{
    /**
     * Phase 1 : prépare l'enrichissement (lance la requête).
     *
     * @param LookupResult   $partial Les données partielles à enrichir
     * @param ComicType|null $type    Le type de série
     *
     * @return mixed État intermédiaire
     */
    public function prepareEnrich(LookupResult $partial, ?ComicType $type): mixed;

    /**
     * Phase 2 : traite la réponse d'enrichissement.
     *
     * @param mixed $state État retourné par prepareEnrich()
     */
    public function resolveEnrich(mixed $state): ?LookupResult;
}

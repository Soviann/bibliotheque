<?php

declare(strict_types=1);

namespace App\Service\Lookup;

use App\Enum\ComicType;

/**
 * Interface pour les providers capables de retourner plusieurs résultats distincts.
 *
 * Utilisée pour le lookup par titre multi-candidats.
 */
interface MultiResultLookupProviderInterface extends LookupProviderInterface
{
    /**
     * Phase 1 : lance la requête HTTP pour la recherche multi-résultats.
     *
     * @param string         $query Titre de recherche
     * @param ComicType|null $type  Le type de série
     * @param int            $limit Nombre maximum de résultats
     *
     * @return mixed État intermédiaire
     */
    public function prepareMultipleLookup(string $query, ?ComicType $type, int $limit): mixed;

    /**
     * Phase 2 : traite la réponse et retourne plusieurs résultats.
     *
     * @param mixed $state État retourné par prepareMultipleLookup()
     *
     * @return list<LookupResult>
     */
    public function resolveMultipleLookup(mixed $state): array;
}

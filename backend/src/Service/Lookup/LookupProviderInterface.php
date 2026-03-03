<?php

declare(strict_types=1);

namespace App\Service\Lookup;

use App\Enum\ComicType;

/**
 * Interface pour les providers de recherche de données bibliographiques.
 *
 * Utilise un modèle deux phases (prepare/resolve) pour permettre
 * le multiplexage HTTP via curl_multi de Symfony HttpClient.
 */
interface LookupProviderInterface
{
    /**
     * Retourne la priorité de ce provider pour un champ donné.
     *
     * Plus la valeur est haute, plus le provider est prioritaire pour ce champ.
     * Permet de découpler la priorité d'exécution (tag priority) de la priorité
     * de préremplissage par champ.
     *
     * @param string         $field Nom du champ (ex: 'description', 'title', 'authors')
     * @param ComicType|null $type  Contexte de type (certains providers sont plus pertinents pour certains types)
     */
    public function getFieldPriority(string $field, ?ComicType $type = null): int;

    /**
     * Nom unique du provider (ex: 'google_books', 'anilist', 'gemini').
     */
    public function getName(): string;

    /**
     * Retourne le message de statut du dernier appel.
     */
    public function getLastApiMessage(): ?ApiMessage;

    /**
     * Phase 1 : lance la requête HTTP (non bloquante) et retourne un état intermédiaire.
     *
     * @param string         $query ISBN ou titre selon le mode
     * @param ComicType|null $type  Le type de série
     * @param string         $mode  'isbn' ou 'title'
     *
     * @return mixed État intermédiaire (ResponseInterface, LookupResult depuis cache, null, etc.)
     */
    public function prepareLookup(string $query, ?ComicType $type, string $mode = 'title'): mixed;

    /**
     * Phase 2 : traite la réponse et retourne le résultat.
     *
     * @param mixed $state État retourné par prepareLookup()
     */
    public function resolveLookup(mixed $state): ?LookupResult;

    /**
     * Indique si le provider supporte le mode donné.
     *
     * @param string         $mode 'isbn' ou 'title'
     * @param ComicType|null $type Le type de série (certains providers sont spécifiques à un type)
     */
    public function supports(string $mode, ?ComicType $type): bool;
}

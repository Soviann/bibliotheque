<?php

declare(strict_types=1);

namespace App\Service\Lookup;

use App\Enum\ComicType;

/**
 * Interface pour les providers de recherche de données bibliographiques.
 */
interface LookupProviderInterface
{
    /**
     * Nom unique du provider (ex: 'google_books', 'anilist', 'gemini').
     */
    public function getName(): string;

    /**
     * Retourne le message de statut du dernier appel.
     *
     * @return array{status: string, message: string}|null
     */
    public function getLastApiMessage(): ?array;

    /**
     * Recherche des informations sur une série.
     *
     * @param string         $query ISBN ou titre selon le mode
     * @param ComicType|null $type  Le type de série
     * @param string         $mode  'isbn' ou 'title'
     */
    public function lookup(string $query, ?ComicType $type, string $mode = 'title'): ?LookupResult;

    /**
     * Indique si le provider supporte le mode donné.
     *
     * @param string         $mode 'isbn' ou 'title'
     * @param ComicType|null $type Le type de série (certains providers sont spécifiques à un type)
     */
    public function supports(string $mode, ?ComicType $type): bool;
}

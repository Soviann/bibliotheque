<?php

declare(strict_types=1);

namespace App\Service\Lookup\Gemini;

/**
 * Levée lorsque toutes les combinaisons clé × modèle Gemini ont échoué.
 *
 * `$rateLimited` distingue un épuisement par quota (au moins un 429 rencontré)
 * d'une indisponibilité générale (uniquement des erreurs 500/503/auth).
 */
final class GeminiAllKeysExhaustedException extends \RuntimeException
{
    public function __construct(
        public readonly bool $rateLimited,
        ?\Throwable $previous = null,
    ) {
        parent::__construct(
            $rateLimited
                ? 'Toutes les clés/modèles Gemini sont en quota.'
                : 'Toutes les clés/modèles Gemini sont indisponibles.',
            0,
            $previous,
        );
    }
}

<?php

declare(strict_types=1);

namespace App\Service\Lookup;

/**
 * Utilitaire de nettoyage de titre pour les providers de lookup.
 * Supprime les suffixes de volume/tome courants avant la recherche.
 */
final class LookupTitleCleaner
{
    /** @var list<string> */
    private const array PATTERNS = [
        '/\s*[-–—]\s*(?:T(?:ome)?|Vol(?:ume)?|V)\.?\s*\d+.*$/iu',
        '/\s+(?:T(?:ome)?|Vol(?:ume)?|V)\.?\s*\d+.*$/iu',
        '/\s*#\d+.*$/u',
        '/\s*\(\d+\)\s*$/u',
        '/\s+\d+\s*$/u',
    ];

    /**
     * Nettoie un titre pour la recherche par API.
     * Supprime les suffixes de volume/tome courants.
     */
    public static function clean(string $title): string
    {
        $cleaned = $title;
        foreach (self::PATTERNS as $pattern) {
            $cleaned = \preg_replace($pattern, '', $cleaned) ?? $cleaned;
        }

        return \trim($cleaned);
    }
}

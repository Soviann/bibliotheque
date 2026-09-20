<?php

declare(strict_types=1);

namespace App\Service\Lookup\Util;

/**
 * Compare un titre de résultat avec la requête de recherche pour déterminer la pertinence.
 */
final class TitleMatcher
{
    /** Seuil minimal de similarité Levenshtein par défaut (85%). */
    public const float DEFAULT_THRESHOLD = 0.85;

    /** Articles et stopwords courants ignorés pour la comparaison de titre. */
    private const array ARTICLES = [
        'a', 'an', 'au', 'aux', 'ce', 'ces', 'd', 'de', 'des', 'du', 'en', 'et', 'l', 'la', 'le', 'les', 'of', 'the', 'un', 'une',
    ];

    /**
     * Vérifie si un titre de résultat correspond à la requête selon le seuil Levenshtein.
     */
    public static function matches(string $query, string $resultTitle, float $threshold = self::DEFAULT_THRESHOLD): bool
    {
        return self::similarity($query, $resultTitle) >= $threshold;
    }

    /**
     * Calcule le score de similarité normalisé (0.0 à 1.0) entre la requête et le titre.
     */
    public static function similarity(string $query, string $resultTitle): float
    {
        $normQuery = self::normalizeForMatching($query);
        $normTitle = self::normalizeForMatching($resultTitle);

        if ($normQuery === $normTitle) {
            return '' === $normQuery ? 0.0 : 1.0;
        }

        if ('' === $normQuery || '' === $normTitle) {
            return 0.0;
        }

        $lenQuery = \strlen($normQuery);
        $lenTitle = \strlen($normTitle);
        $maxLen = \max($lenQuery, $lenTitle);

        // Borne inférieure mathématique : dist >= |lenA - lenB|
        // Si |lenA - lenB| / maxLen > (1.0 - 0.85), la similarité ne peut pas atteindre 85%
        $diff = \abs($lenQuery - $lenTitle);
        if (($diff / $maxLen) > (1.0 - self::DEFAULT_THRESHOLD)) {
            return 0.0;
        }

        if ($maxLen > 255) {
            $safeQuery = \mb_substr($normQuery, 0, 255);
            $safeTitle = \mb_substr($normTitle, 0, 255);
            $safeMaxLen = \max(\strlen($safeQuery), \strlen($safeTitle));
            $dist = \levenshtein($safeQuery, $safeTitle);
            if ($dist < 0) {
                return 0.0;
            }

            return \max(0.0, 1.0 - ($dist / $safeMaxLen));
        }

        $dist = \levenshtein($normQuery, $normTitle);
        if ($dist < 0) {
            return 0.0;
        }

        return \max(0.0, 1.0 - ($dist / $maxLen));
    }

    /**
     * Normalise un titre pour la comparaison : nettoyage tomes, casse, accents, stopwords, ponctuation.
     */
    public static function normalizeForMatching(string $text): string
    {
        $cleaned = LookupTitleCleaner::clean($text);
        $normalized = \mb_strtolower(\trim($cleaned));

        $transliterated = \transliterator_transliterate('NFD; [:Nonspacing Mark:] Remove; NFC', $normalized);
        if (\is_string($transliterated)) {
            $normalized = $transliterated;
        }

        $normalized = \str_replace('&', 'et', $normalized);
        $normalized = (string) \preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $normalized);

        $articlesPattern = '/\b('.\implode('|', self::ARTICLES).')\b/u';
        $withoutArticles = (string) \preg_replace($articlesPattern, ' ', $normalized);

        $collapsed = \trim((string) \preg_replace('/\s+/', ' ', $withoutArticles));

        if ('' === $collapsed) {
            $collapsed = \trim((string) \preg_replace('/\s+/', ' ', $normalized));
        }

        return $collapsed;
    }
}

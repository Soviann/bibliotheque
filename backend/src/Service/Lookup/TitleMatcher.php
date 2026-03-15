<?php

declare(strict_types=1);

namespace App\Service\Lookup;

/**
 * Compare un titre de résultat avec la requête de recherche pour déterminer la pertinence.
 */
final class TitleMatcher
{
    /** Mots trop courants pour être discriminants (articles, prépositions, etc.). */
    private const STOPWORDS = ['au', 'aux', 'ce', 'ces', 'de', 'des', 'du', 'en', 'et', 'la', 'le', 'les', 'of', 'the', 'un', 'une'];

    /** Longueur minimale pour qu'un mot soit considéré significatif. */
    private const MIN_WORD_LENGTH = 3;

    /**
     * Vérifie si un titre de résultat correspond suffisamment à la requête.
     *
     * Retourne true si la requête n'a pas de mots significatifs (pas de filtrage possible)
     * ou si au moins un mot significatif de la requête est présent dans le titre.
     */
    public static function matches(string $query, string $resultTitle): bool
    {
        $queryWords = self::extractSignificantWords($query);

        // Pas de mots significatifs dans la requête → pas de filtrage
        if (0 === \count($queryWords)) {
            return true;
        }

        $normalizedTitle = self::normalize($resultTitle);

        if ('' === $normalizedTitle) {
            return false;
        }

        // Au moins un mot significatif de la requête doit apparaître dans le titre
        foreach ($queryWords as $word) {
            if (\str_contains($normalizedTitle, $word)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Extrait les mots significatifs d'une chaîne (hors stopwords et mots courts).
     *
     * @return list<string>
     */
    private static function extractSignificantWords(string $text): array
    {
        $normalized = self::normalize($text);

        if ('' === $normalized) {
            return [];
        }

        $words = \preg_split('/[\s\-_:,;.!?]+/', $normalized, -1, \PREG_SPLIT_NO_EMPTY) ?: [];
        $significant = [];

        foreach ($words as $word) {
            if (\mb_strlen($word) >= self::MIN_WORD_LENGTH && !\in_array($word, self::STOPWORDS, true)) {
                $significant[] = $word;
            }
        }

        return $significant;
    }

    /**
     * Normalise une chaîne : minuscules, suppression des accents, trim.
     */
    private static function normalize(string $text): string
    {
        $text = \mb_strtolower(\trim($text));

        // Suppression des accents via translitération
        $transliterated = \transliterator_transliterate('NFD; [:Nonspacing Mark:] Remove; NFC', $text);

        return \is_string($transliterated) ? $transliterated : $text;
    }
}

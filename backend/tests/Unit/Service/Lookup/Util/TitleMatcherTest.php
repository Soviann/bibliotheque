<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Lookup\Util;

use App\Service\Lookup\Util\TitleMatcher;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Tests unitaires pour TitleMatcher avec garde-fou Levenshtein > 85%.
 */
final class TitleMatcherTest extends TestCase
{
    /**
     * Teste les cas nominaux et de correspondance forte (Levenshtein >= 85%).
     */
    #[DataProvider('matchingTitlesProvider')]
    public function testMatchingTitles(string $query, string $resultTitle): void
    {
        self::assertTrue(
            TitleMatcher::matches($query, $resultTitle),
            \sprintf('"%s" devrait correspondre à "%s"', $query, $resultTitle),
        );
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function matchingTitlesProvider(): iterable
    {
        yield 'titre exact' => ['One Piece', 'One Piece'];
        yield 'casse différente' => ['one piece', 'One Piece'];
        yield 'titre avec articles' => ['Les 3 instincts', 'Les 3 instincts'];
        yield 'article manquant' => ['Walking Dead', 'The Walking Dead'];
        yield 'accentuation différente' => ['étoile', 'Etoile'];
        yield 'translitération accents' => ['Astérix', 'Asterix'];
        yield 'esperluette vs et' => ['Tom & Jerry', 'Tom et Jerry'];
        yield 'tirets et ponctuation' => ['Spider-Man', 'Spider Man'];
        yield 'nettoyage suffixe tome' => ['One Piece', 'One Piece - Tome 1'];
        yield 'nettoyage suffixe volume' => ['Naruto', 'Naruto Vol. 1'];
        yield 'nettoyage suffixe hashtag' => ['Batman', 'Batman #42'];
        yield 'faible faute de frappe (>= 85%)' => ['Berserk', 'Berzerk']; // 1 diff sur 7 = 85.7%
    }

    /**
     * Teste les cas de rejet (sous-titres, spin-offs, titres différents < 85%).
     */
    #[DataProvider('nonMatchingTitlesProvider')]
    public function testNonMatchingTitles(string $query, string $resultTitle): void
    {
        self::assertFalse(
            TitleMatcher::matches($query, $resultTitle),
            \sprintf('"%s" ne devrait PAS correspondre à "%s"', $query, $resultTitle),
        );
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function nonMatchingTitlesProvider(): iterable
    {
        yield 'aucun mot commun' => ['3 instincts', 'Le guide des oiseaux'];
        yield 'titre complètement différent' => ['One Piece', 'Dragon Ball'];
        yield 'spin-off Naruto' => ['Naruto', 'Naruto Shippuden'];
        yield 'spin-off Dragon Ball' => ['Dragon Ball', 'Dragon Ball Super'];
        yield 'film / sous-titre différent' => ['Spider-Man', 'Spider-Man: No Way Home'];
        yield 'album spécifique vs série' => ['Astérix', 'Astérix le Gaulois'];
        yield 'mot clé partagé mais série différente' => ['Solo', 'Han Solo'];
        yield 'distance Levenshtein trop élevée' => ['Monster', 'Monster Hunter'];
    }

    /**
     * Teste les cas limites (chaînes vides, espaces, stopwords seuls).
     */
    public function testEdgeCases(): void
    {
        // Requête vide → rejet
        self::assertFalse(TitleMatcher::matches('', 'Anything'));
        self::assertFalse(TitleMatcher::matches('   ', 'Anything'));

        // Titre résultat vide → rejet
        self::assertFalse(TitleMatcher::matches('query', ''));
        self::assertFalse(TitleMatcher::matches('query', '   '));

        // Deux chaînes vides
        self::assertFalse(TitleMatcher::matches('', ''));
    }

    /**
     * Teste le calcul direct de similarité.
     */
    public function testSimilarityCalculation(): void
    {
        self::assertSame(1.0, TitleMatcher::similarity('One Piece', 'One Piece'));
        self::assertSame(1.0, TitleMatcher::similarity('The Walking Dead', 'Walking Dead'));
        self::assertGreaterThanOrEqual(0.85, TitleMatcher::similarity('Berserk', 'Berzerk'));
        self::assertLessThan(0.85, TitleMatcher::similarity('Naruto', 'Naruto Shippuden'));
        self::assertSame(0.0, TitleMatcher::similarity('', 'One Piece'));
    }

    /**
     * Teste que les chaînes très longues (> 255 caractères) ne contournent pas le seuil de similarité.
     */
    public function testLongStringBypassPrevention(): void
    {
        $longGarbage = \str_repeat('something completely unrelated ', 20); // ~600 chars
        self::assertSame(0.0, TitleMatcher::similarity('Batman', $longGarbage));
        self::assertFalse(TitleMatcher::matches('Batman', $longGarbage));
    }
}

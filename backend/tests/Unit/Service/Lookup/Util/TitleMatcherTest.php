<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Lookup\Util;

use App\Service\Lookup\Util\TitleMatcher;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Tests unitaires pour TitleMatcher.
 */
final class TitleMatcherTest extends TestCase
{
    /**
     * Teste les cas de correspondance évidents.
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
        yield 'mot clé présent' => ['3 instincts', '3 instincts : La survie'];
        yield 'sous-titre dans résultat' => ['Naruto', 'Naruto Shippuden'];
        yield 'accentuation différente' => ['étoile', 'Etoile'];
        yield 'pluriel/singulier' => ['instinct', 'Les instincts'];
        yield 'titre avec tirets' => ['Spider-Man', 'Spider-Man: No Way Home'];
        yield 'mots significatifs partagés' => ['Walking Dead', 'The Walking Dead'];
        yield 'un seul mot significatif correspondant' => ['Astérix', 'Astérix le Gaulois'];
    }

    /**
     * Teste les cas de non-correspondance.
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
        yield 'partage uniquement des stopwords' => ['Les aventures', 'Les misérables'];
        yield 'partage uniquement un article' => ['Le chat', 'Le chien'];
    }

    /**
     * Teste que les cas limites ne plantent pas.
     */
    public function testEdgeCases(): void
    {
        // Requête vide → pas de filtrage, tout passe
        self::assertTrue(TitleMatcher::matches('', 'Anything'));
        self::assertTrue(TitleMatcher::matches('   ', 'Anything'));

        // Titre résultat vide → ne correspond pas
        self::assertFalse(TitleMatcher::matches('query', ''));

        // Requête avec uniquement des mots courts/stopwords → tout passe
        self::assertTrue(TitleMatcher::matches('le la de', 'Anything'));
    }

    /**
     * Teste la normalisation des accents.
     */
    public function testAccentNormalization(): void
    {
        self::assertTrue(TitleMatcher::matches('Astérix', 'Asterix'));
        self::assertTrue(TitleMatcher::matches('café', 'Cafe'));
        self::assertTrue(TitleMatcher::matches('noel', 'Noël'));
    }
}

<?php

declare(strict_types=1);

namespace App\Tests\Enum;

use App\Enum\ComicType;
use PHPUnit\Framework\TestCase;

/**
 * Tests unitaires pour l'enum ComicType.
 */
class ComicTypeTest extends TestCase
{
    /**
     * Teste que tous les cas existent.
     */
    public function testAllCasesExist(): void
    {
        $cases = ComicType::cases();

        self::assertCount(4, $cases);
        self::assertContainsEquals(ComicType::BD, $cases);
        self::assertContainsEquals(ComicType::COMICS, $cases);
        self::assertContainsEquals(ComicType::LIVRE, $cases);
        self::assertContainsEquals(ComicType::MANGA, $cases);
    }

    /**
     * Teste les valeurs string des cas.
     */
    public function testCaseValues(): void
    {
        self::assertSame('bd', ComicType::BD->value);
        self::assertSame('comics', ComicType::COMICS->value);
        self::assertSame('livre', ComicType::LIVRE->value);
        self::assertSame('manga', ComicType::MANGA->value);
    }

    /**
     * Teste getLabel retourne les traductions françaises.
     */
    public function testGetLabelReturnsCorrectTranslations(): void
    {
        self::assertSame('BD', ComicType::BD->getLabel());
        self::assertSame('Comics', ComicType::COMICS->getLabel());
        self::assertSame('Livre', ComicType::LIVRE->getLabel());
        self::assertSame('Manga', ComicType::MANGA->getLabel());
    }

    /**
     * Teste la sérialisation depuis une chaîne.
     */
    public function testFromString(): void
    {
        self::assertSame(ComicType::BD, ComicType::from('bd'));
        self::assertSame(ComicType::COMICS, ComicType::from('comics'));
        self::assertSame(ComicType::LIVRE, ComicType::from('livre'));
        self::assertSame(ComicType::MANGA, ComicType::from('manga'));
    }

    /**
     * Teste tryFrom avec une valeur invalide.
     */
    public function testTryFromWithInvalidValue(): void
    {
        self::assertNull(ComicType::tryFrom('invalid'));
        self::assertNull(ComicType::tryFrom(''));
        self::assertNull(ComicType::tryFrom('BD')); // Sensible à la casse
    }

    /**
     * Teste tryFrom avec une valeur valide.
     */
    public function testTryFromWithValidValue(): void
    {
        self::assertSame(ComicType::BD, ComicType::tryFrom('bd'));
    }
}

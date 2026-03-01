<?php

declare(strict_types=1);

namespace App\Tests\Unit\Enum;

use App\Enum\ComicType;
use PHPUnit\Framework\TestCase;

/**
 * Tests unitaires pour l'enum ComicType.
 */
final class ComicTypeTest extends TestCase
{
    // ---------------------------------------------------------------
    // Valeurs des cases
    // ---------------------------------------------------------------

    public function testBdValue(): void
    {
        self::assertSame('bd', ComicType::BD->value);
    }

    public function testComicsValue(): void
    {
        self::assertSame('comics', ComicType::COMICS->value);
    }

    public function testLivreValue(): void
    {
        self::assertSame('livre', ComicType::LIVRE->value);
    }

    public function testMangaValue(): void
    {
        self::assertSame('manga', ComicType::MANGA->value);
    }

    // ---------------------------------------------------------------
    // Labels
    // ---------------------------------------------------------------

    public function testBdLabel(): void
    {
        self::assertSame('BD', ComicType::BD->getLabel());
    }

    public function testComicsLabel(): void
    {
        self::assertSame('Comics', ComicType::COMICS->getLabel());
    }

    public function testLivreLabel(): void
    {
        self::assertSame('Livre', ComicType::LIVRE->getLabel());
    }

    public function testMangaLabel(): void
    {
        self::assertSame('Manga', ComicType::MANGA->getLabel());
    }

    // ---------------------------------------------------------------
    // Nombre de cases
    // ---------------------------------------------------------------

    public function testCaseCount(): void
    {
        self::assertCount(4, ComicType::cases());
    }

    // ---------------------------------------------------------------
    // Instanciation depuis la valeur
    // ---------------------------------------------------------------

    public function testFromValue(): void
    {
        self::assertSame(ComicType::BD, ComicType::from('bd'));
        self::assertSame(ComicType::COMICS, ComicType::from('comics'));
        self::assertSame(ComicType::LIVRE, ComicType::from('livre'));
        self::assertSame(ComicType::MANGA, ComicType::from('manga'));
    }

    public function testTryFromInvalidValueReturnsNull(): void
    {
        self::assertNull(ComicType::tryFrom('invalid'));
    }
}

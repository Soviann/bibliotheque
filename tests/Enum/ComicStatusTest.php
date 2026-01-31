<?php

declare(strict_types=1);

namespace App\Tests\Enum;

use App\Enum\ComicStatus;
use PHPUnit\Framework\TestCase;

/**
 * Tests unitaires pour l'enum ComicStatus.
 */
class ComicStatusTest extends TestCase
{
    /**
     * Teste que tous les cas existent.
     */
    public function testAllCasesExist(): void
    {
        $cases = ComicStatus::cases();

        self::assertCount(4, $cases);
        self::assertContainsEquals(ComicStatus::BUYING, $cases);
        self::assertContainsEquals(ComicStatus::FINISHED, $cases);
        self::assertContainsEquals(ComicStatus::STOPPED, $cases);
        self::assertContainsEquals(ComicStatus::WISHLIST, $cases);
    }

    /**
     * Teste les valeurs string des cas.
     */
    public function testCaseValues(): void
    {
        self::assertSame('buying', ComicStatus::BUYING->value);
        self::assertSame('finished', ComicStatus::FINISHED->value);
        self::assertSame('stopped', ComicStatus::STOPPED->value);
        self::assertSame('wishlist', ComicStatus::WISHLIST->value);
    }

    /**
     * Teste getLabel retourne les traductions françaises.
     */
    public function testGetLabelReturnsCorrectTranslations(): void
    {
        self::assertSame("En cours d'achat", ComicStatus::BUYING->getLabel());
        self::assertSame('Terminée', ComicStatus::FINISHED->getLabel());
        self::assertSame('Arrêtée', ComicStatus::STOPPED->getLabel());
        self::assertSame('Liste de souhaits', ComicStatus::WISHLIST->getLabel());
    }

    /**
     * Teste la sérialisation depuis une chaîne.
     */
    public function testFromString(): void
    {
        self::assertSame(ComicStatus::BUYING, ComicStatus::from('buying'));
        self::assertSame(ComicStatus::FINISHED, ComicStatus::from('finished'));
        self::assertSame(ComicStatus::STOPPED, ComicStatus::from('stopped'));
        self::assertSame(ComicStatus::WISHLIST, ComicStatus::from('wishlist'));
    }

    /**
     * Teste tryFrom avec une valeur invalide.
     */
    public function testTryFromWithInvalidValue(): void
    {
        self::assertNull(ComicStatus::tryFrom('invalid'));
        self::assertNull(ComicStatus::tryFrom(''));
    }

    /**
     * Teste tryFrom avec une valeur valide.
     */
    public function testTryFromWithValidValue(): void
    {
        self::assertSame(ComicStatus::BUYING, ComicStatus::tryFrom('buying'));
    }
}

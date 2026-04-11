<?php

declare(strict_types=1);

namespace App\Tests\Unit\Enum;

use App\Enum\ComicStatus;
use PHPUnit\Framework\TestCase;

/**
 * Tests unitaires pour l'enum ComicStatus.
 */
final class ComicStatusTest extends TestCase
{
    // ---------------------------------------------------------------
    // Valeurs des cases
    // ---------------------------------------------------------------

    public function testBuyingValue(): void
    {
        self::assertSame('buying', ComicStatus::BUYING->value);
    }

    public function testDownloadingValue(): void
    {
        self::assertSame('downloading', ComicStatus::DOWNLOADING->value);
    }

    public function testFinishedValue(): void
    {
        self::assertSame('finished', ComicStatus::FINISHED->value);
    }

    public function testStoppedValue(): void
    {
        self::assertSame('stopped', ComicStatus::STOPPED->value);
    }

    public function testWishlistValue(): void
    {
        self::assertSame('wishlist', ComicStatus::WISHLIST->value);
    }

    // ---------------------------------------------------------------
    // Labels
    // ---------------------------------------------------------------

    public function testBuyingLabel(): void
    {
        self::assertSame("En cours d'achat", ComicStatus::BUYING->getLabel());
    }

    public function testDownloadingLabel(): void
    {
        self::assertSame('En cours de téléchargement', ComicStatus::DOWNLOADING->getLabel());
    }

    public function testFinishedLabel(): void
    {
        self::assertSame('Terminée', ComicStatus::FINISHED->getLabel());
    }

    public function testStoppedLabel(): void
    {
        self::assertSame('Arrêtée', ComicStatus::STOPPED->getLabel());
    }

    public function testWishlistLabel(): void
    {
        self::assertSame('Liste de souhaits', ComicStatus::WISHLIST->getLabel());
    }

    // ---------------------------------------------------------------
    // Nombre de cases
    // ---------------------------------------------------------------

    public function testCaseCount(): void
    {
        self::assertCount(5, ComicStatus::cases());
    }

    // ---------------------------------------------------------------
    // Instanciation depuis la valeur
    // ---------------------------------------------------------------

    public function testFromValue(): void
    {
        self::assertSame(ComicStatus::BUYING, ComicStatus::from('buying'));
        self::assertSame(ComicStatus::DOWNLOADING, ComicStatus::from('downloading'));
        self::assertSame(ComicStatus::FINISHED, ComicStatus::from('finished'));
        self::assertSame(ComicStatus::STOPPED, ComicStatus::from('stopped'));
        self::assertSame(ComicStatus::WISHLIST, ComicStatus::from('wishlist'));
    }

    public function testTryFromInvalidValueReturnsNull(): void
    {
        self::assertNull(ComicStatus::tryFrom('invalid'));
    }
}

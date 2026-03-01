<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\Entity\Tome;
use App\Tests\Factory\EntityFactory;
use PHPUnit\Framework\TestCase;

/**
 * Tests unitaires pour l'entité Tome.
 */
final class TomeTest extends TestCase
{
    // ---------------------------------------------------------------
    // Valeurs par défaut du constructeur
    // ---------------------------------------------------------------

    public function testConstructorDefaults(): void
    {
        $tome = new Tome();

        self::assertNull($tome->getId());
        self::assertFalse($tome->isBought());
        self::assertFalse($tome->isDownloaded());
        self::assertFalse($tome->isOnNas());
        self::assertFalse($tome->isRead());
        self::assertSame(0, $tome->getNumber());
        self::assertNull($tome->getIsbn());
        self::assertNull($tome->getTitle());
        self::assertNull($tome->getComicSeries());
        self::assertInstanceOf(\DateTimeImmutable::class, $tome->getCreatedAt());
        self::assertInstanceOf(\DateTimeImmutable::class, $tome->getUpdatedAt());
    }

    // ---------------------------------------------------------------
    // Getters / Setters fluides
    // ---------------------------------------------------------------

    public function testSetBoughtReturnsFluent(): void
    {
        $tome = new Tome();
        $result = $tome->setBought(true);

        self::assertSame($tome, $result);
        self::assertTrue($tome->isBought());
    }

    public function testSetBoughtFalse(): void
    {
        $tome = new Tome();
        $tome->setBought(true);
        $tome->setBought(false);

        self::assertFalse($tome->isBought());
    }

    public function testSetDownloadedReturnsFluent(): void
    {
        $tome = new Tome();
        $result = $tome->setDownloaded(true);

        self::assertSame($tome, $result);
        self::assertTrue($tome->isDownloaded());
    }

    public function testSetDownloadedFalse(): void
    {
        $tome = new Tome();
        $tome->setDownloaded(true);
        $tome->setDownloaded(false);

        self::assertFalse($tome->isDownloaded());
    }

    public function testSetOnNasReturnsFluent(): void
    {
        $tome = new Tome();
        $result = $tome->setOnNas(true);

        self::assertSame($tome, $result);
        self::assertTrue($tome->isOnNas());
    }

    public function testSetOnNasFalse(): void
    {
        $tome = new Tome();
        $tome->setOnNas(true);
        $tome->setOnNas(false);

        self::assertFalse($tome->isOnNas());
    }

    public function testSetReadReturnsFluent(): void
    {
        $tome = new Tome();
        $result = $tome->setRead(true);

        self::assertSame($tome, $result);
        self::assertTrue($tome->isRead());
    }

    public function testSetReadFalse(): void
    {
        $tome = new Tome();
        $tome->setRead(true);
        $tome->setRead(false);

        self::assertFalse($tome->isRead());
    }

    public function testSetNumberReturnsFluent(): void
    {
        $tome = new Tome();
        $result = $tome->setNumber(42);

        self::assertSame($tome, $result);
        self::assertSame(42, $tome->getNumber());
    }

    public function testSetNumberZero(): void
    {
        $tome = new Tome();
        $tome->setNumber(5);
        $tome->setNumber(0);

        self::assertSame(0, $tome->getNumber());
    }

    public function testSetIsbnReturnsFluent(): void
    {
        $tome = new Tome();
        $result = $tome->setIsbn('978-2-01-210024-5');

        self::assertSame($tome, $result);
        self::assertSame('978-2-01-210024-5', $tome->getIsbn());
    }

    public function testSetIsbnNull(): void
    {
        $tome = new Tome();
        $tome->setIsbn('978-2-01-210024-5');
        $tome->setIsbn(null);

        self::assertNull($tome->getIsbn());
    }

    public function testSetTitleReturnsFluent(): void
    {
        $tome = new Tome();
        $result = $tome->setTitle('Le Grand Voyage');

        self::assertSame($tome, $result);
        self::assertSame('Le Grand Voyage', $tome->getTitle());
    }

    public function testSetTitleNull(): void
    {
        $tome = new Tome();
        $tome->setTitle('Un titre');
        $tome->setTitle(null);

        self::assertNull($tome->getTitle());
    }

    public function testSetComicSeriesReturnsFluent(): void
    {
        $tome = new Tome();
        $comic = EntityFactory::createComicSeries();
        $result = $tome->setComicSeries($comic);

        self::assertSame($tome, $result);
        self::assertSame($comic, $tome->getComicSeries());
    }

    public function testSetComicSeriesNull(): void
    {
        $tome = new Tome();
        $comic = EntityFactory::createComicSeries();
        $tome->setComicSeries($comic);
        $tome->setComicSeries(null);

        self::assertNull($tome->getComicSeries());
    }

    public function testSetCreatedAtReturnsFluent(): void
    {
        $tome = new Tome();
        $date = new \DateTimeImmutable('2024-03-15');
        $result = $tome->setCreatedAt($date);

        self::assertSame($tome, $result);
        self::assertSame($date, $tome->getCreatedAt());
    }

    public function testSetUpdatedAtReturnsFluent(): void
    {
        $tome = new Tome();
        $date = new \DateTimeImmutable('2024-03-15');
        $result = $tome->setUpdatedAt($date);

        self::assertSame($tome, $result);
        self::assertSame($date, $tome->getUpdatedAt());
    }

    // ---------------------------------------------------------------
    // preUpdate
    // ---------------------------------------------------------------

    public function testPreUpdateSetsNewUpdatedAt(): void
    {
        $tome = new Tome();
        $originalUpdatedAt = $tome->getUpdatedAt();

        \usleep(1000);

        $tome->preUpdate();

        self::assertInstanceOf(\DateTimeImmutable::class, $tome->getUpdatedAt());
        self::assertGreaterThan($originalUpdatedAt, $tome->getUpdatedAt());
    }

    // ---------------------------------------------------------------
    // EntityFactory
    // ---------------------------------------------------------------

    public function testFactoryCreateTomeSetsValues(): void
    {
        $tome = EntityFactory::createTome(
            number: 7,
            bought: true,
            downloaded: true,
            onNas: true,
            read: true,
        );

        self::assertSame(7, $tome->getNumber());
        self::assertTrue($tome->isBought());
        self::assertTrue($tome->isDownloaded());
        self::assertTrue($tome->isOnNas());
        self::assertTrue($tome->isRead());
    }
}

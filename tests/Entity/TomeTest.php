<?php

declare(strict_types=1);

namespace App\Tests\Entity;

use App\Entity\ComicSeries;
use App\Entity\Tome;
use PHPUnit\Framework\TestCase;

/**
 * Tests unitaires pour l'entité Tome.
 */
class TomeTest extends TestCase
{
    /**
     * Teste que le constructeur initialise createdAt.
     */
    public function testConstructorInitializesCreatedAt(): void
    {
        $before = new \DateTimeImmutable();
        $tome = new Tome();
        $after = new \DateTimeImmutable();

        self::assertGreaterThanOrEqual($before, $tome->getCreatedAt());
        self::assertLessThanOrEqual($after, $tome->getCreatedAt());
    }

    /**
     * Teste que le constructeur initialise updatedAt.
     */
    public function testConstructorInitializesUpdatedAt(): void
    {
        $before = new \DateTimeImmutable();
        $tome = new Tome();
        $after = new \DateTimeImmutable();

        self::assertGreaterThanOrEqual($before, $tome->getUpdatedAt());
        self::assertLessThanOrEqual($after, $tome->getUpdatedAt());
    }

    /**
     * Teste les valeurs par défaut des booléens.
     */
    public function testDefaultBooleanValues(): void
    {
        $tome = new Tome();

        self::assertFalse($tome->isBought());
        self::assertFalse($tome->isDownloaded());
        self::assertFalse($tome->isOnNas());
        self::assertFalse($tome->isRead());
    }

    /**
     * Teste le getter et setter de number.
     */
    public function testNumberGetterAndSetter(): void
    {
        $tome = new Tome();
        $tome->setNumber(5);

        self::assertSame(5, $tome->getNumber());
    }

    /**
     * Teste le getter et setter de bought.
     */
    public function testBoughtGetterAndSetter(): void
    {
        $tome = new Tome();
        $tome->setBought(true);

        self::assertTrue($tome->isBought());
    }

    /**
     * Teste le getter et setter de downloaded.
     */
    public function testDownloadedGetterAndSetter(): void
    {
        $tome = new Tome();
        $tome->setDownloaded(true);

        self::assertTrue($tome->isDownloaded());
    }

    /**
     * Teste le getter et setter de onNas.
     */
    public function testOnNasGetterAndSetter(): void
    {
        $tome = new Tome();
        $tome->setOnNas(true);

        self::assertTrue($tome->isOnNas());
    }

    /**
     * Teste le getter et setter de read.
     */
    public function testReadGetterAndSetter(): void
    {
        $tome = new Tome();
        $tome->setRead(true);

        self::assertTrue($tome->isRead());
    }

    /**
     * Teste le getter et setter de isbn.
     */
    public function testIsbnGetterAndSetter(): void
    {
        $tome = new Tome();
        $tome->setIsbn('978-2-505-00123-4');

        self::assertSame('978-2-505-00123-4', $tome->getIsbn());
    }

    /**
     * Teste que isbn peut être null.
     */
    public function testIsbnCanBeNull(): void
    {
        $tome = new Tome();
        $tome->setIsbn(null);

        self::assertNull($tome->getIsbn());
    }

    /**
     * Teste le getter et setter de title.
     */
    public function testTitleGetterAndSetter(): void
    {
        $tome = new Tome();
        $tome->setTitle('Le Retour du Héros');

        self::assertSame('Le Retour du Héros', $tome->getTitle());
    }

    /**
     * Teste que title peut être null.
     */
    public function testTitleCanBeNull(): void
    {
        $tome = new Tome();
        $tome->setTitle(null);

        self::assertNull($tome->getTitle());
    }

    /**
     * Teste le getter et setter de comicSeries.
     */
    public function testComicSeriesGetterAndSetter(): void
    {
        $tome = new Tome();
        $series = new ComicSeries();

        $tome->setComicSeries($series);

        self::assertSame($series, $tome->getComicSeries());
    }

    /**
     * Teste que comicSeries peut être null.
     */
    public function testComicSeriesCanBeNull(): void
    {
        $tome = new Tome();
        $tome->setComicSeries(null);

        self::assertNull($tome->getComicSeries());
    }

    /**
     * Teste le getter et setter de createdAt.
     */
    public function testCreatedAtGetterAndSetter(): void
    {
        $tome = new Tome();
        $date = new \DateTimeImmutable('2023-01-15');

        $tome->setCreatedAt($date);

        self::assertSame($date, $tome->getCreatedAt());
    }

    /**
     * Teste le getter et setter de updatedAt.
     */
    public function testUpdatedAtGetterAndSetter(): void
    {
        $tome = new Tome();
        $date = new \DateTimeImmutable('2023-06-20');

        $tome->setUpdatedAt($date);

        self::assertSame($date, $tome->getUpdatedAt());
    }

    /**
     * Teste que getId retourne null pour une nouvelle entité.
     */
    public function testGetIdReturnsNullForNewEntity(): void
    {
        $tome = new Tome();

        self::assertNull($tome->getId());
    }

    /**
     * Teste que setNumber retourne l'instance pour le chaînage.
     */
    public function testSetNumberReturnsInstance(): void
    {
        $tome = new Tome();

        $result = $tome->setNumber(1);

        self::assertSame($tome, $result);
    }

    /**
     * Teste que setBought retourne l'instance pour le chaînage.
     */
    public function testSetBoughtReturnsInstance(): void
    {
        $tome = new Tome();

        $result = $tome->setBought(true);

        self::assertSame($tome, $result);
    }

    /**
     * Teste que setDownloaded retourne l'instance pour le chaînage.
     */
    public function testSetDownloadedReturnsInstance(): void
    {
        $tome = new Tome();

        $result = $tome->setDownloaded(true);

        self::assertSame($tome, $result);
    }

    /**
     * Teste que setOnNas retourne l'instance pour le chaînage.
     */
    public function testSetOnNasReturnsInstance(): void
    {
        $tome = new Tome();

        $result = $tome->setOnNas(true);

        self::assertSame($tome, $result);
    }

    /**
     * Teste que setRead retourne l'instance pour le chaînage.
     */
    public function testSetReadReturnsInstance(): void
    {
        $tome = new Tome();

        $result = $tome->setRead(true);

        self::assertSame($tome, $result);
    }

    /**
     * Teste que setIsbn retourne l'instance pour le chaînage.
     */
    public function testSetIsbnReturnsInstance(): void
    {
        $tome = new Tome();

        $result = $tome->setIsbn('123');

        self::assertSame($tome, $result);
    }

    /**
     * Teste que setTitle retourne l'instance pour le chaînage.
     */
    public function testSetTitleReturnsInstance(): void
    {
        $tome = new Tome();

        $result = $tome->setTitle('Test');

        self::assertSame($tome, $result);
    }

    /**
     * Teste que setComicSeries retourne l'instance pour le chaînage.
     */
    public function testSetComicSeriesReturnsInstance(): void
    {
        $tome = new Tome();

        $result = $tome->setComicSeries(new ComicSeries());

        self::assertSame($tome, $result);
    }
}

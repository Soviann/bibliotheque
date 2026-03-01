<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\Entity\Author;
use App\Tests\Factory\EntityFactory;
use Doctrine\Common\Collections\Collection;
use PHPUnit\Framework\TestCase;

/**
 * Tests unitaires pour l'entité Author.
 */
final class AuthorTest extends TestCase
{
    // ---------------------------------------------------------------
    // Valeurs par défaut du constructeur
    // ---------------------------------------------------------------

    public function testConstructorDefaults(): void
    {
        $author = new Author();

        self::assertNull($author->getId());
        self::assertSame('', $author->getName());
        self::assertInstanceOf(Collection::class, $author->getComicSeries());
        self::assertCount(0, $author->getComicSeries());
    }

    // ---------------------------------------------------------------
    // Getters / Setters
    // ---------------------------------------------------------------

    public function testSetNameReturnsFluent(): void
    {
        $author = new Author();
        $result = $author->setName('Goscinny');

        self::assertSame($author, $result);
        self::assertSame('Goscinny', $author->getName());
    }

    public function testSetNameEmptyString(): void
    {
        $author = new Author();
        $author->setName('Goscinny');
        $author->setName('');

        self::assertSame('', $author->getName());
    }

    // ---------------------------------------------------------------
    // __toString
    // ---------------------------------------------------------------

    public function testToStringReturnsName(): void
    {
        $author = EntityFactory::createAuthor('Uderzo');

        self::assertSame('Uderzo', (string) $author);
    }

    public function testToStringEmptyName(): void
    {
        $author = new Author();

        self::assertSame('', (string) $author);
    }

    // ---------------------------------------------------------------
    // addComicSeries / removeComicSeries (bidirectionnel)
    // ---------------------------------------------------------------

    public function testAddComicSeriesAddsToCollectionAndSyncs(): void
    {
        $author = EntityFactory::createAuthor('Hergé');
        $comic = EntityFactory::createComicSeries('Tintin');

        $result = $author->addComicSeries($comic);

        self::assertSame($author, $result);
        self::assertCount(1, $author->getComicSeries());
        self::assertTrue($author->getComicSeries()->contains($comic));
        // Vérifie la synchronisation bidirectionnelle
        self::assertTrue($comic->getAuthors()->contains($author));
    }

    public function testAddComicSeriesNoDuplicates(): void
    {
        $author = EntityFactory::createAuthor('Hergé');
        $comic = EntityFactory::createComicSeries('Tintin');

        $author->addComicSeries($comic);
        $author->addComicSeries($comic);

        self::assertCount(1, $author->getComicSeries());
    }

    public function testAddComicSeriesMultipleSeries(): void
    {
        $author = EntityFactory::createAuthor('Hergé');
        $comic1 = EntityFactory::createComicSeries('Tintin');
        $comic2 = EntityFactory::createComicSeries('Quick et Flupke');

        $author->addComicSeries($comic1);
        $author->addComicSeries($comic2);

        self::assertCount(2, $author->getComicSeries());
        self::assertTrue($author->getComicSeries()->contains($comic1));
        self::assertTrue($author->getComicSeries()->contains($comic2));
    }

    public function testRemoveComicSeriesRemovesAndSyncs(): void
    {
        $author = EntityFactory::createAuthor('Hergé');
        $comic = EntityFactory::createComicSeries('Tintin');

        $author->addComicSeries($comic);
        $result = $author->removeComicSeries($comic);

        self::assertSame($author, $result);
        self::assertCount(0, $author->getComicSeries());
        self::assertFalse($author->getComicSeries()->contains($comic));
        // Vérifie la synchronisation bidirectionnelle
        self::assertFalse($comic->getAuthors()->contains($author));
    }

    public function testRemoveComicSeriesNotInCollection(): void
    {
        $author = EntityFactory::createAuthor('Hergé');
        $comic = EntityFactory::createComicSeries('Tintin');

        $result = $author->removeComicSeries($comic);

        self::assertSame($author, $result);
        self::assertCount(0, $author->getComicSeries());
    }

    // ---------------------------------------------------------------
    // Stringable interface
    // ---------------------------------------------------------------

    public function testImplementsStringable(): void
    {
        $author = new Author();

        self::assertInstanceOf(\Stringable::class, $author);
    }
}

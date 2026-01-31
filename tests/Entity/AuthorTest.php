<?php

declare(strict_types=1);

namespace App\Tests\Entity;

use App\Entity\Author;
use App\Entity\ComicSeries;
use PHPUnit\Framework\TestCase;

/**
 * Tests unitaires pour l'entité Author.
 */
class AuthorTest extends TestCase
{
    /**
     * Teste que le constructeur initialise la collection comicSeries.
     */
    public function testConstructorInitializesComicSeriesCollection(): void
    {
        $author = new Author();

        self::assertCount(0, $author->getComicSeries());
    }

    /**
     * Teste que __toString retourne le nom.
     */
    public function testToStringReturnsName(): void
    {
        $author = new Author();
        $author->setName('Jean Dupont');

        self::assertSame('Jean Dupont', (string) $author);
    }

    /**
     * Teste que __toString retourne une chaîne vide si le nom est null.
     */
    public function testToStringReturnsEmptyStringWhenNameIsNull(): void
    {
        $author = new Author();

        self::assertSame('', (string) $author);
    }

    /**
     * Teste le getter et setter du nom.
     */
    public function testNameGetterAndSetter(): void
    {
        $author = new Author();
        $author->setName('Marie Martin');

        self::assertSame('Marie Martin', $author->getName());
    }

    /**
     * Teste que getId retourne null pour une nouvelle entité.
     */
    public function testGetIdReturnsNullForNewEntity(): void
    {
        $author = new Author();

        self::assertNull($author->getId());
    }

    /**
     * Teste l'ajout d'une série à un auteur.
     */
    public function testAddComicSeries(): void
    {
        $author = new Author();
        $series = new ComicSeries();

        $author->addComicSeries($series);

        self::assertCount(1, $author->getComicSeries());
        self::assertTrue($author->getComicSeries()->contains($series));
    }

    /**
     * Teste que l'ajout d'une série est bidirectionnel.
     */
    public function testAddComicSeriesIsBidirectional(): void
    {
        $author = new Author();
        $series = new ComicSeries();

        $author->addComicSeries($series);

        self::assertTrue($series->getAuthors()->contains($author));
    }

    /**
     * Teste que l'ajout de la même série deux fois ne crée pas de doublon.
     */
    public function testAddComicSeriesDoesNotDuplicate(): void
    {
        $author = new Author();
        $series = new ComicSeries();

        $author->addComicSeries($series);
        $author->addComicSeries($series);

        self::assertCount(1, $author->getComicSeries());
    }

    /**
     * Teste la suppression d'une série d'un auteur.
     */
    public function testRemoveComicSeries(): void
    {
        $author = new Author();
        $series = new ComicSeries();

        $author->addComicSeries($series);
        $author->removeComicSeries($series);

        self::assertCount(0, $author->getComicSeries());
    }

    /**
     * Teste que la suppression d'une série est bidirectionnelle.
     */
    public function testRemoveComicSeriesIsBidirectional(): void
    {
        $author = new Author();
        $series = new ComicSeries();

        $author->addComicSeries($series);
        $author->removeComicSeries($series);

        self::assertFalse($series->getAuthors()->contains($author));
    }

    /**
     * Teste que setName retourne l'instance pour le chaînage.
     */
    public function testSetNameReturnsInstance(): void
    {
        $author = new Author();

        $result = $author->setName('Test');

        self::assertSame($author, $result);
    }

    /**
     * Teste que addComicSeries retourne l'instance pour le chaînage.
     */
    public function testAddComicSeriesReturnsInstance(): void
    {
        $author = new Author();
        $series = new ComicSeries();

        $result = $author->addComicSeries($series);

        self::assertSame($author, $result);
    }

    /**
     * Teste que removeComicSeries retourne l'instance pour le chaînage.
     */
    public function testRemoveComicSeriesReturnsInstance(): void
    {
        $author = new Author();
        $series = new ComicSeries();

        $result = $author->removeComicSeries($series);

        self::assertSame($author, $result);
    }
}

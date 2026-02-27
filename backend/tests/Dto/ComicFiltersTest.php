<?php

declare(strict_types=1);

namespace App\Tests\Dto;

use App\Dto\ComicFilters;
use App\Enum\ComicStatus;
use App\Enum\ComicType;
use PHPUnit\Framework\TestCase;

/**
 * Tests unitaires pour le DTO ComicFilters.
 */
class ComicFiltersTest extends TestCase
{
    /**
     * Teste les valeurs par défaut.
     */
    public function testDefaultValues(): void
    {
        $filters = new ComicFilters();

        self::assertNull($filters->nas);
        self::assertNull($filters->q);
        self::assertNull($filters->reading);
        self::assertSame('title_asc', $filters->sort);
        self::assertNull($filters->status);
        self::assertNull($filters->type);
    }

    /**
     * Teste getOnNas retourne true quand nas = '1'.
     */
    public function testGetOnNasReturnsTrue(): void
    {
        $filters = new ComicFilters(nas: '1');

        self::assertTrue($filters->getOnNas());
    }

    /**
     * Teste getOnNas retourne false quand nas = '0'.
     */
    public function testGetOnNasReturnsFalse(): void
    {
        $filters = new ComicFilters(nas: '0');

        self::assertFalse($filters->getOnNas());
    }

    /**
     * Teste getOnNas retourne null quand nas n'est pas défini.
     */
    public function testGetOnNasReturnsNullWhenNotSet(): void
    {
        $filters = new ComicFilters();

        self::assertNull($filters->getOnNas());
    }

    /**
     * Teste getSearch retourne la valeur de q.
     */
    public function testGetSearchReturnsValue(): void
    {
        $filters = new ComicFilters(q: 'naruto');

        self::assertSame('naruto', $filters->getSearch());
    }

    /**
     * Teste getSearch retourne null quand q est vide.
     */
    public function testGetSearchReturnsNullWhenEmpty(): void
    {
        $filters = new ComicFilters(q: '');

        self::assertNull($filters->getSearch());
    }

    /**
     * Teste getSearch retourne null quand q n'est pas défini.
     */
    public function testGetSearchReturnsNullWhenNotSet(): void
    {
        $filters = new ComicFilters();

        self::assertNull($filters->getSearch());
    }

    /**
     * Teste getReading retourne la valeur du filtre lecture.
     */
    public function testGetReadingReturnsValue(): void
    {
        $filters = new ComicFilters(reading: 'reading');

        self::assertSame('reading', $filters->getReading());
    }

    /**
     * Teste getReading retourne null pour une valeur invalide.
     */
    public function testGetReadingReturnsNullForInvalidValue(): void
    {
        $filters = new ComicFilters(reading: 'invalid');

        self::assertNull($filters->getReading());
    }

    /**
     * Teste getReading retourne null quand non défini.
     */
    public function testGetReadingReturnsNullWhenNotSet(): void
    {
        $filters = new ComicFilters();

        self::assertNull($filters->getReading());
    }

    /**
     * Teste les trois valeurs valides de getReading.
     */
    public function testGetReadingValidValues(): void
    {
        self::assertSame('read', (new ComicFilters(reading: 'read'))->getReading());
        self::assertSame('reading', (new ComicFilters(reading: 'reading'))->getReading());
        self::assertSame('unread', (new ComicFilters(reading: 'unread'))->getReading());
    }

    /**
     * Teste la construction avec tous les paramètres.
     */
    public function testConstructWithAllParameters(): void
    {
        $filters = new ComicFilters(
            nas: '1',
            q: 'asterix',
            reading: 'reading',
            sort: 'updated_desc',
            status: 'buying',
            type: 'bd',
        );

        self::assertSame('1', $filters->nas);
        self::assertSame('asterix', $filters->q);
        self::assertSame('reading', $filters->reading);
        self::assertSame('updated_desc', $filters->sort);
        self::assertSame('buying', $filters->status);
        self::assertSame('bd', $filters->type);
        self::assertTrue($filters->getOnNas());
        self::assertSame('reading', $filters->getReading());
        self::assertSame('asterix', $filters->getSearch());
        self::assertSame(ComicStatus::BUYING, $filters->getStatus());
        self::assertSame(ComicType::BD, $filters->getType());
    }

    /**
     * Teste getStatus retourne l'enum correspondant.
     */
    public function testGetStatusReturnsEnum(): void
    {
        $filters = new ComicFilters(status: 'finished');

        self::assertSame(ComicStatus::FINISHED, $filters->getStatus());
    }

    /**
     * Teste getStatus retourne null pour une valeur invalide.
     */
    public function testGetStatusReturnsNullForInvalidValue(): void
    {
        $filters = new ComicFilters(status: 'invalid');

        self::assertNull($filters->getStatus());
    }

    /**
     * Teste getStatus retourne null quand non défini.
     */
    public function testGetStatusReturnsNullWhenNotSet(): void
    {
        $filters = new ComicFilters();

        self::assertNull($filters->getStatus());
    }

    /**
     * Teste getType retourne l'enum correspondant.
     */
    public function testGetTypeReturnsEnum(): void
    {
        $filters = new ComicFilters(type: 'manga');

        self::assertSame(ComicType::MANGA, $filters->getType());
    }

    /**
     * Teste getType retourne null pour une valeur invalide.
     */
    public function testGetTypeReturnsNullForInvalidValue(): void
    {
        $filters = new ComicFilters(type: 'invalid');

        self::assertNull($filters->getType());
    }

    /**
     * Teste getType retourne null quand non défini.
     */
    public function testGetTypeReturnsNullWhenNotSet(): void
    {
        $filters = new ComicFilters();

        self::assertNull($filters->getType());
    }
}

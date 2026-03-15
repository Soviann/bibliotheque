<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Nas;

use App\Service\Nas\NasDirectoryParser;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Tests du parsing des répertoires NAS.
 */
final class NasDirectoryParserTest extends TestCase
{
    private NasDirectoryParser $parser;

    protected function setUp(): void
    {
        $this->parser = new NasDirectoryParser();
    }

    // --- extractTomeNumber ---

    #[DataProvider('tomeNumberProvider')]
    public function testExtractTomeNumber(string $filename, ?int $expected): void
    {
        self::assertSame($expected, $this->parser->extractTomeNumber($filename));
    }

    /**
     * @return iterable<string, array{string, ?int}>
     */
    public static function tomeNumberProvider(): iterable
    {
        yield 'format "Série 01 - Titre.cbr"' => [
            'Androïdes 01 - Résurrection - (Digital)(phillywilly-Empire).cbr',
            1,
        ];

        yield 'format "Série 07.cbr"' => [
            'Androïdes 07.cbr',
            7,
        ];

        yield 'format "Série - T01 - Titre.cbr"' => [
            'Blake & Mortimer -  01 Le Secret de L\'Espadon [Tome 1].cbr',
            1,
        ];

        yield 'format "BDFR - SERIE - 01 - Titre.cbz"' => [
            'BDFR - CEDRIC - 05 - Quelle Mouche Le Pique.cbz',
            5,
        ];

        yield 'format "bd_fr_serie_t10_titre.cbr"' => [
            'bd_fr_achille_talon_t10_le_roi_de_la_science_diction.cbr',
            10,
        ];

        yield 'format "04 - Vilyana.pdf"' => [
            '04 - Vilyana.pdf',
            4,
        ];

        yield 'format "Nausicaa 01 (Source)"' => [
            'Nausicaa 01 (Krystal)',
            1,
        ];

        yield 'format "Série (T01-06)"' => [
            'Artica (T01-06)',
            6,
        ];

        yield 'format "Anahire - Tome  1 à 4"' => [
            'Anahire - Tome  1 à 4',
            4,
        ];

        yield 'pas de numéro' => [
            '@eaDir',
            null,
        ];

        yield 'fichier unique one-shot' => [
            'Death Note (One Shot) - fini.cbz',
            null,
        ];

        yield 'fichier zip doublon' => [
            'Crossbeat-[one-shot].zip',
            null,
        ];
    }

    // --- parseSeriesTitle ---

    #[DataProvider('seriesTitleProvider')]
    public function testParseSeriesTitle(string $dirName, string $expectedTitle, bool $expectedComplete): void
    {
        $result = $this->parser->parseSeriesDirectory($dirName);

        self::assertSame($expectedTitle, $result['title']);
        self::assertSame($expectedComplete, $result['isComplete']);
    }

    /**
     * @return iterable<string, array{string, string, bool}>
     */
    public static function seriesTitleProvider(): iterable
    {
        yield 'titre simple' => [
            'Androides',
            'Androides',
            false,
        ];

        yield 'titre avec (complet)' => [
            '4 Princes De Ganahan (les) (complet)',
            '4 Princes De Ganahan (les)',
            true,
        ];

        yield 'titre avec (incomplet)' => [
            '42 agents intergalactiques (incomplet)',
            '42 agents intergalactiques',
            false,
        ];

        yield 'titre avec (COMPLET) majuscules' => [
            'Axis (2014).(COMPLET).VO.cbr-KAIL',
            'Axis (2014).(COMPLET).VO.cbr-KAIL',
            false,
        ];

        yield 'titre avec article (l\')' => [
            'Alkaest (l\')',
            'Alkaest (l\')',
            false,
        ];

        yield 'Anachron (complet)' => [
            'Anachron (complet)',
            'Anachron',
            true,
        ];
    }

    // --- parseListing pour /volume1/lecture/{type}/ ---

    public function testParseUnreadListing(): void
    {
        $listing = [
            '@eaDir',
            'Androides',
            '4 Princes De Ganahan (les) (complet)',
            '_lus',
        ];

        $filesByDir = [
            'Androides' => [
                'Androïdes 01 - Résurrection.cbr',
                'Androïdes 02 - Heureux qui comme Ulysse.cbr',
                'Androïdes 07.cbr',
            ],
            '4 Princes De Ganahan (les) (complet)' => [
                'Les 4 Princes De Ganahan - T01 - Galin.cbr',
                'Les 4 Princes De Ganahan - T02 - Shaal.cbr',
                'Les 4 Princes De Ganahan - T03 - Filien.cbr',
                'Les 4 Princes De Ganahan - T04 - Althis.cbr',
            ],
        ];

        $result = $this->parser->parseUnreadSeries($listing, $filesByDir);

        self::assertCount(2, $result);

        // Androides : pas complet, 7 tomes, pas lu
        self::assertSame('Androides', $result[0]->title);
        self::assertFalse($result[0]->isComplete);
        self::assertSame(7, $result[0]->lastDownloaded);
        self::assertNull($result[0]->readUpTo);
        self::assertFalse($result[0]->readComplete);

        // 4 Princes : complet, 4 tomes, pas lu
        self::assertSame('4 Princes De Ganahan (les)', $result[1]->title);
        self::assertTrue($result[1]->isComplete);
        self::assertSame(4, $result[1]->lastDownloaded);
        self::assertNull($result[1]->readUpTo);
        self::assertFalse($result[1]->readComplete);
    }

    public function testParseReadListing(): void
    {
        $listing = [
            'Blake & Mortimer',
            'Cedric',
        ];

        $filesByDir = [
            'Blake & Mortimer' => [
                'Blake & Mortimer -  01 Le Secret.cbr',
                'Blake & Mortimer -  02 Le Secret T2.cbr',
                'Blake & Mortimer -  03 Le Secret T3.cbr',
                'Blake & Mortimer -  04 Le Mystere.cbr',
            ],
            'Cedric' => [
                'BDFR - CEDRIC - 01 - Premières Classes.cbz',
                'BDFR - CEDRIC - 10 - Gâteau Surprise.cbz',
            ],
        ];

        $result = $this->parser->parseReadSeries($listing, $filesByDir);

        self::assertCount(2, $result);

        self::assertSame('Blake & Mortimer', $result[0]->title);
        self::assertSame(4, $result[0]->lastDownloaded);
        self::assertTrue($result[0]->readComplete);

        self::assertSame('Cedric', $result[1]->title);
        self::assertSame(10, $result[1]->lastDownloaded);
        self::assertTrue($result[1]->readComplete);
    }

    public function testParseInProgressListing(): void
    {
        $listing = [
            'Achille Talon',
            'Angor',
            'Anachron (complet)',
        ];

        $filesByDir = [
            'Achille Talon' => [
                'bd_fr_achille_talon_t10_le_roi_de_la_science_diction.cbr',
                'bd_fr_achille_talon_t11_brave_et_honnete.cbr',
                'bd_fr_achille_talon_t45_le_maitre_est_talon.cbr',
            ],
            'Angor' => [
                '04 - Vilyana.pdf',
                '05 - Lekerson.pdf',
            ],
            'Anachron (complet)' => [
                'Anachron 01.cbr',
                'Anachron 02.cbr',
                'Anachron 03.cbr',
            ],
        ];

        $result = $this->parser->parseInProgressSeries($listing, $filesByDir);

        self::assertCount(3, $result);

        // Achille Talon : commence au T10, donc T1-T9 lus → readUpTo = 9
        self::assertSame('Achille Talon', $result[0]->title);
        self::assertSame(45, $result[0]->lastDownloaded);
        self::assertSame(9, $result[0]->readUpTo);
        self::assertFalse($result[0]->readComplete);

        // Angor : commence au T4, donc T1-T3 lus → readUpTo = 3
        self::assertSame('Angor', $result[1]->title);
        self::assertSame(5, $result[1]->lastDownloaded);
        self::assertSame(3, $result[1]->readUpTo);
        self::assertFalse($result[1]->readComplete);

        // Anachron (complet) : commence au T1, donc readUpTo = null (pas de tomes précédents lus)
        self::assertSame('Anachron', $result[2]->title);
        self::assertTrue($result[2]->isComplete);
        self::assertSame(3, $result[2]->lastDownloaded);
        self::assertNull($result[2]->readUpTo);
        self::assertFalse($result[2]->readComplete);
    }

    public function testParseUnreadListingSkipsMetadataDirs(): void
    {
        $listing = ['@eaDir', '#recycle', '_lus', 'Androides'];
        $filesByDir = [
            'Androides' => ['Androïdes 01.cbr'],
        ];

        $result = $this->parser->parseUnreadSeries($listing, $filesByDir);

        self::assertCount(1, $result);
        self::assertSame('Androides', $result[0]->title);
    }

    public function testParseRangeTomeFormat(): void
    {
        $listing = ['Artica (T01-06)'];
        $filesByDir = [];

        $result = $this->parser->parseUnreadSeries($listing, $filesByDir);

        self::assertCount(1, $result);
        self::assertSame('Artica', $result[0]->title);
        self::assertSame(6, $result[0]->lastDownloaded);
    }

    public function testParseRangeTomeFormatWithA(): void
    {
        $listing = ['Anahire - Tome  1 à 4'];
        $filesByDir = [];

        $result = $this->parser->parseUnreadSeries($listing, $filesByDir);

        self::assertCount(1, $result);
        self::assertSame('Anahire', $result[0]->title);
        self::assertSame(4, $result[0]->lastDownloaded);
    }
}

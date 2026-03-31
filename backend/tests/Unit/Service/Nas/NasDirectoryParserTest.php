<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Nas;

use App\DTO\NasSeriesData;
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

        yield 'tome 0' => [
            'Serie 00 - Prologue.cbr',
            0,
        ];

        yield 'tome 0 avec T' => [
            'Serie - T00 - Origines.cbz',
            0,
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

        yield 'titre avec (COMPLET) majuscules et extension' => [
            'Axis (2014).(COMPLET).VO.cbr-KAIL',
            'Axis',
            true,
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

        yield 'tome individuel T01 - titre' => [
            'Clockwerx T01 - Genèse',
            'Clockwerx',
            false,
        ];

        yield 'tome individuel T00' => [
            'Chaos team T00 - La vengeance du Beret Vert',
            'Chaos team',
            false,
        ];

        yield 'tome individuel T01 seul' => [
            'Chaos team T01',
            'Chaos team',
            false,
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
        self::assertSame(7, $result[0]->lastOnNas);
        self::assertNull($result[0]->readUpTo);
        self::assertFalse($result[0]->readComplete);

        // 4 Princes : complet, 4 tomes, pas lu
        self::assertSame('4 Princes De Ganahan (les)', $result[1]->title);
        self::assertTrue($result[1]->isComplete);
        self::assertSame(4, $result[1]->lastOnNas);
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

        // Blake & Mortimer : pas (complet), 4 tomes lus
        self::assertSame('Blake & Mortimer', $result[0]->title);
        self::assertSame(4, $result[0]->lastOnNas);
        self::assertFalse($result[0]->readComplete);
        self::assertSame(4, $result[0]->readUpTo);

        // Cedric : pas (complet), 10 tomes lus
        self::assertSame('Cedric', $result[1]->title);
        self::assertSame(10, $result[1]->lastOnNas);
        self::assertFalse($result[1]->readComplete);
        self::assertSame(10, $result[1]->readUpTo);
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
        self::assertSame(45, $result[0]->lastOnNas);
        self::assertSame(9, $result[0]->readUpTo);
        self::assertFalse($result[0]->readComplete);

        // Angor : commence au T4, donc T1-T3 lus → readUpTo = 3
        self::assertSame('Angor', $result[1]->title);
        self::assertSame(5, $result[1]->lastOnNas);
        self::assertSame(3, $result[1]->readUpTo);
        self::assertFalse($result[1]->readComplete);

        // Anachron (complet) : commence au T1, donc readUpTo = null (pas de tomes précédents lus)
        self::assertSame('Anachron', $result[2]->title);
        self::assertTrue($result[2]->isComplete);
        self::assertSame(3, $result[2]->lastOnNas);
        self::assertNull($result[2]->readUpTo);
        self::assertFalse($result[2]->readComplete);
    }

    public function testParseReadListingWithComplete(): void
    {
        $listing = [
            'Anachron (complet)',
        ];

        $filesByDir = [
            'Anachron (complet)' => [
                'Anachron 01.cbr',
                'Anachron 02.cbr',
                'Anachron 03.cbr',
            ],
        ];

        $result = $this->parser->parseReadSeries($listing, $filesByDir);

        self::assertCount(1, $result);

        // Anachron (complet) dans _lus : readComplete = true
        self::assertSame('Anachron', $result[0]->title);
        self::assertTrue($result[0]->isComplete);
        self::assertSame(3, $result[0]->lastOnNas);
        self::assertTrue($result[0]->readComplete);
        self::assertNull($result[0]->readUpTo);
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

    // --- mergeDuplicateSeries ---

    public function testMergeDuplicateSeriesCombinesData(): void
    {
        $series = [
            // Depuis BD/ : 15 tomes téléchargés, pas lu
            new NasSeriesData(
                isComplete: false,
                lastOnNas: 15,
                readUpTo: null,
                readComplete: false,
                title: 'Blake et Mortimer',
            ),
            // Depuis BD/_lus/ : 10 tomes lus
            new NasSeriesData(
                isComplete: false,
                lastOnNas: 10,
                readUpTo: 10,
                readComplete: false,
                title: 'Blake et Mortimer',
            ),
        ];

        $result = $this->parser->mergeDuplicateSeries($series);

        self::assertCount(1, $result);
        self::assertSame('Blake et Mortimer', $result[0]->title);
        self::assertSame(15, $result[0]->lastOnNas);
        self::assertSame(10, $result[0]->readUpTo);
        self::assertFalse($result[0]->readComplete);
        self::assertFalse($result[0]->isComplete);
    }

    public function testMergeDuplicateSeriesPreservesComplete(): void
    {
        $series = [
            // Depuis BD/ : marqué (complet), 4 tomes
            new NasSeriesData(
                isComplete: true,
                lastOnNas: 4,
                readUpTo: null,
                readComplete: false,
                title: 'Anachron',
            ),
            // Depuis BD/_lus/ : 2 tomes lus
            new NasSeriesData(
                isComplete: false,
                lastOnNas: 2,
                readUpTo: 2,
                readComplete: false,
                title: 'Anachron',
            ),
        ];

        $result = $this->parser->mergeDuplicateSeries($series);

        self::assertCount(1, $result);
        self::assertSame('Anachron', $result[0]->title);
        self::assertSame(4, $result[0]->lastOnNas);
        self::assertSame(2, $result[0]->readUpTo);
        self::assertTrue($result[0]->isComplete);
        self::assertFalse($result[0]->readComplete);
    }

    public function testMergeDuplicateSeriesThreeSources(): void
    {
        $series = [
            // Depuis BD/ : 20 tomes téléchargés
            new NasSeriesData(
                isComplete: false,
                lastOnNas: 20,
                readUpTo: null,
                readComplete: false,
                title: 'One Piece',
            ),
            // Depuis BD/_lus/ : 15 tomes lus
            new NasSeriesData(
                isComplete: false,
                lastOnNas: 15,
                readUpTo: 15,
                readComplete: false,
                title: 'One Piece',
            ),
            // Depuis /lecture en cours/ : tomes 16-18, readUpTo = 15
            new NasSeriesData(
                isComplete: false,
                lastOnNas: 18,
                readUpTo: 15,
                readComplete: false,
                title: 'One Piece',
            ),
        ];

        $result = $this->parser->mergeDuplicateSeries($series);

        self::assertCount(1, $result);
        self::assertSame('One Piece', $result[0]->title);
        self::assertSame(20, $result[0]->lastOnNas);
        self::assertSame(15, $result[0]->readUpTo);
    }

    public function testMergeDuplicateSeriesNoDuplicates(): void
    {
        $series = [
            new NasSeriesData(
                isComplete: false,
                lastOnNas: 5,
                readUpTo: null,
                readComplete: false,
                title: 'Androides',
            ),
            new NasSeriesData(
                isComplete: true,
                lastOnNas: 3,
                readUpTo: null,
                readComplete: true,
                title: 'Blake et Mortimer',
            ),
        ];

        $result = $this->parser->mergeDuplicateSeries($series);

        self::assertCount(2, $result);
    }

    // --- normalizeTitle ---

    #[DataProvider('normalizeTitleProvider')]
    public function testNormalizeTitle(string $input, string $expected): void
    {
        self::assertSame($expected, $this->parser->normalizeTitle($input));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function normalizeTitleProvider(): iterable
    {
        yield 'lowercase + accents' => ['Étoile du Désert', 'etoile desert'];
        yield '& remplacé par et' => ['Blake & Mortimer', 'blake et mortimer'];
        yield 'articles supprimés' => ['Les 4 Princes De Ganahan', '4 princes ganahan'];
        yield 'ponctuation supprimée' => ['L\'Étoile (du) Nord!', 'etoile nord'];
        yield 'espaces multiples' => ['  One   Piece  ', 'one piece'];
        yield 'article le en début' => ['Le Château des étoiles', 'chateau etoiles'];
        yield 'article the' => ['The Walking Dead', 'walking dead'];
    }

    // --- fuzzy merge ---

    public function testMergeDuplicateSeriesFuzzyMatch(): void
    {
        $series = [
            new NasSeriesData(
                isComplete: false,
                lastOnNas: 15,
                readUpTo: null,
                readComplete: false,
                title: 'Blake et Morter',
            ),
            new NasSeriesData(
                isComplete: false,
                lastOnNas: 10,
                readUpTo: 10,
                readComplete: false,
                title: 'Blake & Mortimer',
            ),
        ];

        $result = $this->parser->mergeDuplicateSeries($series);

        self::assertCount(1, $result);
        self::assertSame(15, $result[0]->lastOnNas);
        self::assertSame(10, $result[0]->readUpTo);
    }

    public function testMergeDuplicateSeriesNoFalsePositive(): void
    {
        $series = [
            new NasSeriesData(
                isComplete: false,
                lastOnNas: 5,
                readUpTo: null,
                readComplete: false,
                title: 'Naruto',
            ),
            new NasSeriesData(
                isComplete: false,
                lastOnNas: 3,
                readUpTo: null,
                readComplete: false,
                title: 'Narutaru',
            ),
        ];

        $result = $this->parser->mergeDuplicateSeries($series);

        // Titres trop différents, pas de fusion
        self::assertCount(2, $result);
    }

    // --- publisher ---

    public function testParseUnreadSeriesWithPublisher(): void
    {
        $listing = ['Spider-Man'];
        $filesByDir = [
            'Spider-Man' => ['Spider-Man 01.cbr'],
        ];

        $result = $this->parser->parseUnreadSeries($listing, $filesByDir, 'Marvel');

        self::assertCount(1, $result);
        self::assertSame('Marvel', $result[0]->publisher);
    }

    public function testMergeDuplicateSeriesPreservesPublisher(): void
    {
        $series = [
            new NasSeriesData(
                isComplete: false,
                lastOnNas: 5,
                readUpTo: null,
                readComplete: false,
                title: 'Batman',
                publisher: 'DC Comics',
            ),
            new NasSeriesData(
                isComplete: false,
                lastOnNas: 3,
                readUpTo: 3,
                readComplete: false,
                title: 'Batman',
            ),
        ];

        $result = $this->parser->mergeDuplicateSeries($series);

        self::assertCount(1, $result);
        self::assertSame('DC Comics', $result[0]->publisher);
    }

    // --- nettoyage fichiers ---

    public function testParseUnreadSeriesIgnoresInfoFiles(): void
    {
        $listing = ['Batman'];
        $filesByDir = [
            'Batman' => [
                'Batman 01.cbr',
                'Batman 02.cbr',
                'GetComics.INFO',
                'cover.info',
                'metadata.INFO',
            ],
        ];

        $result = $this->parser->parseUnreadSeries($listing, $filesByDir);

        self::assertCount(1, $result);
        self::assertSame(2, $result[0]->lastOnNas);
    }

    public function testParseUnreadSeriesCleansGetComicsFromFilenames(): void
    {
        $listing = ['Batman'];
        $filesByDir = [
            'Batman' => [
                'Batman 01 (GetComics.INFO).cbr',
                'Batman 02 - Title (GetComics.INFO).cbz',
            ],
        ];

        $result = $this->parser->parseUnreadSeries($listing, $filesByDir);

        self::assertCount(1, $result);
        self::assertSame(2, $result[0]->lastOnNas);
    }

    // --- groupLooseFiles ---

    public function testGroupLooseFilesWithMatchingDir(): void
    {
        $listing = [
            'Aquablue',
            'Aquablue - T12 retour aux sources.cbr',
            'Aquablue - T13 Septentrion.cbz',
            'Aquablue - T14 standard island.cbr',
            'Batman',
        ];

        $result = $this->parser->groupLooseFiles($listing);

        // Les fichiers .cbr/.cbz doivent être rattachés au dossier "Aquablue"
        self::assertSame(['Aquablue', 'Batman'], $result['directories']);
        self::assertSame([
            'Aquablue - T12 retour aux sources.cbr',
            'Aquablue - T13 Septentrion.cbz',
            'Aquablue - T14 standard island.cbr',
        ], $result['looseFiles']['Aquablue']);
    }

    public function testGroupLooseFilesWithoutMatchingDir(): void
    {
        $listing = [
            'Aquablue - T12 retour aux sources.cbr',
            'Aquablue - T13 Septentrion.cbz',
            'Batman',
        ];

        $result = $this->parser->groupLooseFiles($listing);

        // Pas de dossier "Aquablue" → création synthétique
        self::assertContains('Aquablue', $result['directories']);
        self::assertContains('Batman', $result['directories']);
        self::assertArrayHasKey('Aquablue', $result['looseFiles']);
    }

    public function testGroupLooseFilesChaosTeamPattern(): void
    {
        $listing = [
            'Chaos team 1.2.cbr',
            'Chaos team T00 - La vengeance du Beret Vert.cbr',
            'Chaos team T01.cbr',
        ];

        $result = $this->parser->groupLooseFiles($listing);

        // Tous les fichiers doivent être regroupés sous "Chaos team"
        self::assertCount(1, $result['directories']);
        self::assertSame('Chaos team', $result['directories'][0]);
        self::assertCount(3, $result['looseFiles']['Chaos team']);
    }

    public function testGroupLooseFilesSpaceTomePattern(): void
    {
        $listing = [
            'Batman',
            'Batman 01 - Year One.cbr',
            'Batman 02 - The Dark Knight.cbr',
        ];

        $result = $this->parser->groupLooseFiles($listing);

        self::assertSame(['Batman'], $result['directories']);
        self::assertCount(2, $result['looseFiles']['Batman']);
    }

    public function testGroupLooseFilesIgnoresMetadata(): void
    {
        $listing = [
            '@eaDir',
            '_lus',
            'Aquablue',
            'GetComics.INFO',
        ];

        $result = $this->parser->groupLooseFiles($listing);

        self::assertSame(['Aquablue'], $result['directories']);
        self::assertEmpty($result['looseFiles']);
    }

    public function testParseRangeTomeFormat(): void
    {
        $listing = ['Artica (T01-06)'];
        $filesByDir = [];

        $result = $this->parser->parseUnreadSeries($listing, $filesByDir);

        self::assertCount(1, $result);
        self::assertSame('Artica', $result[0]->title);
        self::assertSame(6, $result[0]->lastOnNas);
    }

    public function testParseRangeTomeFormatWithA(): void
    {
        $listing = ['Anahire - Tome  1 à 4'];
        $filesByDir = [];

        $result = $this->parser->parseUnreadSeries($listing, $filesByDir);

        self::assertCount(1, $result);
        self::assertSame('Anahire', $result[0]->title);
        self::assertSame(4, $result[0]->lastOnNas);
    }

    // --- Fixes pour problèmes identifiés ---

    public function testIgnoredSeriesAreSkipped(): void
    {
        $listing = ['Star Wars', 'Star Wars (integrale)', 'Batman'];
        $filesByDir = [
            'Star Wars' => ['Episode 1.cbr'],
            'Star Wars (integrale)' => ['Vol 01.cbr'],
            'Batman' => ['Batman 01.cbr'],
        ];

        $result = $this->parser->parseUnreadSeries($listing, $filesByDir);

        self::assertCount(1, $result);
        self::assertSame('Batman', $result[0]->title);
    }

    public function testMaxReasonableTomesCapsPagesAsNull(): void
    {
        // 176 fichiers image = pages, pas tomes
        $listing = ['Aliens'];
        $files = [];
        for ($i = 1; $i <= 176; ++$i) {
            $files[] = \sprintf('page_%03d.jpg', $i);
        }
        $filesByDir = ['Aliens' => $files];

        $result = $this->parser->parseUnreadSeries($listing, $filesByDir);

        self::assertCount(1, $result);
        // Les images ne sont pas des fichiers BD, donc lastOnNas = null (pas de fallback sur le nom)
        self::assertNull($result[0]->lastOnNas);
    }

    public function testHighTomeNumberFromFilesIsCapped(): void
    {
        // Fichiers .cbr numérotés très haut (>300) = probablement faux
        $listing = ['Serie'];
        $filesByDir = [
            'Serie' => ['Serie 999.cbr'],
        ];

        $result = $this->parser->parseUnreadSeries($listing, $filesByDir);

        self::assertCount(1, $result);
        self::assertNull($result[0]->lastOnNas);
    }

    public function testContainerDirectoryIsDetected(): void
    {
        self::assertTrue($this->parser->isContainerDirectory('crossovers'));
        self::assertTrue($this->parser->isContainerDirectory('One Shots'));
        self::assertFalse($this->parser->isContainerDirectory('Batman'));
    }

    public function testParseSeriesDirectoryStripsTrailingTome(): void
    {
        $result = $this->parser->parseSeriesDirectory('La Légende de Drizzt Tome');
        self::assertSame('La Légende de Drizzt', $result['title']);
    }

    public function testParseSeriesDirectoryStripsTrailingDash(): void
    {
        $result = $this->parser->parseSeriesDirectory('Licorne -');
        self::assertSame('Licorne', $result['title']);
    }

    public function testCbrFilesAreTomesNotPages(): void
    {
        // 115 fichiers .cbr = 115 tomes (issues de comics)
        $listing = ['Aliens Labyrinth'];
        $files = [];
        for ($i = 1; $i <= 115; ++$i) {
            $files[] = \sprintf('Aliens Labyrinth %03d.cbr', $i);
        }
        $filesByDir = ['Aliens Labyrinth' => $files];

        $result = $this->parser->parseUnreadSeries($listing, $filesByDir);

        self::assertCount(1, $result);
        self::assertSame(115, $result[0]->lastOnNas);
    }

    public function testLegitHighTomeCountNotFlagged(): void
    {
        // 50 tomes avec des trous (pas consécutifs) = vrais tomes
        $listing = ['Serie'];
        $files = [];
        for ($i = 1; $i <= 50; $i += 2) {
            $files[] = \sprintf('Serie %02d.cbr', $i);
        }
        $filesByDir = ['Serie' => $files];

        $result = $this->parser->parseUnreadSeries($listing, $filesByDir);

        self::assertCount(1, $result);
        self::assertSame(49, $result[0]->lastOnNas);
    }

    public function testBlockNumberInTitleNotExtracted(): void
    {
        $listing = ['Block 109'];
        $filesByDir = [];

        $result = $this->parser->parseUnreadSeries($listing, $filesByDir);

        self::assertCount(1, $result);
        self::assertSame('Block 109', $result[0]->title);
        // 109 is part of the title, not a tome number
        self::assertNull($result[0]->lastOnNas);
    }

    public function testIgnoredSeriesInReadAndInProgress(): void
    {
        $listing = ['Star Wars', 'Batman'];
        $filesByDir = [
            'Star Wars' => ['Episode 1.cbr'],
            'Batman' => ['Batman 01.cbr'],
        ];

        $readResult = $this->parser->parseReadSeries($listing, $filesByDir);
        self::assertCount(1, $readResult);
        self::assertSame('Batman', $readResult[0]->title);

        $inProgressResult = $this->parser->parseInProgressSeries($listing, $filesByDir);
        self::assertCount(1, $inProgressResult);
        self::assertSame('Batman', $inProgressResult[0]->title);
    }
}

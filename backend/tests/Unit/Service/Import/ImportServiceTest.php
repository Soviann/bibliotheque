<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Import;

use App\Entity\ComicSeries;
use App\Entity\Tome;
use App\Repository\AuthorRepository;
use App\Repository\ComicSeriesRepository;
use App\Service\Import\ImportService;
use Doctrine\ORM\EntityManagerInterface;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PHPUnit\Framework\TestCase;

/**
 * Tests unitaires pour ImportService.
 */
final class ImportServiceTest extends TestCase
{
    /** @var list<ComicSeries> */
    private array $persistedSeries = [];
    private ImportService $service;
    /** @var list<string> */
    private array $tempFiles = [];

    protected function setUp(): void
    {
        $authorRepository = $this->createStub(AuthorRepository::class);
        $comicSeriesRepository = $this->createStub(ComicSeriesRepository::class);
        $entityManager = $this->createStub(EntityManagerInterface::class);

        $comicSeriesRepository->method('findOneByFuzzyTitle')->willReturn(null);

        $this->persistedSeries = [];
        $entityManager->method('persist')
            ->willReturnCallback(function (object $entity): void {
                if ($entity instanceof ComicSeries) {
                    $this->persistedSeries[] = $entity;
                }
            });

        $this->service = new ImportService(
            $authorRepository,
            $comicSeriesRepository,
            $entityManager,
        );
    }

    protected function tearDown(): void
    {
        foreach ($this->tempFiles as $file) {
            if (\file_exists($file)) {
                \unlink($file);
            }
        }
    }

    public function testImportCreatesTomesFromPublishedCountAlone(): void
    {
        $filePath = $this->createExcelFile([
            ['Type', 'Titre', 'Achète?', 'Dernier acheté', 'Lu', 'Parution', 'Dernier DL', 'Sur NAS?', 'Parution terminée'],
            ['BD', 'Série À Acheter', '', '', '', 5, '', '', ''],
        ]);

        $result = $this->service->import($filePath, dryRun: false);

        self::assertCount(1, $this->persistedSeries);
        $series = $this->persistedSeries[0];
        self::assertSame(5, $series->getLatestPublishedIssue());
        self::assertCount(5, $series->getTomes());
        self::assertSame(5, $result->totalTomes);

        $numbers = \array_map(static fn (Tome $t): int => $t->getNumber(), $series->getTomes()->toArray());
        \sort($numbers);
        self::assertSame([1, 2, 3, 4, 5], $numbers);

        foreach ($series->getTomes() as $tome) {
            self::assertFalse($tome->isBought());
            self::assertFalse($tome->isOnNas());
            self::assertFalse($tome->isHorsSerie());
        }
    }

    public function testImportCreatesAllTomesWhenLastBoughtIsLessThanPublishedCount(): void
    {
        $filePath = $this->createExcelFile([
            ['Type', 'Titre', 'Achète?', 'Dernier acheté', 'Lu', 'Parution', 'Dernier DL', 'Sur NAS?', 'Parution terminée'],
            ['Manga', 'Série Partiellement Achetée', '', 3, '', 10, '', '', ''],
        ]);

        $this->service->import($filePath, dryRun: false);

        self::assertCount(1, $this->persistedSeries);
        $series = $this->persistedSeries[0];
        self::assertSame(10, $series->getLatestPublishedIssue());
        self::assertCount(10, $series->getTomes());

        $bought = [];
        $notBought = [];
        foreach ($series->getTomes() as $tome) {
            if ($tome->isBought()) {
                $bought[] = $tome->getNumber();
            } else {
                $notBought[] = $tome->getNumber();
            }
        }
        \sort($bought);
        \sort($notBought);
        self::assertSame([1, 2, 3], $bought);
        self::assertSame([4, 5, 6, 7, 8, 9, 10], $notBought);
    }

    public function testImportCreatesTomesUpToMaxWhenSeriesIsCompleteAndLastBoughtExceedsPublishedCount(): void
    {
        $filePath = $this->createExcelFile([
            ['Type', 'Titre', 'Achète?', 'Dernier acheté', 'Lu', 'Parution', 'Dernier DL', 'Sur NAS?', 'Parution terminée'],
            ['BD', 'Série Terminée', '', 15, '', 10, '', '', 'oui'],
        ]);

        $this->service->import($filePath, dryRun: false);

        self::assertCount(1, $this->persistedSeries);
        $series = $this->persistedSeries[0];
        self::assertSame(10, $series->getLatestPublishedIssue());
        self::assertCount(15, $series->getTomes());

        $numbers = \array_map(static fn (Tome $t): int => $t->getNumber(), $series->getTomes()->toArray());
        \sort($numbers);
        self::assertSame([1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13, 14, 15], $numbers);

        foreach ($series->getTomes() as $tome) {
            self::assertTrue($tome->isBought());
        }
    }

    public function testImportAppliesReadStatusToTomes(): void
    {
        $filePath = $this->createExcelFile([
            ['Type', 'Titre', 'Achète?', 'Dernier acheté', 'Lu', 'Parution', 'Dernier DL', 'Sur NAS?', 'Parution terminée'],
            ['Manga', 'Série Lectorat', '', 5, 3, 5, '', '', ''],
        ]);

        $this->service->import($filePath, dryRun: false);

        self::assertCount(1, $this->persistedSeries);
        $series = $this->persistedSeries[0];
        self::assertCount(5, $series->getTomes());

        $readTomes = [];
        $unreadTomes = [];
        foreach ($series->getTomes() as $tome) {
            if ($tome->isRead()) {
                $readTomes[] = $tome->getNumber();
            } else {
                $unreadTomes[] = $tome->getNumber();
            }
        }
        \sort($readTomes);
        \sort($unreadTomes);

        self::assertSame([1, 2, 3], $readTomes);
        self::assertSame([4, 5], $unreadTomes);
    }

    public function testImportDecouplesNasPresencePerTome(): void
    {
        $filePath = $this->createExcelFile([
            ['Type', 'Titre', 'Achète?', 'Dernier acheté', 'Lu', 'Parution', 'Dernier DL', 'Sur NAS?', 'Parution terminée'],
            ['BD', 'Série NAS Partiel', '', 5, '', 5, 2, 'oui', ''],
        ]);

        $this->service->import($filePath, dryRun: false);

        self::assertCount(1, $this->persistedSeries);
        $series = $this->persistedSeries[0];
        self::assertCount(5, $series->getTomes());

        $onNasTomes = [];
        $notOnNasTomes = [];
        foreach ($series->getTomes() as $tome) {
            if ($tome->isOnNas()) {
                $onNasTomes[] = $tome->getNumber();
            } else {
                $notOnNasTomes[] = $tome->getNumber();
            }
        }
        \sort($onNasTomes);
        \sort($notOnNasTomes);

        self::assertSame([1, 2], $onNasTomes);
        self::assertSame([3, 4, 5], $notOnNasTomes);
    }

    public function testImportDeterminesCorrectComicStatuses(): void
    {
        $filePath = $this->createExcelFile([
            ['Type', 'Titre', 'Achète?', 'Dernier acheté', 'Lu', 'Parution', 'Dernier DL', 'Sur NAS?', 'Parution terminée'],
            ['BD', 'Série Wishlist', 'non', '', '', 5, '', 'non', ''],
            ['BD', 'Série Dl Only', 'non', '', '', 5, 3, 'oui', ''],
            ['BD', 'Série Stopped', '', '', '', 'stop 4', 2, 'oui', ''],
            ['BD', 'Série Fini', 'fini', 10, 'fini', 10, 10, 'oui', 'oui'],
        ]);

        $this->service->import($filePath, dryRun: false);

        self::assertCount(4, $this->persistedSeries);
        self::assertSame(\App\Enum\ComicStatus::WISHLIST, $this->persistedSeries[0]->getStatus());
        self::assertSame(\App\Enum\ComicStatus::DOWNLOADING, $this->persistedSeries[1]->getStatus());
        self::assertSame(\App\Enum\ComicStatus::STOPPED, $this->persistedSeries[2]->getStatus());
        self::assertSame(\App\Enum\ComicStatus::FINISHED, $this->persistedSeries[3]->getStatus());
    }

    public function testImportHandlesRangesAndComplexFormats(): void
    {
        $filePath = $this->createExcelFile([
            ['Type', 'Titre', 'Achète?', 'Dernier acheté', 'Lu', 'Parution', 'Dernier DL', 'Sur NAS?', 'Parution terminée'],
            ['Manga', 'Série Range', '', '01-02', '', 5, '1-3, 5', 'oui', ''],
        ]);

        $this->service->import($filePath, dryRun: false);

        self::assertCount(1, $this->persistedSeries);
        $series = $this->persistedSeries[0];
        self::assertCount(5, $series->getTomes());

        $bought = [];
        $onNas = [];
        foreach ($series->getTomes() as $tome) {
            if ($tome->isBought()) {
                $bought[] = $tome->getNumber();
            }
            if ($tome->isOnNas()) {
                $onNas[] = $tome->getNumber();
            }
        }
        \sort($bought);
        \sort($onNas);

        self::assertSame([1, 2], $bought);
        self::assertSame([1, 2, 3, 5], $onNas);
    }

    public function testImportPreventsEmptyShellSeries(): void
    {
        $filePath = $this->createExcelFile([
            ['Type', 'Titre', 'Achète?', 'Dernier acheté', 'Lu', 'Parution', 'Dernier DL', 'Sur NAS?', 'Parution terminée'],
            ['BD', 'Série OneShot Sans Chiffre', '', '', '', '', '', 'oui', ''],
        ]);

        $this->service->import($filePath, dryRun: false);

        self::assertCount(1, $this->persistedSeries);
        $series = $this->persistedSeries[0];
        $firstTome = $series->getTomes()->first();
        self::assertInstanceOf(Tome::class, $firstTome);
        self::assertSame(1, $firstTome->getNumber());
        self::assertTrue($firstTome->isOnNas());
    }

    public function testImportDoesNotMarkSeriesCompleteWhenOnlyReadingIsComplete(): void
    {
        $filePath = $this->createExcelFile([
            ['Type', 'Titre', 'Achète?', 'Dernier acheté', 'Lu', 'Parution', 'Dernier DL', 'Sur NAS?', 'Parution terminée'],
            ['Manga', 'Série En Cours Lecture À Jour', '', 10, 'fini', 10, '', '', ''],
        ]);

        $this->service->import($filePath, dryRun: false);

        self::assertCount(1, $this->persistedSeries);
        $series = $this->persistedSeries[0];
        self::assertFalse($series->isLatestPublishedIssueComplete());
    }

    public function testImportSanitizesMultiIsbnAndScientificNotation(): void
    {
        $filePath = $this->createExcelFile([
            ['Type', 'Titre', 'Achète?', 'Dernier acheté', 'Lu', 'Parution', 'Dernier DL', 'Sur NAS?', 'Parution terminée', 'ISBN'],
            ['BD', 'Série Scientific ISBN', '', 2, '', 2, '', '', '', '9.78201E+12,9782012345678'],
        ]);

        $this->service->import($filePath, dryRun: false);

        self::assertCount(1, $this->persistedSeries);
        $series = $this->persistedSeries[0];
        self::assertCount(2, $series->getTomes());

        $tomes = $series->getTomes()->toArray();
        \usort($tomes, static fn (Tome $a, Tome $b): int => $a->getNumber() <=> $b->getNumber());

        self::assertNotNull($tomes[0]->getIsbn());
        self::assertLessThanOrEqual(20, \strlen((string) $tomes[0]->getIsbn()));
        self::assertStringNotContainsString('E+', (string) $tomes[0]->getIsbn());
        self::assertStringNotContainsString(',', (string) $tomes[0]->getIsbn());
        self::assertSame('9782012345678', $tomes[1]->getIsbn());
    }

    public function testImportHandlesScientificIsbnWithFrenchDecimalComma(): void
    {
        $filePath = $this->createExcelFile([
            ['Type', 'Titre', 'Achète?', 'Dernier acheté', 'Lu', 'Parution', 'Dernier DL', 'Sur NAS?', 'Parution terminée', 'ISBN'],
            ['BD', 'Série French Comma ISBN', '', 1, '', 1, '', '', '', '9,78201E+12'],
        ]);

        $this->service->import($filePath, dryRun: false);

        self::assertCount(1, $this->persistedSeries);
        $firstTome = $this->persistedSeries[0]->getTomes()->first();
        self::assertInstanceOf(Tome::class, $firstTome);
        self::assertSame('9782010000000', $firstTome->getIsbn());
    }

    public function testImportAppliesReadStatusWithRangeAndSpecificValues(): void
    {
        $filePath = $this->createExcelFile([
            ['Type', 'Titre', 'Achète?', 'Dernier acheté', 'Lu', 'Parution', 'Dernier DL', 'Sur NAS?', 'Parution terminée'],
            ['Manga', 'Série Lecture Range', '', 6, '1-3, 5', 6, '', '', ''],
        ]);

        $this->service->import($filePath, dryRun: false);

        self::assertCount(1, $this->persistedSeries);
        $series = $this->persistedSeries[0];
        self::assertCount(6, $series->getTomes());

        $readTomes = [];
        $unreadTomes = [];
        foreach ($series->getTomes() as $tome) {
            if ($tome->isRead()) {
                $readTomes[] = $tome->getNumber();
            } else {
                $unreadTomes[] = $tome->getNumber();
            }
        }
        \sort($readTomes);
        \sort($unreadTomes);

        self::assertSame([1, 2, 3, 5], $readTomes);
        self::assertSame([4, 6], $unreadTomes);
    }

    public function testImportHandlesUnnumberedStopStatus(): void
    {
        $filePath = $this->createExcelFile([
            ['Type', 'Titre', 'Achète?', 'Dernier acheté', 'Lu', 'Parution', 'Dernier DL', 'Sur NAS?', 'Parution terminée'],
            ['BD', 'Série Unnumbered Stop', '', '', '', 'stop', 1, 'oui', ''],
        ]);

        $this->service->import($filePath, dryRun: false);

        self::assertCount(1, $this->persistedSeries);
        $series = $this->persistedSeries[0];
        self::assertSame(\App\Enum\ComicStatus::STOPPED, $series->getStatus());
        self::assertCount(1, $series->getTomes());
    }

    /**
     * @param list<list<mixed>> $rows
     */
    private function createExcelFile(array $rows): string
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->fromArray($rows, null, 'A1');

        $filePath = \tempnam(\sys_get_temp_dir(), 'import_test_').'.xlsx';
        $writer = IOFactory::createWriter($spreadsheet, 'Xlsx');
        $writer->save($filePath);
        $this->tempFiles[] = $filePath;

        return $filePath;
    }
}

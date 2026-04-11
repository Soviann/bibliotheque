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

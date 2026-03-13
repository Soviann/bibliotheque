<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Import;

use App\DTO\ImportExcelResult;
use App\Entity\ComicSeries;
use App\Repository\ComicSeriesRepository;
use App\Service\Import\ImportExcelService;
use Doctrine\ORM\EntityManagerInterface;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Tests unitaires pour ImportExcelService.
 */
final class ImportExcelServiceTest extends TestCase
{
    private ComicSeriesRepository&MockObject $comicSeriesRepository;
    private EntityManagerInterface&MockObject $entityManager;
    private ImportExcelService $service;

    protected function setUp(): void
    {
        $this->comicSeriesRepository = $this->createMock(ComicSeriesRepository::class);
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->service = new ImportExcelService($this->comicSeriesRepository, $this->entityManager);
    }

    public function testImportDryRunDoesNotPersist(): void
    {
        $filePath = $this->createExcelFile([
            'Mangas' => [
                ['Titre', 'Buy?', 'Last bought', 'Current', 'Parution', 'Last dled', 'On NAS?'],
                ['Naruto', 'oui', 10, 10, 72, 10, 'oui'],
            ],
        ]);

        $this->entityManager->expects(self::never())->method('persist');
        $this->entityManager->expects(self::never())->method('flush');

        $result = $this->service->import($filePath, true);

        self::assertSame(1, $result->totalCreated);
        self::assertSame(10, $result->totalTomes);
        self::assertArrayHasKey('Mangas', $result->sheetDetails);

        \unlink($filePath);
    }

    public function testImportPersistsWhenNotDryRun(): void
    {
        $filePath = $this->createExcelFile([
            'BD' => [
                ['Titre', 'Buy?', 'Last bought', 'Current', 'Parution', 'Last dled', 'On NAS?'],
                ['Asterix', 'oui', 5, 5, 40, null, null],
            ],
        ]);

        $this->entityManager->expects(self::once())->method('persist');
        $this->entityManager->expects(self::once())->method('flush');

        $result = $this->service->import($filePath, false);

        self::assertSame(1, $result->totalCreated);
        self::assertArrayHasKey('BD', $result->sheetDetails);
        self::assertSame(1, $result->sheetDetails['BD']['created']);

        \unlink($filePath);
    }

    public function testImportSkipsUnknownSheets(): void
    {
        $filePath = $this->createExcelFile([
            'Unknown' => [
                ['Titre'],
                ['Something'],
            ],
        ]);

        $result = $this->service->import($filePath, true);

        self::assertSame(0, $result->totalCreated);
        self::assertSame(0, $result->totalTomes);
        self::assertSame([], $result->sheetDetails);

        \unlink($filePath);
    }

    public function testImportSkipsEmptyTitles(): void
    {
        $filePath = $this->createExcelFile([
            'Mangas' => [
                ['Titre', 'Buy?'],
                ['', 'oui'],
                [null, 'oui'],
            ],
        ]);

        $result = $this->service->import($filePath, true);

        self::assertSame(0, $result->totalCreated);

        \unlink($filePath);
    }

    public function testImportMultipleSheets(): void
    {
        $filePath = $this->createExcelFile([
            'BD' => [
                ['Titre', 'Buy?', 'Last bought', 'Current', 'Parution', 'Last dled', 'On NAS?'],
                ['Asterix', 'oui', 3, 3, 40, null, null],
            ],
            'Mangas' => [
                ['Titre', 'Buy?', 'Last bought', 'Current', 'Parution', 'Last dled', 'On NAS?'],
                ['Naruto', 'fini', 'fini', 'fini', 'fini', null, null],
            ],
        ]);

        $result = $this->service->import($filePath, true);

        self::assertSame(2, $result->totalCreated);
        self::assertCount(2, $result->sheetDetails);

        \unlink($filePath);
    }

    public function testNormalizeTitleWithArticle(): void
    {
        self::assertSame("l'age d'ombre", ImportExcelService::normalizeTitle("age d'ombre (l')"));
        self::assertSame('le monde perdu', ImportExcelService::normalizeTitle('monde perdu (le)'));
        self::assertSame('la rose ecarlate', ImportExcelService::normalizeTitle('rose ecarlate (la)'));
        self::assertSame('les legendaires', ImportExcelService::normalizeTitle('legendaires (les)'));
    }

    public function testNormalizeTitleWithoutArticle(): void
    {
        self::assertSame('Naruto', ImportExcelService::normalizeTitle('Naruto'));
    }

    // ---------------------------------------------------------------
    // Dérivation des flags defaultTome* et latestPublishedIssueComplete
    // ---------------------------------------------------------------

    public function testImportBuyFiniSetsDefaultTomeBoughtAndPublicationComplete(): void
    {
        $persisted = $this->importAndCapture([
            'BD' => [
                ['Titre', 'Buy?', 'Last bought', 'Current', 'Parution', 'Last dled', 'On NAS?'],
                ['Asterix', 'fini', null, null, null, null, null],
            ],
        ]);

        self::assertCount(1, $persisted);
        self::assertTrue($persisted[0]->isLatestPublishedIssueComplete());
        self::assertTrue($persisted[0]->isDefaultTomeBought());
        self::assertFalse($persisted[0]->isDefaultTomeDownloaded());
        self::assertFalse($persisted[0]->isDefaultTomeRead());
    }

    public function testImportLastBoughtFiniSetsDefaultTomeBoughtAndPublicationComplete(): void
    {
        $persisted = $this->importAndCapture([
            'BD' => [
                ['Titre', 'Buy?', 'Last bought', 'Current', 'Parution', 'Last dled', 'On NAS?'],
                ['Asterix', 'oui', 'fini', null, null, null, null],
            ],
        ]);

        self::assertTrue($persisted[0]->isLatestPublishedIssueComplete());
        self::assertTrue($persisted[0]->isDefaultTomeBought());
    }

    public function testImportCurrentFiniSetsDefaultTomeReadAndPublicationComplete(): void
    {
        $persisted = $this->importAndCapture([
            'BD' => [
                ['Titre', 'Buy?', 'Last bought', 'Current', 'Parution', 'Last dled', 'On NAS?'],
                ['Asterix', 'oui', null, 'fini', null, null, null],
            ],
        ]);

        self::assertTrue($persisted[0]->isLatestPublishedIssueComplete());
        self::assertTrue($persisted[0]->isDefaultTomeRead());
        self::assertFalse($persisted[0]->isDefaultTomeBought());
    }

    public function testImportLastDledFiniSetsDefaultTomeDownloadedAndPublicationComplete(): void
    {
        $persisted = $this->importAndCapture([
            'BD' => [
                ['Titre', 'Buy?', 'Last bought', 'Current', 'Parution', 'Last dled', 'On NAS?'],
                ['Asterix', 'oui', null, null, null, 'fini', null],
            ],
        ]);

        self::assertTrue($persisted[0]->isLatestPublishedIssueComplete());
        self::assertTrue($persisted[0]->isDefaultTomeDownloaded());
    }

    public function testImportOnNasFiniSetsDefaultTomeDownloadedAndPublicationComplete(): void
    {
        $persisted = $this->importAndCapture([
            'BD' => [
                ['Titre', 'Buy?', 'Last bought', 'Current', 'Parution', 'Last dled', 'On NAS?'],
                ['Asterix', 'oui', null, null, null, null, 'fini'],
            ],
        ]);

        self::assertTrue($persisted[0]->isLatestPublishedIssueComplete());
        self::assertTrue($persisted[0]->isDefaultTomeDownloaded());
    }

    public function testImportMultipleFiniColumnsCombineFlags(): void
    {
        $persisted = $this->importAndCapture([
            'Mangas' => [
                ['Titre', 'Buy?', 'Last bought', 'Current', 'Parution', 'Last dled', 'On NAS?'],
                ['Naruto', 'fini', 'fini', 'fini', 'fini', 'fini', 'fini'],
            ],
        ]);

        self::assertTrue($persisted[0]->isLatestPublishedIssueComplete());
        self::assertTrue($persisted[0]->isDefaultTomeBought());
        self::assertTrue($persisted[0]->isDefaultTomeDownloaded());
        self::assertTrue($persisted[0]->isDefaultTomeRead());
    }

    public function testImportNoFiniKeepsDefaultsFalse(): void
    {
        $persisted = $this->importAndCapture([
            'BD' => [
                ['Titre', 'Buy?', 'Last bought', 'Current', 'Parution', 'Last dled', 'On NAS?'],
                ['Asterix', 'oui', 5, 5, 40, 5, 'oui'],
            ],
        ]);

        self::assertFalse($persisted[0]->isLatestPublishedIssueComplete());
        self::assertFalse($persisted[0]->isDefaultTomeBought());
        self::assertFalse($persisted[0]->isDefaultTomeDownloaded());
        self::assertFalse($persisted[0]->isDefaultTomeRead());
    }

    // ---------------------------------------------------------------

    public function testImportExcelResultIsJsonSerializable(): void
    {
        $result = new ImportExcelResult(
            sheetDetails: ['BD' => ['created' => 5, 'tomes' => 20, 'updated' => 2]],
            totalCreated: 5,
            totalTomes: 20,
            totalUpdated: 2,
        );

        $json = \json_encode($result);
        self::assertNotFalse($json);

        $data = \json_decode($json, true);
        self::assertSame(5, $data['totalCreated']);
        self::assertSame(20, $data['totalTomes']);
        self::assertSame(2, $data['totalUpdated']);
        self::assertSame(['created' => 5, 'tomes' => 20, 'updated' => 2], $data['sheetDetails']['BD']);
    }

    /**
     * Importe un fichier Excel et capture les entités persistées.
     *
     * @param array<string, array<int, array<int, mixed>>> $sheets
     *
     * @return list<ComicSeries>
     */
    private function importAndCapture(array $sheets): array
    {
        $filePath = $this->createExcelFile($sheets);

        /** @var list<ComicSeries> $persisted */
        $persisted = [];
        $this->entityManager->expects(self::atLeastOnce())->method('persist')
            ->willReturnCallback(static function (ComicSeries $entity) use (&$persisted): void {
                $persisted[] = $entity;
            });

        $this->service->import($filePath, false);

        \unlink($filePath);

        return $persisted;
    }

    /**
     * Crée un fichier Excel temporaire avec les données fournies.
     *
     * @param array<string, array<int, array<int, mixed>>> $sheets
     */
    private function createExcelFile(array $sheets): string
    {
        $spreadsheet = new Spreadsheet();
        $spreadsheet->removeSheetByIndex(0);

        foreach ($sheets as $sheetName => $rows) {
            $sheet = $spreadsheet->createSheet();
            $sheet->setTitle($sheetName);

            foreach ($rows as $rowIndex => $row) {
                foreach ($row as $colIndex => $value) {
                    $sheet->setCellValue([$colIndex + 1, $rowIndex + 1], $value);
                }
            }
        }

        $filePath = \sys_get_temp_dir().'/'.\uniqid('test_excel_', true).'.xlsx';
        $writer = new Xlsx($spreadsheet);
        $writer->save($filePath);

        return $filePath;
    }
}

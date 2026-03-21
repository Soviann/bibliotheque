<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Import;

use App\DTO\ImportBooksResult;
use App\Entity\ComicSeries;
use App\Entity\Tome;
use App\Enum\ComicType;
use App\Repository\AuthorRepository;
use App\Repository\ComicSeriesRepository;
use App\Service\Import\ImportBooksService;
use Doctrine\ORM\EntityManagerInterface;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Tests unitaires pour ImportBooksService.
 */
final class ImportBooksServiceTest extends TestCase
{
    private AuthorRepository&MockObject $authorRepository;
    private ComicSeriesRepository&MockObject $comicSeriesRepository;
    private EntityManagerInterface&MockObject $entityManager;
    private ImportBooksService $service;

    protected function setUp(): void
    {
        $this->authorRepository = $this->createMock(AuthorRepository::class);
        $this->comicSeriesRepository = $this->createMock(ComicSeriesRepository::class);
        $this->entityManager = $this->createMock(EntityManagerInterface::class);

        $this->service = new ImportBooksService(
            $this->authorRepository,
            $this->comicSeriesRepository,
            $this->entityManager,
        );
    }

    public function testImportDryRunDoesNotPersist(): void
    {
        $filePath = $this->createBooksFile([
            ['9781234567890', 'Mon Livre', 'Auteur Test', 'Editeur', '', 'BD', 'Description'],
        ]);

        $this->comicSeriesRepository->method('findOneByFuzzyTitleAnyType')->willReturn(null);
        $this->entityManager->expects(self::never())->method('persist');
        $this->entityManager->expects(self::never())->method('flush');

        $result = $this->service->import($filePath, true);

        self::assertSame(1, $result->created);
        self::assertSame(0, $result->enriched);
        self::assertSame(1, $result->groupCount);

        \unlink($filePath);
    }

    public function testImportCreatesNewSeries(): void
    {
        $filePath = $this->createBooksFile([
            ['9781234567890', 'Mon Livre', 'Auteur', 'Editeur', '', 'Manga', 'Desc'],
        ]);

        $this->comicSeriesRepository->method('findOneByFuzzyTitleAnyType')->willReturn(null);
        $this->authorRepository->method('findOrCreateMultiple')->willReturn([]);
        $this->entityManager->expects(self::once())->method('persist');
        $this->entityManager->expects(self::once())->method('flush');

        $result = $this->service->import($filePath, false);

        self::assertSame(1, $result->created);
        self::assertSame(0, $result->enriched);

        \unlink($filePath);
    }

    public function testImportEnrichesExistingSeries(): void
    {
        $filePath = $this->createBooksFile([
            ['9781234567890', 'Existing Series', 'Auteur', 'Editeur', '', 'BD', 'Desc'],
        ]);

        $existing = new ComicSeries();
        $existing->setTitle('Existing Series');
        $existing->setType(ComicType::BD);

        $this->comicSeriesRepository->method('findOneByFuzzyTitleAnyType')
            ->with('Existing Series')
            ->willReturn($existing);

        $this->entityManager->expects(self::never())->method('persist');
        $this->entityManager->expects(self::once())->method('flush');

        $result = $this->service->import($filePath, false);

        self::assertSame(0, $result->created);
        self::assertSame(1, $result->enriched);

        \unlink($filePath);
    }

    public function testImportGroupsTomesOfSameSeries(): void
    {
        $filePath = $this->createBooksFile([
            ['111', 'Naruto - Tome 1', 'Kishimoto', 'Kana', '', 'Manga', ''],
            ['222', 'Naruto - Tome 2', 'Kishimoto', 'Kana', '', 'Manga', ''],
            ['333', 'Naruto - Tome 3', 'Kishimoto', 'Kana', '', 'Manga', ''],
        ]);

        $this->comicSeriesRepository->method('findOneByFuzzyTitleAnyType')->willReturn(null);
        $this->authorRepository->method('findOrCreateMultiple')->willReturn([]);

        $result = $this->service->import($filePath, true);

        self::assertSame(1, $result->groupCount);
        self::assertSame(1, $result->created);

        \unlink($filePath);
    }

    public function testImportSkipsEmptyTitles(): void
    {
        $filePath = $this->createBooksFile([
            ['111', '', 'Auteur', 'Editeur', '', '', ''],
            ['222', null, 'Auteur', 'Editeur', '', '', ''],
        ]);

        $result = $this->service->import($filePath, true);

        self::assertSame(0, $result->groupCount);
        self::assertSame(0, $result->created);

        \unlink($filePath);
    }

    public function testEnrichExistingMarksTomesAsBought(): void
    {
        $filePath = $this->createBooksFile([
            ['9781234567890', 'Naruto - Tome 1', 'Kishimoto', 'Kana', '', 'Manga', ''],
            ['9781234567891', 'Naruto - Tome 2', 'Kishimoto', 'Kana', '', 'Manga', ''],
        ]);

        $existing = new ComicSeries();
        $existing->setTitle('Naruto');
        $existing->setType(ComicType::MANGA);

        $tome1 = new Tome();
        $tome1->setNumber(1);
        $tome1->setBought(false);
        $existing->addTome($tome1);

        $tome2 = new Tome();
        $tome2->setNumber(2);
        $tome2->setBought(false);
        $existing->addTome($tome2);

        $this->comicSeriesRepository->method('findOneByFuzzyTitleAnyType')
            ->with('Naruto')
            ->willReturn($existing);

        $this->service->import($filePath, false);

        self::assertTrue($tome1->isBought(), 'Le tome 1 devrait être marqué comme acheté');
        self::assertTrue($tome2->isBought(), 'Le tome 2 devrait être marqué comme acheté');

        \unlink($filePath);
    }

    public function testEnrichExistingCreatesMissingTomes(): void
    {
        $filePath = $this->createBooksFile([
            ['111', 'Naruto - Tome 1', 'Kishimoto', 'Kana', '', 'Manga', ''],
            ['222', 'Naruto - Tome 2', 'Kishimoto', 'Kana', '', 'Manga', ''],
            ['333', 'Naruto - Tome 3', 'Kishimoto', 'Kana', '', 'Manga', ''],
        ]);

        $existing = new ComicSeries();
        $existing->setTitle('Naruto');
        $existing->setType(ComicType::MANGA);

        // Seul le tome 1 existe déjà
        $tome1 = new Tome();
        $tome1->setNumber(1);
        $tome1->setBought(false);
        $existing->addTome($tome1);

        $this->comicSeriesRepository->method('findOneByFuzzyTitleAnyType')
            ->with('Naruto')
            ->willReturn($existing);

        $this->service->import($filePath, false);

        $tomes = $existing->getTomes();
        self::assertCount(3, $tomes, 'La série devrait avoir 3 tomes');

        $tomeNumbers = [];
        foreach ($tomes as $tome) {
            $tomeNumbers[] = $tome->getNumber();
            self::assertTrue($tome->isBought(), \sprintf('Le tome %d devrait être marqué comme acheté', $tome->getNumber()));
        }

        self::assertContains(2, $tomeNumbers, 'Le tome 2 devrait avoir été créé');
        self::assertContains(3, $tomeNumbers, 'Le tome 3 devrait avoir été créé');

        \unlink($filePath);
    }

    public function testImportBooksResultIsJsonSerializable(): void
    {
        $result = new ImportBooksResult(
            created: 5,
            enriched: 3,
            groupCount: 8,
        );

        $json = \json_encode($result);
        self::assertNotFalse($json);

        $data = \json_decode($json, true);
        self::assertSame(5, $data['created']);
        self::assertSame(3, $data['enriched']);
        self::assertSame(8, $data['groupCount']);
    }

    /**
     * Crée un fichier Excel temporaire au format Livres.xlsx.
     *
     * @param array<int, array<int, mixed>> $rows
     */
    private function createBooksFile(array $rows): string
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        // En-tête
        $headers = ['Code-barres', 'Titre', 'Auteur', 'Éditeur', 'Couverture', 'Catégories', 'Description'];
        foreach ($headers as $colIndex => $header) {
            $sheet->setCellValue([$colIndex + 1, 1], $header);
        }

        foreach ($rows as $rowIndex => $row) {
            foreach ($row as $colIndex => $value) {
                $sheet->setCellValue([$colIndex + 1, $rowIndex + 2], $value);
            }
        }

        $filePath = \sys_get_temp_dir().'/'.\uniqid('test_books_', true).'.xlsx';
        $writer = new Xlsx($spreadsheet);
        $writer->save($filePath);

        return $filePath;
    }
}

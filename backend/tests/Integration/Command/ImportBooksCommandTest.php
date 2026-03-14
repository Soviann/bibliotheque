<?php

declare(strict_types=1);

namespace App\Tests\Integration\Command;

use App\Entity\ComicSeries;
use App\Enum\ComicStatus;
use App\Enum\ComicType;
use Doctrine\ORM\EntityManagerInterface;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * Tests d'intégration pour la commande app:import-books.
 */
final class ImportBooksCommandTest extends KernelTestCase
{
    private CommandTester $commandTester;
    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        self::bootKernel();

        $this->em = static::getContainer()->get(EntityManagerInterface::class);

        /** @var \Symfony\Component\HttpKernel\KernelInterface $kernel */
        $kernel = self::$kernel;
        $application = new Application($kernel);
        $command = $application->find('app:import-books');
        $this->commandTester = new CommandTester($command);
    }

    public function testFileNotFoundReturnsFailure(): void
    {
        $this->commandTester->execute([
            'file' => '/tmp/inexistant_file_12345.xlsx',
        ]);

        self::assertSame(Command::FAILURE, $this->commandTester->getStatusCode());
        self::assertStringContainsString('n\'existe pas', $this->commandTester->getDisplay());
    }

    public function testSuccessfulImportCreatesSeriesAndTomes(): void
    {
        $tmpFile = $this->createBooksExcel([
            ['9782723489003', 'One Piece - Tome 1', 'Eiichiro Oda', 'Glenat', '', 'Manga', ''],
            ['9782723489010', 'One Piece - Tome 2', 'Eiichiro Oda', 'Glenat', '', 'Manga', ''],
        ]);

        try {
            $this->commandTester->execute(['file' => $tmpFile]);

            self::assertSame(Command::SUCCESS, $this->commandTester->getStatusCode());
            self::assertStringContainsString('1 créés', $this->commandTester->getDisplay());

            $series = $this->em->getRepository(ComicSeries::class)->findOneBy(['title' => 'One Piece']);

            self::assertNotNull($series);
            self::assertSame(ComicType::MANGA, $series->getType());
            self::assertSame(ComicStatus::FINISHED, $series->getStatus());
            self::assertCount(2, $series->getTomes());
        } finally {
            @\unlink($tmpFile);
        }
    }

    public function testDryRunDoesNotPersist(): void
    {
        $tmpFile = $this->createBooksExcel([
            ['', 'Asterix', '', '', '', 'BD', ''],
        ]);

        try {
            $this->commandTester->execute([
                'file' => $tmpFile,
                '--dry-run' => true,
            ]);

            self::assertSame(Command::SUCCESS, $this->commandTester->getStatusCode());
            self::assertStringContainsString('dry-run', \strtolower($this->commandTester->getDisplay()));

            $series = $this->em->getRepository(ComicSeries::class)->findAll();
            self::assertCount(0, $series);
        } finally {
            @\unlink($tmpFile);
        }
    }

    public function testInvalidExcelFileReturnsFailure(): void
    {
        $tmpFile = \tempnam(\sys_get_temp_dir(), 'test_import_').'.xlsx';
        \file_put_contents($tmpFile, "PK\x03\x04corrupt");

        try {
            $this->commandTester->execute(['file' => $tmpFile]);

            self::assertSame(Command::FAILURE, $this->commandTester->getStatusCode());
            self::assertStringContainsString('Impossible de lire le fichier Excel', $this->commandTester->getDisplay());
        } finally {
            @\unlink($tmpFile);
        }
    }

    public function testOneShotBookCreatesOneTome(): void
    {
        $tmpFile = $this->createBooksExcel([
            ['9782723489003', 'Le Petit Prince', 'Saint-Exupéry', 'Gallimard', '', 'BD', 'Un classique'],
        ]);

        try {
            $this->commandTester->execute(['file' => $tmpFile]);

            self::assertSame(Command::SUCCESS, $this->commandTester->getStatusCode());

            $series = $this->em->getRepository(ComicSeries::class)->findOneBy(['title' => 'Le Petit Prince']);

            self::assertNotNull($series);
            self::assertTrue($series->isOneShot());
            self::assertCount(1, $series->getTomes());
            $firstTome = $series->getTomes()->first();
            self::assertNotFalse($firstTome);
            self::assertSame('9782723489003', $firstTome->getIsbn());
        } finally {
            @\unlink($tmpFile);
        }
    }

    public function testEmptyRowsAreSkipped(): void
    {
        $tmpFile = $this->createBooksExcel([
            ['', '', '', '', '', '', ''],
            ['', 'Valid Book', '', '', '', '', ''],
            ['', '   ', '', '', '', '', ''],
        ]);

        try {
            $this->commandTester->execute(['file' => $tmpFile]);

            self::assertSame(Command::SUCCESS, $this->commandTester->getStatusCode());

            $series = $this->em->getRepository(ComicSeries::class)->findAll();
            self::assertCount(1, $series);
            self::assertSame('Valid Book', $series[0]->getTitle());
        } finally {
            @\unlink($tmpFile);
        }
    }

    /**
     * Crée un fichier Excel au format Livres.xlsx (ISBN, Titre, Auteur, Editeur, Cover, Catégories, Description).
     *
     * @param list<list<string>> $rows
     */
    private function createBooksExcel(array $rows): string
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Livres');

        // En-tête
        $headers = ['ISBN', 'Titre', 'Auteur', 'Editeur', 'Cover', 'Catégories', 'Description'];
        foreach ($headers as $col => $header) {
            $sheet->setCellValue([$col + 1, 1], $header);
        }

        // Données
        foreach ($rows as $rowIndex => $rowData) {
            foreach ($rowData as $col => $value) {
                $sheet->setCellValue([$col + 1, $rowIndex + 2], $value);
            }
        }

        $tmpFile = \tempnam(\sys_get_temp_dir(), 'test_import_books_').'.xlsx';
        $writer = new Xlsx($spreadsheet);
        $writer->save($tmpFile);

        return $tmpFile;
    }
}

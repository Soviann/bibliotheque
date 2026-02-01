<?php

declare(strict_types=1);

namespace App\Tests\Command;

use App\Entity\ComicSeries;
use App\Enum\ComicStatus;
use App\Enum\ComicType;
use Doctrine\ORM\EntityManagerInterface;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * Tests d'intégration pour ImportExcelCommand.
 */
class ImportExcelCommandTest extends KernelTestCase
{
    private EntityManagerInterface $em;
    private string $testFilePath;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->em = static::getContainer()->get(EntityManagerInterface::class);
        $this->testFilePath = \sys_get_temp_dir().'/test_import_'.\uniqid().'.xlsx';
    }

    protected function tearDown(): void
    {
        if (\file_exists($this->testFilePath)) {
            \unlink($this->testFilePath);
        }
        parent::tearDown();
    }

    /**
     * Teste l'import avec un fichier inexistant.
     */
    public function testExecuteWithNonExistentFile(): void
    {
        $application = new Application(self::$kernel);
        $command = $application->find('app:import-excel');
        $commandTester = new CommandTester($command);

        $exitCode = $commandTester->execute([
            'file' => '/nonexistent/file.xlsx',
        ]);

        self::assertSame(1, $exitCode);
        self::assertStringContainsString("n'existe pas", $commandTester->getDisplay());
    }

    /**
     * Teste l'import d'une série BD.
     */
    public function testImportBdSeries(): void
    {
        $title = 'Test BD Import '.\uniqid();
        $this->createTestExcel([
            'BD' => [
                [$title, 'oui', '3', '5', '10', '2', 'oui'],
            ],
        ]);

        $application = new Application(self::$kernel);
        $command = $application->find('app:import-excel');
        $commandTester = new CommandTester($command);

        $commandTester->execute(['file' => $this->testFilePath]);
        $commandTester->assertCommandIsSuccessful();

        $series = $this->em->getRepository(ComicSeries::class)->findOneBy(['title' => $title]);
        self::assertNotNull($series);
        self::assertSame(ComicType::BD, $series->getType());
        self::assertSame(ComicStatus::BUYING, $series->getStatus());
        self::assertSame(10, $series->getLatestPublishedIssue());
        self::assertCount(5, $series->getTomes());

        // Nettoyer
        $this->em->remove($series);
        $this->em->flush();
    }

    /**
     * Teste l'import d'une série Manga.
     */
    public function testImportMangaSeries(): void
    {
        $title = 'Test Manga Import '.\uniqid();
        $this->createTestExcel([
            'Mangas' => [
                [$title, 'oui', '5', '5', '8', '5', 'non'],
            ],
        ]);

        $application = new Application(self::$kernel);
        $command = $application->find('app:import-excel');
        $commandTester = new CommandTester($command);

        $commandTester->execute(['file' => $this->testFilePath]);
        $commandTester->assertCommandIsSuccessful();

        $series = $this->em->getRepository(ComicSeries::class)->findOneBy(['title' => $title]);
        self::assertNotNull($series);
        self::assertSame(ComicType::MANGA, $series->getType());

        // Nettoyer
        $this->em->remove($series);
        $this->em->flush();
    }

    /**
     * Teste l'import d'une série Comics.
     */
    public function testImportComicsSeries(): void
    {
        $title = 'Test Comics Import '.\uniqid();
        $this->createTestExcel([
            'Comics' => [
                [$title, 'oui', '2', '2', '5', '', ''],
            ],
        ]);

        $application = new Application(self::$kernel);
        $command = $application->find('app:import-excel');
        $commandTester = new CommandTester($command);

        $commandTester->execute(['file' => $this->testFilePath]);
        $commandTester->assertCommandIsSuccessful();

        $series = $this->em->getRepository(ComicSeries::class)->findOneBy(['title' => $title]);
        self::assertNotNull($series);
        self::assertSame(ComicType::COMICS, $series->getType());

        // Nettoyer
        $this->em->remove($series);
        $this->em->flush();
    }

    /**
     * Teste l'import d'une série Livre.
     */
    public function testImportLivreSeries(): void
    {
        $title = 'Test Livre Import '.\uniqid();
        $this->createTestExcel([
            'Livre' => [
                [$title, 'fini', 'fini', 'fini', 'fini', '', ''],
            ],
        ]);

        $application = new Application(self::$kernel);
        $command = $application->find('app:import-excel');
        $commandTester = new CommandTester($command);

        $commandTester->execute(['file' => $this->testFilePath]);
        $commandTester->assertCommandIsSuccessful();

        $series = $this->em->getRepository(ComicSeries::class)->findOneBy(['title' => $title]);
        self::assertNotNull($series);
        self::assertSame(ComicType::LIVRE, $series->getType());
        self::assertSame(ComicStatus::FINISHED, $series->getStatus());

        // Nettoyer
        $this->em->remove($series);
        $this->em->flush();
    }

    /**
     * Teste le statut "non" => STOPPED.
     */
    public function testStatusNonIsStopped(): void
    {
        $title = 'Test Stopped Import '.\uniqid();
        $this->createTestExcel([
            'BD' => [
                [$title, 'non', '3', '3', '10', '', ''],
            ],
        ]);

        $application = new Application(self::$kernel);
        $command = $application->find('app:import-excel');
        $commandTester = new CommandTester($command);

        $commandTester->execute(['file' => $this->testFilePath]);

        $series = $this->em->getRepository(ComicSeries::class)->findOneBy(['title' => $title]);
        self::assertNotNull($series);
        self::assertSame(ComicStatus::STOPPED, $series->getStatus());

        // Nettoyer
        $this->em->remove($series);
        $this->em->flush();
    }

    /**
     * Teste le dry-run ne persiste pas.
     */
    public function testDryRunDoesNotPersist(): void
    {
        $title = 'Test DryRun Import '.\uniqid();
        $this->createTestExcel([
            'BD' => [
                [$title, 'oui', '3', '3', '10', '', ''],
            ],
        ]);

        $application = new Application(self::$kernel);
        $command = $application->find('app:import-excel');
        $commandTester = new CommandTester($command);

        $commandTester->execute([
            'file' => $this->testFilePath,
            '--dry-run' => true,
        ]);

        $commandTester->assertCommandIsSuccessful();
        self::assertStringContainsString('simulation', $commandTester->getDisplay());

        // Vérifier que rien n'a été persisté
        $series = $this->em->getRepository(ComicSeries::class)->findOneBy(['title' => $title]);
        self::assertNull($series);
    }

    /**
     * Teste les tomes achetés sont marqués correctement.
     */
    public function testTomesBoughtFlag(): void
    {
        $title = 'Test Bought Flag '.\uniqid();
        $this->createTestExcel([
            'BD' => [
                [$title, 'oui', '3', '5', '5', '', ''],
            ],
        ]);

        $application = new Application(self::$kernel);
        $command = $application->find('app:import-excel');
        $commandTester = new CommandTester($command);

        $commandTester->execute(['file' => $this->testFilePath]);

        $series = $this->em->getRepository(ComicSeries::class)->findOneBy(['title' => $title]);
        self::assertNotNull($series);

        // Tomes 1-3 devraient être achetés, 4-5 non
        foreach ($series->getTomes() as $tome) {
            if ($tome->getNumber() <= 3) {
                self::assertTrue($tome->isBought(), "Tome {$tome->getNumber()} should be bought");
            } else {
                self::assertFalse($tome->isBought(), "Tome {$tome->getNumber()} should not be bought");
            }
        }

        // Nettoyer
        $this->em->remove($series);
        $this->em->flush();
    }

    /**
     * Teste les tomes téléchargés sont marqués correctement.
     */
    public function testTomesDownloadedFlag(): void
    {
        $title = 'Test Downloaded Flag '.\uniqid();
        $this->createTestExcel([
            'BD' => [
                [$title, 'oui', '5', '5', '5', '2', ''],
            ],
        ]);

        $application = new Application(self::$kernel);
        $command = $application->find('app:import-excel');
        $commandTester = new CommandTester($command);

        $commandTester->execute(['file' => $this->testFilePath]);

        $series = $this->em->getRepository(ComicSeries::class)->findOneBy(['title' => $title]);
        self::assertNotNull($series);

        // Tomes 1-2 devraient être téléchargés
        foreach ($series->getTomes() as $tome) {
            if ($tome->getNumber() <= 2) {
                self::assertTrue($tome->isDownloaded(), "Tome {$tome->getNumber()} should be downloaded");
            } else {
                self::assertFalse($tome->isDownloaded(), "Tome {$tome->getNumber()} should not be downloaded");
            }
        }

        // Nettoyer
        $this->em->remove($series);
        $this->em->flush();
    }

    /**
     * Teste le flag onNas sur les tomes.
     */
    public function testTomesOnNasFlag(): void
    {
        $title = 'Test OnNas Flag '.\uniqid();
        $this->createTestExcel([
            'BD' => [
                [$title, 'oui', '3', '3', '3', '', 'oui'],
            ],
        ]);

        $application = new Application(self::$kernel);
        $command = $application->find('app:import-excel');
        $commandTester = new CommandTester($command);

        $commandTester->execute(['file' => $this->testFilePath]);

        $series = $this->em->getRepository(ComicSeries::class)->findOneBy(['title' => $title]);
        self::assertNotNull($series);

        // Tous les tomes devraient être sur NAS
        foreach ($series->getTomes() as $tome) {
            self::assertTrue($tome->isOnNas(), "Tome {$tome->getNumber()} should be on NAS");
        }

        // Nettoyer
        $this->em->remove($series);
        $this->em->flush();
    }

    /**
     * Teste le parsing des valeurs séparées par virgule.
     */
    public function testCommaSeparatedValues(): void
    {
        $title = 'Test Comma Values '.\uniqid();
        $this->createTestExcel([
            'BD' => [
                [$title, 'oui', '3, 5', '5', '5', '', ''],
            ],
        ]);

        $application = new Application(self::$kernel);
        $command = $application->find('app:import-excel');
        $commandTester = new CommandTester($command);

        $commandTester->execute(['file' => $this->testFilePath]);

        $series = $this->em->getRepository(ComicSeries::class)->findOneBy(['title' => $title]);
        self::assertNotNull($series);

        // Le max (5) devrait être utilisé pour lastBought
        self::assertSame(5, $series->getLastBought());

        // Nettoyer
        $this->em->remove($series);
        $this->em->flush();
    }

    /**
     * Teste l'onglet manquant est ignoré.
     */
    public function testMissingSheetIsIgnored(): void
    {
        $title = 'Test Only BD '.\uniqid();
        $this->createTestExcel([
            'BD' => [
                [$title, 'oui', '1', '1', '1', '', ''],
            ],
        ]);

        $application = new Application(self::$kernel);
        $command = $application->find('app:import-excel');
        $commandTester = new CommandTester($command);

        $commandTester->execute(['file' => $this->testFilePath]);

        $commandTester->assertCommandIsSuccessful();
        $output = $commandTester->getDisplay();
        self::assertStringContainsString('non trouvé', $output);

        // Nettoyer
        $series = $this->em->getRepository(ComicSeries::class)->findOneBy(['title' => $title]);
        if ($series instanceof ComicSeries) {
            $this->em->remove($series);
            $this->em->flush();
        }
    }

    /**
     * Teste les lignes vides sont ignorées.
     */
    public function testEmptyRowsAreIgnored(): void
    {
        $title = 'Test Non Empty '.\uniqid();
        $this->createTestExcel([
            'BD' => [
                ['', '', '', '', '', '', ''],
                [$title, 'oui', '1', '1', '1', '', ''],
                ['   ', '', '', '', '', '', ''],
            ],
        ]);

        $application = new Application(self::$kernel);
        $command = $application->find('app:import-excel');
        $commandTester = new CommandTester($command);

        $commandTester->execute(['file' => $this->testFilePath]);

        // Seule la série valide doit être importée
        $series = $this->em->getRepository(ComicSeries::class)->findOneBy(['title' => $title]);
        self::assertNotNull($series);

        // Nettoyer
        $this->em->remove($series);
        $this->em->flush();
    }

    /**
     * Teste qu'un fichier corrompu affiche un message d'erreur clair.
     */
    public function testCorruptedFileShowsErrorMessage(): void
    {
        // Créer un fichier avec un header ZIP invalide pour simuler un XLSX corrompu
        $corruptedFilePath = \sys_get_temp_dir().'/corrupted_'.\uniqid().'.xlsx';
        \file_put_contents($corruptedFilePath, "PK\x03\x04".\str_repeat("\x00", 100));

        try {
            $application = new Application(self::$kernel);
            $command = $application->find('app:import-excel');
            $commandTester = new CommandTester($command);

            $exitCode = $commandTester->execute(['file' => $corruptedFilePath]);

            // La commande doit échouer avec un message d'erreur clair
            self::assertSame(1, $exitCode);
            $output = $commandTester->getDisplay();
            self::assertStringContainsString('impossible', \mb_strtolower($output));
        } finally {
            if (\file_exists($corruptedFilePath)) {
                \unlink($corruptedFilePath);
            }
        }
    }

    /**
     * Crée un fichier Excel de test.
     *
     * @param array<string, array<int, array<int, mixed>>> $sheets
     */
    private function createTestExcel(array $sheets): void
    {
        $spreadsheet = new Spreadsheet();
        $spreadsheet->removeSheetByIndex(0);

        foreach ($sheets as $sheetName => $data) {
            $sheet = $spreadsheet->createSheet();
            $sheet->setTitle($sheetName);

            // En-têtes
            $sheet->setCellValue('A1', 'Titre');
            $sheet->setCellValue('B1', 'Buy?');
            $sheet->setCellValue('C1', 'Last bought');
            $sheet->setCellValue('D1', 'Current');
            $sheet->setCellValue('E1', 'Parution');
            $sheet->setCellValue('F1', 'Last dled');
            $sheet->setCellValue('G1', 'On NAS?');

            // Données
            $row = 2;
            foreach ($data as $rowData) {
                $col = 'A';
                foreach ($rowData as $value) {
                    $sheet->setCellValue($col.$row, $value);
                    ++$col;
                }
                ++$row;
            }
        }

        $writer = new Xlsx($spreadsheet);
        $writer->save($this->testFilePath);
    }
}

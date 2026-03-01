<?php

declare(strict_types=1);

namespace App\Tests\Integration\Command;

use App\Command\ImportExcelCommand;
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
 * Tests d'integration pour la commande app:import-excel.
 */
final class ImportExcelCommandTest extends KernelTestCase
{
    private CommandTester $commandTester;
    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        self::bootKernel();

        $this->em = static::getContainer()->get(EntityManagerInterface::class);

        $application = new Application(self::$kernel);
        $command = $application->find('app:import-excel');
        $this->commandTester = new CommandTester($command);
    }

    // ---------------------------------------------------------------
    // normalizeTitle (methode statique, testable directement)
    // ---------------------------------------------------------------

    public function testNormalizeTitleWithArticleElided(): void
    {
        self::assertSame("l'age d'ombre", ImportExcelCommand::normalizeTitle("age d'ombre (l')"));
    }

    public function testNormalizeTitleWithArticleLe(): void
    {
        self::assertSame('le monde perdu', ImportExcelCommand::normalizeTitle('monde perdu (le)'));
    }

    public function testNormalizeTitleWithArticleLa(): void
    {
        self::assertSame('la rose ecarlate', ImportExcelCommand::normalizeTitle('rose ecarlate (la)'));
    }

    public function testNormalizeTitleWithArticleLes(): void
    {
        self::assertSame('les vieux fourneaux', ImportExcelCommand::normalizeTitle('vieux fourneaux (les)'));
    }

    public function testNormalizeTitleWithoutArticleReturnsUnchanged(): void
    {
        self::assertSame('Asterix', ImportExcelCommand::normalizeTitle('Asterix'));
    }

    public function testNormalizeTitleCaseInsensitive(): void
    {
        self::assertSame("L'age d'ombre", ImportExcelCommand::normalizeTitle("age d'ombre (L')"));
    }

    // ---------------------------------------------------------------
    // Gestion d'erreurs de la commande
    // ---------------------------------------------------------------

    public function testFileNotFoundReturnsFailure(): void
    {
        $this->commandTester->execute([
            'file' => '/tmp/inexistant_file_12345.xlsx',
        ]);

        self::assertSame(Command::FAILURE, $this->commandTester->getStatusCode());
        self::assertStringContainsString('n\'existe pas', $this->commandTester->getDisplay());
    }

    /**
     * Teste un import reel qui persiste les series et tomes en base.
     */
    public function testSuccessfulImportPersistsSeriesWithTomes(): void
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Mangas');
        $sheet->setCellValue('A1', 'Titre');
        $sheet->setCellValue('B1', 'Buy?');
        $sheet->setCellValue('C1', 'Last bought');
        $sheet->setCellValue('D1', 'Current');
        $sheet->setCellValue('E1', 'Parution');
        $sheet->setCellValue('F1', 'Last dled');
        $sheet->setCellValue('G1', 'On NAS?');
        $sheet->setCellValue('A2', 'Naruto');
        $sheet->setCellValue('B2', 'oui');
        $sheet->setCellValue('C2', '5');
        $sheet->setCellValue('D2', '5');
        $sheet->setCellValue('E2', '72');
        $sheet->setCellValue('F2', '3');
        $sheet->setCellValue('G2', 'oui');

        $tmpFile = \tempnam(\sys_get_temp_dir(), 'test_import_').'.xlsx';
        $writer = new Xlsx($spreadsheet);
        $writer->save($tmpFile);

        try {
            $this->commandTester->execute(['file' => $tmpFile]);

            self::assertSame(Command::SUCCESS, $this->commandTester->getStatusCode());

            $series = $this->em->getRepository(ComicSeries::class)->findOneBy(['title' => 'Naruto']);

            self::assertNotNull($series);
            self::assertSame(ComicType::MANGA, $series->getType());
            self::assertSame(ComicStatus::BUYING, $series->getStatus());
            self::assertSame(72, $series->getLatestPublishedIssue());
            self::assertCount(5, $series->getTomes());
        } finally {
            @\unlink($tmpFile);
        }
    }

    /**
     * Teste le dry-run : affiche les compteurs sans persister.
     */
    public function testDryRunOutputsCorrectCounts(): void
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('BD');
        $sheet->setCellValue('A1', 'Titre');
        $sheet->setCellValue('B1', 'Buy?');
        $sheet->setCellValue('C1', 'Last bought');
        $sheet->setCellValue('D1', 'Current');
        $sheet->setCellValue('E1', 'Parution');
        $sheet->setCellValue('F1', 'Last dled');
        $sheet->setCellValue('G1', 'On NAS?');
        $sheet->setCellValue('A2', 'Asterix');
        $sheet->setCellValue('B2', 'oui');
        $sheet->setCellValue('C2', '3');
        $sheet->setCellValue('D2', '3');
        $sheet->setCellValue('E2', '5');
        $sheet->setCellValue('A3', 'Tintin');
        $sheet->setCellValue('B3', 'fini');
        $sheet->setCellValue('C3', 'fini');
        $sheet->setCellValue('D3', 'fini');
        $sheet->setCellValue('E3', 'fini');

        $tmpFile = \tempnam(\sys_get_temp_dir(), 'test_import_').'.xlsx';
        $writer = new Xlsx($spreadsheet);
        $writer->save($tmpFile);

        try {
            $this->commandTester->execute([
                'file' => $tmpFile,
                '--dry-run' => true,
            ]);

            $display = $this->commandTester->getDisplay();

            self::assertSame(Command::SUCCESS, $this->commandTester->getStatusCode());
            self::assertStringContainsString('2 séries importées', $display);
            self::assertStringContainsString('dry-run', $display);

            // Rien en base
            $series = $this->em->getRepository(ComicSeries::class)->findAll();
            self::assertCount(0, $series);
        } finally {
            @\unlink($tmpFile);
        }
    }

    /**
     * Teste determineStatus avec differentes valeurs.
     */
    public function testDetermineStatusViaImport(): void
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('BD');
        $sheet->setCellValue('A1', 'Titre');
        $sheet->setCellValue('B1', 'Buy?');
        $sheet->setCellValue('C1', 'Last bought');
        $sheet->setCellValue('D1', 'Current');
        $sheet->setCellValue('E1', 'Parution');
        // oui → BUYING
        $sheet->setCellValue('A2', 'Buying Series');
        $sheet->setCellValue('B2', 'oui');
        $sheet->setCellValue('D2', '1');
        // non → STOPPED
        $sheet->setCellValue('A3', 'Stopped Series');
        $sheet->setCellValue('B3', 'non');
        $sheet->setCellValue('D3', '1');
        // fini → FINISHED
        $sheet->setCellValue('A4', 'Finished Series');
        $sheet->setCellValue('B4', 'fini');
        $sheet->setCellValue('D4', '1');
        // null → BUYING (default)
        $sheet->setCellValue('A5', 'Default Series');
        $sheet->setCellValue('D5', '1');

        $tmpFile = \tempnam(\sys_get_temp_dir(), 'test_import_').'.xlsx';
        $writer = new Xlsx($spreadsheet);
        $writer->save($tmpFile);

        try {
            $this->commandTester->execute(['file' => $tmpFile]);

            self::assertSame(Command::SUCCESS, $this->commandTester->getStatusCode());

            $buying = $this->em->getRepository(ComicSeries::class)->findOneBy(['title' => 'Buying Series']);
            $stopped = $this->em->getRepository(ComicSeries::class)->findOneBy(['title' => 'Stopped Series']);
            $finished = $this->em->getRepository(ComicSeries::class)->findOneBy(['title' => 'Finished Series']);
            $default = $this->em->getRepository(ComicSeries::class)->findOneBy(['title' => 'Default Series']);

            self::assertSame(ComicStatus::BUYING, $buying->getStatus());
            self::assertSame(ComicStatus::STOPPED, $stopped->getStatus());
            self::assertSame(ComicStatus::FINISHED, $finished->getStatus());
            self::assertSame(ComicStatus::BUYING, $default->getStatus());
        } finally {
            @\unlink($tmpFile);
        }
    }

    /**
     * Teste parseIntegerValue : "fini" retourne [null, true], "3, 4" retourne [4, false].
     */
    public function testParseIntegerValueEdgeCasesViaImport(): void
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('BD');
        $sheet->setCellValue('A1', 'Titre');
        $sheet->setCellValue('B1', 'Buy?');
        $sheet->setCellValue('C1', 'Last bought');
        $sheet->setCellValue('D1', 'Current');
        $sheet->setCellValue('E1', 'Parution');
        // "fini" pour last bought → null value, true complete
        $sheet->setCellValue('A2', 'Fini Series');
        $sheet->setCellValue('B2', 'fini');
        $sheet->setCellValue('C2', 'fini');
        $sheet->setCellValue('D2', '5');
        $sheet->setCellValue('E2', '5');
        // "3, 4" for current → max is 4
        $sheet->setCellValue('A3', 'Comma Series');
        $sheet->setCellValue('B3', 'oui');
        $sheet->setCellValue('D3', '3, 4');
        $sheet->setCellValue('E3', '10');

        $tmpFile = \tempnam(\sys_get_temp_dir(), 'test_import_').'.xlsx';
        $writer = new Xlsx($spreadsheet);
        $writer->save($tmpFile);

        try {
            $this->commandTester->execute(['file' => $tmpFile]);

            self::assertSame(Command::SUCCESS, $this->commandTester->getStatusCode());

            // "Fini Series" : last bought is "fini" → all tomes should be marked as bought
            $finiSeries = $this->em->getRepository(ComicSeries::class)->findOneBy(['title' => 'Fini Series']);
            self::assertNotNull($finiSeries);
            self::assertCount(5, $finiSeries->getTomes());
            foreach ($finiSeries->getTomes() as $tome) {
                self::assertTrue($tome->isBought(), 'Tome should be bought when "fini"');
            }

            // "Comma Series" : current is "3, 4" → parsed as max=4, so 4 tomes created
            $commaSeries = $this->em->getRepository(ComicSeries::class)->findOneBy(['title' => 'Comma Series']);
            self::assertNotNull($commaSeries);
            self::assertCount(4, $commaSeries->getTomes());
        } finally {
            @\unlink($tmpFile);
        }
    }

    /**
     * Teste le warning quand un onglet attendu n'est pas trouve.
     */
    public function testSheetNotFoundWarning(): void
    {
        $spreadsheet = new Spreadsheet();
        // Creer un onglet avec un nom different de ceux attendus
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Inconnu');
        $sheet->setCellValue('A1', 'Titre');

        $tmpFile = \tempnam(\sys_get_temp_dir(), 'test_import_').'.xlsx';
        $writer = new Xlsx($spreadsheet);
        $writer->save($tmpFile);

        try {
            $this->commandTester->execute(['file' => $tmpFile]);

            $display = $this->commandTester->getDisplay();

            self::assertSame(Command::SUCCESS, $this->commandTester->getStatusCode());
            // Tous les onglets attendus (BD, Comics, Livre, Mangas) sont absents
            self::assertStringContainsString('non trouvé', $display);
        } finally {
            @\unlink($tmpFile);
        }
    }

    public function testDryRunDoesNotPersistData(): void
    {
        // Creer un fichier Excel minimal avec PhpSpreadsheet
        $spreadsheet = new Spreadsheet();

        // Onglet BD avec une ligne d'en-tete + une serie
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('BD');
        $sheet->setCellValue('A1', 'Titre');
        $sheet->setCellValue('B1', 'Buy?');
        $sheet->setCellValue('C1', 'Last bought');
        $sheet->setCellValue('D1', 'Current');
        $sheet->setCellValue('E1', 'Parution');
        $sheet->setCellValue('F1', 'Last dled');
        $sheet->setCellValue('G1', 'On NAS?');
        $sheet->setCellValue('A2', 'Asterix');
        $sheet->setCellValue('B2', 'oui');
        $sheet->setCellValue('C2', '3');
        $sheet->setCellValue('D2', '3');
        $sheet->setCellValue('E2', '5');
        $sheet->setCellValue('F2', '');
        $sheet->setCellValue('G2', '');

        $tmpFile = \tempnam(\sys_get_temp_dir(), 'test_import_') . '.xlsx';
        $writer = new Xlsx($spreadsheet);
        $writer->save($tmpFile);

        try {
            $this->commandTester->execute([
                'file' => $tmpFile,
                '--dry-run' => true,
            ]);

            self::assertSame(Command::SUCCESS, $this->commandTester->getStatusCode());
            self::assertStringContainsString('dry-run', $this->commandTester->getDisplay());

            // Verifier qu'aucune serie n'a ete persistee
            $series = $this->em->getRepository(ComicSeries::class)->findAll();
            self::assertCount(0, $series);
        } finally {
            @\unlink($tmpFile);
        }
    }
}

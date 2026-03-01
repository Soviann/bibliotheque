<?php

declare(strict_types=1);

namespace App\Tests\Integration\Command;

use App\Command\ImportExcelCommand;
use App\Entity\ComicSeries;
use Doctrine\ORM\EntityManagerInterface;
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

    public function testDryRunDoesNotPersistData(): void
    {
        // Creer un fichier Excel minimal avec PhpSpreadsheet
        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();

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
        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
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

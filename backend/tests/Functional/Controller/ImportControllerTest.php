<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller;

use ApiPlatform\Symfony\Bundle\Test\ApiTestCase;
use App\Repository\UserRepository;
use App\Tests\Factory\EntityFactory;
use App\Tests\Trait\AuthenticatedTestTrait;
use Doctrine\ORM\EntityManagerInterface;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * Tests fonctionnels pour ImportController.
 */
final class ImportControllerTest extends ApiTestCase
{
    use AuthenticatedTestTrait;

    protected static ?bool $alwaysBootKernel = true;

    protected function setUp(): void
    {
        $container = static::getContainer();
        $em = $container->get(EntityManagerInterface::class);

        /** @var UserRepository $userRepo */
        $userRepo = $container->get(UserRepository::class);

        if (null === $userRepo->findOneBy(['email' => 'test@example.com'])) {
            $user = EntityFactory::createUser();
            $em->persist($user);
            $em->flush();
        }
    }

    // ---------------------------------------------------------------
    // Authentification
    // ---------------------------------------------------------------

    public function testExcelRequiresAuthentication(): void
    {
        $client = $this->createUnauthenticatedClient();

        $client->request('POST', '/api/tools/import/excel');

        self::assertResponseStatusCodeSame(401);
    }

    public function testBooksRequiresAuthentication(): void
    {
        $client = $this->createUnauthenticatedClient();

        $client->request('POST', '/api/tools/import/books');

        self::assertResponseStatusCodeSame(401);
    }

    // ---------------------------------------------------------------
    // POST /api/tools/import/excel
    // ---------------------------------------------------------------

    public function testExcelReturns400WithoutFile(): void
    {
        $client = $this->createAuthenticatedClient();

        $client->request('POST', '/api/tools/import/excel');

        self::assertResponseStatusCodeSame(400);
        $data = $client->getResponse()->toArray(false);
        self::assertSame('Le fichier est requis.', $data['error']);
    }

    public function testExcelImportDryRun(): void
    {
        $client = $this->createAuthenticatedClient();
        $filePath = $this->createExcelFile();

        $client->request('POST', '/api/tools/import/excel', [
            'extra' => [
                'files' => [
                    'file' => new UploadedFile($filePath, 'test.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true),
                ],
                'parameters' => [
                    'dryRun' => 'true',
                ],
            ],
        ]);

        self::assertResponseIsSuccessful();
        $data = $client->getResponse()->toArray();
        self::assertArrayHasKey('totalSeries', $data);
        self::assertArrayHasKey('totalTomes', $data);
        self::assertArrayHasKey('sheetDetails', $data);
        self::assertSame(1, $data['totalSeries']);

        \unlink($filePath);
    }

    // ---------------------------------------------------------------
    // POST /api/tools/import/books
    // ---------------------------------------------------------------

    public function testBooksReturns400WithoutFile(): void
    {
        $client = $this->createAuthenticatedClient();

        $client->request('POST', '/api/tools/import/books');

        self::assertResponseStatusCodeSame(400);
        $data = $client->getResponse()->toArray(false);
        self::assertSame('Le fichier est requis.', $data['error']);
    }

    public function testBooksImportDryRun(): void
    {
        $client = $this->createAuthenticatedClient();
        $filePath = $this->createBooksFile();

        $client->request('POST', '/api/tools/import/books', [
            'extra' => [
                'files' => [
                    'file' => new UploadedFile($filePath, 'livres.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true),
                ],
                'parameters' => [
                    'dryRun' => 'true',
                ],
            ],
        ]);

        self::assertResponseIsSuccessful();
        $data = $client->getResponse()->toArray();
        self::assertArrayHasKey('created', $data);
        self::assertArrayHasKey('enriched', $data);
        self::assertArrayHasKey('groupCount', $data);
        self::assertSame(1, $data['created']);

        \unlink($filePath);
    }

    private function createExcelFile(): string
    {
        $spreadsheet = new Spreadsheet();
        $spreadsheet->removeSheetByIndex(0);

        $sheet = $spreadsheet->createSheet();
        $sheet->setTitle('Mangas');
        $sheet->setCellValue('A1', 'Titre');
        $sheet->setCellValue('B1', 'Buy?');
        $sheet->setCellValue('C1', 'Last bought');
        $sheet->setCellValue('D1', 'Current');
        $sheet->setCellValue('E1', 'Parution');
        $sheet->setCellValue('A2', 'Naruto');
        $sheet->setCellValue('B2', 'oui');
        $sheet->setCellValue('C2', 5);
        $sheet->setCellValue('D2', 5);
        $sheet->setCellValue('E2', 72);

        $filePath = \sys_get_temp_dir().'/'.\uniqid('func_excel_', true).'.xlsx';
        $writer = new Xlsx($spreadsheet);
        $writer->save($filePath);

        return $filePath;
    }

    private function createBooksFile(): string
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        $headers = ['Code-barres', 'Titre', 'Auteur', 'Éditeur', 'Couverture', 'Catégories', 'Description'];
        foreach ($headers as $i => $h) {
            $sheet->setCellValue([$i + 1, 1], $h);
        }

        $row = ['9781234567890', 'Mon Livre Test', 'Auteur Test', 'Editeur', '', 'BD', 'Une description'];
        foreach ($row as $i => $v) {
            $sheet->setCellValue([$i + 1, 2], $v);
        }

        $filePath = \sys_get_temp_dir().'/'.\uniqid('func_books_', true).'.xlsx';
        $writer = new Xlsx($spreadsheet);
        $writer->save($filePath);

        return $filePath;
    }
}

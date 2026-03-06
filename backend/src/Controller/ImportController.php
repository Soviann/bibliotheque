<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\Import\ImportBooksService;
use App\Service\Import\ImportExcelService;
use PhpOffice\PhpSpreadsheet\Reader\Exception as ReaderException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Endpoints pour l'import de données depuis des fichiers Excel.
 */
#[IsGranted('ROLE_USER')]
#[Route('/api/tools/import')]
class ImportController
{
    public function __construct(
        private readonly ImportBooksService $importBooksService,
        private readonly ImportExcelService $importExcelService,
    ) {
    }

    /**
     * Importe des livres depuis un fichier Excel (format Livres.xlsx).
     */
    #[Route('/books', name: 'api_tools_import_books', methods: ['POST'])]
    public function books(Request $request): JsonResponse
    {
        $file = $request->files->get('file');

        if (!$file instanceof UploadedFile) {
            return new JsonResponse(
                ['error' => 'Le fichier est requis.'],
                Response::HTTP_BAD_REQUEST,
            );
        }

        $dryRun = 'true' === $request->request->get('dryRun', 'false');

        $tmpPath = $file->getPathname();

        try {
            $result = $this->importBooksService->import($tmpPath, $dryRun);
        } catch (ReaderException $e) {
            return new JsonResponse(
                ['error' => \sprintf('Impossible de lire le fichier Excel : %s', $e->getMessage())],
                Response::HTTP_BAD_REQUEST,
            );
        }

        return new JsonResponse($result);
    }

    /**
     * Importe des données depuis un fichier Excel de suivi.
     */
    #[Route('/excel', name: 'api_tools_import_excel', methods: ['POST'])]
    public function excel(Request $request): JsonResponse
    {
        $file = $request->files->get('file');

        if (!$file instanceof UploadedFile) {
            return new JsonResponse(
                ['error' => 'Le fichier est requis.'],
                Response::HTTP_BAD_REQUEST,
            );
        }

        $dryRun = 'true' === $request->request->get('dryRun', 'false');

        $tmpPath = $file->getPathname();

        try {
            $result = $this->importExcelService->import($tmpPath, $dryRun);
        } catch (ReaderException $e) {
            return new JsonResponse(
                ['error' => \sprintf('Impossible de lire le fichier Excel : %s', $e->getMessage())],
                Response::HTTP_BAD_REQUEST,
            );
        }

        return new JsonResponse($result);
    }
}

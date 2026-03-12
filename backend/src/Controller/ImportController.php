<?php

declare(strict_types=1);

namespace App\Controller;

use App\Controller\Trait\RateLimitTrait;
use App\Service\Import\ImportBooksService;
use App\Service\Import\ImportExcelService;
use PhpOffice\PhpSpreadsheet\Reader\Exception as ReaderException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Endpoints pour l'import de données depuis des fichiers Excel.
 */
#[IsGranted('ROLE_USER')]
#[Route('/api/tools/import')]
class ImportController
{
    use RateLimitTrait;

    private const array ALLOWED_MIME_TYPES = [
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    ];

    private const int MAX_FILE_SIZE = 10 * 1024 * 1024; // 10 Mo

    public function __construct(
        private readonly ImportBooksService $importBooksService,
        private readonly ImportExcelService $importExcelService,
        private readonly RateLimiterFactory $importLimiter,
    ) {
    }

    /**
     * Importe des livres depuis un fichier Excel (format Livres.xlsx).
     */
    #[Route('/books', name: 'api_tools_import_books', methods: ['POST'])]
    public function books(Request $request): JsonResponse
    {
        $rateLimitResponse = $this->checkRateLimit($request, $this->importLimiter);
        if ($rateLimitResponse instanceof JsonResponse) {
            return $rateLimitResponse;
        }

        $file = $request->files->get('file');

        if (!$file instanceof UploadedFile) {
            return new JsonResponse(
                ['error' => 'Le fichier est requis.'],
                Response::HTTP_BAD_REQUEST,
            );
        }

        $validationResponse = $this->validateFile($file);
        if ($validationResponse instanceof JsonResponse) {
            return $validationResponse;
        }

        $dryRun = 'true' === $request->request->get('dryRun', 'false');

        try {
            $result = $this->importBooksService->import($file->getPathname(), $dryRun);
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
        $rateLimitResponse = $this->checkRateLimit($request, $this->importLimiter);
        if ($rateLimitResponse instanceof JsonResponse) {
            return $rateLimitResponse;
        }

        $file = $request->files->get('file');

        if (!$file instanceof UploadedFile) {
            return new JsonResponse(
                ['error' => 'Le fichier est requis.'],
                Response::HTTP_BAD_REQUEST,
            );
        }

        $validationResponse = $this->validateFile($file);
        if ($validationResponse instanceof JsonResponse) {
            return $validationResponse;
        }

        $dryRun = 'true' === $request->request->get('dryRun', 'false');

        try {
            $result = $this->importExcelService->import($file->getPathname(), $dryRun);
        } catch (ReaderException $e) {
            return new JsonResponse(
                ['error' => \sprintf('Impossible de lire le fichier Excel : %s', $e->getMessage())],
                Response::HTTP_BAD_REQUEST,
            );
        }

        return new JsonResponse($result);
    }

    /**
     * Valide le type MIME et la taille du fichier uploadé.
     */
    private function validateFile(UploadedFile $file): ?JsonResponse
    {
        $mimeType = $file->getMimeType();

        if (null === $mimeType || !\in_array($mimeType, self::ALLOWED_MIME_TYPES, true)) {
            return new JsonResponse(
                ['error' => 'Le fichier doit être au format Excel (.xlsx).'],
                Response::HTTP_UNPROCESSABLE_ENTITY,
            );
        }

        if ($file->getSize() > self::MAX_FILE_SIZE) {
            return new JsonResponse(
                ['error' => \sprintf('Le fichier ne doit pas dépasser %d Mo.', self::MAX_FILE_SIZE / 1024 / 1024)],
                Response::HTTP_UNPROCESSABLE_ENTITY,
            );
        }

        return null;
    }
}

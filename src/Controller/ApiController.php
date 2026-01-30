<?php

namespace App\Controller;

use App\Repository\ComicSeriesRepository;
use App\Service\IsbnLookupService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api')]
class ApiController extends AbstractController
{
    #[Route('/comics', name: 'api_comics', methods: ['GET'])]
    public function comics(ComicSeriesRepository $comicSeriesRepository): JsonResponse
    {
        $comics = $comicSeriesRepository->findAllForApi();

        return $this->json($comics);
    }

    /**
     * Recherche les informations d'un livre par ISBN.
     */
    #[Route('/isbn-lookup', name: 'api_isbn_lookup', methods: ['GET'])]
    public function isbnLookup(Request $request, IsbnLookupService $isbnLookupService): JsonResponse
    {
        $isbn = $request->query->get('isbn', '');

        if (empty($isbn)) {
            return $this->json(['error' => 'ISBN requis'], 400);
        }

        $result = $isbnLookupService->lookup($isbn);

        if ($result === null) {
            return $this->json(['error' => 'Aucun résultat trouvé'], 404);
        }

        return $this->json($result);
    }
}

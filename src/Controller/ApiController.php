<?php

declare(strict_types=1);

namespace App\Controller;

use App\Enum\ComicType;
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
        $typeString = $request->query->get('type');
        $type = \is_string($typeString) ? ComicType::tryFrom($typeString) : null;

        if (empty($isbn)) {
            return $this->json(['error' => 'ISBN requis'], 400);
        }

        $result = $isbnLookupService->lookup($isbn, $type);
        $apiMessages = $isbnLookupService->getLastApiMessages();

        if (null === $result) {
            return $this->json(['apiMessages' => $apiMessages, 'error' => 'Aucun résultat trouvé'], 404);
        }

        $result['apiMessages'] = $apiMessages;

        return $this->json($result);
    }

    /**
     * Recherche les informations par titre.
     */
    #[Route('/title-lookup', name: 'api_title_lookup', methods: ['GET'])]
    public function titleLookup(Request $request, IsbnLookupService $isbnLookupService): JsonResponse
    {
        $title = $request->query->get('title', '');
        $typeString = $request->query->get('type');
        $type = \is_string($typeString) ? ComicType::tryFrom($typeString) : null;

        if (empty($title)) {
            return $this->json(['error' => 'Titre requis'], 400);
        }

        $result = $isbnLookupService->lookupByTitle($title, $type);
        $apiMessages = $isbnLookupService->getLastApiMessages();

        if (null === $result) {
            return $this->json(['apiMessages' => $apiMessages, 'error' => 'Aucun résultat trouvé'], 404);
        }

        $result['apiMessages'] = $apiMessages;

        return $this->json($result);
    }
}

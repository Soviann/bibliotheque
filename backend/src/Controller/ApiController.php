<?php

declare(strict_types=1);

namespace App\Controller;

use App\Enum\ComicType;
use App\Service\Lookup\LookupOrchestrator;
use App\Service\Lookup\LookupResult;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
#[Route('/api/lookup')]
class ApiController extends AbstractController
{
    /**
     * Recherche les informations d'un livre par ISBN.
     */
    #[Route('/isbn', name: 'api_lookup_isbn', methods: ['GET'])]
    public function isbnLookup(Request $request, LookupOrchestrator $lookupOrchestrator): JsonResponse
    {
        $isbn = $request->query->get('isbn', '');
        $type = $this->resolveComicType($request);

        if (empty($isbn)) {
            return $this->json(['error' => 'ISBN requis'], 400);
        }

        $result = $lookupOrchestrator->lookup($isbn, $type);

        return $this->buildLookupResponse($lookupOrchestrator, $result, 'Aucun résultat trouvé');
    }

    /**
     * Recherche les informations par titre.
     */
    #[Route('/title', name: 'api_lookup_title', methods: ['GET'])]
    public function titleLookup(Request $request, LookupOrchestrator $lookupOrchestrator): JsonResponse
    {
        $title = $request->query->get('title', '');
        $type = $this->resolveComicType($request);

        if (empty($title)) {
            return $this->json(['error' => 'Titre requis'], 400);
        }

        $result = $lookupOrchestrator->lookupByTitle($title, $type);

        return $this->buildLookupResponse($lookupOrchestrator, $result, 'Aucun résultat trouvé');
    }

    /**
     * Construit la réponse JSON pour un résultat de lookup.
     */
    private function buildLookupResponse(LookupOrchestrator $lookupOrchestrator, ?LookupResult $result, string $errorMessage): JsonResponse
    {
        $apiMessages = $lookupOrchestrator->getLastApiMessages();

        if (null === $result) {
            return $this->json(['apiMessages' => $apiMessages, 'error' => $errorMessage], 404);
        }

        return $this->json([
            ...$result->jsonSerialize(),
            'apiMessages' => $apiMessages,
            'sources' => $lookupOrchestrator->getLastSources(),
        ]);
    }

    /**
     * Résout le type de comic depuis la requête.
     */
    private function resolveComicType(Request $request): ?ComicType
    {
        $typeString = $request->query->get('type');

        return \is_string($typeString) ? ComicType::tryFrom($typeString) : null;
    }
}

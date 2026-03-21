<?php

declare(strict_types=1);

namespace App\Controller;

use App\Enum\ComicType;
use App\Service\Cover\CoverSearchService;
use App\Service\Lookup\Contract\LookupResult;
use App\Service\Lookup\LookupOrchestrator;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
#[Route('/api/lookup')]
final class ApiController
{
    /**
     * Recherche les informations d'un livre par ISBN.
     */
    #[Route('/isbn', name: 'api_lookup_isbn', methods: ['GET'])]
    public function isbnLookup(Request $request, LookupOrchestrator $lookupOrchestrator): JsonResponse
    {
        $isbn = $request->query->get('isbn', '');
        $type = $this->resolveComicType($request);

        if ('' === $isbn) {
            return new JsonResponse(['error' => 'ISBN requis'], Response::HTTP_BAD_REQUEST);
        }

        $result = $lookupOrchestrator->lookup($isbn, $type);

        return $this->buildLookupResponse($lookupOrchestrator, $result, 'Aucun résultat trouvé');
    }

    /**
     * Recherche les informations par titre.
     *
     * Paramètre `limit` : 1 = résultat unique fusionné (défaut), 2-10 = multi-candidats.
     */
    #[Route('/title', name: 'api_lookup_title', methods: ['GET'])]
    public function titleLookup(Request $request, LookupOrchestrator $lookupOrchestrator): JsonResponse
    {
        $title = $request->query->get('title', '');
        $type = $this->resolveComicType($request);
        $limit = \max(0, (int) $request->query->get('limit', '1'));

        if ('' === $title) {
            return new JsonResponse(['error' => 'Titre requis'], Response::HTTP_BAD_REQUEST);
        }

        if ($limit < 1 || $limit > 10) {
            return new JsonResponse(['error' => 'Le paramètre limit doit être entre 1 et 10'], Response::HTTP_BAD_REQUEST);
        }

        if ($limit > 1) {
            return $this->buildMultiLookupResponse($lookupOrchestrator, $title, $type, $limit);
        }

        $result = $lookupOrchestrator->lookupByTitle($title, $type);

        return $this->buildLookupResponse($lookupOrchestrator, $result, 'Aucun résultat trouvé');
    }

    /**
     * Recherche des images de couverture.
     */
    #[Route('/covers', name: 'api_lookup_covers', methods: ['GET'])]
    public function coverSearch(Request $request, CoverSearchService $coverSearchService): JsonResponse
    {
        $query = $request->query->get('query', '');
        $type = $this->resolveComicType($request);

        if ('' === $query) {
            return new JsonResponse(['error' => 'Requête requise'], Response::HTTP_BAD_REQUEST);
        }

        $results = $coverSearchService->search($query, $type);

        return new JsonResponse($results);
    }

    /**
     * Construit la réponse JSON pour un lookup multi-candidats.
     */
    private function buildMultiLookupResponse(LookupOrchestrator $lookupOrchestrator, string $title, ?ComicType $type, int $limit): JsonResponse
    {
        $results = $lookupOrchestrator->lookupByTitleMultiple($title, $type, $limit);

        return new JsonResponse([
            'apiMessages' => $lookupOrchestrator->getLastApiMessages(),
            'results' => \array_map(static fn (LookupResult $r): array => $r->jsonSerialize(), $results),
            'sources' => $lookupOrchestrator->getLastSources(),
        ]);
    }

    /**
     * Construit la réponse JSON pour un résultat de lookup.
     */
    private function buildLookupResponse(LookupOrchestrator $lookupOrchestrator, ?LookupResult $result, string $errorMessage): JsonResponse
    {
        $apiMessages = $lookupOrchestrator->getLastApiMessages();

        if (!$result instanceof LookupResult) {
            return new JsonResponse(['apiMessages' => $apiMessages, 'error' => $errorMessage], Response::HTTP_NOT_FOUND);
        }

        return new JsonResponse([
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

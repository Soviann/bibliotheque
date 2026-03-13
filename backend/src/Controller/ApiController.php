<?php

declare(strict_types=1);

namespace App\Controller;

use App\Controller\Trait\RateLimitTrait;
use App\Enum\ComicType;
use App\Service\CoverSearchService;
use App\Service\Lookup\LookupOrchestrator;
use App\Service\Lookup\LookupResult;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
#[Route('/api/lookup')]
final class ApiController
{
    use RateLimitTrait;

    public function __construct(
        private readonly RateLimiterFactory $apiLookupLimiter,
        #[Autowire(service: 'limiter.cover_search')]
        private readonly RateLimiterFactory $coverSearchLimiter,
    ) {
    }

    /**
     * Recherche les informations d'un livre par ISBN.
     */
    #[Route('/isbn', name: 'api_lookup_isbn', methods: ['GET'])]
    public function isbnLookup(Request $request, LookupOrchestrator $lookupOrchestrator): JsonResponse
    {
        $rateLimitResponse = $this->checkRateLimit($request, $this->apiLookupLimiter);
        if ($rateLimitResponse instanceof JsonResponse) {
            return $rateLimitResponse;
        }

        $isbn = $request->query->get('isbn', '');
        $type = $this->resolveComicType($request);

        if (empty($isbn)) {
            return new JsonResponse(['error' => 'ISBN requis'], Response::HTTP_BAD_REQUEST);
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
        $rateLimitResponse = $this->checkRateLimit($request, $this->apiLookupLimiter);
        if ($rateLimitResponse instanceof JsonResponse) {
            return $rateLimitResponse;
        }

        $title = $request->query->get('title', '');
        $type = $this->resolveComicType($request);

        if (empty($title)) {
            return new JsonResponse(['error' => 'Titre requis'], Response::HTTP_BAD_REQUEST);
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
        $rateLimitResponse = $this->checkRateLimit($request, $this->coverSearchLimiter);
        if ($rateLimitResponse instanceof JsonResponse) {
            return $rateLimitResponse;
        }

        $query = $request->query->get('query', '');
        $type = $this->resolveComicType($request);

        if (empty($query)) {
            return new JsonResponse(['error' => 'Requête requise'], Response::HTTP_BAD_REQUEST);
        }

        $results = $coverSearchService->search($query, $type);

        return new JsonResponse($results);
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

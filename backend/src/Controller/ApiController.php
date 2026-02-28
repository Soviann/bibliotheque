<?php

declare(strict_types=1);

namespace App\Controller;

use App\Enum\ComicType;
use App\Service\Lookup\LookupOrchestrator;
use App\Service\Lookup\LookupResult;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
#[Route('/api/lookup')]
class ApiController
{
    public function __construct(
        private readonly RateLimiterFactory $apiLookupLimiter,
    ) {
    }

    /**
     * Recherche les informations d'un livre par ISBN.
     */
    #[Route('/isbn', name: 'api_lookup_isbn', methods: ['GET'])]
    public function isbnLookup(Request $request, LookupOrchestrator $lookupOrchestrator): JsonResponse
    {
        $rateLimitResponse = $this->checkRateLimit($request);
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
        $rateLimitResponse = $this->checkRateLimit($request);
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
     * Vérifie le rate limit pour la requête courante.
     */
    private function checkRateLimit(Request $request): ?JsonResponse
    {
        $limiter = $this->apiLookupLimiter->create($request->getClientIp() ?? 'unknown');
        $limit = $limiter->consume();

        if (false === $limit->isAccepted()) {
            $retryAfter = $limit->getRetryAfter()->getTimestamp() - \time();

            $response = new JsonResponse(
                ['error' => 'Trop de requêtes. Réessayez plus tard.'],
                Response::HTTP_TOO_MANY_REQUESTS,
            );
            $response->headers->set('Retry-After', (string) \max(1, $retryAfter));

            return $response;
        }

        return null;
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

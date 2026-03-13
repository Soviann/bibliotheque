<?php

declare(strict_types=1);

namespace App\Controller;

use App\Controller\Trait\RateLimitTrait;
use App\Service\PurgeService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Endpoints pour la purge des séries soft-deleted.
 */
#[IsGranted('ROLE_USER')]
#[Route('/api/tools/purge')]
final class PurgeController
{
    use RateLimitTrait;

    public function __construct(
        private readonly PurgeService $purgeService,
        private readonly RateLimiterFactory $purgeLimiter,
    ) {
    }

    /**
     * Exécute la purge des séries identifiées.
     */
    #[Route('/execute', name: 'api_tools_purge_execute', methods: ['POST'])]
    public function execute(Request $request): JsonResponse
    {
        $rateLimitResponse = $this->checkRateLimit($request, $this->purgeLimiter);
        if ($rateLimitResponse instanceof JsonResponse) {
            return $rateLimitResponse;
        }

        /** @var array<string, mixed> $data */
        $data = \json_decode($request->getContent(), true) ?? [];

        $seriesIds = $data['seriesIds'] ?? null;

        if (!\is_array($seriesIds) || [] === $seriesIds) {
            return new JsonResponse(
                ['error' => 'Le champ "seriesIds" est requis et ne peut pas être vide.'],
                Response::HTTP_BAD_REQUEST,
            );
        }

        /** @var int[] $ids */
        $ids = \array_map('\intval', $seriesIds);
        $count = $this->purgeService->executePurge($ids);

        return new JsonResponse(['purged' => $count]);
    }

    /**
     * Prévisualise les séries éligibles à la purge.
     */
    #[Route('/preview', name: 'api_tools_purge_preview', methods: ['GET'])]
    public function preview(Request $request): JsonResponse
    {
        $days = (int) $request->query->get('days', '30');

        if ($days <= 0) {
            return new JsonResponse(
                ['error' => 'Le paramètre "days" doit être supérieur à 0.'],
                Response::HTTP_BAD_REQUEST,
            );
        }

        $purgeable = $this->purgeService->findPurgeable($days);

        return new JsonResponse($purgeable);
    }
}

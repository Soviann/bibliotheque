<?php

declare(strict_types=1);

namespace App\Controller;

use App\Enum\ComicType;
use App\Service\Lookup\BatchLookupService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Endpoints pour le lookup batch des métadonnées manquantes.
 */
#[IsGranted('ROLE_USER')]
#[Route('/api/tools/batch-lookup')]
final readonly class BatchLookupController
{
    public function __construct(
        private BatchLookupService $batchLookupService,
    ) {
    }

    /**
     * Prévisualise le nombre de séries à traiter.
     */
    #[Route('/preview', name: 'api_tools_batch_lookup_preview', methods: ['GET'])]
    public function preview(Request $request): JsonResponse
    {
        $typeValue = $request->query->get('type');
        $type = \is_string($typeValue) ? ComicType::tryFrom($typeValue) : null;
        $force = 'true' === $request->query->get('force', 'false');

        $count = $this->batchLookupService->countSeriesToProcess($type, $force);

        return new JsonResponse(['count' => $count]);
    }

    /**
     * Met en file l'enrichissement des séries à traiter (traitement asynchrone par le worker).
     */
    #[Route('/run', name: 'api_tools_batch_lookup_run', methods: ['POST'])]
    public function run(Request $request): JsonResponse
    {
        /** @var array<string, mixed> $data */
        $data = \json_decode($request->getContent(), true) ?? [];

        $force = (bool) ($data['force'] ?? false);
        /** @var int|string $rawLimit */
        $rawLimit = $data['limit'] ?? 0;
        $limit = (int) $rawLimit;
        $typeValue = $data['type'] ?? null;
        $type = \is_string($typeValue) ? ComicType::tryFrom($typeValue) : null;

        $queued = $this->batchLookupService->queue($type, $force, $limit);

        return new JsonResponse(['queued' => $queued]);
    }
}

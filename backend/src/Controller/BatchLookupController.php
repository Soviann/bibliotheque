<?php

declare(strict_types=1);

namespace App\Controller;

use App\DTO\BatchLookupSummary;
use App\Enum\ComicType;
use App\Service\BatchLookupService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Endpoints pour le lookup batch des métadonnées manquantes.
 */
#[IsGranted('ROLE_USER')]
#[Route('/api/tools/batch-lookup')]
class BatchLookupController
{
    public function __construct(
        private readonly BatchLookupService $batchLookupService,
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
     * Lance le lookup batch avec streaming SSE.
     */
    #[Route('/run', name: 'api_tools_batch_lookup_run', methods: ['POST'])]
    public function run(Request $request): StreamedResponse
    {
        /** @var array<string, mixed> $data */
        $data = \json_decode($request->getContent(), true) ?? [];

        /** @var int|string $rawDelay */
        $rawDelay = $data['delay'] ?? 2;
        $delay = (int) $rawDelay;
        $force = (bool) ($data['force'] ?? false);
        /** @var int|string $rawLimit */
        $rawLimit = $data['limit'] ?? 0;
        $limit = (int) $rawLimit;
        $typeValue = $data['type'] ?? null;
        $type = \is_string($typeValue) ? ComicType::tryFrom($typeValue) : null;

        $response = new StreamedResponse(function () use ($delay, $force, $limit, $type): void {
            $failed = 0;
            $processed = 0;
            $skipped = 0;
            $updated = 0;

            foreach ($this->batchLookupService->run(
                delay: $delay,
                force: $force,
                limit: $limit,
                type: $type,
            ) as $progress) {
                echo 'data: '.\json_encode($progress, \JSON_THROW_ON_ERROR)."\n\n";

                if (\ob_get_level() > 0) {
                    \ob_flush();
                }
                \flush();

                ++$processed;

                match ($progress->status) {
                    'failed' => ++$failed,
                    'skipped' => ++$skipped,
                    'updated' => ++$updated,
                    default => null,
                };
            }

            $summary = new BatchLookupSummary(
                failed: $failed,
                processed: $processed,
                skipped: $skipped,
                updated: $updated,
            );

            echo "event: complete\ndata: ".\json_encode($summary, \JSON_THROW_ON_ERROR)."\n\n";

            if (\ob_get_level() > 0) {
                \ob_flush();
            }
            \flush();
        });

        $response->headers->set('Cache-Control', 'no-cache');
        $response->headers->set('Content-Type', 'text/event-stream');
        $response->headers->set('X-Accel-Buffering', 'no');

        return $response;
    }
}

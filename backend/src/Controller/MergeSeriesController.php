<?php

declare(strict_types=1);

namespace App\Controller;

use App\Enum\ComicType;
use App\Repository\ComicSeriesRepository;
use App\Service\Merge\MergePreviewBuilder;
use App\Service\Merge\MergePreviewHydrator;
use App\Service\Merge\SeriesGroupDetector;
use App\Service\Merge\SeriesMerger;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Endpoints pour la détection et la fusion de séries.
 */
#[IsGranted('ROLE_USER')]
#[Route('/api/merge-series')]
final readonly class MergeSeriesController
{
    public function __construct(
        private ComicSeriesRepository $comicSeriesRepository,
        private MergePreviewBuilder $mergePreviewBuilder,
        private MergePreviewHydrator $mergePreviewHydrator,
        private SeriesGroupDetector $seriesGroupDetector,
        private SeriesMerger $seriesMerger,
    ) {
    }

    /**
     * Détecte les groupes de séries à fusionner.
     */
    #[Route('/detect', name: 'api_merge_series_detect', methods: ['POST'])]
    public function detect(Request $request): JsonResponse
    {
        /** @var array<string, mixed> $data */
        $data = \json_decode($request->getContent(), true) ?? [];

        $type = isset($data['type']) && \is_string($data['type'])
            ? ComicType::tryFrom($data['type'])
            : null;
        $force = (bool) ($data['all'] ?? false);
        $startsWith = isset($data['startsWith']) && \is_string($data['startsWith'])
            ? $data['startsWith']
            : null;

        $seriesList = $this->comicSeriesRepository->findForMergeDetection($force, $startsWith, $type);

        try {
            $groups = $this->seriesGroupDetector->detect($seriesList);
        } catch (\RuntimeException $e) {
            return new JsonResponse(
                ['error' => $e->getMessage()],
                Response::HTTP_TOO_MANY_REQUESTS,
            );
        }

        return new JsonResponse($groups);
    }

    /**
     * Construit un aperçu de fusion à partir d'une sélection manuelle.
     */
    #[Route('/preview', name: 'api_merge_series_preview', methods: ['POST'])]
    public function preview(Request $request): JsonResponse
    {
        /** @var array<string, mixed> $data */
        $data = \json_decode($request->getContent(), true) ?? [];

        $seriesIds = $data['seriesIds'] ?? [];
        if (!\is_array($seriesIds) || \count($seriesIds) < 2) {
            return new JsonResponse(
                ['error' => 'Au moins 2 séries sont requises pour la fusion.'],
                Response::HTTP_BAD_REQUEST,
            );
        }

        $seriesList = [];
        foreach ($seriesIds as $id) {
            if (!\is_int($id) && !\is_string($id)) {
                return new JsonResponse(
                    ['error' => 'Les identifiants de séries doivent être des entiers.'],
                    Response::HTTP_BAD_REQUEST,
                );
            }
            $series = $this->comicSeriesRepository->find((int) $id);
            if (null === $series) {
                return new JsonResponse(
                    ['error' => \sprintf('Série introuvable : %d', (int) $id)],
                    Response::HTTP_NOT_FOUND,
                );
            }
            $seriesList[] = $series;
        }

        $preview = $this->mergePreviewBuilder->buildFromManualSelection($seriesList);

        return new JsonResponse($preview);
    }

    /**
     * Suggère un titre canonique et des numéros de tomes via Gemini.
     */
    #[Route('/suggest', name: 'api_merge_series_suggest', methods: ['POST'])]
    public function suggest(Request $request): JsonResponse
    {
        /** @var array<string, mixed> $data */
        $data = \json_decode($request->getContent(), true) ?? [];

        $seriesIds = $data['seriesIds'] ?? [];
        if (!\is_array($seriesIds) || \count($seriesIds) < 2) {
            return new JsonResponse(
                ['error' => 'Au moins 2 séries sont requises.'],
                Response::HTTP_BAD_REQUEST,
            );
        }

        $seriesList = [];
        foreach ($seriesIds as $id) {
            if (!\is_int($id) && !\is_string($id)) {
                return new JsonResponse(
                    ['error' => 'Les identifiants de séries doivent être des entiers.'],
                    Response::HTTP_BAD_REQUEST,
                );
            }
            $series = $this->comicSeriesRepository->find((int) $id);
            if (null === $series) {
                return new JsonResponse(
                    ['error' => \sprintf('Série introuvable : %d', (int) $id)],
                    Response::HTTP_NOT_FOUND,
                );
            }
            $seriesList[] = $series;
        }

        $suggestion = $this->mergePreviewBuilder->suggestFromGemini($seriesList);

        if (null === $suggestion) {
            return new JsonResponse(
                ['error' => 'Impossible d\'obtenir des suggestions.'],
                Response::HTTP_SERVICE_UNAVAILABLE,
            );
        }

        return new JsonResponse($suggestion);
    }

    /**
     * Exécute la fusion selon un aperçu validé.
     */
    #[Route('/execute', name: 'api_merge_series_execute', methods: ['POST'])]
    public function execute(Request $request): JsonResponse
    {
        /** @var array<string, mixed> $data */
        $data = \json_decode($request->getContent(), true) ?? [];

        try {
            $preview = $this->mergePreviewHydrator->hydrate($data);
        } catch (\InvalidArgumentException $e) {
            return new JsonResponse(
                ['error' => $e->getMessage()],
                Response::HTTP_BAD_REQUEST,
            );
        }

        try {
            $mergedSeries = $this->seriesMerger->execute($preview);
        } catch (\InvalidArgumentException $e) {
            return new JsonResponse(
                ['error' => $e->getMessage()],
                Response::HTTP_NOT_FOUND,
            );
        }

        return new JsonResponse([
            'id' => $mergedSeries->getId(),
            'title' => $mergedSeries->getTitle(),
            'type' => $mergedSeries->getType()->value,
        ]);
    }
}

<?php

declare(strict_types=1);

namespace App\Controller;

use App\DTO\MergePreview;
use App\DTO\MergePreviewTome;
use App\Enum\ComicType;
use App\Repository\ComicSeriesRepository;
use App\Service\Merge\MergePreviewBuilder;
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
class MergeSeriesController
{
    public function __construct(
        private readonly ComicSeriesRepository $comicSeriesRepository,
        private readonly MergePreviewBuilder $mergePreviewBuilder,
        private readonly SeriesGroupDetector $seriesGroupDetector,
        private readonly SeriesMerger $seriesMerger,
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
     * Exécute la fusion selon un aperçu validé.
     */
    #[Route('/execute', name: 'api_merge_series_execute', methods: ['POST'])]
    public function execute(Request $request): JsonResponse
    {
        /** @var array<string, mixed> $data */
        $data = \json_decode($request->getContent(), true) ?? [];

        try {
            $preview = $this->hydrateMergePreview($data);
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

    /**
     * Hydrate un MergePreview depuis les données JSON.
     *
     * @param array<string, mixed> $data
     *
     * @throws \InvalidArgumentException si les données sont invalides
     */
    private function hydrateMergePreview(array $data): MergePreview
    {
        if (!isset($data['title']) || !\is_string($data['title'])) {
            throw new \InvalidArgumentException('Le champ "title" est requis.');
        }

        if (!isset($data['type']) || !\is_string($data['type'])) {
            throw new \InvalidArgumentException('Le champ "type" est requis.');
        }

        if (!isset($data['sourceSeriesIds']) || !\is_array($data['sourceSeriesIds'])) {
            throw new \InvalidArgumentException('Le champ "sourceSeriesIds" est requis.');
        }

        if (!isset($data['tomes']) || !\is_array($data['tomes'])) {
            throw new \InvalidArgumentException('Le champ "tomes" est requis.');
        }

        /** @var list<MergePreviewTome> $tomes */
        $tomes = \array_values(\array_map(
            static function (mixed $tomeData): MergePreviewTome {
                if (!\is_array($tomeData)) {
                    throw new \InvalidArgumentException('Chaque tome doit être un objet.');
                }

                return new MergePreviewTome(
                    bought: (bool) ($tomeData['bought'] ?? false),
                    downloaded: (bool) ($tomeData['downloaded'] ?? false),
                    isbn: isset($tomeData['isbn']) && \is_string($tomeData['isbn']) ? $tomeData['isbn'] : null,
                    number: (int) ($tomeData['number'] ?? 0),
                    onNas: (bool) ($tomeData['onNas'] ?? false),
                    read: (bool) ($tomeData['read'] ?? false),
                    title: isset($tomeData['title']) && \is_string($tomeData['title']) ? $tomeData['title'] : null,
                    tomeEnd: isset($tomeData['tomeEnd']) && \is_numeric($tomeData['tomeEnd']) ? (int) $tomeData['tomeEnd'] : null,
                );
            },
            $data['tomes'],
        ));

        /** @var list<string> $authors */
        $authors = \is_array($data['authors'] ?? null) ? \array_values($data['authors']) : [];

        /** @var list<int> $sourceSeriesIds */
        $sourceSeriesIds = \array_values(\array_map('\intval', $data['sourceSeriesIds']));

        return new MergePreview(
            authors: $authors,
            coverUrl: isset($data['coverUrl']) && \is_string($data['coverUrl']) ? $data['coverUrl'] : null,
            description: isset($data['description']) && \is_string($data['description']) ? $data['description'] : null,
            isOneShot: (bool) ($data['isOneShot'] ?? false),
            latestPublishedIssue: isset($data['latestPublishedIssue']) && \is_numeric($data['latestPublishedIssue']) ? (int) $data['latestPublishedIssue'] : null,
            latestPublishedIssueComplete: (bool) ($data['latestPublishedIssueComplete'] ?? false),
            publisher: isset($data['publisher']) && \is_string($data['publisher']) ? $data['publisher'] : null,
            sourceSeriesIds: $sourceSeriesIds,
            title: $data['title'],
            tomes: $tomes,
            type: $data['type'],
        );
    }
}

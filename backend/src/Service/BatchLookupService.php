<?php

declare(strict_types=1);

namespace App\Service;

use App\DTO\BatchLookupProgress;
use App\Entity\ComicSeries;
use App\Enum\BatchLookupStatus;
use App\Enum\ComicType;
use App\Repository\ComicSeriesRepository;
use App\Service\Lookup\LookupApplier;
use App\Service\Lookup\LookupOrchestrator;
use App\Service\Lookup\LookupResult;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Service de lookup batch pour les séries avec métadonnées manquantes.
 *
 * Utilise un générateur pour permettre le streaming (SSE) ou l'affichage CLI.
 */
final readonly class BatchLookupService
{
    private const int BATCH_SIZE = 10;
    private const int MAX_DELAY = 60;

    public function __construct(
        private ComicSeriesRepository $comicSeriesRepository,
        private EntityManagerInterface $entityManager,
        private LookupApplier $lookupApplier,
        private LookupOrchestrator $lookupOrchestrator,
    ) {
    }

    /**
     * Compte les séries à traiter.
     */
    public function countSeriesToProcess(?ComicType $type = null, bool $force = false): int
    {
        return \count($this->comicSeriesRepository->findWithMissingLookupData(
            type: $type,
            force: $force,
        ));
    }

    /**
     * Exécute le lookup batch et yield la progression après chaque série.
     *
     * @return \Generator<BatchLookupProgress>
     */
    public function run(
        int $delay = 2,
        bool $dryRun = false,
        bool $force = false,
        int $limit = 0,
        ?ComicType $type = null,
    ): \Generator {
        $seriesList = $this->comicSeriesRepository->findWithMissingLookupData(
            type: $type,
            limit: $limit > 0 ? $limit : null,
            force: $force,
        );

        $currentDelay = $delay;
        $total = \count($seriesList);

        foreach ($seriesList as $index => $series) {
            $progress = $this->processSeries($series, $currentDelay, $dryRun, $index, $total);
            $currentDelay = $progress['delay'];

            yield $progress['progress'];

            // Flush par batch
            if (!$dryRun && 0 === ($index + 1) % self::BATCH_SIZE) {
                $this->entityManager->flush();
            }

            // Délai entre les lookups (sauf dernier)
            if ($index < $total - 1 && $currentDelay > 0) {
                \sleep($currentDelay);
            }
        }

        // Flush final
        if (!$dryRun) {
            $this->entityManager->flush();
        }
    }

    /**
     * @return array{delay: int, progress: BatchLookupProgress}
     */
    private function processSeries(
        ComicSeries $series,
        int $currentDelay,
        bool $dryRun,
        int $index,
        int $total,
    ): array {
        $title = $series->getTitle();
        $type = $series->getType();

        $result = $this->lookupOrchestrator->lookupByTitle($title, $type);

        // Vérifier le rate limiting
        if ($this->lookupOrchestrator->hasRateLimitError()) {
            $currentDelay = \min($currentDelay * 2, self::MAX_DELAY);
            \sleep($currentDelay);

            // Retry
            $result = $this->lookupOrchestrator->lookupByTitle($title, $type);

            if ($this->lookupOrchestrator->hasRateLimitError()) { // @phpstan-ignore if.alwaysTrue (état dépend de l'appel API)
                return [
                    'delay' => $currentDelay,
                    'progress' => new BatchLookupProgress(
                        current: $index + 1,
                        seriesTitle: $title,
                        status: BatchLookupStatus::FAILED,
                        total: $total,
                    ),
                ];
            }
        }

        if (!$result instanceof LookupResult) {
            if (!$dryRun) {
                $series->setLookupCompletedAt(new \DateTimeImmutable());
            }

            return [
                'delay' => $currentDelay,
                'progress' => new BatchLookupProgress(
                    current: $index + 1,
                    seriesTitle: $title,
                    status: BatchLookupStatus::SKIPPED,
                    total: $total,
                ),
            ];
        }

        $updatedFields = $this->lookupApplier->apply($series, $result);

        if (!$dryRun) {
            $series->setLookupCompletedAt(new \DateTimeImmutable());
        }

        if ([] !== $updatedFields) {
            // Reset delay après un succès
            return [
                'delay' => $currentDelay,
                'progress' => new BatchLookupProgress(
                    current: $index + 1,
                    seriesTitle: $title,
                    status: BatchLookupStatus::UPDATED,
                    total: $total,
                    updatedFields: $updatedFields,
                ),
            ];
        }

        return [
            'delay' => $currentDelay,
            'progress' => new BatchLookupProgress(
                current: $index + 1,
                seriesTitle: $title,
                status: BatchLookupStatus::SKIPPED,
                total: $total,
            ),
        ];
    }
}

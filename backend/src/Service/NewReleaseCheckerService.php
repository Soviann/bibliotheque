<?php

declare(strict_types=1);

namespace App\Service;

use App\DTO\NewReleaseProgress;
use App\Entity\ComicSeries;
use App\Entity\Tome;
use App\Enum\ApiLookupStatus;
use App\Enum\BatchLookupStatus;
use App\Repository\ComicSeriesRepository;
use App\Service\Lookup\LookupOrchestrator;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Vérifie les nouvelles parutions pour les séries en cours d'achat.
 */
final readonly class NewReleaseCheckerService
{
    private const int BATCH_SIZE = 10;

    public function __construct(
        private EntityManagerInterface $entityManager,
        private LookupOrchestrator $lookupOrchestrator,
        private ComicSeriesRepository $repository,
    ) {
    }

    /**
     * Vérifie les nouvelles parutions et yield la progression.
     *
     * @return \Generator<NewReleaseProgress>
     */
    public function run(bool $dryRun, ?int $limit): \Generator
    {
        $seriesList = $this->repository->findBuyingForReleaseCheck(
            $limit > 0 ? $limit : null,
        );

        $total = \count($seriesList);

        foreach ($seriesList as $index => $series) {
            $title = $series->getTitle();
            $type = $series->getType();
            $previousLatestIssue = $series->getLatestPublishedIssue();

            $result = $this->lookupOrchestrator->lookupByTitle($title, $type);

            // Vérifier le rate limiting — arrêt total
            if ($this->hasRateLimitError()) {
                yield new NewReleaseProgress(
                    current: $index + 1,
                    newLatestIssue: null,
                    previousLatestIssue: $previousLatestIssue,
                    seriesTitle: $title,
                    status: BatchLookupStatus::FAILED,
                    stoppedByRateLimit: true,
                    total: $total,
                );

                return;
            }

            $newLatestIssue = $result?->latestPublishedIssue;

            // Déterminer si c'est une mise à jour (nouveau > ancien)
            $isUpdate = null !== $newLatestIssue
                && (null === $previousLatestIssue || $newLatestIssue > $previousLatestIssue);

            if ($isUpdate && !$dryRun) {
                $series->setLatestPublishedIssue($newLatestIssue);
                $series->setLatestPublishedIssueUpdatedAt(new \DateTimeImmutable());
                $this->createMissingTomes($series, $newLatestIssue);
            }

            if (!$dryRun) {
                $series->setNewReleasesCheckedAt(new \DateTimeImmutable());
            }

            yield new NewReleaseProgress(
                current: $index + 1,
                newLatestIssue: $isUpdate ? $newLatestIssue : null,
                previousLatestIssue: $isUpdate ? $previousLatestIssue : null,
                seriesTitle: $title,
                status: $isUpdate ? BatchLookupStatus::UPDATED : BatchLookupStatus::SKIPPED,
                stoppedByRateLimit: false,
                total: $total,
            );

            // Flush par batch
            if (!$dryRun && 0 === ($index + 1) % self::BATCH_SIZE) {
                $this->entityManager->flush();
            }
        }

        // Flush final
        if (!$dryRun && $total > 0) {
            $this->entityManager->flush();
        }
    }

    /**
     * Vérifie si un des messages API indique un rate limit.
     */
    private function hasRateLimitError(): bool
    {
        foreach ($this->lookupOrchestrator->getLastApiMessages() as $message) {
            if (ApiLookupStatus::RATE_LIMITED->value === $message->status) {
                return true;
            }
        }

        return false;
    }

    /**
     * Crée les tomes manquants avec les flags par défaut de la série.
     */
    private function createMissingTomes(ComicSeries $series, int $latestPublishedIssue): void
    {
        $existingNumbers = [];
        foreach ($series->getTomes() as $tome) {
            $existingNumbers[$tome->getNumber()] = true;
        }

        for ($number = 1; $number <= $latestPublishedIssue; ++$number) {
            if (isset($existingNumbers[$number])) {
                continue;
            }

            $tome = new Tome();
            $tome->setBought($series->isDefaultTomeBought());
            $tome->setDownloaded($series->isDefaultTomeDownloaded());
            $tome->setNumber($number);
            $tome->setRead($series->isDefaultTomeRead());
            $series->addTome($tome);
        }
    }
}

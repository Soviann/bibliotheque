<?php

declare(strict_types=1);

namespace App\Service;

use App\DTO\MissingTomeResult;
use App\Enum\NotificationEntityType;
use App\Enum\NotificationType;
use App\Repository\ComicSeriesRepository;
use App\Repository\NotificationRepository;
use App\Repository\UserRepository;
use App\Service\Notification\NotificationService;
use Psr\Log\LoggerInterface;

/**
 * Détecte les tomes manquants pour les séries en cours d'achat ou terminées.
 */
class MissingTomeDetectorService
{
    public function __construct(
        private readonly ComicSeriesRepository $comicSeriesRepository,
        private readonly LoggerInterface $logger,
        private readonly NotificationRepository $notificationRepository,
        private readonly NotificationService $notificationService,
        private readonly UserRepository $userRepository,
    ) {
    }

    /**
     * @return \Generator<MissingTomeResult>
     */
    public function detect(bool $dryRun = false): \Generator
    {
        $seriesList = $this->comicSeriesRepository->findForMissingTomeDetection();
        $user = $this->userRepository->findOneBy([]);

        if (null === $user) {
            $this->logger->warning('Aucun utilisateur trouvé pour les notifications');

            return;
        }

        foreach ($seriesList as $series) {
            $missing = $series->getMissingTomesNumbers();

            if ([] === $missing) {
                continue;
            }

            $seriesId = $series->getId();

            if (null === $seriesId) {
                continue;
            }

            // Déduplication : ne pas notifier si une notification non lue existe déjà
            if ($this->hasUnreadNotification($seriesId)) {
                continue;
            }

            /** @var list<int> $missingList */
            $missingList = $missing;

            $result = new MissingTomeResult(
                missingNumbers: $missingList,
                seriesId: $seriesId,
                seriesTitle: $series->getTitle(),
            );

            if (!$dryRun) {
                $this->notificationService->create(
                    user: $user,
                    type: NotificationType::MISSING_TOME,
                    title: \sprintf('%d tome(s) manquant(s)', \count($missing)),
                    message: \sprintf('%s : tomes %s', $series->getTitle(), \implode(', ', $missing)),
                    relatedEntityType: NotificationEntityType::COMIC_SERIES,
                    relatedEntityId: $seriesId,
                    metadata: ['missingNumbers' => $missing],
                );
            }

            yield $result;
        }
    }

    private function hasUnreadNotification(int $seriesId): bool
    {
        return $this->notificationRepository->existsUnreadByTypeAndEntity(
            NotificationType::MISSING_TOME,
            NotificationEntityType::COMIC_SERIES,
            $seriesId,
        );
    }
}

<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Message\DownloadCoverMessage;
use App\Repository\ComicSeriesRepository;
use App\Service\Cover\CoverDownloader;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Handler pour le téléchargement asynchrone d'une couverture.
 */
#[AsMessageHandler]
final readonly class DownloadCoverHandler
{
    public function __construct(
        private ComicSeriesRepository $comicSeriesRepository,
        private CoverDownloader $coverDownloader,
        private EntityManagerInterface $entityManager,
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(DownloadCoverMessage $message): void
    {
        $series = $this->comicSeriesRepository->find($message->seriesId);

        if (null === $series) {
            $this->logger->warning('Série {id} non trouvée pour téléchargement de couverture', [
                'id' => $message->seriesId,
            ]);

            return;
        }

        if ($this->coverDownloader->downloadAndStore($series, $message->coverUrl)) {
            $this->entityManager->flush();

            $this->logger->info('Couverture téléchargée pour "{title}"', [
                'title' => $series->getTitle(),
            ]);
        } else {
            $this->logger->warning('Échec du téléchargement de couverture pour "{title}"', [
                'title' => $series->getTitle(),
                'url' => $message->coverUrl,
            ]);
        }
    }
}

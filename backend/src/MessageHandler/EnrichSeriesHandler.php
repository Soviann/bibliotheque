<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Enum\LookupMode;
use App\Message\EnrichSeriesMessage;
use App\Repository\ComicSeriesRepository;
use App\Service\Enrichment\EnrichmentService;
use App\Service\Lookup\LookupOrchestrator;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Handler pour l'enrichissement asynchrone d'une série.
 */
#[AsMessageHandler]
final readonly class EnrichSeriesHandler
{
    public function __construct(
        private ComicSeriesRepository $comicSeriesRepository,
        private EntityManagerInterface $entityManager,
        private EnrichmentService $enrichmentService,
        private LoggerInterface $logger,
        private LookupOrchestrator $lookupOrchestrator,
    ) {
    }

    public function __invoke(EnrichSeriesMessage $message): void
    {
        $series = $this->comicSeriesRepository->find($message->seriesId);

        if (null === $series) {
            $this->logger->warning('Série {id} non trouvée pour enrichissement', [
                'id' => $message->seriesId,
            ]);

            return;
        }

        $result = $this->lookupOrchestrator->lookupByTitle(
            $series->getTitle(),
            $series->getType(),
        );

        if (null !== $result) {
            $sources = $this->lookupOrchestrator->getLastSources();

            $confidence = $this->enrichmentService->enrich(
                $series,
                $result,
                LookupMode::TITLE,
                $sources,
            );

            $this->logger->info('Enrichissement de "{title}" : confiance {confidence}', [
                'confidence' => $confidence->value,
                'title' => $series->getTitle(),
            ]);
        }

        $series->setLookupCompletedAt(new \DateTimeImmutable());
        $this->entityManager->flush();
    }
}

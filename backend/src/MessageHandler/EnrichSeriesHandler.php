<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Enum\LookupMode;
use App\Message\EnrichSeriesMessage;
use App\Repository\ComicSeriesRepository;
use App\Service\Enrichment\EnrichmentService;
use App\Service\Lookup\Gemini\GeminiCircuitBreaker;
use App\Service\Lookup\LookupOrchestrator;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DelayStamp;

/**
 * Handler pour l'enrichissement asynchrone d'une série.
 */
#[AsMessageHandler]
final readonly class EnrichSeriesHandler
{
    /** Délai de repli si le disjoncteur ne fournit pas de durée (5 minutes, en ms). */
    private const int FALLBACK_REDISPATCH_DELAY_MS = 300_000;

    public function __construct(
        private ComicSeriesRepository $comicSeriesRepository,
        private EntityManagerInterface $entityManager,
        private EnrichmentService $enrichmentService,
        private GeminiCircuitBreaker $circuitBreaker,
        private LoggerInterface $logger,
        private LookupOrchestrator $lookupOrchestrator,
        private MessageBusInterface $messageBus,
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

        // Déjà enrichie (lookup manuel ou batch précédent)
        if (null !== $series->getLookupCompletedAt()) {
            $this->logger->info('Série "{title}" déjà enrichie, enrichissement automatique ignoré', [
                'title' => $series->getTitle(),
            ]);

            return;
        }

        // Disjoncteur ouvert (quota Gemini épuisé) : reporter sans appeler l'API
        // ni consommer définitivement le message.
        if ($this->circuitBreaker->isOpen()) {
            $this->redispatchLater($message);
            $this->logger->info('Disjoncteur Gemini ouvert — enrichissement de "{title}" reporté', [
                'title' => $series->getTitle(),
            ]);

            return;
        }

        $result = $this->lookupOrchestrator->lookupByTitle(
            $series->getTitle(),
            $series->getType(),
        );

        // Quota Gemini épuisé : ouvrir le disjoncteur et reporter le message
        // jusqu'au prochain reset quotidien (pas de mise en file d'échec).
        if ($this->lookupOrchestrator->hasRateLimitError()) {
            $until = $this->circuitBreaker->open();
            $this->redispatchLater($message);
            $this->logger->warning('Quota Gemini épuisé — disjoncteur ouvert jusqu\'à {until}, série "{title}" reportée', [
                'title' => $series->getTitle(),
                'until' => $until->format(\DateTimeInterface::ATOM),
            ]);

            return;
        }

        if (null !== $result) {
            $sources = $this->lookupOrchestrator->getLastSources();

            $confidence = $this->enrichmentService->enrich(
                $series,
                $result,
                LookupMode::TITLE,
                $sources,
                $message->triggeredBy,
            );

            $this->logger->info('Enrichissement de "{title}" : confiance {confidence}', [
                'confidence' => $confidence->value,
                'title' => $series->getTitle(),
            ]);
        }

        $series->setLookupCompletedAt(new \DateTimeImmutable());
        $this->entityManager->flush();
    }

    /**
     * Redispatche le message avec un délai jusqu'au prochain reset Gemini.
     */
    private function redispatchLater(EnrichSeriesMessage $message): void
    {
        $delaySeconds = $this->circuitBreaker->retryAfterSeconds();
        $delayMs = $delaySeconds > 0 ? $delaySeconds * 1000 : self::FALLBACK_REDISPATCH_DELAY_MS;

        $this->messageBus->dispatch($message, [new DelayStamp($delayMs)]);
    }
}

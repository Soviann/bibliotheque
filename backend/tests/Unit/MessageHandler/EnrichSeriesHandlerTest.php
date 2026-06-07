<?php

declare(strict_types=1);

namespace App\Tests\Unit\MessageHandler;

use App\Entity\ComicSeries;
use App\Enum\ComicType;
use App\Enum\EnrichmentConfidence;
use App\Enum\LookupMode;
use App\Message\EnrichSeriesMessage;
use App\MessageHandler\EnrichSeriesHandler;
use App\Repository\ComicSeriesRepository;
use App\Service\Enrichment\EnrichmentService;
use App\Service\Lookup\Contract\LookupResult;
use App\Service\Lookup\Gemini\GeminiCircuitBreaker;
use App\Service\Lookup\LookupOrchestrator;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DelayStamp;

/**
 * Tests unitaires pour le handler d'enrichissement asynchrone.
 */
final class EnrichSeriesHandlerTest extends TestCase
{
    private MockObject&GeminiCircuitBreaker $circuitBreaker;
    private MockObject&ComicSeriesRepository $comicSeriesRepository;
    private MockObject&EntityManagerInterface $entityManager;
    private MockObject&EnrichmentService $enrichmentService;
    private EnrichSeriesHandler $handler;
    private MockObject&LookupOrchestrator $lookupOrchestrator;
    private MockObject&MessageBusInterface $messageBus;

    protected function setUp(): void
    {
        $this->circuitBreaker = $this->createMock(GeminiCircuitBreaker::class);
        $this->comicSeriesRepository = $this->createMock(ComicSeriesRepository::class);
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->enrichmentService = $this->createMock(EnrichmentService::class);
        $this->lookupOrchestrator = $this->createMock(LookupOrchestrator::class);
        $this->messageBus = $this->createMock(MessageBusInterface::class);

        $this->handler = new EnrichSeriesHandler(
            $this->comicSeriesRepository,
            $this->entityManager,
            $this->enrichmentService,
            $this->circuitBreaker,
            new NullLogger(),
            $this->lookupOrchestrator,
            $this->messageBus,
        );
    }

    /**
     * Teste que le handler exécute le lookup et l'enrichissement.
     */
    public function testHandlerRunsLookupAndEnrichment(): void
    {
        $series = new ComicSeries();
        $series->setTitle('One Piece');
        $series->setType(ComicType::MANGA);

        $this->comicSeriesRepository->expects(self::once())
            ->method('find')
            ->with(42)
            ->willReturn($series);

        $this->circuitBreaker->method('isOpen')->willReturn(false);

        $lookupResult = new LookupResult(description: 'Description', source: 'google');

        $this->lookupOrchestrator->expects(self::once())
            ->method('lookupByTitle')
            ->with('One Piece', ComicType::MANGA)
            ->willReturn($lookupResult);

        $this->lookupOrchestrator->method('hasRateLimitError')->willReturn(false);
        $this->lookupOrchestrator->method('getLastSources')->willReturn(['google']);

        $this->enrichmentService->expects(self::once())
            ->method('enrich')
            ->with($series, $lookupResult, LookupMode::TITLE, ['google'])
            ->willReturn(EnrichmentConfidence::HIGH);

        $this->entityManager->expects(self::once())->method('flush');

        ($this->handler)(new EnrichSeriesMessage(42));

        self::assertNotNull($series->getLookupCompletedAt());
    }

    /**
     * Teste que le handler ne fait rien si la série n'existe pas.
     */
    public function testHandlerSkipsIfSeriesNotFound(): void
    {
        $this->comicSeriesRepository->expects(self::once())
            ->method('find')
            ->with(999)
            ->willReturn(null);

        $this->lookupOrchestrator->expects(self::never())->method('lookupByTitle');
        $this->enrichmentService->expects(self::never())->method('enrich');
        $this->messageBus->expects(self::never())->method('dispatch');

        ($this->handler)(new EnrichSeriesMessage(999));
    }

    /**
     * Teste que le handler gère le cas où le lookup ne retourne rien.
     */
    public function testHandlerHandlesNoLookupResult(): void
    {
        $series = new ComicSeries();
        $series->setTitle('Série Inconnue');
        $series->setType(ComicType::BD);

        $this->comicSeriesRepository->expects(self::once())
            ->method('find')
            ->with(10)
            ->willReturn($series);

        $this->circuitBreaker->method('isOpen')->willReturn(false);

        $this->lookupOrchestrator->expects(self::once())
            ->method('lookupByTitle')
            ->willReturn(null);

        $this->lookupOrchestrator->method('hasRateLimitError')->willReturn(false);

        $this->enrichmentService->expects(self::never())->method('enrich');
        $this->entityManager->expects(self::once())->method('flush');

        ($this->handler)(new EnrichSeriesMessage(10));

        self::assertNotNull($series->getLookupCompletedAt());
    }

    /**
     * Teste qu'une série déjà enrichie est ignorée (sans force).
     */
    public function testHandlerSkipsAlreadyEnrichedSeries(): void
    {
        $series = new ComicSeries();
        $series->setTitle('Déjà enrichie');
        $series->setType(ComicType::MANGA);
        $series->setLookupCompletedAt(new \DateTimeImmutable());

        $this->comicSeriesRepository->expects(self::once())
            ->method('find')
            ->with(5)
            ->willReturn($series);

        $this->lookupOrchestrator->expects(self::never())->method('lookupByTitle');
        $this->enrichmentService->expects(self::never())->method('enrich');
        $this->messageBus->expects(self::never())->method('dispatch');

        ($this->handler)(new EnrichSeriesMessage(5));
    }

    /**
     * Teste que le disjoncteur ouvert reporte le message sans appeler l'API.
     */
    public function testHandlerRedispatchesWhenCircuitBreakerOpen(): void
    {
        $series = new ComicSeries();
        $series->setTitle('En attente de quota');
        $series->setType(ComicType::BD);

        $this->comicSeriesRepository->expects(self::once())
            ->method('find')
            ->with(7)
            ->willReturn($series);

        $this->circuitBreaker->method('isOpen')->willReturn(true);
        $this->circuitBreaker->method('retryAfterSeconds')->willReturn(3600);

        $this->lookupOrchestrator->expects(self::never())->method('lookupByTitle');
        $this->enrichmentService->expects(self::never())->method('enrich');
        $this->entityManager->expects(self::never())->method('flush');

        $message = new EnrichSeriesMessage(7);
        $this->messageBus->expects(self::once())
            ->method('dispatch')
            ->with(
                $message,
                self::callback(static fn (array $stamps): bool => 1 === \count($stamps) && $stamps[0] instanceof DelayStamp),
            )
            ->willReturn(new Envelope($message));

        ($this->handler)($message);

        self::assertNull($series->getLookupCompletedAt());
    }

    /**
     * Teste qu'un quota épuisé ouvre le disjoncteur et reporte le message.
     */
    public function testHandlerOpensBreakerAndRedispatchesOnRateLimit(): void
    {
        $series = new ComicSeries();
        $series->setTitle('Quota épuisé');
        $series->setType(ComicType::BD);

        $this->comicSeriesRepository->expects(self::once())
            ->method('find')
            ->with(8)
            ->willReturn($series);

        $this->circuitBreaker->method('isOpen')->willReturn(false);
        $this->circuitBreaker->expects(self::once())->method('open')->willReturn(new \DateTimeImmutable('+1 hour'));
        $this->circuitBreaker->method('retryAfterSeconds')->willReturn(3600);

        $this->lookupOrchestrator->expects(self::once())->method('lookupByTitle')->willReturn(null);
        $this->lookupOrchestrator->method('hasRateLimitError')->willReturn(true);

        $this->enrichmentService->expects(self::never())->method('enrich');
        $this->entityManager->expects(self::never())->method('flush');

        $message = new EnrichSeriesMessage(8);
        $this->messageBus->expects(self::once())
            ->method('dispatch')
            ->willReturn(new Envelope($message));

        ($this->handler)($message);

        self::assertNull($series->getLookupCompletedAt());
    }
}

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
use App\Service\Lookup\LookupOrchestrator;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

/**
 * Tests unitaires pour le handler d'enrichissement asynchrone.
 */
final class EnrichSeriesHandlerTest extends TestCase
{
    private MockObject&ComicSeriesRepository $comicSeriesRepository;
    private MockObject&EntityManagerInterface $entityManager;
    private MockObject&EnrichmentService $enrichmentService;
    private EnrichSeriesHandler $handler;
    private MockObject&LookupOrchestrator $lookupOrchestrator;

    protected function setUp(): void
    {
        $this->comicSeriesRepository = $this->createMock(ComicSeriesRepository::class);
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->enrichmentService = $this->createMock(EnrichmentService::class);
        $this->lookupOrchestrator = $this->createMock(LookupOrchestrator::class);

        $this->handler = new EnrichSeriesHandler(
            $this->comicSeriesRepository,
            $this->entityManager,
            $this->enrichmentService,
            new NullLogger(),
            $this->lookupOrchestrator,
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

        $lookupResult = new LookupResult(description: 'Description', source: 'google');

        $this->lookupOrchestrator->expects(self::once())
            ->method('lookupByTitle')
            ->with('One Piece', ComicType::MANGA)
            ->willReturn($lookupResult);

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

        $this->lookupOrchestrator->expects(self::once())
            ->method('lookupByTitle')
            ->willReturn(null);

        $this->enrichmentService->expects(self::never())->method('enrich');
        $this->entityManager->expects(self::once())->method('flush');

        ($this->handler)(new EnrichSeriesMessage(10));

        self::assertNotNull($series->getLookupCompletedAt());
    }
}

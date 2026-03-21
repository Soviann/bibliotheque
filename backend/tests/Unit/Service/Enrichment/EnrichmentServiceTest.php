<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Enrichment;

use App\Entity\ComicSeries;
use App\Entity\EnrichmentLog;
use App\Entity\EnrichmentProposal;
use App\Enum\ComicType;
use App\Enum\EnrichmentAction;
use App\Enum\EnrichmentConfidence;
use App\Enum\LookupMode;
use App\Enum\ProposalStatus;
use App\Repository\EnrichmentProposalRepository;
use App\Service\Enrichment\ConfidenceScorer;
use App\Service\Enrichment\EnrichmentService;
use App\Service\Lookup\LookupApplier;
use App\Service\Lookup\Contract\LookupResult;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

/**
 * Tests unitaires pour le service d'enrichissement.
 */
final class EnrichmentServiceTest extends TestCase
{
    private Stub&ConfidenceScorer $confidenceScorer;
    private Stub&EntityManagerInterface $entityManager;
    private EnrichmentService $enrichmentService;
    private Stub&EnrichmentProposalRepository $proposalRepository;
    private Stub&LookupApplier $lookupApplier;

    /** @var list<object> */
    private array $persisted = [];

    protected function setUp(): void
    {
        $this->confidenceScorer = $this->createStub(ConfidenceScorer::class);
        $this->entityManager = $this->createStub(EntityManagerInterface::class);
        $this->lookupApplier = $this->createStub(LookupApplier::class);
        $this->proposalRepository = $this->createStub(EnrichmentProposalRepository::class);

        $this->entityManager->method('persist')->willReturnCallback(function (object $entity): void {
            $this->persisted[] = $entity;
        });

        $this->enrichmentService = new EnrichmentService(
            $this->confidenceScorer,
            $this->entityManager,
            $this->lookupApplier,
            new NullLogger(),
            $this->proposalRepository,
        );
    }

    /**
     * Teste que HIGH auto-applique via LookupApplier et crée des logs.
     */
    public function testHighConfidenceAutoApplies(): void
    {
        $series = $this->createSeries();
        $result = new LookupResult(description: 'Une description', publisher: 'Glénat', source: 'google');

        $this->confidenceScorer->method('score')->willReturn(EnrichmentConfidence::HIGH);
        $this->lookupApplier->method('apply')->willReturn(['description', 'publisher']);

        $confidence = $this->enrichmentService->enrich($series, $result, LookupMode::TITLE, ['google']);

        self::assertSame(EnrichmentConfidence::HIGH, $confidence);

        $logs = \array_filter($this->persisted, static fn ($e) => $e instanceof EnrichmentLog);
        self::assertCount(2, $logs);

        /** @var EnrichmentLog $log */
        $log = \array_values($logs)[0];
        self::assertSame(EnrichmentAction::AUTO_APPLIED, $log->getAction());
    }

    /**
     * Teste que MEDIUM crée des propositions pour les champs modifiables.
     */
    public function testMediumConfidenceCreatesProposals(): void
    {
        $series = $this->createSeries();
        $result = new LookupResult(description: 'Nouvelle description', publisher: 'Glénat', source: 'bnf');

        $this->confidenceScorer->method('score')->willReturn(EnrichmentConfidence::MEDIUM);
        $this->proposalRepository->method('findPendingBySeriesAndField')->willReturn(null);

        $confidence = $this->enrichmentService->enrich($series, $result, LookupMode::TITLE, ['bnf']);

        self::assertSame(EnrichmentConfidence::MEDIUM, $confidence);

        $proposals = \array_filter($this->persisted, static fn ($e) => $e instanceof EnrichmentProposal);
        self::assertGreaterThanOrEqual(1, \count($proposals));

        /** @var EnrichmentProposal $proposal */
        $proposal = \array_values($proposals)[0];
        self::assertSame(ProposalStatus::PENDING, $proposal->getStatus());
    }

    /**
     * Teste que MEDIUM ne crée pas de doublon si une proposition existe déjà.
     */
    public function testMediumConfidenceSkipsDuplicateProposal(): void
    {
        $series = $this->createSeries();
        $result = new LookupResult(description: 'Nouvelle description', source: 'bnf');

        $this->confidenceScorer->method('score')->willReturn(EnrichmentConfidence::MEDIUM);

        $existingProposal = $this->createStub(EnrichmentProposal::class);
        $this->proposalRepository->method('findPendingBySeriesAndField')
            ->willReturn($existingProposal);

        $this->enrichmentService->enrich($series, $result, LookupMode::TITLE, ['bnf']);

        $proposals = \array_filter($this->persisted, static fn ($e) => $e instanceof EnrichmentProposal);
        self::assertCount(0, $proposals);
    }

    /**
     * Teste que LOW crée un log SKIPPED.
     */
    public function testLowConfidenceLogsSkip(): void
    {
        $series = $this->createSeries();
        $result = new LookupResult(description: 'Description', source: 'google');

        $this->confidenceScorer->method('score')->willReturn(EnrichmentConfidence::LOW);

        $confidence = $this->enrichmentService->enrich($series, $result, LookupMode::TITLE, ['google']);

        self::assertSame(EnrichmentConfidence::LOW, $confidence);

        $logs = \array_filter($this->persisted, static fn ($e) => $e instanceof EnrichmentLog);
        self::assertCount(1, $logs);

        /** @var EnrichmentLog $log */
        $log = \array_values($logs)[0];
        self::assertSame(EnrichmentAction::SKIPPED, $log->getAction());
    }

    private function createSeries(): ComicSeries
    {
        $series = new ComicSeries();
        $series->setTitle('One Piece');
        $series->setType(ComicType::MANGA);

        return $series;
    }
}

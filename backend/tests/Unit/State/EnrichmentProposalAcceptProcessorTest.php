<?php

declare(strict_types=1);

namespace App\Tests\Unit\State;

use ApiPlatform\Metadata\Patch;
use App\Entity\ComicSeries;
use App\Entity\EnrichmentLog;
use App\Entity\EnrichmentProposal;
use App\Enum\ComicType;
use App\Enum\EnrichableField;
use App\Enum\EnrichmentConfidence;
use App\Enum\ProposalStatus;
use App\Repository\AuthorRepository;
use App\Service\CoverDownloader;
use App\Service\Enrichment\EnrichmentService;
use App\State\EnrichmentProposalAcceptProcessor;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * Tests unitaires pour le processor d'acceptation de proposition.
 */
final class EnrichmentProposalAcceptProcessorTest extends TestCase
{
    private Stub&AuthorRepository $authorRepository;
    private Stub&CoverDownloader $coverDownloader;
    private Stub&EntityManagerInterface $entityManager;

    /** @var list<object> */
    private array $persisted = [];

    private EnrichmentProposalAcceptProcessor $processor;

    protected function setUp(): void
    {
        $this->authorRepository = $this->createStub(AuthorRepository::class);
        $this->coverDownloader = $this->createStub(CoverDownloader::class);
        $enrichmentService = $this->createStub(EnrichmentService::class);
        $enrichmentService->method('getSeriesValue')->willReturn(null);
        $this->entityManager = $this->createStub(EntityManagerInterface::class);

        $this->entityManager->method('persist')->willReturnCallback(function (object $entity): void {
            $this->persisted[] = $entity;
        });

        $this->processor = new EnrichmentProposalAcceptProcessor(
            $this->authorRepository,
            $this->coverDownloader,
            $enrichmentService,
            $this->entityManager,
        );
    }

    /**
     * Teste l'acceptation d'une proposition de description.
     */
    public function testAcceptDescriptionProposal(): void
    {
        $series = $this->createSeries();
        $proposal = new EnrichmentProposal(
            comicSeries: $series,
            confidence: EnrichmentConfidence::MEDIUM,
            currentValue: null,
            field: EnrichableField::DESCRIPTION,
            proposedValue: 'Nouvelle description',
            source: 'google',
        );

        $result = $this->processor->process($proposal, new Patch());

        self::assertSame(ProposalStatus::ACCEPTED, $result->getStatus());
        self::assertNotNull($result->getReviewedAt());
        self::assertSame('Nouvelle description', $series->getDescription());

        $logs = \array_filter($this->persisted, static fn ($e) => $e instanceof EnrichmentLog);
        self::assertCount(1, $logs);
    }

    /**
     * Teste qu'une proposition non-PENDING lève une exception.
     */
    public function testRejectsNonPendingProposal(): void
    {
        $series = $this->createSeries();
        $proposal = new EnrichmentProposal(
            comicSeries: $series,
            confidence: EnrichmentConfidence::MEDIUM,
            currentValue: null,
            field: EnrichableField::DESCRIPTION,
            proposedValue: 'desc',
            source: 'google',
        );
        $proposal->reject(); // status = REJECTED

        $this->expectException(BadRequestHttpException::class);
        $this->processor->process($proposal, new Patch());
    }

    private function createSeries(): ComicSeries
    {
        $series = new ComicSeries();
        $series->setTitle('Test');
        $series->setType(ComicType::BD);

        return $series;
    }
}

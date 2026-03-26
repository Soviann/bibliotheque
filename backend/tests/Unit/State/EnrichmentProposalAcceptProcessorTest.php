<?php

declare(strict_types=1);

namespace App\Tests\Unit\State;

use ApiPlatform\Metadata\Patch;
use App\Entity\ComicSeries;
use App\Entity\EnrichmentProposal;
use App\Enum\ComicType;
use App\Enum\EnrichableField;
use App\Enum\EnrichmentConfidence;
use App\Enum\ProposalStatus;
use App\Service\Enrichment\EnrichmentService;
use App\State\EnrichmentProposalAcceptProcessor;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * Tests unitaires pour le processor d'acceptation de proposition.
 */
final class EnrichmentProposalAcceptProcessorTest extends TestCase
{
    private Stub&EntityManagerInterface $entityManager;
    private MockObject&EnrichmentService $enrichmentService;
    private EnrichmentProposalAcceptProcessor $processor;

    protected function setUp(): void
    {
        $this->enrichmentService = $this->createMock(EnrichmentService::class);
        $this->enrichmentService->method('getSeriesValue')->willReturn(null);
        $this->entityManager = $this->createStub(EntityManagerInterface::class);

        $this->processor = new EnrichmentProposalAcceptProcessor(
            $this->enrichmentService,
            $this->entityManager,
        );
    }

    /**
     * Teste l'acceptation d'une proposition PENDING de description.
     */
    public function testAcceptPendingProposal(): void
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

        $this->enrichmentService->expects(self::once())
            ->method('applyFieldValue')
            ->with($series, EnrichableField::DESCRIPTION, 'Nouvelle description');

        $result = $this->processor->process($proposal, new Patch());

        self::assertSame(ProposalStatus::ACCEPTED, $result->getStatus());
        self::assertNotNull($result->getReviewedAt());
    }

    /**
     * Teste l'acceptation d'une proposition PRE_ACCEPTED (pas d'application, juste confirmation).
     */
    public function testAcceptPreAcceptedProposalSkipsApply(): void
    {
        $series = $this->createSeries();
        $proposal = new EnrichmentProposal(
            comicSeries: $series,
            confidence: EnrichmentConfidence::HIGH,
            currentValue: null,
            field: EnrichableField::DESCRIPTION,
            proposedValue: 'Description auto-appliquée',
            source: 'google',
        );
        $proposal->preAccept();

        $this->enrichmentService->expects(self::never())
            ->method('applyFieldValue');

        $result = $this->processor->process($proposal, new Patch());

        self::assertSame(ProposalStatus::ACCEPTED, $result->getStatus());
    }

    /**
     * Teste qu'une proposition REJECTED lève une exception.
     */
    public function testRejectsInvalidStatus(): void
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
        $proposal->reject();

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

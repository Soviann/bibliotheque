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
use App\State\EnrichmentProposalRejectProcessor;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * Tests unitaires pour le processor de rejet de proposition.
 */
final class EnrichmentProposalRejectProcessorTest extends TestCase
{
    private MockObject&EnrichmentService $enrichmentService;
    private Stub&EntityManagerInterface $entityManager;
    private EnrichmentProposalRejectProcessor $processor;

    protected function setUp(): void
    {
        $this->enrichmentService = $this->createMock(EnrichmentService::class);
        $this->entityManager = $this->createStub(EntityManagerInterface::class);

        $this->processor = new EnrichmentProposalRejectProcessor(
            $this->enrichmentService,
            $this->entityManager,
        );
    }

    /**
     * Teste le rejet d'une proposition PENDING.
     */
    public function testRejectPendingProposal(): void
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

        $this->enrichmentService->expects(self::never())
            ->method('revertFieldValue');

        $result = $this->processor->process($proposal, new Patch());

        self::assertSame(ProposalStatus::REJECTED, $result->getStatus());
        self::assertNotNull($result->getReviewedAt());
    }

    /**
     * Teste le rejet d'une proposition PRE_ACCEPTED (revert de la valeur).
     */
    public function testRejectPreAcceptedProposalRevertsValue(): void
    {
        $series = $this->createSeries();
        $series->setDescription('Description auto-appliquée');
        $proposal = new EnrichmentProposal(
            comicSeries: $series,
            confidence: EnrichmentConfidence::HIGH,
            currentValue: 'Ancienne description',
            field: EnrichableField::DESCRIPTION,
            proposedValue: 'Description auto-appliquée',
            source: 'google',
        );
        $proposal->preAccept();

        $this->enrichmentService->expects(self::once())
            ->method('revertFieldValue')
            ->with($series, EnrichableField::DESCRIPTION, 'Ancienne description');

        $result = $this->processor->process($proposal, new Patch());

        self::assertSame(ProposalStatus::REJECTED, $result->getStatus());
    }

    /**
     * Teste qu'une proposition ACCEPTED lève une exception.
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
        $proposal->accept();

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

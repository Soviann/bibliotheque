<?php

declare(strict_types=1);

namespace App\Tests\Unit\State;

use ApiPlatform\Metadata\Patch;
use App\Entity\ComicSeries;
use App\Entity\EnrichmentLog;
use App\Entity\EnrichmentProposal;
use App\Enum\ComicType;
use App\Enum\EnrichableField;
use App\Enum\EnrichmentAction;
use App\Enum\EnrichmentConfidence;
use App\Enum\ProposalStatus;
use App\State\EnrichmentProposalRejectProcessor;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * Tests unitaires pour le processor de rejet de proposition.
 */
final class EnrichmentProposalRejectProcessorTest extends TestCase
{
    private Stub&EntityManagerInterface $entityManager;

    /** @var list<object> */
    private array $persisted = [];

    private EnrichmentProposalRejectProcessor $processor;

    protected function setUp(): void
    {
        $this->entityManager = $this->createStub(EntityManagerInterface::class);

        $this->entityManager->method('persist')->willReturnCallback(function (object $entity): void {
            $this->persisted[] = $entity;
        });

        $this->processor = new EnrichmentProposalRejectProcessor($this->entityManager);
    }

    /**
     * Teste le rejet d'une proposition.
     */
    public function testRejectProposal(): void
    {
        $series = new ComicSeries();
        $series->setTitle('Test');
        $series->setType(ComicType::BD);

        $proposal = new EnrichmentProposal(
            comicSeries: $series,
            confidence: EnrichmentConfidence::MEDIUM,
            currentValue: null,
            field: EnrichableField::DESCRIPTION,
            proposedValue: 'desc',
            source: 'google',
        );

        $result = $this->processor->process($proposal, new Patch());

        self::assertSame(ProposalStatus::REJECTED, $result->getStatus());
        self::assertNotNull($result->getReviewedAt());

        $logs = \array_filter($this->persisted, static fn ($e) => $e instanceof EnrichmentLog);
        self::assertCount(1, $logs);

        /** @var EnrichmentLog $log */
        $log = \array_values($logs)[0];
        self::assertSame(EnrichmentAction::REJECTED, $log->getAction());
    }

    /**
     * Teste qu'une proposition non-PENDING lève une exception.
     */
    public function testRejectsNonPendingProposal(): void
    {
        $series = new ComicSeries();
        $series->setTitle('Test');
        $series->setType(ComicType::BD);

        $proposal = new EnrichmentProposal(
            comicSeries: $series,
            confidence: EnrichmentConfidence::MEDIUM,
            currentValue: null,
            field: EnrichableField::DESCRIPTION,
            proposedValue: 'desc',
            source: 'google',
        );
        $proposal->accept(); // status = ACCEPTED

        $this->expectException(BadRequestHttpException::class);
        $this->processor->process($proposal, new Patch());
    }
}

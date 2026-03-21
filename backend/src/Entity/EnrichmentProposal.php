<?php

declare(strict_types=1);

namespace App\Entity;

use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use App\Enum\EnrichableField;
use App\Enum\EnrichmentConfidence;
use App\Enum\ProposalStatus;
use App\Repository\EnrichmentProposalRepository;
use App\State\EnrichmentProposalAcceptProcessor;
use App\State\EnrichmentProposalRejectProcessor;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;

/**
 * Proposition d'enrichissement en attente de validation.
 */
#[ApiResource(
    operations: [
        new GetCollection(
            paginationEnabled: false,
            order: ['createdAt' => 'DESC'],
            normalizationContext: ['groups' => ['enrichment:read']],
        ),
        new Get(normalizationContext: ['groups' => ['enrichment:read']]),
        new Patch(
            uriTemplate: '/enrichment_proposals/{id}/accept',
            input: false,
            processor: EnrichmentProposalAcceptProcessor::class,
        ),
        new Patch(
            uriTemplate: '/enrichment_proposals/{id}/reject',
            input: false,
            processor: EnrichmentProposalRejectProcessor::class,
        ),
    ],
)]
#[ApiFilter(SearchFilter::class, properties: ['status' => 'exact'])]
#[ORM\Entity(repositoryClass: EnrichmentProposalRepository::class)]
#[ORM\Index(name: 'idx_enrichment_proposal_status', columns: ['status'])]
#[ORM\UniqueConstraint(
    name: 'uniq_proposal_series_field_pending',
    columns: ['comic_series_id', 'field', 'status'],
)]
class EnrichmentProposal
{
    #[ORM\ManyToOne(targetEntity: ComicSeries::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    #[Groups(['enrichment:read'])]
    private ComicSeries $comicSeries;

    #[ORM\Column(type: Types::STRING, enumType: EnrichmentConfidence::class)]
    #[Groups(['enrichment:read'])]
    private EnrichmentConfidence $confidence;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    #[Groups(['enrichment:read'])]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    #[Groups(['enrichment:read'])]
    private mixed $currentValue = null;

    #[ORM\Column(type: Types::STRING, enumType: EnrichableField::class)]
    #[Groups(['enrichment:read'])]
    private EnrichableField $field;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['enrichment:read'])]
    private ?int $id = null;

    #[ORM\Column(type: Types::JSON)]
    #[Groups(['enrichment:read'])]
    private mixed $proposedValue = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    #[Groups(['enrichment:read'])]
    private ?\DateTimeImmutable $reviewedAt = null;

    #[ORM\Column(length: 100)]
    #[Groups(['enrichment:read'])]
    private string $source;

    #[ORM\Column(type: Types::STRING, enumType: ProposalStatus::class)]
    #[Groups(['enrichment:read'])]
    private ProposalStatus $status;

    public function __construct(
        ComicSeries $comicSeries,
        EnrichmentConfidence $confidence,
        mixed $currentValue,
        EnrichableField $field,
        mixed $proposedValue,
        string $source,
    ) {
        $this->comicSeries = $comicSeries;
        $this->confidence = $confidence;
        $this->createdAt = new \DateTimeImmutable();
        $this->currentValue = $currentValue;
        $this->field = $field;
        $this->proposedValue = $proposedValue;
        $this->source = $source;
        $this->status = ProposalStatus::PENDING;
    }

    public function getComicSeries(): ComicSeries
    {
        return $this->comicSeries;
    }

    public function getConfidence(): EnrichmentConfidence
    {
        return $this->confidence;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getCurrentValue(): mixed
    {
        return $this->currentValue;
    }

    public function getField(): EnrichableField
    {
        return $this->field;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getProposedValue(): mixed
    {
        return $this->proposedValue;
    }

    public function getReviewedAt(): ?\DateTimeImmutable
    {
        return $this->reviewedAt;
    }

    public function getSource(): string
    {
        return $this->source;
    }

    public function getStatus(): ProposalStatus
    {
        return $this->status;
    }

    public function accept(): void
    {
        $this->reviewedAt = new \DateTimeImmutable();
        $this->status = ProposalStatus::ACCEPTED;
    }

    public function reject(): void
    {
        $this->reviewedAt = new \DateTimeImmutable();
        $this->status = ProposalStatus::REJECTED;
    }
}

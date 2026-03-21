<?php

declare(strict_types=1);

namespace App\Entity;

use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\GetCollection;
use App\Enum\EnrichableField;
use App\Enum\EnrichmentAction;
use App\Enum\EnrichmentConfidence;
use App\Repository\EnrichmentLogRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;

/**
 * Trace d'audit d'un enrichissement (appliqué, accepté, rejeté ou ignoré).
 */
#[ApiResource(
    operations: [
        new GetCollection(
            paginationEnabled: false,
            order: ['createdAt' => 'DESC'],
            normalizationContext: ['groups' => ['enrichment-log:read']],
        ),
    ],
)]
#[ApiFilter(SearchFilter::class, properties: ['comicSeries' => 'exact'])]
#[ORM\Entity(repositoryClass: EnrichmentLogRepository::class)]
#[ORM\Index(name: 'idx_enrichment_log_series', columns: ['comic_series_id'])]
class EnrichmentLog
{
    #[ORM\Column(type: Types::STRING, enumType: EnrichmentAction::class)]
    #[Groups(['enrichment-log:read'])]
    private EnrichmentAction $action;

    #[ORM\ManyToOne(targetEntity: ComicSeries::class)]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['enrichment-log:read'])]
    private ComicSeries $comicSeries;

    #[ORM\Column(type: Types::STRING, enumType: EnrichmentConfidence::class)]
    #[Groups(['enrichment-log:read'])]
    private EnrichmentConfidence $confidence;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    #[Groups(['enrichment-log:read'])]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: Types::STRING, enumType: EnrichableField::class)]
    #[Groups(['enrichment-log:read'])]
    private EnrichableField $field;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['enrichment-log:read'])]
    private ?int $id = null;

    #[ORM\Column(type: Types::JSON)]
    #[Groups(['enrichment-log:read'])]
    private mixed $newValue = null;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    #[Groups(['enrichment-log:read'])]
    private mixed $oldValue = null;

    #[ORM\Column(length: 100)]
    #[Groups(['enrichment-log:read'])]
    private string $source;

    public function __construct(
        EnrichmentAction $action,
        ComicSeries $comicSeries,
        EnrichmentConfidence $confidence,
        EnrichableField $field,
        mixed $newValue,
        mixed $oldValue,
        string $source,
    ) {
        $this->action = $action;
        $this->comicSeries = $comicSeries;
        $this->confidence = $confidence;
        $this->createdAt = new \DateTimeImmutable();
        $this->field = $field;
        $this->newValue = $newValue;
        $this->oldValue = $oldValue;
        $this->source = $source;
    }

    public function getAction(): EnrichmentAction
    {
        return $this->action;
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

    public function getField(): EnrichableField
    {
        return $this->field;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getNewValue(): mixed
    {
        return $this->newValue;
    }

    public function getOldValue(): mixed
    {
        return $this->oldValue;
    }

    public function getSource(): string
    {
        return $this->source;
    }
}

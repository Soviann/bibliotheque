<?php

declare(strict_types=1);

namespace App\Entity;

use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use App\Enum\ComicType;
use App\Enum\SuggestionStatus;
use App\Repository\SeriesSuggestionRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;

/**
 * Suggestion de série similaire générée par IA.
 */
#[ApiResource(
    operations: [
        new GetCollection(
            paginationEnabled: false,
            order: ['createdAt' => 'DESC'],
            normalizationContext: ['groups' => ['suggestion:read']],
        ),
        new Patch(denormalizationContext: ['groups' => ['suggestion:write']]),
    ],
)]
#[ApiFilter(SearchFilter::class, properties: ['status' => 'exact'])]
#[ORM\Entity(repositoryClass: SeriesSuggestionRepository::class)]
#[ORM\Index(name: 'idx_suggestion_status', columns: ['status'])]
class SeriesSuggestion
{
    /** @var list<string> */
    #[ORM\Column(type: Types::JSON)]
    #[Groups(['suggestion:read'])]
    private array $authors;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    #[Groups(['suggestion:read'])]
    private \DateTimeImmutable $createdAt;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['suggestion:read'])]
    private ?int $id = null;

    #[ORM\Column(type: Types::TEXT)]
    #[Groups(['suggestion:read'])]
    private string $reason;

    #[ORM\ManyToOne(targetEntity: ComicSeries::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    #[Groups(['suggestion:read'])]
    private ?ComicSeries $sourceSeries;

    #[ORM\Column(type: Types::STRING, enumType: SuggestionStatus::class)]
    #[Groups(['suggestion:read', 'suggestion:write'])]
    private SuggestionStatus $status;

    #[ORM\Column(length: 255)]
    #[Groups(['suggestion:read'])]
    private string $title;

    #[ORM\Column(type: Types::STRING, enumType: ComicType::class)]
    #[Groups(['suggestion:read'])]
    private ComicType $type;

    /**
     * @param list<string> $authors
     */
    public function __construct(
        array $authors,
        string $reason,
        ?ComicSeries $sourceSeries,
        string $title,
        ComicType $type,
    ) {
        $this->authors = $authors;
        $this->createdAt = new \DateTimeImmutable();
        $this->reason = $reason;
        $this->sourceSeries = $sourceSeries;
        $this->status = SuggestionStatus::PENDING;
        $this->title = $title;
        $this->type = $type;
    }

    /** @return list<string> */
    public function getAuthors(): array
    {
        return $this->authors;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getReason(): string
    {
        return $this->reason;
    }

    public function getSourceSeries(): ?ComicSeries
    {
        return $this->sourceSeries;
    }

    public function getStatus(): SuggestionStatus
    {
        return $this->status;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function getType(): ComicType
    {
        return $this->type;
    }

    public function setStatus(SuggestionStatus $status): void
    {
        $this->status = $status;
    }
}

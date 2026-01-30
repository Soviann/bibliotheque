<?php

namespace App\Entity;

use App\Enum\ComicStatus;
use App\Enum\ComicType;
use App\Repository\ComicSeriesRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: ComicSeriesRepository::class)]
#[ORM\HasLifecycleCallbacks]
class ComicSeries
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank]
    private ?string $title = null;

    #[ORM\Column(type: Types::STRING, length: 20, enumType: ComicStatus::class)]
    private ComicStatus $status = ComicStatus::BUYING;

    #[ORM\Column(type: Types::STRING, length: 20, enumType: ComicType::class)]
    private ComicType $type = ComicType::BD;

    #[ORM\Column(nullable: true)]
    #[Assert\PositiveOrZero]
    private ?int $currentIssue = null;

    #[ORM\Column]
    private bool $currentIssueComplete = false;

    #[ORM\Column(nullable: true)]
    #[Assert\PositiveOrZero]
    private ?int $lastBought = null;

    #[ORM\Column]
    private bool $lastBoughtComplete = false;

    #[ORM\Column(nullable: true)]
    #[Assert\PositiveOrZero]
    private ?int $lastDownloaded = null;

    #[ORM\Column]
    private bool $lastDownloadedComplete = false;

    #[ORM\Column(nullable: true)]
    #[Assert\PositiveOrZero]
    private ?int $publishedCount = null;

    #[ORM\Column]
    private bool $publishedCountComplete = false;

    #[ORM\Column]
    private bool $onNas = false;

    #[ORM\Column]
    private bool $isWishlist = false;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $missingIssues = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $ownedIssues = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column]
    private \DateTimeImmutable $updatedAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
    }

    #[ORM\PreUpdate]
    public function preUpdate(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(string $title): static
    {
        $this->title = $title;

        return $this;
    }

    public function getStatus(): ComicStatus
    {
        return $this->status;
    }

    public function setStatus(ComicStatus $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function getType(): ComicType
    {
        return $this->type;
    }

    public function setType(ComicType $type): static
    {
        $this->type = $type;

        return $this;
    }

    public function getCurrentIssue(): ?int
    {
        return $this->currentIssue;
    }

    public function setCurrentIssue(?int $currentIssue): static
    {
        $this->currentIssue = $currentIssue;

        return $this;
    }

    public function isCurrentIssueComplete(): bool
    {
        return $this->currentIssueComplete;
    }

    public function setCurrentIssueComplete(bool $currentIssueComplete): static
    {
        $this->currentIssueComplete = $currentIssueComplete;

        return $this;
    }

    public function getLastBought(): ?int
    {
        return $this->lastBought;
    }

    public function setLastBought(?int $lastBought): static
    {
        $this->lastBought = $lastBought;

        return $this;
    }

    public function isLastBoughtComplete(): bool
    {
        return $this->lastBoughtComplete;
    }

    public function setLastBoughtComplete(bool $lastBoughtComplete): static
    {
        $this->lastBoughtComplete = $lastBoughtComplete;

        return $this;
    }

    public function getLastDownloaded(): ?int
    {
        return $this->lastDownloaded;
    }

    public function setLastDownloaded(?int $lastDownloaded): static
    {
        $this->lastDownloaded = $lastDownloaded;

        return $this;
    }

    public function isLastDownloadedComplete(): bool
    {
        return $this->lastDownloadedComplete;
    }

    public function setLastDownloadedComplete(bool $lastDownloadedComplete): static
    {
        $this->lastDownloadedComplete = $lastDownloadedComplete;

        return $this;
    }

    public function getPublishedCount(): ?int
    {
        return $this->publishedCount;
    }

    public function setPublishedCount(?int $publishedCount): static
    {
        $this->publishedCount = $publishedCount;

        return $this;
    }

    public function isPublishedCountComplete(): bool
    {
        return $this->publishedCountComplete;
    }

    public function setPublishedCountComplete(bool $publishedCountComplete): static
    {
        $this->publishedCountComplete = $publishedCountComplete;

        return $this;
    }

    public function isOnNas(): bool
    {
        return $this->onNas;
    }

    public function setOnNas(bool $onNas): static
    {
        $this->onNas = $onNas;

        return $this;
    }

    public function isWishlist(): bool
    {
        return $this->isWishlist;
    }

    public function setIsWishlist(bool $isWishlist): static
    {
        $this->isWishlist = $isWishlist;

        return $this;
    }

    public function getMissingIssues(): ?string
    {
        return $this->missingIssues;
    }

    public function setMissingIssues(?string $missingIssues): static
    {
        $this->missingIssues = $missingIssues;

        return $this;
    }

    public function getOwnedIssues(): ?string
    {
        return $this->ownedIssues;
    }

    public function setOwnedIssues(?string $ownedIssues): static
    {
        $this->ownedIssues = $ownedIssues;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(\DateTimeImmutable $updatedAt): static
    {
        $this->updatedAt = $updatedAt;

        return $this;
    }
}

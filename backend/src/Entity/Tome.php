<?php

declare(strict_types=1);

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Link;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use App\Repository\TomeRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Représente un tome individuel d'une série.
 */
#[ApiResource(
    operations: [
        new Get(),
        new Patch(denormalizationContext: ['groups' => ['tome:write']]),
        new Put(denormalizationContext: ['groups' => ['tome:write']]),
        new Delete(),
    ],
    normalizationContext: ['groups' => ['tome:read']],
)]
#[ApiResource(
    uriTemplate: '/comic_series/{comicSeriesId}/tomes',
    operations: [
        new GetCollection(
            paginationEnabled: false,
            order: ['number' => 'ASC'],
        ),
        new Post(
            denormalizationContext: ['groups' => ['tome:write']],
            read: false,
        ),
    ],
    uriVariables: [
        'comicSeriesId' => new Link(toProperty: 'comicSeries', fromClass: ComicSeries::class),
    ],
    normalizationContext: ['groups' => ['tome:read']],
)]
#[ORM\Entity(repositoryClass: TomeRepository::class)]
#[ORM\HasLifecycleCallbacks]
#[ORM\Index(name: 'idx_tome_isbn', columns: ['isbn'])]
#[ORM\Index(name: 'idx_tome_on_nas', columns: ['on_nas'])]
#[ORM\Index(name: 'idx_tome_series_number', columns: ['comic_series_id', 'number'])]
class Tome
{
    #[Groups(['tome:read', 'comic:read'])]
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    /**
     * Indique si le tome a été acheté.
     */
    #[Groups(['comic:read', 'comic:write', 'tome:read', 'tome:write'])]
    #[ORM\Column]
    private bool $bought = false;

    /**
     * Série à laquelle appartient ce tome.
     */
    #[Groups(['tome:write'])]
    #[ORM\ManyToOne(targetEntity: ComicSeries::class, inversedBy: 'tomes')]
    #[ORM\JoinColumn(nullable: false)]
    private ?ComicSeries $comicSeries = null;

    #[Groups(['tome:read'])]
    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    /**
     * Indique si le tome a été téléchargé.
     */
    #[Groups(['comic:read', 'comic:write', 'tome:read', 'tome:write'])]
    #[ORM\Column]
    private bool $downloaded = false;

    /**
     * Numéro ISBN du tome.
     */
    #[Groups(['comic:read', 'comic:write', 'tome:read', 'tome:write'])]
    #[ORM\Column(length: 20, nullable: true)]
    private ?string $isbn = null;

    /**
     * Numéro du tome dans la série.
     */
    #[Groups(['comic:read', 'comic:write', 'tome:read', 'tome:write'])]
    #[ORM\Column]
    #[Assert\NotNull]
    #[Assert\PositiveOrZero]
    private int $number = 0;

    /**
     * Indique si le tome est présent sur le NAS.
     */
    #[Groups(['comic:read', 'comic:write', 'tome:read', 'tome:write'])]
    #[ORM\Column]
    private bool $onNas = false;

    /**
     * Indique si le tome a été lu.
     */
    #[Groups(['comic:read', 'comic:write', 'tome:read', 'tome:write'])]
    #[ORM\Column(name: '`read`')]
    private bool $read = false;

    /**
     * Numéro de fin pour les tomes multi-numéros (intégrales).
     * Ex : number=4, tomeEnd=6 → « Tome 4-6 ».
     */
    #[Groups(['comic:read', 'comic:write', 'tome:read', 'tome:write'])]
    #[ORM\Column(nullable: true)]
    #[Assert\GreaterThanOrEqual(propertyPath: 'number')]
    private ?int $tomeEnd = null;

    /**
     * Titre spécifique du tome (optionnel).
     */
    #[Groups(['comic:read', 'comic:write', 'tome:read', 'tome:write'])]
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $title = null;

    #[Groups(['tome:read'])]
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

    public function isBought(): bool
    {
        return $this->bought;
    }

    public function setBought(bool $bought): static
    {
        $this->bought = $bought;

        return $this;
    }

    public function getComicSeries(): ?ComicSeries
    {
        return $this->comicSeries;
    }

    public function setComicSeries(?ComicSeries $comicSeries): static
    {
        $this->comicSeries = $comicSeries;

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

    public function isDownloaded(): bool
    {
        return $this->downloaded;
    }

    public function setDownloaded(bool $downloaded): static
    {
        $this->downloaded = $downloaded;

        return $this;
    }

    public function getIsbn(): ?string
    {
        return $this->isbn;
    }

    public function setIsbn(?string $isbn): static
    {
        $this->isbn = $isbn;

        return $this;
    }

    public function getNumber(): int
    {
        return $this->number;
    }

    public function setNumber(int $number): static
    {
        $this->number = $number;

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

    public function isRead(): bool
    {
        return $this->read;
    }

    public function setRead(bool $read): static
    {
        $this->read = $read;

        return $this;
    }

    public function getTomeEnd(): ?int
    {
        return $this->tomeEnd;
    }

    public function setTomeEnd(?int $tomeEnd): static
    {
        $this->tomeEnd = $tomeEnd;

        return $this;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(?string $title): static
    {
        $this->title = $title;

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

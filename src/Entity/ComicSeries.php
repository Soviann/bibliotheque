<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\ComicStatus;
use App\Enum\ComicType;
use App\Repository\ComicSeriesRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\Validator\Constraints as Assert;
use Vich\UploaderBundle\Mapping\Annotation as Vich;

#[ORM\Entity(repositoryClass: ComicSeriesRepository::class)]
#[ORM\HasLifecycleCallbacks]
#[Vich\Uploadable]
class ComicSeries
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank]
    private string $title = '';

    #[ORM\Column(type: Types::STRING, length: 20, enumType: ComicStatus::class)]
    private ComicStatus $status = ComicStatus::BUYING;

    #[ORM\Column(type: Types::STRING, length: 20, enumType: ComicType::class)]
    private ComicType $type = ComicType::BD;

    /**
     * Dernier tome paru (numéro du dernier tome publié).
     */
    #[ORM\Column(nullable: true)]
    #[Assert\PositiveOrZero]
    private ?int $latestPublishedIssue = null;

    /**
     * Indique si la série est terminée (plus aucun tome à paraître).
     */
    #[ORM\Column]
    private bool $latestPublishedIssueComplete = false;

    /**
     * Indique si c'est un one-shot (tome unique, intégrale).
     */
    #[ORM\Column]
    private bool $isOneShot = false;

    /**
     * Auteur(s) de la série.
     *
     * @var Collection<int, Author>
     */
    #[ORM\ManyToMany(targetEntity: Author::class, inversedBy: 'comicSeries')]
    #[ORM\JoinTable(name: 'comic_series_author')]
    private Collection $authors;

    /**
     * Fichier image uploadé pour la couverture.
     */
    #[Vich\UploadableField(mapping: 'comic_covers', fileNameProperty: 'coverImage')]
    #[Assert\File(
        maxSize: '5M',
        mimeTypes: ['image/jpeg', 'image/png', 'image/gif', 'image/webp'],
        mimeTypesMessage: 'Veuillez télécharger une image valide (JPEG, PNG, GIF ou WebP).'
    )]
    private ?File $coverFile = null;

    /**
     * Nom du fichier de couverture uploadé.
     */
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $coverImage = null;

    /**
     * URL de la couverture.
     */
    #[ORM\Column(length: 500, nullable: true)]
    private ?string $coverUrl = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    /**
     * Description ou résumé.
     */
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    /**
     * Date de publication.
     */
    #[ORM\Column(length: 50, nullable: true)]
    private ?string $publishedDate = null;

    /**
     * Éditeur.
     */
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $publisher = null;

    /**
     * Tomes de la série.
     *
     * @var Collection<int, Tome>
     */
    #[ORM\OneToMany(targetEntity: Tome::class, mappedBy: 'comicSeries', cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['number' => 'ASC'])]
    private Collection $tomes;

    #[ORM\Column]
    private \DateTimeImmutable $updatedAt;

    public function __construct()
    {
        $this->authors = new ArrayCollection();
        $this->createdAt = new \DateTimeImmutable();
        $this->tomes = new ArrayCollection();
        $this->updatedAt = new \DateTimeImmutable();
    }

    #[ORM\PreUpdate]
    public function preUpdate(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    /**
     * @return Collection<int, Author>
     */
    public function getAuthors(): Collection
    {
        return $this->authors;
    }

    public function addAuthor(Author $author): static
    {
        if (!$this->authors->contains($author)) {
            $this->authors->add($author);
        }

        return $this;
    }

    public function removeAuthor(Author $author): static
    {
        $this->authors->removeElement($author);

        return $this;
    }

    /**
     * Retourne les noms des auteurs sous forme de chaîne.
     */
    public function getAuthorsAsString(): string
    {
        return \implode(', ', $this->authors->map(static fn (Author $a) => $a->getName())->toArray());
    }

    public function getCoverFile(): ?File
    {
        return $this->coverFile;
    }

    /**
     * Définit le fichier de couverture et met à jour updatedAt pour déclencher Doctrine.
     */
    public function setCoverFile(?File $coverFile = null): static
    {
        $this->coverFile = $coverFile;

        if (null !== $coverFile) {
            // Met à jour updatedAt pour que Doctrine détecte le changement
            $this->updatedAt = new \DateTimeImmutable();
        }

        return $this;
    }

    public function getCoverImage(): ?string
    {
        return $this->coverImage;
    }

    public function setCoverImage(?string $coverImage): static
    {
        $this->coverImage = $coverImage;

        return $this;
    }

    public function getCoverUrl(): ?string
    {
        return $this->coverUrl;
    }

    public function setCoverUrl(?string $coverUrl): static
    {
        $this->coverUrl = $coverUrl;

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

    /**
     * Retourne le dernier numéro de tome possédé (maximum des tomes de la collection).
     */
    public function getCurrentIssue(): ?int
    {
        if ($this->tomes->isEmpty()) {
            return null;
        }

        $numbers = $this->tomes->map(static fn (Tome $t) => $t->getNumber())->toArray();

        return [] === $numbers ? null : \max($numbers);
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description;

        return $this;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    /**
     * Retourne le dernier numéro de tome acheté.
     */
    public function getLastBought(): ?int
    {
        $boughtTomes = $this->tomes->filter(static fn (Tome $t) => $t->isBought());

        if ($boughtTomes->isEmpty()) {
            return null;
        }

        $numbers = $boughtTomes->map(static fn (Tome $t) => $t->getNumber())->toArray();

        return [] === $numbers ? null : \max($numbers);
    }

    /**
     * Retourne le dernier numéro de tome téléchargé.
     */
    public function getLastDownloaded(): ?int
    {
        $downloadedTomes = $this->tomes->filter(static fn (Tome $t) => $t->isDownloaded());

        if ($downloadedTomes->isEmpty()) {
            return null;
        }

        $numbers = $downloadedTomes->map(static fn (Tome $t) => $t->getNumber())->toArray();

        return [] === $numbers ? null : \max($numbers);
    }

    public function getLatestPublishedIssue(): ?int
    {
        return $this->latestPublishedIssue;
    }

    public function setLatestPublishedIssue(?int $latestPublishedIssue): static
    {
        $this->latestPublishedIssue = $latestPublishedIssue;

        return $this;
    }

    /**
     * Retourne les numéros des tomes manquants (entre 1 et latestPublishedIssue).
     *
     * @return int[]
     */
    public function getMissingTomesNumbers(): array
    {
        if (null === $this->latestPublishedIssue || $this->latestPublishedIssue <= 0) {
            return [];
        }

        $ownedNumbers = $this->getOwnedTomesNumbers();
        $allNumbers = \range(1, $this->latestPublishedIssue);

        return \array_values(\array_diff($allNumbers, $ownedNumbers));
    }

    /**
     * Retourne les numéros des tomes possédés.
     *
     * @return int[]
     */
    public function getOwnedTomesNumbers(): array
    {
        return $this->tomes->map(static fn (Tome $t) => $t->getNumber())->toArray();
    }

    public function getPublishedDate(): ?string
    {
        return $this->publishedDate;
    }

    public function setPublishedDate(?string $publishedDate): static
    {
        $this->publishedDate = $publishedDate;

        return $this;
    }

    public function getPublisher(): ?string
    {
        return $this->publisher;
    }

    public function setPublisher(?string $publisher): static
    {
        $this->publisher = $publisher;

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

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): static
    {
        $this->title = $title;

        return $this;
    }

    /**
     * @return Collection<int, Tome>
     */
    public function getTomes(): Collection
    {
        return $this->tomes;
    }

    public function addTome(Tome $tome): static
    {
        if (!$this->tomes->contains($tome)) {
            $this->tomes->add($tome);
            $tome->setComicSeries($this);
        }

        return $this;
    }

    public function removeTome(Tome $tome): static
    {
        if ($this->tomes->removeElement($tome)) {
            if ($tome->getComicSeries() === $this) {
                $tome->setComicSeries(null);
            }
        }

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

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(\DateTimeImmutable $updatedAt): static
    {
        $this->updatedAt = $updatedAt;

        return $this;
    }

    /**
     * Indique si le dernier tome possédé correspond au dernier tome paru.
     */
    public function isCurrentIssueComplete(): bool
    {
        $currentIssue = $this->getCurrentIssue();

        if (null === $currentIssue || null === $this->latestPublishedIssue) {
            return false;
        }

        return $currentIssue >= $this->latestPublishedIssue;
    }

    /**
     * Indique si le dernier tome acheté correspond au dernier tome paru.
     */
    public function isLastBoughtComplete(): bool
    {
        $lastBought = $this->getLastBought();

        if (null === $lastBought || null === $this->latestPublishedIssue) {
            return false;
        }

        return $lastBought >= $this->latestPublishedIssue;
    }

    /**
     * Indique si le dernier tome téléchargé correspond au dernier tome paru.
     */
    public function isLastDownloadedComplete(): bool
    {
        $lastDownloaded = $this->getLastDownloaded();

        if (null === $lastDownloaded || null === $this->latestPublishedIssue) {
            return false;
        }

        return $lastDownloaded >= $this->latestPublishedIssue;
    }

    public function isLatestPublishedIssueComplete(): bool
    {
        return $this->latestPublishedIssueComplete;
    }

    public function setLatestPublishedIssueComplete(bool $latestPublishedIssueComplete): static
    {
        $this->latestPublishedIssueComplete = $latestPublishedIssueComplete;

        return $this;
    }

    public function isOneShot(): bool
    {
        return $this->isOneShot;
    }

    public function setIsOneShot(bool $isOneShot): static
    {
        $this->isOneShot = $isOneShot;

        return $this;
    }

    /**
     * Indique si la série est dans la liste de souhaits.
     * Calculé à partir du statut, pas d'une propriété séparée.
     */
    public function isWishlist(): bool
    {
        return ComicStatus::WISHLIST === $this->status;
    }
}

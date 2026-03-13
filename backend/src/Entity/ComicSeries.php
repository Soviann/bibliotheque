<?php

declare(strict_types=1);

namespace App\Entity;

use ApiPlatform\Doctrine\Orm\Filter\BooleanFilter;
use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use App\Enum\ComicStatus;
use App\Enum\ComicType;
use App\Repository\ComicSeriesRepository;
use App\State\ComicSeriesDeleteProcessor;
use App\State\ComicSeriesPermanentDeleteProcessor;
use App\State\ComicSeriesRestoreProcessor;
use App\State\SoftDeletedComicSeriesProvider;
use App\State\TrashCollectionProvider;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Knp\DoctrineBehaviors\Contract\Entity\SoftDeletableInterface;
use Knp\DoctrineBehaviors\Model\SoftDeletable\SoftDeletableTrait;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;
use Vich\UploaderBundle\Mapping\Attribute as Vich;

#[ApiResource(
    operations: [
        new GetCollection(
            paginationEnabled: false,
            order: ['title' => 'ASC'],
        ),
        new GetCollection(
            uriTemplate: '/trash',
            paginationEnabled: false,
            provider: TrashCollectionProvider::class,
        ),
        new Get(),
        new Patch(denormalizationContext: ['groups' => ['comic:write']]),
        new Post(denormalizationContext: ['groups' => ['comic:write']]),
        new Delete(processor: ComicSeriesDeleteProcessor::class),
        new Put(
            uriTemplate: '/comic_series/{id}/restore',
            input: false,
            provider: SoftDeletedComicSeriesProvider::class,
            processor: ComicSeriesRestoreProcessor::class,
        ),
        new Delete(
            uriTemplate: '/trash/{id}/permanent',
            provider: SoftDeletedComicSeriesProvider::class,
            processor: ComicSeriesPermanentDeleteProcessor::class,
        ),
    ],
    normalizationContext: ['groups' => ['comic:read']],
    denormalizationContext: ['groups' => ['comic:write']],
)]
#[ApiFilter(BooleanFilter::class, properties: ['isOneShot'])]
#[ApiFilter(SearchFilter::class, properties: [
    'status' => 'exact',
    'title' => 'partial',
    'type' => 'exact',
])]
#[ORM\Entity(repositoryClass: ComicSeriesRepository::class)]
#[ORM\HasLifecycleCallbacks]
#[ORM\Index(name: 'idx_comic_series_deleted_at', columns: ['deleted_at'])]
#[ORM\Index(name: 'idx_comic_series_status', columns: ['status'])]
#[ORM\Index(name: 'idx_comic_series_title', columns: ['title'])]
#[ORM\Index(name: 'idx_comic_series_type', columns: ['type'])]
#[Vich\Uploadable]
class ComicSeries implements SoftDeletableInterface
{
    use SoftDeletableTrait;
    #[Groups(['comic:read'])]
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[Groups(['comic:read', 'comic:write'])]
    #[ORM\Column(length: 255)]
    #[Assert\NotBlank]
    private string $title = '';

    #[Groups(['comic:read', 'comic:write'])]
    #[ORM\Column(type: Types::STRING, length: 20, enumType: ComicStatus::class)]
    private ComicStatus $status = ComicStatus::BUYING;

    #[Groups(['comic:read', 'comic:write'])]
    #[ORM\Column(type: Types::STRING, length: 20, enumType: ComicType::class)]
    private ComicType $type = ComicType::BD;

    /**
     * Les nouveaux tomes créés par le lookup doivent être marqués achetés.
     */
    #[Groups(['comic:read', 'comic:write'])]
    #[ORM\Column]
    private bool $defaultTomeBought = false;

    /**
     * Les nouveaux tomes créés par le lookup doivent être marqués téléchargés.
     */
    #[Groups(['comic:read', 'comic:write'])]
    #[ORM\Column]
    private bool $defaultTomeDownloaded = false;

    /**
     * Les nouveaux tomes créés par le lookup doivent être marqués lus.
     */
    #[Groups(['comic:read', 'comic:write'])]
    #[ORM\Column]
    private bool $defaultTomeRead = false;

    /**
     * Dernier tome paru (numéro du dernier tome publié).
     */
    #[Groups(['comic:read', 'comic:write'])]
    #[ORM\Column(nullable: true)]
    #[Assert\PositiveOrZero]
    private ?int $latestPublishedIssue = null;

    /**
     * Indique si la série est terminée (plus aucun tome à paraître).
     */
    #[Groups(['comic:read', 'comic:write'])]
    #[ORM\Column]
    private bool $latestPublishedIssueComplete = false;

    /**
     * Indique si c'est un one-shot (tome unique, intégrale).
     */
    #[Groups(['comic:read', 'comic:write'])]
    #[ORM\Column]
    private bool $isOneShot = false;

    /**
     * Auteur(s) de la série.
     *
     * @var Collection<int, Author>
     */
    #[Groups(['comic:read', 'comic:write'])]
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
    #[Groups(['comic:read'])]
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $coverImage = null;

    /**
     * URL de la couverture.
     */
    #[Groups(['comic:read', 'comic:write'])]
    #[ORM\Column(length: 500, nullable: true)]
    private ?string $coverUrl = null;

    #[Groups(['comic:read'])]
    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    /**
     * Description ou résumé.
     */
    #[Groups(['comic:read', 'comic:write'])]
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    /**
     * Date de dernière mise à jour du nombre de tomes parus ou du statut de parution.
     */
    #[Groups(['comic:read'])]
    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $latestPublishedIssueUpdatedAt = null;

    /**
     * Date du dernier lookup automatique effectué.
     */
    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $lookupCompletedAt = null;

    /**
     * Date de la dernière vérification de fusion de séries.
     */
    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $mergeCheckedAt = null;

    /**
     * Date de publication.
     */
    #[Groups(['comic:read', 'comic:write'])]
    #[ORM\Column(length: 50, nullable: true)]
    private ?string $publishedDate = null;

    /**
     * Éditeur.
     */
    #[Groups(['comic:read', 'comic:write'])]
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $publisher = null;

    /**
     * Tomes de la série.
     *
     * @var Collection<int, Tome>
     */
    #[Groups(['comic:read', 'comic:write'])]
    #[ORM\OneToMany(targetEntity: Tome::class, mappedBy: 'comicSeries', cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['number' => 'ASC'])]
    private Collection $tomes;

    #[Groups(['comic:read'])]
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
        return \implode(', ', $this->authors->map(static fn (Author $a): string => $a->getName())->toArray());
    }

    public function isDefaultTomeBought(): bool
    {
        return $this->defaultTomeBought;
    }

    public function setDefaultTomeBought(bool $defaultTomeBought): static
    {
        $this->defaultTomeBought = $defaultTomeBought;

        return $this;
    }

    public function isDefaultTomeDownloaded(): bool
    {
        return $this->defaultTomeDownloaded;
    }

    public function setDefaultTomeDownloaded(bool $defaultTomeDownloaded): static
    {
        $this->defaultTomeDownloaded = $defaultTomeDownloaded;

        return $this;
    }

    public function isDefaultTomeRead(): bool
    {
        return $this->defaultTomeRead;
    }

    public function setDefaultTomeRead(bool $defaultTomeRead): static
    {
        $this->defaultTomeRead = $defaultTomeRead;

        return $this;
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

        if ($coverFile instanceof File) {
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
        if (null !== $coverUrl) {
            $coverUrl = (string) \preg_replace('#^http://#', 'https://', $coverUrl);
        }

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
        return $this->getMaxTomeNumber();
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
        return $this->getMaxTomeNumber(static fn (Tome $t): bool => $t->isBought());
    }

    /**
     * Retourne le dernier numéro de tome téléchargé.
     */
    public function getLastDownloaded(): ?int
    {
        return $this->getMaxTomeNumber(static fn (Tome $t): bool => $t->isDownloaded());
    }

    /**
     * Retourne le dernier numéro de tome lu.
     */
    public function getLastRead(): ?int
    {
        return $this->getMaxTomeNumber(static fn (Tome $t): bool => $t->isRead());
    }

    public function getLatestPublishedIssue(): ?int
    {
        return $this->latestPublishedIssue;
    }

    public function getLookupCompletedAt(): ?\DateTimeImmutable
    {
        return $this->lookupCompletedAt;
    }

    public function setLookupCompletedAt(?\DateTimeImmutable $lookupCompletedAt): static
    {
        $this->lookupCompletedAt = $lookupCompletedAt;

        return $this;
    }

    public function getMergeCheckedAt(): ?\DateTimeImmutable
    {
        return $this->mergeCheckedAt;
    }

    public function setMergeCheckedAt(?\DateTimeImmutable $mergeCheckedAt): static
    {
        $this->mergeCheckedAt = $mergeCheckedAt;

        return $this;
    }

    public function getLatestPublishedIssueUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->latestPublishedIssueUpdatedAt;
    }

    public function setLatestPublishedIssueUpdatedAt(?\DateTimeImmutable $latestPublishedIssueUpdatedAt): static
    {
        $this->latestPublishedIssueUpdatedAt = $latestPublishedIssueUpdatedAt;

        return $this;
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
        return $this->tomes->map(static fn (Tome $t): int => $t->getNumber())->toArray();
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

    /**
     * Retourne le nombre de tomes lus.
     */
    public function getReadTomesCount(): int
    {
        return $this->tomes->filter(static fn (Tome $t): bool => $t->isRead())->count();
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
        if ($this->tomes->removeElement($tome) && $tome->getComicSeries() === $this) {
            $tome->setComicSeries(null);
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
     * Indique si la série est en cours de lecture (au moins 1 lu, pas tous).
     */
    public function isCurrentlyReading(): bool
    {
        if ($this->tomes->isEmpty()) {
            return false;
        }

        $readCount = $this->getReadTomesCount();

        return $readCount > 0 && $readCount < $this->tomes->count();
    }

    /**
     * Indique si le dernier tome possédé correspond au dernier tome paru.
     */
    public function isCurrentIssueComplete(): bool
    {
        return $this->isIssueComplete($this->getCurrentIssue());
    }

    /**
     * Indique si tous les tomes sont lus.
     */
    public function isFullyRead(): bool
    {
        if ($this->tomes->isEmpty()) {
            return false;
        }

        return $this->getReadTomesCount() === $this->tomes->count();
    }

    /**
     * Indique si le dernier tome acheté correspond au dernier tome paru.
     */
    public function isLastBoughtComplete(): bool
    {
        return $this->isIssueComplete($this->getLastBought());
    }

    /**
     * Indique si le dernier tome téléchargé correspond au dernier tome paru.
     */
    public function isLastDownloadedComplete(): bool
    {
        return $this->isIssueComplete($this->getLastDownloaded());
    }

    /**
     * Indique si le dernier tome lu correspond au dernier tome paru.
     */
    public function isLastReadComplete(): bool
    {
        return $this->isIssueComplete($this->getLastRead());
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

    /**
     * Retourne le numéro maximum des tomes, optionnellement filtrés.
     *
     * @param \Closure(Tome, int):bool|null $filter Filtre optionnel à appliquer
     */
    private function getMaxTomeNumber(?\Closure $filter = null): ?int
    {
        $tomes = $filter instanceof \Closure ? $this->tomes->filter($filter) : $this->tomes;

        if ($tomes->isEmpty()) {
            return null;
        }

        $numbers = $tomes->map(static fn (Tome $t): int => $t->getNumber())->toArray();

        return [] === $numbers ? null : \max($numbers);
    }

    /**
     * Vérifie si un numéro de tome atteint ou dépasse le dernier paru.
     */
    private function isIssueComplete(?int $issue): bool
    {
        if (null === $issue || null === $this->latestPublishedIssue) {
            return false;
        }

        return $issue >= $this->latestPublishedIssue;
    }
}

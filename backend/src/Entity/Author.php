<?php

declare(strict_types=1);

namespace App\Entity;

use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use App\Repository\AuthorRepository;
use App\State\AuthorCreateProcessor;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ApiResource(
    operations: [
        new GetCollection(
            paginationItemsPerPage: 50,
            order: ['name' => 'ASC'],
        ),
        new Get(),
        new Patch(denormalizationContext: ['groups' => ['author:write']]),
        new Post(processor: AuthorCreateProcessor::class),
    ],
    normalizationContext: ['groups' => ['author:read']],
    denormalizationContext: ['groups' => ['author:write']],
)]
#[ApiFilter(SearchFilter::class, properties: ['name' => 'partial'])]
#[ORM\Entity(repositoryClass: AuthorRepository::class)]
class Author implements \Stringable
{
    #[Groups(['author:read', 'comic:list', 'comic:read'])]
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[Groups(['author:read', 'author:write', 'comic:list', 'comic:read'])]
    #[ORM\Column(length: 255, unique: true)]
    #[Assert\NotBlank]
    private string $name = '';

    #[Groups(['author:read', 'author:write', 'comic:read'])]
    #[ORM\Column(options: ['default' => false])]
    private bool $followedForNewSeries = false;

    /**
     * @var Collection<int, ComicSeries>
     */
    #[ORM\ManyToMany(targetEntity: ComicSeries::class, mappedBy: 'authors')]
    private Collection $comicSeries;

    public function __construct()
    {
        $this->comicSeries = new ArrayCollection();
    }

    public function __toString(): string
    {
        return $this->name;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function isFollowedForNewSeries(): bool
    {
        return $this->followedForNewSeries;
    }

    public function setFollowedForNewSeries(bool $followedForNewSeries): static
    {
        $this->followedForNewSeries = $followedForNewSeries;

        return $this;
    }

    /**
     * @return Collection<int, ComicSeries>
     */
    public function getComicSeries(): Collection
    {
        return $this->comicSeries;
    }

    public function addComicSeries(ComicSeries $comicSeries): static
    {
        if (!$this->comicSeries->contains($comicSeries)) {
            $this->comicSeries->add($comicSeries);
            $comicSeries->addAuthor($this);
        }

        return $this;
    }

    public function removeComicSeries(ComicSeries $comicSeries): static
    {
        if ($this->comicSeries->removeElement($comicSeries)) {
            $comicSeries->removeAuthor($this);
        }

        return $this;
    }
}

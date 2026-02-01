<?php

declare(strict_types=1);

namespace App\Dto\Input;

use App\Entity\ComicSeries;
use App\Enum\ComicStatus;
use App\Enum\ComicType;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\ObjectMapper\Attribute\Map;
use Symfony\Component\ObjectMapper\Transform\MapCollection;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * DTO pour la création/édition d'une série.
 */
#[Map(target: ComicSeries::class)]
class ComicSeriesInput
{
    #[Assert\NotBlank(message: 'Le titre est obligatoire.')]
    public string $title = '';

    public ComicStatus $status = ComicStatus::BUYING;

    public ComicType $type = ComicType::BD;

    #[Assert\PositiveOrZero]
    public ?int $latestPublishedIssue = null;

    public bool $latestPublishedIssueComplete = false;

    public bool $isOneShot = false;

    public bool $isWishlist = false;

    public ?string $description = null;

    public ?string $publishedDate = null;

    public ?string $publisher = null;

    public ?string $coverUrl = null;

    /**
     * Nom du fichier de couverture existant (lecture seule, pour l'affichage).
     */
    #[Map(if: false)]
    public ?string $coverImage = null;

    #[Map(if: false)]
    #[Assert\File(
        maxSize: '5M',
        mimeTypes: ['image/jpeg', 'image/png', 'image/gif', 'image/webp'],
        mimeTypesMessage: 'Veuillez télécharger une image valide (JPEG, PNG, GIF ou WebP).'
    )]
    public ?File $coverFile = null;

    /**
     * @var list<TomeInput>
     */
    #[Map(transform: new MapCollection())]
    #[Assert\Valid]
    public array $tomes = [];

    /**
     * @var list<AuthorInput>
     */
    #[Map(if: false)]
    #[Assert\Valid]
    public array $authors = [];

    /**
     * Retourne les numéros des tomes possédés.
     *
     * @return int[]
     */
    public function getOwnedTomesNumbers(): array
    {
        return \array_map(static fn (TomeInput $t): int => $t->number, $this->tomes);
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
}

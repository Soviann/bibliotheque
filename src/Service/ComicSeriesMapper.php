<?php

declare(strict_types=1);

namespace App\Service;

use App\Dto\Input\AuthorInput;
use App\Dto\Input\ComicSeriesInput;
use App\Dto\Input\TomeInput;
use App\Entity\Author;
use App\Entity\ComicSeries;
use App\Entity\Tome;
use App\Repository\AuthorRepository;
use Symfony\Component\ObjectMapper\ObjectMapperInterface;

/**
 * Service de mapping entre ComicSeriesInput et ComicSeries.
 */
class ComicSeriesMapper
{
    public function __construct(
        private readonly AuthorRepository $authorRepository,
        private readonly ObjectMapperInterface $mapper,
    ) {
    }

    /**
     * Crée ou met à jour une entité ComicSeries depuis un DTO.
     */
    public function mapToEntity(ComicSeriesInput $input, ?ComicSeries $entity = null): ComicSeries
    {
        $isNew = null === $entity;
        $entity ??= new ComicSeries();

        // Mapping des propriétés scalaires
        $entity->setTitle($input->title);
        $entity->setStatus($input->status);
        $entity->setType($input->type);
        $entity->setLatestPublishedIssue($input->latestPublishedIssue);
        $entity->setLatestPublishedIssueComplete($input->latestPublishedIssueComplete);
        $entity->setIsOneShot($input->isOneShot);
        $entity->setIsWishlist($input->isWishlist);
        $entity->setDescription($input->description);
        $entity->setPublishedDate($input->publishedDate);
        $entity->setPublisher($input->publisher);
        $entity->setCoverUrl($input->coverUrl);

        if (null !== $input->coverFile) {
            $entity->setCoverFile($input->coverFile);
        }

        // Gestion des Authors (findOrCreate)
        $entity->getAuthors()->clear();
        foreach ($input->authors as $authorInput) {
            if ('' !== $authorInput->name) {
                $author = $this->authorRepository->findOrCreate($authorInput->name);
                $entity->addAuthor($author);
            }
        }

        // Gestion des Tomes
        $this->syncTomes($input->tomes, $entity, $isNew);

        return $entity;
    }

    /**
     * Crée un DTO depuis une entité (pour pré-remplir le formulaire en édition).
     */
    public function mapToInput(ComicSeries $entity): ComicSeriesInput
    {
        $input = new ComicSeriesInput();

        $input->title = $entity->getTitle();
        $input->status = $entity->getStatus();
        $input->type = $entity->getType();
        $input->latestPublishedIssue = $entity->getLatestPublishedIssue();
        $input->latestPublishedIssueComplete = $entity->isLatestPublishedIssueComplete();
        $input->isOneShot = $entity->isOneShot();
        $input->isWishlist = $entity->isWishlist();
        $input->description = $entity->getDescription();
        $input->publishedDate = $entity->getPublishedDate();
        $input->publisher = $entity->getPublisher();
        $input->coverUrl = $entity->getCoverUrl();
        $input->coverImage = $entity->getCoverImage();

        // Mapping des Authors
        $input->authors = \array_values(\array_map(
            fn (Author $author) => $this->mapper->map($author, AuthorInput::class),
            $entity->getAuthors()->toArray()
        ));

        // Mapping des Tomes
        $input->tomes = \array_values(\array_map(
            fn (Tome $tome) => $this->mapper->map($tome, TomeInput::class),
            $entity->getTomes()->toArray()
        ));

        return $input;
    }

    /**
     * Synchronise la collection de tomes.
     *
     * @param list<TomeInput> $tomesInput
     */
    private function syncTomes(array $tomesInput, ComicSeries $entity, bool $isNew): void
    {
        if ($isNew) {
            // Création : ajouter tous les tomes
            foreach ($tomesInput as $tomeInput) {
                $tome = $this->mapper->map($tomeInput, Tome::class);
                $entity->addTome($tome);
            }

            return;
        }

        // Édition : synchroniser la collection
        $existingTomes = $entity->getTomes()->toArray();
        $inputNumbers = \array_map(static fn (TomeInput $t) => $t->number, $tomesInput);

        // Supprimer les tomes qui ne sont plus dans l'input
        foreach ($existingTomes as $tome) {
            if (!\in_array($tome->getNumber(), $inputNumbers, true)) {
                $entity->removeTome($tome);
            }
        }

        // Ajouter ou mettre à jour les tomes
        foreach ($tomesInput as $tomeInput) {
            $existingTome = $this->findTomeByNumber($existingTomes, $tomeInput->number);

            if (null !== $existingTome) {
                // Mise à jour
                $existingTome->setBought($tomeInput->bought);
                $existingTome->setDownloaded($tomeInput->downloaded);
                $existingTome->setIsbn($tomeInput->isbn);
                $existingTome->setOnNas($tomeInput->onNas);
                $existingTome->setTitle($tomeInput->title);
            } else {
                // Création
                $tome = $this->mapper->map($tomeInput, Tome::class);
                $entity->addTome($tome);
            }
        }
    }

    /**
     * Trouve un tome par son numéro dans une liste.
     *
     * @param Tome[] $tomes
     */
    private function findTomeByNumber(array $tomes, int $number): ?Tome
    {
        foreach ($tomes as $tome) {
            if ($tome->getNumber() === $number) {
                return $tome;
            }
        }

        return null;
    }
}

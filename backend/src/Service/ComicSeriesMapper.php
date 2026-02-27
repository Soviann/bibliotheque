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
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\ObjectMapper\ObjectMapperInterface;

/**
 * Service de mapping entre ComicSeriesInput et ComicSeries.
 */
class ComicSeriesMapper
{
    public function __construct(
        private readonly AuthorRepository $authorRepository,
        private readonly CoverRemoverInterface $coverRemover,
        private readonly ObjectMapperInterface $mapper,
    ) {
    }

    /**
     * Crée ou met à jour une entité ComicSeries depuis un DTO.
     */
    public function mapToEntity(ComicSeriesInput $input, ?ComicSeries $entity = null): ComicSeries
    {
        $isNew = !$entity instanceof ComicSeries;
        $entity ??= new ComicSeries();

        // Mapping des propriétés scalaires
        $entity->setTitle($input->title);
        $entity->setType($input->type);
        $entity->setLatestPublishedIssue($input->latestPublishedIssue);
        $entity->setLatestPublishedIssueComplete($input->latestPublishedIssueComplete);
        $entity->setIsOneShot($input->isOneShot);
        $entity->setDescription($input->description);

        $entity->setStatus($input->status);
        $entity->setPublishedDate($input->publishedDate);
        $entity->setPublisher($input->publisher);
        $entity->setCoverUrl($input->coverUrl);

        // Gestion de la couverture : suppression ou upload
        if ($input->deleteCover && null !== $entity->getCoverImage()) {
            $this->coverRemover->remove($entity);
        } elseif ($input->coverFile instanceof File) {
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

        // One-shot : créer automatiquement un tome n°1 s'il n'en existe pas
        if ($input->isOneShot && $entity->getTomes()->isEmpty()) {
            $defaultTomeInput = new TomeInput();
            $defaultTomeInput->number = 1;
            $tome = $this->mapper->map($defaultTomeInput, Tome::class);
            $entity->addTome($tome);
        }

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
        $input->description = $entity->getDescription();
        $input->publishedDate = $this->normalizeDate($entity->getPublishedDate());
        $input->publisher = $entity->getPublisher();
        $input->coverUrl = $entity->getCoverUrl();
        $input->coverImage = $entity->getCoverImage();

        // Mapping des Authors
        $input->authors = \array_values(\array_map(
            fn (Author $author): object => $this->mapper->map($author, AuthorInput::class),
            $entity->getAuthors()->toArray()
        ));

        // Mapping des Tomes
        $input->tomes = \array_values(\array_map(
            fn (Tome $tome): object => $this->mapper->map($tome, TomeInput::class),
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
        $inputNumbers = \array_map(static fn (TomeInput $t): int => $t->number, $tomesInput);

        // Supprimer les tomes qui ne sont plus dans l'input
        foreach ($existingTomes as $tome) {
            if (!\in_array($tome->getNumber(), $inputNumbers, true)) {
                $entity->removeTome($tome);
            }
        }

        // Ajouter ou mettre à jour les tomes
        foreach ($tomesInput as $tomeInput) {
            $existingTome = $this->findTomeByNumber($existingTomes, $tomeInput->number);

            if ($existingTome instanceof Tome) {
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
     * Normalise une date en format YYYY-MM-DD pour le champ DateType.
     */
    private function normalizeDate(?string $date): ?string
    {
        if (null === $date || '' === $date) {
            return null;
        }

        // Déjà au format YYYY-MM-DD
        if (\preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            return $date;
        }

        // Format YYYY-MM-DD suivi d'une heure (ex: "2023-01-15 10:30:00")
        if (\preg_match('/^(\d{4}-\d{2}-\d{2})\s/', $date)) {
            return \substr($date, 0, 10);
        }

        // Format YYYY-MM (ex: "2023-06")
        if (\preg_match('/^\d{4}-\d{2}$/', $date)) {
            return $date.'-01';
        }

        // Format YYYY (ex: "2023")
        if (\preg_match('/^\d{4}$/', $date)) {
            return $date.'-01-01';
        }

        // Tente un parsing générique
        try {
            $parsed = new \DateTimeImmutable($date);

            return $parsed->format('Y-m-d');
        } catch (\Exception) {
            return null;
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

<?php

declare(strict_types=1);

namespace App\Service;

use App\Dto\Input\ComicSeriesInput;
use App\Entity\ComicSeries;
use App\Enum\ComicStatus;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Service de gestion du cycle de vie des séries.
 */
class ComicSeriesService
{
    public function __construct(
        private readonly ComicSeriesMapper $comicSeriesMapper,
        private readonly Connection $connection,
        private readonly CoverRemoverInterface $coverRemover,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * Crée une nouvelle série à partir d'un DTO.
     */
    public function create(ComicSeriesInput $input): ComicSeries
    {
        $comic = $this->comicSeriesMapper->mapToEntity($input);
        $this->entityManager->persist($comic);
        $this->entityManager->flush();

        return $comic;
    }

    /**
     * Supprime définitivement une série (DBAL cascade).
     */
    public function permanentDelete(int $id, ComicSeries $comic): void
    {
        $this->coverRemover->remove($comic);

        $this->connection->delete('comic_series_author', ['comic_series_id' => $id]);
        $this->connection->delete('tome', ['comic_series_id' => $id]);
        $this->connection->delete('comic_series', ['id' => $id]);
    }

    /**
     * Restaure une série soft-deleted.
     */
    public function restore(ComicSeries $comic): void
    {
        $comic->restore();
        $this->entityManager->flush();
    }

    /**
     * Soft-delete une série.
     */
    public function softDelete(ComicSeries $comic): void
    {
        $this->entityManager->remove($comic);
        $this->entityManager->flush();
    }

    /**
     * Déplace une série de la wishlist vers la bibliothèque.
     */
    public function moveToLibrary(ComicSeries $comic): void
    {
        $comic->setStatus(ComicStatus::BUYING);
        $this->entityManager->flush();
    }

    /**
     * Met à jour une série existante à partir d'un DTO.
     */
    public function update(ComicSeriesInput $input, ComicSeries $comic): ComicSeries
    {
        $this->comicSeriesMapper->mapToEntity($input, $comic);
        $this->entityManager->flush();

        return $comic;
    }
}

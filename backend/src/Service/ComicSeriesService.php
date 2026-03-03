<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\ComicSeries;
use App\Enum\ComicStatus;
use App\Event\ComicSeriesDeletedEvent;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Service de gestion du cycle de vie des séries.
 */
class ComicSeriesService
{
    public function __construct(
        private readonly Connection $connection,
        private readonly CoverRemoverInterface $coverRemover,
        private readonly EntityManagerInterface $entityManager,
        private readonly EventDispatcherInterface $eventDispatcher,
    ) {
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
     * Supprime définitivement une série (DBAL cascade).
     */
    public function permanentDelete(int $id, ComicSeries $comic): void
    {
        $title = $comic->getTitle();
        $this->coverRemover->remove($comic);

        $this->connection->delete('comic_series_author', ['comic_series_id' => $id]);
        $this->connection->delete('tome', ['comic_series_id' => $id]);
        $this->connection->delete('comic_series', ['id' => $id]);

        $this->eventDispatcher->dispatch(new ComicSeriesDeletedEvent($id, $title));
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
}

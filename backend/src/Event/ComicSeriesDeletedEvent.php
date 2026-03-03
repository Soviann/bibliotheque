<?php

declare(strict_types=1);

namespace App\Event;

/**
 * Événement dispatché lorsqu'une série est supprimée.
 *
 * Contient l'identifiant et le titre car l'entité peut ne plus exister.
 */
final readonly class ComicSeriesDeletedEvent
{
    public function __construct(
        private int $id,
        private string $title,
    ) {
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getTitle(): string
    {
        return $this->title;
    }
}

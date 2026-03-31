<?php

declare(strict_types=1);

namespace App\DTO;

/**
 * Tome dans l'aperçu de fusion.
 */
final readonly class MergePreviewTome implements \JsonSerializable
{
    public function __construct(
        public bool $bought,
        public ?string $isbn,
        public int $number,
        public bool $onNas,
        public bool $read,
        public ?string $title,
        public ?int $tomeEnd,
    ) {
    }

    /**
     * @return array{bought: bool, isbn: ?string, number: int, onNas: bool, read: bool, title: ?string, tomeEnd: ?int}
     */
    public function jsonSerialize(): array
    {
        return [
            'bought' => $this->bought,
            'isbn' => $this->isbn,
            'number' => $this->number,
            'onNas' => $this->onNas,
            'read' => $this->read,
            'title' => $this->title,
            'tomeEnd' => $this->tomeEnd,
        ];
    }
}

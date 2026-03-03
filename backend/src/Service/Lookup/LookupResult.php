<?php

declare(strict_types=1);

namespace App\Service\Lookup;

/**
 * Résultat d'un lookup depuis un provider.
 */
class LookupResult implements \JsonSerializable
{
    public function __construct(
        public readonly ?string $authors = null,
        public readonly ?string $description = null,
        public readonly ?string $isbn = null,
        public readonly ?bool $isOneShot = null,
        public readonly ?int $latestPublishedIssue = null,
        public readonly ?string $publishedDate = null,
        public readonly ?string $publisher = null,
        public readonly string $source = '',
        public readonly ?string $thumbnail = null,
        public readonly ?string $title = null,
        public readonly ?int $tomeEnd = null,
        public readonly ?int $tomeNumber = null,
    ) {
    }

    /**
     * Gère la désérialisation d'objets mis en cache avant l'ajout de nouvelles propriétés.
     *
     * @param array<string, mixed> $data
     */
    public function __unserialize(array $data): void
    {
        $this->authors = \is_string($data['authors'] ?? null) ? $data['authors'] : null;
        $this->description = \is_string($data['description'] ?? null) ? $data['description'] : null;
        $this->isbn = \is_string($data['isbn'] ?? null) ? $data['isbn'] : null;
        $this->isOneShot = \is_bool($data['isOneShot'] ?? null) ? $data['isOneShot'] : null;
        $this->latestPublishedIssue = \is_int($data['latestPublishedIssue'] ?? null) ? $data['latestPublishedIssue'] : null;
        $this->publishedDate = \is_string($data['publishedDate'] ?? null) ? $data['publishedDate'] : null;
        $this->publisher = \is_string($data['publisher'] ?? null) ? $data['publisher'] : null;
        $this->source = \is_string($data['source'] ?? null) ? $data['source'] : '';
        $this->thumbnail = \is_string($data['thumbnail'] ?? null) ? $data['thumbnail'] : null;
        $this->title = \is_string($data['title'] ?? null) ? $data['title'] : null;
        $this->tomeEnd = \is_int($data['tomeEnd'] ?? null) ? $data['tomeEnd'] : null;
        $this->tomeNumber = \is_int($data['tomeNumber'] ?? null) ? $data['tomeNumber'] : null;
    }

    /**
     * Vérifie si les données principales sont complètes.
     */
    public function isComplete(): bool
    {
        return !\in_array(null, [$this->authors, $this->description, $this->publishedDate, $this->publisher, $this->thumbnail, $this->title], true);
    }

    /**
     * @return array{authors: ?string, description: ?string, isbn: ?string, isOneShot: ?bool, latestPublishedIssue: ?int, publishedDate: ?string, publisher: ?string, thumbnail: ?string, title: ?string, tomeEnd: ?int, tomeNumber: ?int}
     */
    public function jsonSerialize(): array
    {
        return [
            'authors' => $this->authors,
            'description' => $this->description,
            'isbn' => $this->isbn,
            'isOneShot' => $this->isOneShot,
            'latestPublishedIssue' => $this->latestPublishedIssue,
            'publishedDate' => $this->publishedDate,
            'publisher' => $this->publisher,
            'thumbnail' => $this->thumbnail,
            'title' => $this->title,
            'tomeEnd' => $this->tomeEnd,
            'tomeNumber' => $this->tomeNumber,
        ];
    }

    /**
     * Retourne une copie avec l'ISBN défini.
     */
    public function withIsbn(string $isbn): self
    {
        return new self(
            authors: $this->authors,
            description: $this->description,
            isbn: $isbn,
            isOneShot: $this->isOneShot,
            latestPublishedIssue: $this->latestPublishedIssue,
            publishedDate: $this->publishedDate,
            publisher: $this->publisher,
            source: $this->source,
            thumbnail: $this->thumbnail,
            title: $this->title,
            tomeEnd: $this->tomeEnd,
            tomeNumber: $this->tomeNumber,
        );
    }
}

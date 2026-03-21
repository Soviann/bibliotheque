<?php

declare(strict_types=1);

namespace App\Service\Lookup;

/**
 * Résultat d'un lookup depuis un provider.
 */
final readonly class LookupResult implements \JsonSerializable
{
    public function __construct(
        public ?string $amazonUrl = null,
        public ?string $authors = null,
        public ?string $description = null,
        public ?string $isbn = null,
        public ?bool $isOneShot = null,
        public ?int $latestPublishedIssue = null,
        public ?string $publishedDate = null,
        public ?string $publisher = null,
        public string $source = '',
        public ?string $thumbnail = null,
        public ?string $title = null,
        public ?int $tomeEnd = null,
        public ?int $tomeNumber = null,
    ) {
    }

    /**
     * Gère la désérialisation d'objets mis en cache avant l'ajout de nouvelles propriétés.
     *
     * @param array<string, mixed> $data
     */
    public function __unserialize(array $data): void
    {
        $this->amazonUrl = \is_string($data['amazonUrl'] ?? null) ? $data['amazonUrl'] : null;
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
     * @return array{amazonUrl: ?string, authors: ?string, description: ?string, isbn: ?string, isOneShot: ?bool, latestPublishedIssue: ?int, publishedDate: ?string, publisher: ?string, thumbnail: ?string, title: ?string, tomeEnd: ?int, tomeNumber: ?int}
     */
    public function jsonSerialize(): array
    {
        return [
            'amazonUrl' => $this->amazonUrl,
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
            amazonUrl: $this->amazonUrl,
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

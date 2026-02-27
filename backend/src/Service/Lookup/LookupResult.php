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
    ) {
    }

    /**
     * Gère la désérialisation d'objets mis en cache avant l'ajout de nouvelles propriétés.
     *
     * @param array<string, mixed> $data
     */
    public function __unserialize(array $data): void
    {
        $this->authors = $data['authors'] ?? null;
        $this->description = $data['description'] ?? null;
        $this->isbn = $data['isbn'] ?? null;
        $this->isOneShot = $data['isOneShot'] ?? null;
        $this->latestPublishedIssue = $data['latestPublishedIssue'] ?? null;
        $this->publishedDate = $data['publishedDate'] ?? null;
        $this->publisher = $data['publisher'] ?? null;
        $this->source = $data['source'] ?? '';
        $this->thumbnail = $data['thumbnail'] ?? null;
        $this->title = $data['title'] ?? null;
    }

    /**
     * Vérifie si les données principales sont complètes.
     */
    public function isComplete(): bool
    {
        return null !== $this->authors
            && null !== $this->description
            && null !== $this->publishedDate
            && null !== $this->publisher
            && null !== $this->thumbnail
            && null !== $this->title;
    }

    /**
     * Fusionne avec un autre résultat : complète les champs manquants.
     *
     * @param list<string> $overrideFields Champs à écraser même s'ils existent déjà
     */
    public function mergeWith(self $other, array $overrideFields = []): self
    {
        $fields = ['authors', 'description', 'isbn', 'isOneShot', 'latestPublishedIssue', 'publishedDate', 'publisher', 'thumbnail', 'title'];
        $values = [];

        foreach ($fields as $field) {
            if (\in_array($field, $overrideFields, true) && null !== $other->$field) {
                $values[$field] = $other->$field;
            } else {
                $values[$field] = $this->$field ?? $other->$field;
            }
        }

        return new self(
            authors: $values['authors'],
            description: $values['description'],
            isbn: $values['isbn'],
            isOneShot: $values['isOneShot'],
            latestPublishedIssue: $values['latestPublishedIssue'],
            publishedDate: $values['publishedDate'],
            publisher: $values['publisher'],
            source: $this->source,
            thumbnail: $values['thumbnail'],
            title: $values['title'],
        );
    }

    /**
     * @return array{authors: ?string, description: ?string, isbn: ?string, isOneShot: ?bool, latestPublishedIssue: ?int, publishedDate: ?string, publisher: ?string, thumbnail: ?string, title: ?string}
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
        );
    }
}

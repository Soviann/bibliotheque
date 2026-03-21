<?php

declare(strict_types=1);

namespace App\DTO;

use App\Entity\ComicSeries;

/**
 * Élément de la liste des séries pour l'API PWA.
 *
 * Utilisé pour le cache applicatif : la méthode __unserialize() gère
 * la compatibilité avec les entrées de cache précédentes.
 */
final readonly class ComicSeriesListItem implements \JsonSerializable
{
    /**
     * @param int[] $missingTomesNumbers
     * @param int[] $ownedTomesNumbers
     */
    public function __construct(
        public ?string $amazonUrl,
        public string $authors,
        public ?string $coverImage,
        public ?string $coverUrl,
        public ?int $currentIssue,
        public bool $currentIssueComplete,
        public bool $defaultTomeBought,
        public bool $defaultTomeDownloaded,
        public bool $defaultTomeRead,
        public ?string $description,
        public bool $hasNasTome,
        public int $id,
        public bool $isCurrentlyReading,
        public bool $isFullyRead,
        public bool $isOneShot,
        public bool $isWishlist,
        public ?int $lastBought,
        public bool $lastBoughtComplete,
        public ?int $lastDownloaded,
        public bool $lastDownloadedComplete,
        public ?int $lastRead,
        public bool $lastReadComplete,
        public ?int $latestPublishedIssue,
        public bool $latestPublishedIssueComplete,
        public ?string $latestPublishedIssueUpdatedAt,
        public array $missingTomesNumbers,
        public array $ownedTomesNumbers,
        public ?string $publishedDate,
        public ?string $publisher,
        public int $readTomesCount,
        public string $status,
        public string $statusLabel,
        public string $title,
        public int $tomesCount,
        public string $type,
        public string $typeLabel,
        public string $updatedAt,
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
        $this->authors = \is_string($data['authors'] ?? null) ? $data['authors'] : '';
        $this->coverImage = \is_string($data['coverImage'] ?? null) ? $data['coverImage'] : null;
        $this->coverUrl = \is_string($data['coverUrl'] ?? null) ? $data['coverUrl'] : null;
        $this->currentIssue = \is_int($data['currentIssue'] ?? null) ? $data['currentIssue'] : null;
        $this->currentIssueComplete = \is_bool($data['currentIssueComplete'] ?? null) && $data['currentIssueComplete'];
        $this->defaultTomeBought = \is_bool($data['defaultTomeBought'] ?? null) && $data['defaultTomeBought'];
        $this->defaultTomeDownloaded = \is_bool($data['defaultTomeDownloaded'] ?? null) && $data['defaultTomeDownloaded'];
        $this->defaultTomeRead = \is_bool($data['defaultTomeRead'] ?? null) && $data['defaultTomeRead'];
        $this->description = \is_string($data['description'] ?? null) ? $data['description'] : null;
        $this->hasNasTome = \is_bool($data['hasNasTome'] ?? null) && $data['hasNasTome'];
        $this->id = \is_int($data['id'] ?? null) ? $data['id'] : 0;
        $this->isCurrentlyReading = \is_bool($data['isCurrentlyReading'] ?? null) && $data['isCurrentlyReading'];
        $this->isFullyRead = \is_bool($data['isFullyRead'] ?? null) && $data['isFullyRead'];
        $this->isOneShot = \is_bool($data['isOneShot'] ?? null) && $data['isOneShot'];
        $this->isWishlist = \is_bool($data['isWishlist'] ?? null) && $data['isWishlist'];
        $this->lastBought = \is_int($data['lastBought'] ?? null) ? $data['lastBought'] : null;
        $this->lastBoughtComplete = \is_bool($data['lastBoughtComplete'] ?? null) && $data['lastBoughtComplete'];
        $this->lastDownloaded = \is_int($data['lastDownloaded'] ?? null) ? $data['lastDownloaded'] : null;
        $this->lastDownloadedComplete = \is_bool($data['lastDownloadedComplete'] ?? null) && $data['lastDownloadedComplete'];
        $this->lastRead = \is_int($data['lastRead'] ?? null) ? $data['lastRead'] : null;
        $this->lastReadComplete = \is_bool($data['lastReadComplete'] ?? null) && $data['lastReadComplete'];
        $this->latestPublishedIssue = \is_int($data['latestPublishedIssue'] ?? null) ? $data['latestPublishedIssue'] : null;
        $this->latestPublishedIssueComplete = \is_bool($data['latestPublishedIssueComplete'] ?? null) && $data['latestPublishedIssueComplete'];
        $this->latestPublishedIssueUpdatedAt = \is_string($data['latestPublishedIssueUpdatedAt'] ?? null) ? $data['latestPublishedIssueUpdatedAt'] : null;
        $this->missingTomesNumbers = \is_array($data['missingTomesNumbers'] ?? null) ? \array_map(static fn (mixed $v): int => (int) (\is_numeric($v) ? $v : 0), $data['missingTomesNumbers']) : [];
        $this->ownedTomesNumbers = \is_array($data['ownedTomesNumbers'] ?? null) ? \array_map(static fn (mixed $v): int => (int) (\is_numeric($v) ? $v : 0), $data['ownedTomesNumbers']) : [];
        $this->publishedDate = \is_string($data['publishedDate'] ?? null) ? $data['publishedDate'] : null;
        $this->publisher = \is_string($data['publisher'] ?? null) ? $data['publisher'] : null;
        $this->readTomesCount = \is_int($data['readTomesCount'] ?? null) ? $data['readTomesCount'] : 0;
        $this->status = \is_string($data['status'] ?? null) ? $data['status'] : '';
        $this->statusLabel = \is_string($data['statusLabel'] ?? null) ? $data['statusLabel'] : '';
        $this->title = \is_string($data['title'] ?? null) ? $data['title'] : '';
        $this->tomesCount = \is_int($data['tomesCount'] ?? null) ? $data['tomesCount'] : 0;
        $this->type = \is_string($data['type'] ?? null) ? $data['type'] : '';
        $this->typeLabel = \is_string($data['typeLabel'] ?? null) ? $data['typeLabel'] : '';
        $this->updatedAt = \is_string($data['updatedAt'] ?? null) ? $data['updatedAt'] : '';
    }

    /**
     * Construit un élément à partir d'une entité ComicSeries.
     */
    public static function fromEntity(ComicSeries $comic, bool $hasNasTome): self
    {
        return new self(
            amazonUrl: $comic->getAmazonUrl(),
            authors: $comic->getAuthorsAsString(),
            coverImage: $comic->getCoverImage(),
            coverUrl: $comic->getCoverUrl(),
            currentIssue: $comic->getCurrentIssue(),
            currentIssueComplete: $comic->isCurrentIssueComplete(),
            defaultTomeBought: $comic->isDefaultTomeBought(),
            defaultTomeDownloaded: $comic->isDefaultTomeDownloaded(),
            defaultTomeRead: $comic->isDefaultTomeRead(),
            description: $comic->getDescription(),
            hasNasTome: $hasNasTome,
            id: (int) $comic->getId(),
            isCurrentlyReading: $comic->isCurrentlyReading(),
            isFullyRead: $comic->isFullyRead(),
            isOneShot: $comic->isOneShot(),
            isWishlist: $comic->isWishlist(),
            lastBought: $comic->getLastBought(),
            lastBoughtComplete: $comic->isLastBoughtComplete(),
            lastDownloaded: $comic->getLastDownloaded(),
            lastDownloadedComplete: $comic->isLastDownloadedComplete(),
            lastRead: $comic->getLastRead(),
            lastReadComplete: $comic->isLastReadComplete(),
            latestPublishedIssue: $comic->getLatestPublishedIssue(),
            latestPublishedIssueComplete: $comic->isLatestPublishedIssueComplete(),
            latestPublishedIssueUpdatedAt: $comic->getLatestPublishedIssueUpdatedAt()?->format('c'),
            missingTomesNumbers: $comic->getMissingTomesNumbers(),
            ownedTomesNumbers: $comic->getOwnedTomesNumbers(),
            publishedDate: $comic->getPublishedDate(),
            publisher: $comic->getPublisher(),
            readTomesCount: $comic->getReadTomesCount(),
            status: $comic->getStatus()->value,
            statusLabel: $comic->getStatus()->getLabel(),
            title: $comic->getTitle(),
            tomesCount: $comic->getTomes()->count(),
            type: $comic->getType()->value,
            typeLabel: $comic->getType()->getLabel(),
            updatedAt: $comic->getUpdatedAt()->format('c'),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return [
            'amazonUrl' => $this->amazonUrl,
            'authors' => $this->authors,
            'coverImage' => $this->coverImage,
            'coverUrl' => $this->coverUrl,
            'currentIssue' => $this->currentIssue,
            'currentIssueComplete' => $this->currentIssueComplete,
            'defaultTomeBought' => $this->defaultTomeBought,
            'defaultTomeDownloaded' => $this->defaultTomeDownloaded,
            'defaultTomeRead' => $this->defaultTomeRead,
            'description' => $this->description,
            'hasNasTome' => $this->hasNasTome,
            'id' => $this->id,
            'isCurrentlyReading' => $this->isCurrentlyReading,
            'isFullyRead' => $this->isFullyRead,
            'isOneShot' => $this->isOneShot,
            'isWishlist' => $this->isWishlist,
            'lastBought' => $this->lastBought,
            'lastBoughtComplete' => $this->lastBoughtComplete,
            'lastDownloaded' => $this->lastDownloaded,
            'lastDownloadedComplete' => $this->lastDownloadedComplete,
            'lastRead' => $this->lastRead,
            'lastReadComplete' => $this->lastReadComplete,
            'latestPublishedIssue' => $this->latestPublishedIssue,
            'latestPublishedIssueComplete' => $this->latestPublishedIssueComplete,
            'latestPublishedIssueUpdatedAt' => $this->latestPublishedIssueUpdatedAt,
            'missingTomesNumbers' => $this->missingTomesNumbers,
            'ownedTomesNumbers' => $this->ownedTomesNumbers,
            'publishedDate' => $this->publishedDate,
            'publisher' => $this->publisher,
            'readTomesCount' => $this->readTomesCount,
            'status' => $this->status,
            'statusLabel' => $this->statusLabel,
            'title' => $this->title,
            'tomesCount' => $this->tomesCount,
            'type' => $this->type,
            'typeLabel' => $this->typeLabel,
            'updatedAt' => $this->updatedAt,
        ];
    }
}

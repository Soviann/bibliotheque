<?php

declare(strict_types=1);

namespace App\DTO;

/**
 * Aperçu complet du résultat d'une fusion de séries.
 */
final readonly class MergePreview implements \JsonSerializable
{
    /**
     * @param list<string>           $authors
     * @param list<int>              $sourceSeriesIds
     * @param list<MergePreviewTome> $tomes
     */
    public function __construct(
        public ?string $amazonUrl,
        public array $authors,
        public ?string $coverUrl,
        public bool $defaultTomeBought,
        public bool $defaultTomeDownloaded,
        public bool $defaultTomeRead,
        public ?string $description,
        public bool $isOneShot,
        public ?int $latestPublishedIssue,
        public bool $latestPublishedIssueComplete,
        public bool $notInterestedBuy,
        public bool $notInterestedNas,
        public ?string $publishedDate,
        public ?string $publisher,
        public array $sourceSeriesIds,
        public string $status,
        public string $title,
        public array $tomes,
        public string $type,
    ) {
    }

    /**
     * @return array{amazonUrl: ?string, authors: list<string>, coverUrl: ?string, defaultTomeBought: bool, defaultTomeDownloaded: bool, defaultTomeRead: bool, description: ?string, isOneShot: bool, latestPublishedIssue: ?int, latestPublishedIssueComplete: bool, notInterestedBuy: bool, notInterestedNas: bool, publishedDate: ?string, publisher: ?string, sourceSeriesIds: list<int>, status: string, title: string, tomes: list<MergePreviewTome>, type: string}
     */
    public function jsonSerialize(): array
    {
        return [
            'amazonUrl' => $this->amazonUrl,
            'authors' => $this->authors,
            'coverUrl' => $this->coverUrl,
            'defaultTomeBought' => $this->defaultTomeBought,
            'defaultTomeDownloaded' => $this->defaultTomeDownloaded,
            'defaultTomeRead' => $this->defaultTomeRead,
            'description' => $this->description,
            'isOneShot' => $this->isOneShot,
            'latestPublishedIssue' => $this->latestPublishedIssue,
            'latestPublishedIssueComplete' => $this->latestPublishedIssueComplete,
            'notInterestedBuy' => $this->notInterestedBuy,
            'notInterestedNas' => $this->notInterestedNas,
            'publishedDate' => $this->publishedDate,
            'publisher' => $this->publisher,
            'sourceSeriesIds' => $this->sourceSeriesIds,
            'status' => $this->status,
            'title' => $this->title,
            'tomes' => $this->tomes,
            'type' => $this->type,
        ];
    }
}

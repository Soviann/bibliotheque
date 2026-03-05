<?php

declare(strict_types=1);

namespace App\DTO;

/**
 * Aperçu complet du résultat d'une fusion de séries.
 */
readonly class MergePreview implements \JsonSerializable
{
    /**
     * @param list<string>          $authors
     * @param list<int>             $sourceSeriesIds
     * @param list<MergePreviewTome> $tomes
     */
    public function __construct(
        public array $authors,
        public ?string $coverUrl,
        public ?string $description,
        public bool $isOneShot,
        public ?int $latestPublishedIssue,
        public bool $latestPublishedIssueComplete,
        public ?string $publisher,
        public array $sourceSeriesIds,
        public string $title,
        public array $tomes,
        public string $type,
    ) {
    }

    /**
     * @return array{authors: list<string>, coverUrl: ?string, description: ?string, isOneShot: bool, latestPublishedIssue: ?int, latestPublishedIssueComplete: bool, publisher: ?string, sourceSeriesIds: list<int>, title: string, tomes: list<MergePreviewTome>, type: string}
     */
    public function jsonSerialize(): array
    {
        return [
            'authors' => $this->authors,
            'coverUrl' => $this->coverUrl,
            'description' => $this->description,
            'isOneShot' => $this->isOneShot,
            'latestPublishedIssue' => $this->latestPublishedIssue,
            'latestPublishedIssueComplete' => $this->latestPublishedIssueComplete,
            'publisher' => $this->publisher,
            'sourceSeriesIds' => $this->sourceSeriesIds,
            'title' => $this->title,
            'tomes' => $this->tomes,
            'type' => $this->type,
        ];
    }
}

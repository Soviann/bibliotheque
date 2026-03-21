<?php

declare(strict_types=1);

namespace App\Service\Merge;

use App\DTO\MergePreview;
use App\DTO\MergePreviewTome;

/**
 * Hydrate un MergePreview depuis des données JSON brutes.
 */
final class MergePreviewHydrator
{
    /**
     * Hydrate un MergePreview depuis les données JSON.
     *
     * @param array<string, mixed> $data
     *
     * @throws \InvalidArgumentException si les données sont invalides
     */
    public function hydrate(array $data): MergePreview
    {
        if (!isset($data['title']) || !\is_string($data['title'])) {
            throw new \InvalidArgumentException('Le champ "title" est requis.');
        }

        if (!isset($data['type']) || !\is_string($data['type'])) {
            throw new \InvalidArgumentException('Le champ "type" est requis.');
        }

        if (!isset($data['sourceSeriesIds']) || !\is_array($data['sourceSeriesIds'])) {
            throw new \InvalidArgumentException('Le champ "sourceSeriesIds" est requis.');
        }

        if (!isset($data['tomes']) || !\is_array($data['tomes'])) {
            throw new \InvalidArgumentException('Le champ "tomes" est requis.');
        }

        /** @var list<MergePreviewTome> $tomes */
        $tomes = \array_values(\array_map(
            static function (mixed $tomeData): MergePreviewTome {
                if (!\is_array($tomeData)) {
                    throw new \InvalidArgumentException('Chaque tome doit être un objet.');
                }

                return new MergePreviewTome(
                    bought: (bool) ($tomeData['bought'] ?? false),
                    downloaded: (bool) ($tomeData['downloaded'] ?? false),
                    isbn: isset($tomeData['isbn']) && \is_string($tomeData['isbn']) ? $tomeData['isbn'] : null,
                    number: \is_numeric($tomeData['number'] ?? null) ? (int) $tomeData['number'] : 0,
                    onNas: (bool) ($tomeData['onNas'] ?? false),
                    read: (bool) ($tomeData['read'] ?? false),
                    title: isset($tomeData['title']) && \is_string($tomeData['title']) ? $tomeData['title'] : null,
                    tomeEnd: isset($tomeData['tomeEnd']) && \is_numeric($tomeData['tomeEnd']) ? (int) $tomeData['tomeEnd'] : null,
                );
            },
            $data['tomes'],
        ));

        /** @var list<string> $authors */
        $authors = \is_array($data['authors'] ?? null) ? \array_values($data['authors']) : [];

        /** @var list<int> $sourceSeriesIds */
        $sourceSeriesIds = \array_values(\array_map(static fn (mixed $v): int => (int) (\is_numeric($v) ? $v : 0), $data['sourceSeriesIds']));

        return new MergePreview(
            amazonUrl: isset($data['amazonUrl']) && \is_string($data['amazonUrl']) ? $data['amazonUrl'] : null,
            authors: $authors,
            coverUrl: isset($data['coverUrl']) && \is_string($data['coverUrl']) ? $data['coverUrl'] : null,
            defaultTomeBought: (bool) ($data['defaultTomeBought'] ?? false),
            defaultTomeDownloaded: (bool) ($data['defaultTomeDownloaded'] ?? false),
            defaultTomeRead: (bool) ($data['defaultTomeRead'] ?? false),
            description: isset($data['description']) && \is_string($data['description']) ? $data['description'] : null,
            isOneShot: (bool) ($data['isOneShot'] ?? false),
            latestPublishedIssue: isset($data['latestPublishedIssue']) && \is_numeric($data['latestPublishedIssue']) ? (int) $data['latestPublishedIssue'] : null,
            latestPublishedIssueComplete: (bool) ($data['latestPublishedIssueComplete'] ?? false),
            notInterestedBuy: (bool) ($data['notInterestedBuy'] ?? false),
            notInterestedNas: (bool) ($data['notInterestedNas'] ?? false),
            publishedDate: isset($data['publishedDate']) && \is_string($data['publishedDate']) ? $data['publishedDate'] : null,
            publisher: isset($data['publisher']) && \is_string($data['publisher']) ? $data['publisher'] : null,
            sourceSeriesIds: $sourceSeriesIds,
            status: isset($data['status']) && \is_string($data['status']) ? $data['status'] : 'buying',
            title: $data['title'],
            tomes: $tomes,
            type: $data['type'],
        );
    }
}

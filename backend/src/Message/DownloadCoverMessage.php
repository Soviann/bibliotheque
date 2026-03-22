<?php

declare(strict_types=1);

namespace App\Message;

/**
 * Message pour le téléchargement asynchrone d'une couverture.
 */
final readonly class DownloadCoverMessage
{
    public function __construct(
        public int $seriesId,
        public string $coverUrl,
    ) {
    }
}

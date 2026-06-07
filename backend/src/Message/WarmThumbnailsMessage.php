<?php

declare(strict_types=1);

namespace App\Message;

/**
 * Message pour la génération asynchrone d'une miniature de couverture.
 */
final readonly class WarmThumbnailsMessage
{
    public function __construct(
        public string $coverImage,
    ) {
    }
}

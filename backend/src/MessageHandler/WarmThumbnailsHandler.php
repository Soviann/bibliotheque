<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Message\WarmThumbnailsMessage;
use App\Service\Cover\ThumbnailGenerator;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Handler pour la génération asynchrone d'une miniature de couverture.
 */
#[AsMessageHandler]
final readonly class WarmThumbnailsHandler
{
    public function __construct(
        private ThumbnailGenerator $thumbnailGenerator,
    ) {
    }

    public function __invoke(WarmThumbnailsMessage $message): void
    {
        $this->thumbnailGenerator->generate($message->coverImage);
    }
}

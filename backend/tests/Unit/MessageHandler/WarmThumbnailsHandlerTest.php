<?php

declare(strict_types=1);

namespace App\Tests\Unit\MessageHandler;

use App\Message\WarmThumbnailsMessage;
use App\MessageHandler\WarmThumbnailsHandler;
use App\Service\Cover\ThumbnailGenerator;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Tests unitaires pour WarmThumbnailsHandler.
 */
final class WarmThumbnailsHandlerTest extends TestCase
{
    public function testInvokeGeneratesThumbnailForCover(): void
    {
        /** @var ThumbnailGenerator&MockObject $thumbnailGenerator */
        $thumbnailGenerator = $this->createMock(ThumbnailGenerator::class);
        $thumbnailGenerator->expects(self::once())
            ->method('generate')
            ->with('cover1.webp');

        $handler = new WarmThumbnailsHandler($thumbnailGenerator);
        $handler(new WarmThumbnailsMessage('cover1.webp'));
    }
}

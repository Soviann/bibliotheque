<?php

declare(strict_types=1);

namespace App\Tests\Unit\Message;

use App\Message\DownloadCoverMessage;
use PHPUnit\Framework\TestCase;

/**
 * Tests unitaires pour le message de téléchargement de couverture asynchrone.
 */
final class DownloadCoverMessageTest extends TestCase
{
    public function testMessageHoldsSeriesIdAndUrl(): void
    {
        $message = new DownloadCoverMessage(42, 'https://example.com/cover.jpg');

        self::assertSame(42, $message->seriesId);
        self::assertSame('https://example.com/cover.jpg', $message->coverUrl);
    }
}

<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Lookup;

use App\Service\Lookup\GoogleBooksUrlHelper;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Tests unitaires pour GoogleBooksUrlHelper.
 */
final class GoogleBooksUrlHelperTest extends TestCase
{
    #[Test]
    public function nonGoogleBooksUrlIsReturnedAsIs(): void
    {
        $url = 'https://example.com/image.jpg';
        self::assertSame($url, GoogleBooksUrlHelper::optimizeThumbnailUrl($url));
    }

    #[Test]
    public function httpIsUpgradedToHttps(): void
    {
        $url = 'http://books.google.com/books/content?id=abc&zoom=0';
        $result = GoogleBooksUrlHelper::optimizeThumbnailUrl($url);
        self::assertStringStartsWith('https://', $result);
    }

    #[Test]
    public function zoomOneIsReplacedByZoomZero(): void
    {
        $url = 'https://books.google.com/books/content?id=abc&zoom=1';
        $result = GoogleBooksUrlHelper::optimizeThumbnailUrl($url);
        self::assertStringContainsString('zoom=0', $result);
        self::assertStringNotContainsString('zoom=1', $result);
    }

    #[Test]
    public function edgeCurlIsRemoved(): void
    {
        $url = 'https://books.google.com/books/content?id=abc&edge=curl&zoom=1';
        $result = GoogleBooksUrlHelper::optimizeThumbnailUrl($url);
        self::assertStringNotContainsString('edge=curl', $result);
    }

    #[Test]
    public function trailingAmpersandIsRemoved(): void
    {
        $url = 'https://books.google.com/books/content?id=abc&edge=curl';
        $result = GoogleBooksUrlHelper::optimizeThumbnailUrl($url);
        self::assertStringEndsNotWith('&', $result);
    }

    #[Test]
    public function fullOptimization(): void
    {
        $url = 'http://books.google.com/books/content?id=abc&zoom=1&edge=curl';
        $expected = 'https://books.google.com/books/content?id=abc&zoom=0';
        self::assertSame($expected, GoogleBooksUrlHelper::optimizeThumbnailUrl($url));
    }
}

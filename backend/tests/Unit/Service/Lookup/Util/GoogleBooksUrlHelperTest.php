<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Lookup\Util;

use App\Service\Lookup\Util\GoogleBooksUrlHelper;
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
        self::assertNotNull($result);
        self::assertStringStartsWith('https://', $result);
    }

    #[Test]
    public function zoomOneIsReplacedByZoomZero(): void
    {
        $url = 'https://books.google.com/books/content?id=abc&zoom=1';
        $result = GoogleBooksUrlHelper::optimizeThumbnailUrl($url);
        self::assertNotNull($result);
        self::assertStringContainsString('zoom=0', $result);
        self::assertStringNotContainsString('zoom=1', $result);
    }

    #[Test]
    public function edgeCurlIsRemoved(): void
    {
        $url = 'https://books.google.com/books/content?id=abc&edge=curl&zoom=1';
        $result = GoogleBooksUrlHelper::optimizeThumbnailUrl($url);
        self::assertNotNull($result);
        self::assertStringNotContainsString('edge=curl', $result);
    }

    #[Test]
    public function edgeCurlAsFirstParamIsCleanedWithoutLeadingAmpersand(): void
    {
        $url = 'https://books.google.com/books/content?edge=curl&id=abc';
        $result = GoogleBooksUrlHelper::optimizeThumbnailUrl($url);
        self::assertSame('https://books.google.com/books/content?id=abc', $result);
    }

    #[Test]
    public function edgeCurlAsOnlyParamIsCleanedWithoutTrailingQuestionMark(): void
    {
        $url = 'https://books.google.com/books/content?edge=curl';
        $result = GoogleBooksUrlHelper::optimizeThumbnailUrl($url);
        self::assertSame('https://books.google.com/books/content', $result);
    }

    #[Test]
    public function trailingAmpersandIsRemoved(): void
    {
        $url = 'https://books.google.com/books/content?id=abc&edge=curl';
        $result = GoogleBooksUrlHelper::optimizeThumbnailUrl($url);
        self::assertNotNull($result);
        self::assertStringEndsNotWith('&', $result);
    }

    #[Test]
    public function fullOptimization(): void
    {
        $url = 'http://books.google.com/books/content?id=abc&zoom=1&edge=curl';
        $expected = 'https://books.google.com/books/content?id=abc&zoom=0';
        self::assertSame($expected, GoogleBooksUrlHelper::optimizeThumbnailUrl($url));
    }

    #[Test]
    public function placeholderUrlsReturnNull(): void
    {
        self::assertNull(GoogleBooksUrlHelper::optimizeThumbnailUrl('https://books.google.com/books/content?id=abc&gbs_preview_button=true'));
        self::assertNull(GoogleBooksUrlHelper::optimizeThumbnailUrl('https://books.google.com/books/content?id=abc&no_cover=1'));
    }
}

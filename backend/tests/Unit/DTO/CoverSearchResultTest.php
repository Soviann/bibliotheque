<?php

declare(strict_types=1);

namespace App\Tests\Unit\DTO;

use App\DTO\CoverSearchResult;
use PHPUnit\Framework\TestCase;

/**
 * Tests unitaires pour CoverSearchResult.
 */
final class CoverSearchResultTest extends TestCase
{
    public function testConstructorAndProperties(): void
    {
        $result = new CoverSearchResult(
            height: 600,
            thumbnail: 'https://example.com/thumb.jpg',
            title: 'Naruto Vol. 1',
            url: 'https://example.com/cover.jpg',
            width: 400,
        );

        self::assertSame(600, $result->height);
        self::assertSame('https://example.com/thumb.jpg', $result->thumbnail);
        self::assertSame('Naruto Vol. 1', $result->title);
        self::assertSame('https://example.com/cover.jpg', $result->url);
        self::assertSame(400, $result->width);
    }

    public function testJsonSerialize(): void
    {
        $result = new CoverSearchResult(
            height: 800,
            thumbnail: 'https://example.com/thumb.jpg',
            title: 'One Piece',
            url: 'https://example.com/cover.jpg',
            width: 500,
        );

        $json = $result->jsonSerialize();

        self::assertSame([
            'height' => 800,
            'thumbnail' => 'https://example.com/thumb.jpg',
            'title' => 'One Piece',
            'url' => 'https://example.com/cover.jpg',
            'width' => 500,
        ], $json);
    }
}

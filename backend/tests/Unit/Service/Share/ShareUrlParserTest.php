<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Share;

use App\DTO\Share\ShareUrlInfo;
use App\Enum\ComicType;
use App\Service\Share\ShareUrlParser;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ShareUrlParserTest extends TestCase
{
    private ShareUrlParser $parser;

    protected function setUp(): void
    {
        $this->parser = new ShareUrlParser();
    }

    /**
     * @return iterable<string, array{string, string, string|null, string|null, ComicType|null}>
     */
    public static function provideUrls(): iterable
    {
        yield 'amazon.fr dp ISBN-10' => [
            'https://www.amazon.fr/dp/2723492532',
            ShareUrlInfo::SOURCE_AMAZON,
            '2723492532',
            null,
            null,
        ];

        yield 'amazon.fr gp/product ISBN-10 trailing slash' => [
            'https://www.amazon.fr/gp/product/2723492532/',
            ShareUrlInfo::SOURCE_AMAZON,
            '2723492532',
            null,
            null,
        ];

        yield 'amazon.com dp ASIN non-ISBN' => [
            'https://www.amazon.com/dp/B08XYZ1234',
            ShareUrlInfo::SOURCE_AMAZON,
            null,
            null,
            null,
        ];

        yield 'bedetheque serie slug' => [
            'https://www.bedetheque.com/serie-12345-BD-Asterix.html',
            ShareUrlInfo::SOURCE_BEDETHEQUE,
            null,
            'BD-Asterix',
            ComicType::BD,
        ];

        yield 'wikipedia fr article with underscores' => [
            'https://fr.wikipedia.org/wiki/Lanfeust_de_Troy',
            ShareUrlInfo::SOURCE_WIKIPEDIA,
            null,
            'Lanfeust de Troy',
            null,
        ];

        yield 'wikipedia fr article with URL encoding' => [
            'https://fr.wikipedia.org/wiki/Acad%C3%A9mie_des_chasseurs_de_primes',
            ShareUrlInfo::SOURCE_WIKIPEDIA,
            null,
            'Académie des chasseurs de primes',
            null,
        ];

        yield 'unknown domain' => [
            'https://example.com/foo',
            ShareUrlInfo::SOURCE_UNKNOWN,
            null,
            null,
            null,
        ];

        yield 'invalid URL' => [
            'not-a-url',
            ShareUrlInfo::SOURCE_UNKNOWN,
            null,
            null,
            null,
        ];
    }

    #[DataProvider('provideUrls')]
    public function testParse(
        string $url,
        string $expectedSource,
        ?string $expectedIsbn,
        ?string $expectedTitleHint,
        ?ComicType $expectedType,
    ): void {
        $result = $this->parser->parse($url);

        self::assertSame($expectedSource, $result->source);
        self::assertSame($expectedIsbn, $result->isbn);
        self::assertSame($expectedType, $result->type);
        self::assertSame($url, $result->originalUrl);

        if (null === $expectedTitleHint) {
            self::assertNull($result->titleHint);
        } else {
            self::assertNotNull($result->titleHint);
            self::assertStringContainsString(
                \str_replace('-', ' ', $expectedTitleHint),
                \str_replace('-', ' ', $result->titleHint),
            );
        }
    }

    public function testParsePreservesOriginalUrlForAllSources(): void
    {
        $urls = [
            'https://www.amazon.fr/dp/2723492532',
            'https://www.bedetheque.com/serie-12345-BD-Asterix.html',
            'https://fr.wikipedia.org/wiki/Lanfeust_de_Troy',
            'https://example.com/foo',
            'not-a-url',
        ];

        foreach ($urls as $url) {
            $result = $this->parser->parse($url);
            self::assertSame($url, $result->originalUrl, \sprintf('originalUrl not preserved for: %s', $url));
        }
    }
}

<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Lookup\Gemini;

use App\Service\Lookup\Gemini\GeminiJsonParser;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Tests unitaires pour GeminiJsonParser.
 */
final class GeminiJsonParserTest extends TestCase
{
    #[Test]
    public function parsesPlainJson(): void
    {
        $result = GeminiJsonParser::parseJsonFromText('{"key": "value"}');
        self::assertSame(['key' => 'value'], $result);
    }

    #[Test]
    public function parsesJsonWrappedInMarkdownCodeBlock(): void
    {
        $text = "```json\n{\"key\": \"value\"}\n```";
        $result = GeminiJsonParser::parseJsonFromText($text);
        self::assertSame(['key' => 'value'], $result);
    }

    #[Test]
    public function parsesCodeBlockWithoutLanguageHint(): void
    {
        $text = "```\n{\"key\": \"value\"}\n```";
        $result = GeminiJsonParser::parseJsonFromText($text);
        self::assertSame(['key' => 'value'], $result);
    }

    #[Test]
    public function returnsNullForInvalidJson(): void
    {
        self::assertNull(GeminiJsonParser::parseJsonFromText('not json'));
    }

    #[Test]
    public function returnsNullForScalarJson(): void
    {
        self::assertNull(GeminiJsonParser::parseJsonFromText('"just a string"'));
    }

    #[Test]
    public function handlesWhitespace(): void
    {
        $text = "  \n```json\n  {\"a\": 1}  \n```\n  ";
        $result = GeminiJsonParser::parseJsonFromText($text);
        self::assertSame(['a' => 1], $result);
    }

    #[Test]
    public function parsesArray(): void
    {
        $result = GeminiJsonParser::parseJsonFromText('[{"a": 1}, {"b": 2}]');
        self::assertSame([['a' => 1], ['b' => 2]], $result);
    }
}

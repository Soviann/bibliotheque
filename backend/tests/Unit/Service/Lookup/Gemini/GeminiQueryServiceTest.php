<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Lookup\Gemini;

use App\Service\Lookup\Gemini\GeminiClientPool;
use App\Service\Lookup\Gemini\GeminiQueryService;
use PHPUnit\Framework\TestCase;

class GeminiQueryServiceTest extends TestCase
{
    public function testQueryJsonArrayReturnsArray(): void
    {
        $pool = $this->createMock(GeminiClientPool::class);
        $pool->expects(self::once())
            ->method('executeWithRetry')
            ->willReturnCallback(static function (callable $callback): string {
                return '[{"title": "Test", "type": "bd"}]';
            });

        $service = new GeminiQueryService($pool);
        $result = $service->queryJsonArray('prompt');

        self::assertCount(1, $result);
        self::assertSame('Test', $result[0]['title']);
    }

    public function testQueryJsonArrayReturnsEmptyOnInvalidJson(): void
    {
        $pool = $this->createMock(GeminiClientPool::class);
        $pool->expects(self::once())
            ->method('executeWithRetry')
            ->willReturnCallback(static fn (callable $callback): string => 'not json');

        $service = new GeminiQueryService($pool);
        $result = $service->queryJsonArray('prompt');

        self::assertSame([], $result);
    }

    public function testQueryJsonArrayHandlesMarkdownWrappedJson(): void
    {
        $pool = $this->createMock(GeminiClientPool::class);
        $pool->expects(self::once())
            ->method('executeWithRetry')
            ->willReturnCallback(static fn (callable $callback): string => "```json\n[{\"title\": \"Wrapped\"}]\n```");

        $service = new GeminiQueryService($pool);
        $result = $service->queryJsonArray('prompt');

        self::assertCount(1, $result);
        self::assertSame('Wrapped', $result[0]['title']);
    }
}

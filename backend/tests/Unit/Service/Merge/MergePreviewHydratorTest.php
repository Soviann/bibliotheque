<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Merge;

use App\DTO\MergePreview;
use App\Service\Merge\MergePreviewHydrator;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Tests unitaires pour MergePreviewHydrator.
 */
final class MergePreviewHydratorTest extends TestCase
{
    private MergePreviewHydrator $hydrator;

    protected function setUp(): void
    {
        $this->hydrator = new MergePreviewHydrator();
    }

    #[Test]
    public function hydrateReturnsValidMergePreview(): void
    {
        $data = [
            'authors' => ['Oda'],
            'coverUrl' => 'https://example.com/cover.jpg',
            'description' => 'Un manga populaire',
            'isOneShot' => false,
            'latestPublishedIssue' => 100,
            'latestPublishedIssueComplete' => true,
            'publisher' => 'Glénat',
            'sourceSeriesIds' => [1, 2, 3],
            'title' => 'One Piece',
            'tomes' => [
                [
                    'bought' => true,
                    'downloaded' => false,
                    'isbn' => '978-2-7234-1234-5',
                    'number' => 1,
                    'onNas' => true,
                    'read' => true,
                    'title' => 'Romance Dawn',
                    'tomeEnd' => null,
                ],
            ],
            'type' => 'manga',
        ];

        $result = $this->hydrator->hydrate($data);

        self::assertInstanceOf(MergePreview::class, $result);
        self::assertSame('One Piece', $result->title);
        self::assertSame('manga', $result->type);
        self::assertSame([1, 2, 3], $result->sourceSeriesIds);
        self::assertSame(['Oda'], $result->authors);
        self::assertSame('https://example.com/cover.jpg', $result->coverUrl);
        self::assertSame('Un manga populaire', $result->description);
        self::assertFalse($result->isOneShot);
        self::assertSame(100, $result->latestPublishedIssue);
        self::assertTrue($result->latestPublishedIssueComplete);
        self::assertSame('Glénat', $result->publisher);
        self::assertCount(1, $result->tomes);
        self::assertSame(1, $result->tomes[0]->number);
        self::assertSame('Romance Dawn', $result->tomes[0]->title);
        self::assertTrue($result->tomes[0]->bought);
        self::assertSame('978-2-7234-1234-5', $result->tomes[0]->isbn);
    }

    #[Test]
    public function hydrateThrowsWhenMissingTitle(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('title');

        $this->hydrator->hydrate([
            'sourceSeriesIds' => [1],
            'tomes' => [],
            'type' => 'manga',
        ]);
    }

    #[Test]
    public function hydrateThrowsWhenMissingType(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('type');

        $this->hydrator->hydrate([
            'sourceSeriesIds' => [1],
            'title' => 'Test',
            'tomes' => [],
        ]);
    }

    #[Test]
    public function hydrateThrowsWhenMissingSourceSeriesIds(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('sourceSeriesIds');

        $this->hydrator->hydrate([
            'title' => 'Test',
            'tomes' => [],
            'type' => 'manga',
        ]);
    }

    #[Test]
    public function hydrateThrowsWhenMissingTomes(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('tomes');

        $this->hydrator->hydrate([
            'sourceSeriesIds' => [1],
            'title' => 'Test',
            'type' => 'manga',
        ]);
    }

    #[Test]
    public function hydrateHandlesMinimalTomeData(): void
    {
        $data = [
            'sourceSeriesIds' => [1],
            'title' => 'Test',
            'tomes' => [
                ['number' => 1],
            ],
            'type' => 'bd',
        ];

        $result = $this->hydrator->hydrate($data);

        self::assertCount(1, $result->tomes);
        self::assertSame(1, $result->tomes[0]->number);
        self::assertFalse($result->tomes[0]->bought);
        self::assertNull($result->tomes[0]->isbn);
        self::assertNull($result->tomes[0]->title);
    }

    #[Test]
    public function hydrateHandlesOptionalFieldsDefault(): void
    {
        $data = [
            'sourceSeriesIds' => [1],
            'title' => 'Test',
            'tomes' => [],
            'type' => 'bd',
        ];

        $result = $this->hydrator->hydrate($data);

        self::assertNull($result->coverUrl);
        self::assertNull($result->description);
        self::assertFalse($result->isOneShot);
        self::assertNull($result->latestPublishedIssue);
        self::assertFalse($result->latestPublishedIssueComplete);
        self::assertNull($result->publisher);
        self::assertSame([], $result->authors);
    }
}

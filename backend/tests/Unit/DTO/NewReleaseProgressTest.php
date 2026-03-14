<?php

declare(strict_types=1);

namespace App\Tests\Unit\DTO;

use App\DTO\NewReleaseProgress;
use App\Enum\BatchLookupStatus;
use PHPUnit\Framework\TestCase;

/**
 * Tests unitaires pour le DTO NewReleaseProgress.
 */
final class NewReleaseProgressTest extends TestCase
{
    public function testConstructAndProperties(): void
    {
        $progress = new NewReleaseProgress(
            current: 3,
            newLatestIssue: 15,
            previousLatestIssue: 12,
            seriesTitle: 'Naruto',
            status: BatchLookupStatus::UPDATED,
            stoppedByRateLimit: false,
            total: 10,
        );

        self::assertSame(3, $progress->current);
        self::assertSame(15, $progress->newLatestIssue);
        self::assertSame(12, $progress->previousLatestIssue);
        self::assertSame('Naruto', $progress->seriesTitle);
        self::assertSame(BatchLookupStatus::UPDATED, $progress->status);
        self::assertFalse($progress->stoppedByRateLimit);
        self::assertSame(10, $progress->total);
    }

    public function testJsonSerialize(): void
    {
        $progress = new NewReleaseProgress(
            current: 1,
            newLatestIssue: 5,
            previousLatestIssue: 3,
            seriesTitle: 'One Piece',
            status: BatchLookupStatus::UPDATED,
            stoppedByRateLimit: false,
            total: 50,
        );

        $json = $progress->jsonSerialize();

        self::assertSame([
            'current' => 1,
            'newLatestIssue' => 5,
            'previousLatestIssue' => 3,
            'seriesTitle' => 'One Piece',
            'status' => 'updated',
            'stoppedByRateLimit' => false,
            'total' => 50,
        ], $json);
    }

    public function testJsonSerializeWithNullValues(): void
    {
        $progress = new NewReleaseProgress(
            current: 2,
            newLatestIssue: null,
            previousLatestIssue: null,
            seriesTitle: 'Asterix',
            status: BatchLookupStatus::SKIPPED,
            stoppedByRateLimit: false,
            total: 10,
        );

        $json = $progress->jsonSerialize();

        self::assertNull($json['newLatestIssue']);
        self::assertNull($json['previousLatestIssue']);
    }

    public function testStoppedByRateLimit(): void
    {
        $progress = new NewReleaseProgress(
            current: 5,
            newLatestIssue: null,
            previousLatestIssue: null,
            seriesTitle: 'Dragon Ball',
            status: BatchLookupStatus::FAILED,
            stoppedByRateLimit: true,
            total: 20,
        );

        self::assertTrue($progress->stoppedByRateLimit);
    }
}

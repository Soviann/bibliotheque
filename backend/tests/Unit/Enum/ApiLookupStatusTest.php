<?php

declare(strict_types=1);

namespace App\Tests\Unit\Enum;

use App\Enum\ApiLookupStatus;
use PHPUnit\Framework\TestCase;

/**
 * Tests unitaires pour l'enum ApiLookupStatus.
 */
final class ApiLookupStatusTest extends TestCase
{
    // ---------------------------------------------------------------
    // Valeurs des cases
    // ---------------------------------------------------------------

    public function testErrorValue(): void
    {
        self::assertSame('error', ApiLookupStatus::ERROR->value);
    }

    public function testNotFoundValue(): void
    {
        self::assertSame('not_found', ApiLookupStatus::NOT_FOUND->value);
    }

    public function testRateLimitedValue(): void
    {
        self::assertSame('rate_limited', ApiLookupStatus::RATE_LIMITED->value);
    }

    public function testSuccessValue(): void
    {
        self::assertSame('success', ApiLookupStatus::SUCCESS->value);
    }

    public function testTimeoutValue(): void
    {
        self::assertSame('timeout', ApiLookupStatus::TIMEOUT->value);
    }

    // ---------------------------------------------------------------
    // Nombre de cases
    // ---------------------------------------------------------------

    public function testCaseCount(): void
    {
        self::assertCount(5, ApiLookupStatus::cases());
    }

    // ---------------------------------------------------------------
    // Instanciation depuis la valeur
    // ---------------------------------------------------------------

    public function testFromValue(): void
    {
        self::assertSame(ApiLookupStatus::ERROR, ApiLookupStatus::from('error'));
        self::assertSame(ApiLookupStatus::NOT_FOUND, ApiLookupStatus::from('not_found'));
        self::assertSame(ApiLookupStatus::RATE_LIMITED, ApiLookupStatus::from('rate_limited'));
        self::assertSame(ApiLookupStatus::SUCCESS, ApiLookupStatus::from('success'));
        self::assertSame(ApiLookupStatus::TIMEOUT, ApiLookupStatus::from('timeout'));
    }

    public function testTryFromInvalidValueReturnsNull(): void
    {
        self::assertNull(ApiLookupStatus::tryFrom('invalid'));
    }
}

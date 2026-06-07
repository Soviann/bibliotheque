<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Lookup\Gemini;

use App\Service\Lookup\Gemini\GeminiCircuitBreaker;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Clock\MockClock;

/**
 * Tests unitaires pour le disjoncteur Gemini.
 */
final class GeminiCircuitBreakerTest extends TestCase
{
    public function testClosedByDefault(): void
    {
        $breaker = new GeminiCircuitBreaker(new ArrayAdapter(), new MockClock('2026-06-07 12:00:00', new \DateTimeZone('UTC')));

        self::assertFalse($breaker->isOpen());
        self::assertNull($breaker->openUntil());
        self::assertSame(0, $breaker->retryAfterSeconds());
    }

    public function testOpenStaysOpenUntilNextPacificMidnight(): void
    {
        // 2026-06-07 12:00 UTC = 05:00 heure du Pacifique (PDT, UTC-7).
        // Le prochain reset est le 2026-06-08 00:00 PDT = 2026-06-08 07:00 UTC.
        $clock = new MockClock('2026-06-07 12:00:00', new \DateTimeZone('UTC'));
        $breaker = new GeminiCircuitBreaker(new ArrayAdapter(), $clock);

        $until = $breaker->open();

        self::assertTrue($breaker->isOpen());
        self::assertSame('2026-06-08 07:00:00', $until->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s'));
        self::assertSame(19 * 3600, $breaker->retryAfterSeconds());
    }

    public function testReopensAfterResetTimePasses(): void
    {
        $clock = new MockClock('2026-06-07 12:00:00', new \DateTimeZone('UTC'));
        $breaker = new GeminiCircuitBreaker(new ArrayAdapter(), $clock);

        $breaker->open();
        self::assertTrue($breaker->isOpen());

        // Avance au-delà du reset.
        $clock->modify('+20 hours');

        self::assertFalse($breaker->isOpen());
        self::assertNull($breaker->openUntil());
    }

    public function testCloseClearsState(): void
    {
        $clock = new MockClock('2026-06-07 12:00:00', new \DateTimeZone('UTC'));
        $breaker = new GeminiCircuitBreaker(new ArrayAdapter(), $clock);

        $breaker->open();
        self::assertTrue($breaker->isOpen());

        $breaker->close();

        self::assertFalse($breaker->isOpen());
    }
}

<?php

declare(strict_types=1);

namespace App\Tests\Unit\Enum;

use App\Enum\EnrichmentConfidence;
use PHPUnit\Framework\TestCase;

/**
 * Tests unitaires pour l'enum EnrichmentConfidence.
 */
final class EnrichmentConfidenceTest extends TestCase
{
    public function testFromScoreReturnsHighAt085(): void
    {
        self::assertSame(EnrichmentConfidence::HIGH, EnrichmentConfidence::fromScore(0.85));
    }

    public function testFromScoreReturnsHighAt1(): void
    {
        self::assertSame(EnrichmentConfidence::HIGH, EnrichmentConfidence::fromScore(1.0));
    }

    public function testFromScoreReturnsMediumAt084(): void
    {
        self::assertSame(EnrichmentConfidence::MEDIUM, EnrichmentConfidence::fromScore(0.84));
    }

    public function testFromScoreReturnsMediumAt070(): void
    {
        self::assertSame(EnrichmentConfidence::MEDIUM, EnrichmentConfidence::fromScore(0.70));
    }

    public function testFromScoreReturnsLowAt069(): void
    {
        self::assertSame(EnrichmentConfidence::LOW, EnrichmentConfidence::fromScore(0.69));
    }

    public function testFromScoreReturnsLowAt0(): void
    {
        self::assertSame(EnrichmentConfidence::LOW, EnrichmentConfidence::fromScore(0.0));
    }
}

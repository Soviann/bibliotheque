<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * Niveau de confiance d'un enrichissement, dérivé d'un score flottant (0-1).
 */
enum EnrichmentConfidence: string
{
    case HIGH = 'high';
    case LOW = 'low';
    case MEDIUM = 'medium';

    private const float HIGH_THRESHOLD = 0.85;
    private const float MEDIUM_THRESHOLD = 0.70;

    public static function fromScore(float $score): self
    {
        return match (true) {
            $score >= self::HIGH_THRESHOLD => self::HIGH,
            $score >= self::MEDIUM_THRESHOLD => self::MEDIUM,
            default => self::LOW,
        };
    }
}

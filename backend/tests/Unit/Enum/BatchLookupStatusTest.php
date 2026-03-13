<?php

declare(strict_types=1);

namespace App\Tests\Unit\Enum;

use App\Enum\BatchLookupStatus;
use PHPUnit\Framework\TestCase;

/**
 * Tests unitaires pour l'enum BatchLookupStatus.
 */
final class BatchLookupStatusTest extends TestCase
{
    // ---------------------------------------------------------------
    // Valeurs des cases
    // ---------------------------------------------------------------

    public function testFailedValue(): void
    {
        self::assertSame('failed', BatchLookupStatus::FAILED->value);
    }

    public function testSkippedValue(): void
    {
        self::assertSame('skipped', BatchLookupStatus::SKIPPED->value);
    }

    public function testUpdatedValue(): void
    {
        self::assertSame('updated', BatchLookupStatus::UPDATED->value);
    }

    // ---------------------------------------------------------------
    // Labels
    // ---------------------------------------------------------------

    public function testFailedLabel(): void
    {
        self::assertSame('Échoué', BatchLookupStatus::FAILED->getLabel());
    }

    public function testSkippedLabel(): void
    {
        self::assertSame('Ignoré', BatchLookupStatus::SKIPPED->getLabel());
    }

    public function testUpdatedLabel(): void
    {
        self::assertSame('Mis à jour', BatchLookupStatus::UPDATED->getLabel());
    }

    // ---------------------------------------------------------------
    // Nombre de cases
    // ---------------------------------------------------------------

    public function testCaseCount(): void
    {
        self::assertCount(3, BatchLookupStatus::cases());
    }

    // ---------------------------------------------------------------
    // Instanciation depuis la valeur
    // ---------------------------------------------------------------

    public function testFromValue(): void
    {
        self::assertSame(BatchLookupStatus::FAILED, BatchLookupStatus::from('failed'));
        self::assertSame(BatchLookupStatus::SKIPPED, BatchLookupStatus::from('skipped'));
        self::assertSame(BatchLookupStatus::UPDATED, BatchLookupStatus::from('updated'));
    }

    public function testTryFromInvalidValueReturnsNull(): void
    {
        self::assertNull(BatchLookupStatus::tryFrom('invalid')); // @phpstan-ignore staticMethod.alreadyNarrowedType
    }
}

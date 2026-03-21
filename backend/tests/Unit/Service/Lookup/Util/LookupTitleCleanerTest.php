<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Lookup\Util;

use App\Service\Lookup\Util\LookupTitleCleaner;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Tests unitaires pour LookupTitleCleaner.
 */
final class LookupTitleCleanerTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string}>
     */
    public static function cleanTitleProvider(): iterable
    {
        yield 'titre simple sans suffixe' => ['One Piece', 'One Piece'];
        yield 'suffixe Tome avec tiret' => ['One Piece - Tome 42', 'One Piece'];
        yield 'suffixe T. avec tiret' => ['Dragon Ball - T.5', 'Dragon Ball'];
        yield 'suffixe Vol. collé' => ['Naruto Vol.12', 'Naruto'];
        yield 'suffixe Volume avec espace' => ['Bleach Volume 3', 'Bleach'];
        yield 'suffixe # numéro' => ['My Hero Academia #25', 'My Hero Academia'];
        yield 'suffixe (N)' => ['Bleach (3)', 'Bleach'];
        yield 'suffixe numéro nu' => ['Naruto 42', 'Naruto'];
        yield 'suffixe avec tiret long' => ['One Piece — T12', 'One Piece'];
        yield 'suffixe V majuscule' => ['Demon Slayer V.5', 'Demon Slayer'];
        yield 'espaces multiples avant suffixe' => ['Test  Tome 1', 'Test'];
        yield 'titre vide après nettoyage' => ['42', '42'];
    }

    #[DataProvider('cleanTitleProvider')]
    public function testClean(string $input, string $expected): void
    {
        self::assertSame($expected, LookupTitleCleaner::clean($input));
    }
}

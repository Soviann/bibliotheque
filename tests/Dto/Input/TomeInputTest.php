<?php

declare(strict_types=1);

namespace App\Tests\Dto\Input;

use App\Dto\Input\TomeInput;
use PHPUnit\Framework\TestCase;

/**
 * Tests unitaires pour le DTO TomeInput.
 */
class TomeInputTest extends TestCase
{
    /**
     * Teste les valeurs par défaut.
     */
    public function testDefaultValues(): void
    {
        $input = new TomeInput();

        self::assertSame(0, $input->number);
        self::assertFalse($input->bought);
        self::assertFalse($input->downloaded);
        self::assertNull($input->isbn);
        self::assertFalse($input->onNas);
        self::assertFalse($input->read);
        self::assertNull($input->title);
    }
}

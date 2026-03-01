<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Service\UploadHandlerInterface;
use App\Service\VichUploadHandlerAdapter;
use PHPUnit\Framework\TestCase;

/**
 * Tests unitaires pour VichUploadHandlerAdapter.
 */
final class VichUploadHandlerAdapterTest extends TestCase
{
    /**
     * Teste que VichUploadHandlerAdapter implémente UploadHandlerInterface.
     */
    public function testImplementsUploadHandlerInterface(): void
    {
        self::assertTrue(
            \is_subclass_of(VichUploadHandlerAdapter::class, UploadHandlerInterface::class),
        );
    }

    /**
     * Teste que la méthode remove est déclarée sur l'adaptateur.
     */
    public function testHasRemoveMethod(): void
    {
        $reflection = new \ReflectionClass(VichUploadHandlerAdapter::class);
        $method = $reflection->getMethod('remove');

        self::assertTrue($method->isPublic());
        self::assertSame(2, $method->getNumberOfParameters());

        $params = $method->getParameters();
        self::assertSame('obj', $params[0]->getName());
        self::assertSame('fieldName', $params[1]->getName());
    }
}

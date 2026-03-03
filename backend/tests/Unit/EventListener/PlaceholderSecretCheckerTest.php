<?php

declare(strict_types=1);

namespace App\Tests\Unit\EventListener;

use App\EventListener\PlaceholderSecretChecker;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\Event\RequestEvent;

/**
 * Tests unitaires pour PlaceholderSecretChecker.
 */
final class PlaceholderSecretCheckerTest extends TestCase
{
    private const string PLACEHOLDER_APP_SECRET = 'change_this_secret_in_env_local';
    private const string PLACEHOLDER_JWT_PASSPHRASE = 'change_this_passphrase_in_env_local';
    private const string REAL_APP_SECRET = 'a_real_secret_value_abc123';
    private const string REAL_JWT_PASSPHRASE = 'a_real_passphrase_value_xyz789';

    /**
     * Teste qu'une exception est lev\u00e9e en prod avec le placeholder APP_SECRET.
     */
    public function testProdMainRequestWithPlaceholderAppSecretThrowsException(): void
    {
        $checker = new PlaceholderSecretChecker(
            self::PLACEHOLDER_APP_SECRET,
            'prod',
            self::REAL_JWT_PASSPHRASE,
        );

        $event = $this->createMainRequestEvent();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/APP_SECRET/');

        $checker->onKernelRequest($event);
    }

    /**
     * Teste qu'une exception est lev\u00e9e en prod avec le placeholder JWT_PASSPHRASE.
     */
    public function testProdMainRequestWithPlaceholderJwtPassphraseThrowsException(): void
    {
        $checker = new PlaceholderSecretChecker(
            self::REAL_APP_SECRET,
            'prod',
            self::PLACEHOLDER_JWT_PASSPHRASE,
        );

        $event = $this->createMainRequestEvent();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/JWT_PASSPHRASE/');

        $checker->onKernelRequest($event);
    }

    /**
     * Teste qu'une exception est lev\u00e9e en prod avec les deux placeholders, mentionnant les deux.
     */
    public function testProdMainRequestWithBothPlaceholdersThrowsExceptionMentioningBoth(): void
    {
        $checker = new PlaceholderSecretChecker(
            self::PLACEHOLDER_APP_SECRET,
            'prod',
            self::PLACEHOLDER_JWT_PASSPHRASE,
        );

        $event = $this->createMainRequestEvent();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/APP_SECRET.*JWT_PASSPHRASE/');

        $checker->onKernelRequest($event);
    }

    /**
     * Teste qu'aucune exception n'est lev\u00e9e en prod avec des valeurs r\u00e9elles.
     */
    public function testProdMainRequestWithRealValuesDoesNotThrow(): void
    {
        $checker = new PlaceholderSecretChecker(
            self::REAL_APP_SECRET,
            'prod',
            self::REAL_JWT_PASSPHRASE,
        );

        $event = $this->createMainRequestEvent();

        $checker->onKernelRequest($event);

        // Si on arrive ici, aucune exception n'a \u00e9t\u00e9 lev\u00e9e
        $this->addToAssertionCount(1);
    }

    /**
     * Teste qu'aucune exception n'est lev\u00e9e en environnement non-prod m\u00eame avec des placeholders.
     */
    public function testNonProdEnvironmentWithPlaceholdersDoesNotThrow(): void
    {
        $checker = new PlaceholderSecretChecker(
            self::PLACEHOLDER_APP_SECRET,
            'test',
            self::PLACEHOLDER_JWT_PASSPHRASE,
        );

        $event = $this->createMainRequestEvent();

        $checker->onKernelRequest($event);

        $this->addToAssertionCount(1);
    }

    /**
     * Teste qu'aucune exception n'est lev\u00e9e pour une sous-requ\u00eate en prod.
     */
    public function testProdSubRequestWithPlaceholdersDoesNotThrow(): void
    {
        $checker = new PlaceholderSecretChecker(
            self::PLACEHOLDER_APP_SECRET,
            'prod',
            self::PLACEHOLDER_JWT_PASSPHRASE,
        );

        $event = $this->createSubRequestEvent();

        $checker->onKernelRequest($event);

        $this->addToAssertionCount(1);
    }

    private function createMainRequestEvent(): RequestEvent&Stub
    {
        $event = $this->createStub(RequestEvent::class);
        $event->method('isMainRequest')->willReturn(true);

        return $event;
    }

    private function createSubRequestEvent(): RequestEvent&Stub
    {
        $event = $this->createStub(RequestEvent::class);
        $event->method('isMainRequest')->willReturn(false);

        return $event;
    }
}

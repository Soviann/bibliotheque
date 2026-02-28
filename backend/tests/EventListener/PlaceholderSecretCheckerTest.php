<?php

declare(strict_types=1);

namespace App\Tests\EventListener;

use App\EventListener\PlaceholderSecretChecker;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

/**
 * Tests unitaires pour le vérificateur de secrets placeholder.
 */
class PlaceholderSecretCheckerTest extends TestCase
{
    /**
     * Teste qu'une exception est levée en prod avec APP_SECRET placeholder.
     */
    public function testThrowsExceptionForPlaceholderAppSecretInProd(): void
    {
        $checker = new PlaceholderSecretChecker(
            appSecret: 'change_this_secret_in_env_local',
            env: 'prod',
            jwtPassphrase: 'real_passphrase_value',
        );

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('APP_SECRET');

        $checker->onKernelRequest($this->createRequestEvent());
    }

    /**
     * Teste qu'une exception est levée en prod avec JWT_PASSPHRASE placeholder.
     */
    public function testThrowsExceptionForPlaceholderJwtPassphraseInProd(): void
    {
        $checker = new PlaceholderSecretChecker(
            appSecret: 'real_secret_value',
            env: 'prod',
            jwtPassphrase: 'change_this_passphrase_in_env_local',
        );

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('JWT_PASSPHRASE');

        $checker->onKernelRequest($this->createRequestEvent());
    }

    /**
     * Teste qu'aucune exception en prod avec des valeurs réelles.
     */
    public function testNoExceptionWithRealSecretsInProd(): void
    {
        $checker = new PlaceholderSecretChecker(
            appSecret: 'a1b2c3d4e5f6',
            env: 'prod',
            jwtPassphrase: 'real_passphrase_value',
        );

        $checker->onKernelRequest($this->createRequestEvent());

        $this->addToAssertionCount(1);
    }

    /**
     * Teste qu'aucune exception en dev même avec des placeholders.
     */
    public function testNoExceptionInDevWithPlaceholders(): void
    {
        $checker = new PlaceholderSecretChecker(
            appSecret: 'change_this_secret_in_env_local',
            env: 'dev',
            jwtPassphrase: 'change_this_passphrase_in_env_local',
        );

        $checker->onKernelRequest($this->createRequestEvent());

        $this->addToAssertionCount(1);
    }

    /**
     * Teste qu'aucune exception en test même avec des placeholders.
     */
    public function testNoExceptionInTestWithPlaceholders(): void
    {
        $checker = new PlaceholderSecretChecker(
            appSecret: 'change_this_secret_in_env_local',
            env: 'test',
            jwtPassphrase: 'change_this_passphrase_in_env_local',
        );

        $checker->onKernelRequest($this->createRequestEvent());

        $this->addToAssertionCount(1);
    }

    /**
     * Teste que les deux placeholders détectés listent les deux dans l'exception.
     */
    public function testThrowsExceptionListingBothPlaceholders(): void
    {
        $checker = new PlaceholderSecretChecker(
            appSecret: 'change_this_secret_in_env_local',
            env: 'prod',
            jwtPassphrase: 'change_this_passphrase_in_env_local',
        );

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('APP_SECRET');

        $checker->onKernelRequest($this->createRequestEvent());
    }

    /**
     * Teste que le checker ne s'exécute qu'une seule fois (sub-requests ignorées).
     */
    public function testIgnoresSubRequests(): void
    {
        $checker = new PlaceholderSecretChecker(
            appSecret: 'change_this_secret_in_env_local',
            env: 'prod',
            jwtPassphrase: 'change_this_passphrase_in_env_local',
        );

        $event = new RequestEvent(
            $this->createStub(HttpKernelInterface::class),
            new Request(),
            HttpKernelInterface::SUB_REQUEST,
        );

        // Les sub-requests ne doivent pas déclencher la vérification
        $checker->onKernelRequest($event);

        $this->addToAssertionCount(1);
    }

    private function createRequestEvent(): RequestEvent
    {
        return new RequestEvent(
            $this->createStub(HttpKernelInterface::class),
            new Request(),
            HttpKernelInterface::MAIN_REQUEST,
        );
    }
}

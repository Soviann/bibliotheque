<?php

declare(strict_types=1);

namespace App\Tests\DataFixtures;

use App\DataFixtures\UserFixtures;
use Doctrine\Persistence\ObjectManager;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Tests pour UserFixtures.
 */
class UserFixturesTest extends TestCase
{
    /**
     * Teste que les fixtures affichent un avertissement en environnement de production.
     */
    public function testLoadLogsWarningInProductionEnvironment(): void
    {
        $passwordHasher = $this->createMock(UserPasswordHasherInterface::class);
        $objectManager = $this->createMock(ObjectManager::class);
        $logger = $this->createMock(LoggerInterface::class);

        // Le logger doit recevoir un warning
        $logger->expects(self::once())
            ->method('warning')
            ->with(self::stringContains('test'));

        // L'objectManager ne doit pas être appelé hors environnement test
        $objectManager->expects(self::never())->method('persist');
        $objectManager->expects(self::never())->method('flush');

        $fixtures = new UserFixtures($passwordHasher, 'prod', $logger);

        $fixtures->load($objectManager);
    }

    /**
     * Teste que les fixtures affichent un avertissement en environnement de développement.
     */
    public function testLoadLogsWarningInDevEnvironment(): void
    {
        $passwordHasher = $this->createMock(UserPasswordHasherInterface::class);
        $objectManager = $this->createMock(ObjectManager::class);
        $logger = $this->createMock(LoggerInterface::class);

        // Le logger doit recevoir un warning
        $logger->expects(self::once())
            ->method('warning')
            ->with(self::stringContains('test'));

        // L'objectManager ne doit pas être appelé hors environnement test
        $objectManager->expects(self::never())->method('persist');
        $objectManager->expects(self::never())->method('flush');

        $fixtures = new UserFixtures($passwordHasher, 'dev', $logger);

        $fixtures->load($objectManager);
    }

    /**
     * Teste que les fixtures fonctionnent normalement en environnement de test.
     */
    public function testLoadWorksInTestEnvironment(): void
    {
        $passwordHasher = $this->createMock(UserPasswordHasherInterface::class);
        $passwordHasher->method('hashPassword')->willReturn('hashed_password');

        $objectManager = $this->createMock(ObjectManager::class);
        $logger = $this->createMock(LoggerInterface::class);

        // Le logger ne doit pas recevoir de warning en test
        $logger->expects(self::never())->method('warning');

        // L'objectManager doit être appelé en test
        $objectManager->expects(self::once())->method('persist');
        $objectManager->expects(self::once())->method('flush');

        $fixtures = new UserFixtures($passwordHasher, 'test', $logger);

        $fixtures->load($objectManager);
    }
}

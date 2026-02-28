<?php

declare(strict_types=1);

namespace App\Tests\Command;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * Tests pour la commande d'invalidation des tokens JWT.
 */
class InvalidateTokensCommandTest extends KernelTestCase
{
    /**
     * Teste que la commande incrémente le tokenVersion de tous les utilisateurs.
     */
    public function testInvalidateAllTokens(): void
    {
        self::bootKernel();

        /** @var EntityManagerInterface $em */
        $em = self::getContainer()->get(EntityManagerInterface::class);

        // Récupérer l'utilisateur de test
        $user = $em->getRepository(User::class)->findOneBy(['email' => 'test@example.com']);
        self::assertNotNull($user);
        $initialVersion = $user->getTokenVersion();

        $application = new Application(self::$kernel);
        $command = $application->find('app:invalidate-tokens');
        $commandTester = new CommandTester($command);

        $commandTester->execute([]);

        $commandTester->assertCommandIsSuccessful();
        self::assertStringContainsString('invalidé', $commandTester->getDisplay());

        // Recharger l'utilisateur depuis la base
        $em->clear();
        $user = $em->getRepository(User::class)->findOneBy(['email' => 'test@example.com']);
        self::assertNotNull($user);
        self::assertSame($initialVersion + 1, $user->getTokenVersion());
    }

    /**
     * Teste l'invalidation pour un utilisateur spécifique.
     */
    public function testInvalidateTokensForSpecificUser(): void
    {
        self::bootKernel();

        /** @var EntityManagerInterface $em */
        $em = self::getContainer()->get(EntityManagerInterface::class);

        $user = $em->getRepository(User::class)->findOneBy(['email' => 'test@example.com']);
        self::assertNotNull($user);
        $initialVersion = $user->getTokenVersion();

        $application = new Application(self::$kernel);
        $command = $application->find('app:invalidate-tokens');
        $commandTester = new CommandTester($command);

        $commandTester->execute(['--email' => 'test@example.com']);

        $commandTester->assertCommandIsSuccessful();

        $em->clear();
        $user = $em->getRepository(User::class)->findOneBy(['email' => 'test@example.com']);
        self::assertNotNull($user);
        self::assertSame($initialVersion + 1, $user->getTokenVersion());
    }

    /**
     * Teste que la commande échoue avec un email inconnu.
     */
    public function testInvalidateTokensFailsForUnknownUser(): void
    {
        self::bootKernel();

        $application = new Application(self::$kernel);
        $command = $application->find('app:invalidate-tokens');
        $commandTester = new CommandTester($command);

        $commandTester->execute(['--email' => 'unknown@example.com']);

        self::assertSame(1, $commandTester->getStatusCode());
        self::assertStringContainsString('introuvable', $commandTester->getDisplay());
    }
}

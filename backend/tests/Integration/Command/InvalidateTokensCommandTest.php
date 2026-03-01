<?php

declare(strict_types=1);

namespace App\Tests\Integration\Command;

use App\Entity\User;
use App\Tests\Factory\EntityFactory;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * Tests d'integration pour la commande app:invalidate-tokens.
 */
final class InvalidateTokensCommandTest extends KernelTestCase
{
    private CommandTester $commandTester;
    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        self::bootKernel();

        $this->em = static::getContainer()->get(EntityManagerInterface::class);

        $application = new Application(self::$kernel);
        $command = $application->find('app:invalidate-tokens');
        $this->commandTester = new CommandTester($command);
    }

    public function testNoEmailIncrementsAllUsers(): void
    {
        $user1 = EntityFactory::createUser('alice@example.com', 'g-alice');
        $user2 = EntityFactory::createUser('bob@example.com', 'g-bob');

        $this->em->persist($user1);
        $this->em->persist($user2);
        $this->em->flush();

        $initialVersion1 = $user1->getTokenVersion();
        $initialVersion2 = $user2->getTokenVersion();

        // Compter tous les utilisateurs en base (fixtures incluses)
        $totalUsers = \count($this->em->getRepository(User::class)->findAll());

        $this->commandTester->execute([]);

        self::assertSame(Command::SUCCESS, $this->commandTester->getStatusCode());
        self::assertStringContainsString(
            \sprintf('%d utilisateur(s)', $totalUsers),
            $this->commandTester->getDisplay(),
        );

        // Verifier l'increment en base
        $this->em->clear();
        $refreshed1 = $this->em->getRepository(User::class)->findOneBy(['email' => 'alice@example.com']);
        $refreshed2 = $this->em->getRepository(User::class)->findOneBy(['email' => 'bob@example.com']);

        self::assertSame($initialVersion1 + 1, $refreshed1->getTokenVersion());
        self::assertSame($initialVersion2 + 1, $refreshed2->getTokenVersion());
    }

    public function testWithEmailIncrementsOnlyTargetUser(): void
    {
        $alice = EntityFactory::createUser('alice@example.com', 'g-alice');
        $bob = EntityFactory::createUser('bob@example.com', 'g-bob');

        $this->em->persist($alice);
        $this->em->persist($bob);
        $this->em->flush();

        $aliceVersion = $alice->getTokenVersion();
        $bobVersion = $bob->getTokenVersion();

        $this->commandTester->execute(['--email' => 'alice@example.com']);

        self::assertSame(Command::SUCCESS, $this->commandTester->getStatusCode());
        self::assertStringContainsString('alice@example.com', $this->commandTester->getDisplay());

        $this->em->clear();
        $refreshedAlice = $this->em->getRepository(User::class)->findOneBy(['email' => 'alice@example.com']);
        $refreshedBob = $this->em->getRepository(User::class)->findOneBy(['email' => 'bob@example.com']);

        self::assertSame($aliceVersion + 1, $refreshedAlice->getTokenVersion());
        self::assertSame($bobVersion, $refreshedBob->getTokenVersion());
    }

    public function testWithNonExistentEmailReturnsFailure(): void
    {
        $this->commandTester->execute(['--email' => 'unknown@nowhere.com']);

        self::assertSame(Command::FAILURE, $this->commandTester->getStatusCode());
        self::assertStringContainsString('introuvable', $this->commandTester->getDisplay());
    }

    public function testTokenVersionIncrementedInDatabase(): void
    {
        $user = EntityFactory::createUser('verify@example.com', 'g-verify');
        $this->em->persist($user);
        $this->em->flush();

        self::assertSame(1, $user->getTokenVersion());

        $this->commandTester->execute(['--email' => 'verify@example.com']);

        $this->em->clear();
        $refreshed = $this->em->getRepository(User::class)->findOneBy(['email' => 'verify@example.com']);

        self::assertSame(2, $refreshed->getTokenVersion());
    }
}

<?php

declare(strict_types=1);

namespace App\Tests\Command;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Tests d'intégration pour CreateUserCommand.
 */
class CreateUserCommandTest extends KernelTestCase
{
    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->em = static::getContainer()->get(EntityManagerInterface::class);
    }

    /**
     * Teste la création d'un utilisateur avec succès.
     */
    public function testExecuteCreatesUser(): void
    {
        $application = new Application(self::$kernel);
        $command = $application->find('app:create-user');
        $commandTester = new CommandTester($command);

        $email = 'testcommand'.\uniqid().'@example.com';

        $commandTester->execute([
            'email' => $email,
            'password' => 'testpassword123',
        ]);

        $commandTester->assertCommandIsSuccessful();

        // Vérifier que l'utilisateur existe
        $user = $this->em->getRepository(User::class)->findOneBy(['email' => $email]);
        self::assertNotNull($user);
        self::assertSame($email, $user->getEmail());

        // Nettoyer
        $this->em->remove($user);
        $this->em->flush();
    }

    /**
     * Teste que le mot de passe est hashé.
     */
    public function testPasswordIsHashed(): void
    {
        $application = new Application(self::$kernel);
        $command = $application->find('app:create-user');
        $commandTester = new CommandTester($command);

        $email = 'hashtest'.\uniqid().'@example.com';
        $plainPassword = 'plainpassword123';

        $commandTester->execute([
            'email' => $email,
            'password' => $plainPassword,
        ]);

        $user = $this->em->getRepository(User::class)->findOneBy(['email' => $email]);
        self::assertNotNull($user);

        // Le mot de passe ne doit pas être en clair
        self::assertNotSame($plainPassword, $user->getPassword());

        // Le mot de passe doit être vérifiable
        /** @var UserPasswordHasherInterface $hasher */
        $hasher = static::getContainer()->get(UserPasswordHasherInterface::class);
        self::assertTrue($hasher->isPasswordValid($user, $plainPassword));

        // Nettoyer
        $this->em->remove($user);
        $this->em->flush();
    }

    /**
     * Teste que le rôle ROLE_USER est défini.
     */
    public function testUserHasRoleUser(): void
    {
        $application = new Application(self::$kernel);
        $command = $application->find('app:create-user');
        $commandTester = new CommandTester($command);

        $email = 'roletest'.\uniqid().'@example.com';

        $commandTester->execute([
            'email' => $email,
            'password' => 'password',
        ]);

        $user = $this->em->getRepository(User::class)->findOneBy(['email' => $email]);
        self::assertNotNull($user);
        self::assertContains('ROLE_USER', $user->getRoles());

        // Nettoyer
        $this->em->remove($user);
        $this->em->flush();
    }

    /**
     * Teste le message de succès.
     */
    public function testSuccessMessage(): void
    {
        $application = new Application(self::$kernel);
        $command = $application->find('app:create-user');
        $commandTester = new CommandTester($command);

        $email = 'msgtest'.\uniqid().'@example.com';

        $commandTester->execute([
            'email' => $email,
            'password' => 'password',
        ]);

        $output = $commandTester->getDisplay();
        self::assertStringContainsString($email, $output);
        self::assertStringContainsString('créé avec succès', $output);

        // Nettoyer
        $user = $this->em->getRepository(User::class)->findOneBy(['email' => $email]);
        if ($user) {
            $this->em->remove($user);
            $this->em->flush();
        }
    }

    /**
     * Teste que la commande retourne SUCCESS.
     */
    public function testCommandReturnsSuccess(): void
    {
        $application = new Application(self::$kernel);
        $command = $application->find('app:create-user');
        $commandTester = new CommandTester($command);

        $email = 'successtest'.\uniqid().'@example.com';

        $exitCode = $commandTester->execute([
            'email' => $email,
            'password' => 'password',
        ]);

        self::assertSame(0, $exitCode);

        // Nettoyer
        $user = $this->em->getRepository(User::class)->findOneBy(['email' => $email]);
        if ($user) {
            $this->em->remove($user);
            $this->em->flush();
        }
    }

    /**
     * Teste que la commande échoue avec un message d'erreur si l'email existe déjà.
     */
    public function testCommandFailsIfEmailAlreadyExists(): void
    {
        $application = new Application(self::$kernel);
        $command = $application->find('app:create-user');
        $commandTester = new CommandTester($command);

        $email = 'duplicate'.\uniqid().'@example.com';

        // Créer un premier utilisateur
        $commandTester->execute([
            'email' => $email,
            'password' => 'password1',
        ]);
        $commandTester->assertCommandIsSuccessful();

        // Tenter de créer un deuxième utilisateur avec le même email
        $exitCode = $commandTester->execute([
            'email' => $email,
            'password' => 'password2',
        ]);

        // La commande doit échouer
        self::assertSame(1, $exitCode);

        // Récupérer le message de la contrainte UniqueEntity depuis l'entité User
        $reflectionClass = new \ReflectionClass(User::class);
        $uniqueEntityAttributes = $reflectionClass->getAttributes(UniqueEntity::class);
        self::assertNotEmpty($uniqueEntityAttributes, 'User doit avoir une contrainte UniqueEntity');
        $expectedMessage = $uniqueEntityAttributes[0]->newInstance()->message;

        // Vérifier que ce message est affiché
        $output = $commandTester->getDisplay();
        self::assertStringContainsString($expectedMessage, $output);

        // Nettoyer
        $user = $this->em->getRepository(User::class)->findOneBy(['email' => $email]);
        if ($user) {
            $this->em->remove($user);
            $this->em->flush();
        }
    }
}

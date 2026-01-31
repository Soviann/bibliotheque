<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Tests fonctionnels pour SecurityController.
 */
class SecurityControllerTest extends WebTestCase
{
    /**
     * Teste que la page de login est accessible.
     */
    public function testLoginPageIsAccessible(): void
    {
        $client = static::createClient();

        $client->request('GET', '/login');

        self::assertResponseIsSuccessful();
    }

    /**
     * Teste que la page de login affiche le formulaire.
     */
    public function testLoginPageDisplaysForm(): void
    {
        $client = static::createClient();

        $crawler = $client->request('GET', '/login');

        self::assertResponseIsSuccessful();
        // Vérifier la présence des champs de formulaire
        self::assertSelectorExists('input[name="_username"]');
        self::assertSelectorExists('input[name="_password"]');
    }

    /**
     * Teste la connexion avec des identifiants valides.
     */
    public function testLoginWithValidCredentials(): void
    {
        $client = static::createClient();
        $container = static::getContainer();
        /** @var EntityManagerInterface $em */
        $em = $container->get(EntityManagerInterface::class);
        /** @var UserPasswordHasherInterface $hasher */
        $hasher = $container->get(UserPasswordHasherInterface::class);

        // Créer un utilisateur avec email unique
        $email = 'login-test-'.\uniqid().'@example.com';
        $user = new User();
        $user->setEmail($email);
        $user->setPassword($hasher->hashPassword($user, 'password123'));
        $user->setRoles(['ROLE_USER']);
        $em->persist($user);
        $em->flush();

        $crawler = $client->request('GET', '/login');
        $form = $crawler->selectButton('Se connecter')->form([
            '_username' => $email,
            '_password' => 'password123',
        ]);
        $client->submit($form);

        // Devrait rediriger vers la page d'accueil
        self::assertResponseRedirects('/');

        // Nettoyer - refetch l'entité car elle est détachée après le submit
        $em->clear();
        $userToDelete = $em->getRepository(User::class)->findOneBy(['email' => $email]);
        if ($userToDelete) {
            $em->remove($userToDelete);
            $em->flush();
        }
    }

    /**
     * Teste la connexion avec des identifiants invalides.
     */
    public function testLoginWithInvalidCredentials(): void
    {
        $client = static::createClient();

        $crawler = $client->request('GET', '/login');
        $form = $crawler->selectButton('Se connecter')->form([
            '_username' => 'nonexistent@example.com',
            '_password' => 'wrongpassword',
        ]);
        $client->submit($form);

        // Devrait rediriger vers login avec erreur
        self::assertResponseRedirects('/login');

        $client->followRedirect();
        // La page devrait afficher une erreur
        self::assertSelectorExists('.alert-error');
    }

    /**
     * Teste que l'utilisateur connecté est redirigé depuis login.
     */
    public function testLoggedInUserIsRedirectedFromLogin(): void
    {
        $client = static::createClient();
        $container = static::getContainer();
        /** @var EntityManagerInterface $em */
        $em = $container->get(EntityManagerInterface::class);
        /** @var UserPasswordHasherInterface $hasher */
        $hasher = $container->get(UserPasswordHasherInterface::class);

        // Créer un utilisateur avec email unique
        $email = 'redirect-'.\uniqid().'@example.com';
        $user = new User();
        $user->setEmail($email);
        $user->setPassword($hasher->hashPassword($user, 'password123'));
        $user->setRoles(['ROLE_USER']);
        $em->persist($user);
        $em->flush();

        // Se connecter d'abord
        $client->loginUser($user);

        // Accéder à la page de login
        $client->request('GET', '/login');

        // Devrait rediriger vers la page d'accueil
        self::assertResponseRedirects('/');

        // Nettoyer
        $em->remove($user);
        $em->flush();
    }

    /**
     * Teste que logout fonctionne.
     */
    public function testLogoutRoute(): void
    {
        $client = static::createClient();
        $container = static::getContainer();
        /** @var EntityManagerInterface $em */
        $em = $container->get(EntityManagerInterface::class);
        /** @var UserPasswordHasherInterface $hasher */
        $hasher = $container->get(UserPasswordHasherInterface::class);

        // Créer un utilisateur avec email unique
        $email = 'logout-'.\uniqid().'@example.com';
        $user = new User();
        $user->setEmail($email);
        $user->setPassword($hasher->hashPassword($user, 'password123'));
        $user->setRoles(['ROLE_USER']);
        $em->persist($user);
        $em->flush();

        // Se connecter
        $client->loginUser($user);

        // Se déconnecter
        $client->request('GET', '/logout');

        // Devrait rediriger (géré par le firewall Symfony)
        self::assertResponseRedirects();

        // Nettoyer
        $em->remove($user);
        $em->flush();
    }

    /**
     * Teste que le dernier nom d'utilisateur est affiché après échec.
     */
    public function testLastUsernameIsDisplayedAfterFailure(): void
    {
        $client = static::createClient();

        $crawler = $client->request('GET', '/login');
        $form = $crawler->selectButton('Se connecter')->form([
            '_username' => 'remembered@example.com',
            '_password' => 'wrongpassword',
        ]);
        $client->submit($form);
        $client->followRedirect();

        // Le champ username devrait contenir l'email précédent
        self::assertInputValueSame('_username', 'remembered@example.com');
    }
}

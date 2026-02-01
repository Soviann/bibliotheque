<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Cache\Adapter\AdapterInterface;
use Symfony\Component\HttpFoundation\Request;
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

        $client->request(Request::METHOD_GET, '/login');

        self::assertResponseIsSuccessful();
    }

    /**
     * Teste que la page de login affiche le formulaire.
     */
    public function testLoginPageDisplaysForm(): void
    {
        $client = static::createClient();

        $client->request(Request::METHOD_GET, '/login');

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

        $crawler = $client->request(Request::METHOD_GET, '/login');
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

        $crawler = $client->request(Request::METHOD_GET, '/login');
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
        $client->request(Request::METHOD_GET, '/login');

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
        $client->request(Request::METHOD_GET, '/logout');

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

        $crawler = $client->request(Request::METHOD_GET, '/login');
        $form = $crawler->selectButton('Se connecter')->form([
            '_username' => 'remembered@example.com',
            '_password' => 'wrongpassword',
        ]);
        $client->submit($form);
        $client->followRedirect();

        // Le champ username devrait contenir l'email précédent
        self::assertInputValueSame('_username', 'remembered@example.com');
    }

    /**
     * Teste que le rate limiting bloque après trop de tentatives.
     */
    public function testLoginThrottlingBlocksAfterMaxAttempts(): void
    {
        $client = static::createClient();

        // Utilise un email unique pour éviter les conflits avec d'autres tests
        $email = 'throttle-test-'.\uniqid().'@example.com';

        // Vide le cache du rate limiter avant le test
        $container = static::getContainer();
        /** @var AdapterInterface $cache */
        $cache = $container->get('cache.rate_limiter');
        $cache->clear();

        // Effectue 5 tentatives (max_attempts configuré)
        for ($i = 1; $i <= 5; ++$i) {
            $crawler = $client->request(Request::METHOD_GET, '/login');
            $form = $crawler->selectButton('Se connecter')->form([
                '_username' => $email,
                '_password' => 'wrongpassword',
            ]);
            $client->submit($form);

            // Les 5 premières tentatives doivent rediriger vers /login (pas bloquées)
            self::assertResponseRedirects('/login', null, "Tentative {$i} devrait rediriger vers /login");
            $client->followRedirect();
        }

        // La 6ème tentative doit être bloquée
        $crawler = $client->request(Request::METHOD_GET, '/login');
        $form = $crawler->selectButton('Se connecter')->form([
            '_username' => $email,
            '_password' => 'wrongpassword',
        ]);
        $client->submit($form);
        $client->followRedirect();

        // Devrait afficher un message d'erreur de throttling
        self::assertSelectorTextContains('.alert-error', 'Too many');
    }

    /**
     * Teste qu'une connexion réussie fonctionne après quelques tentatives échouées.
     */
    public function testSuccessfulLoginAfterSomeFailedAttempts(): void
    {
        $client = static::createClient();
        $container = static::getContainer();
        /** @var EntityManagerInterface $em */
        $em = $container->get(EntityManagerInterface::class);
        /** @var UserPasswordHasherInterface $hasher */
        $hasher = $container->get(UserPasswordHasherInterface::class);

        // Vide le cache du rate limiter avant le test
        /** @var AdapterInterface $cache */
        $cache = $container->get('cache.rate_limiter');
        $cache->clear();

        // Créer un utilisateur avec email unique
        $email = 'throttle-success-'.\uniqid().'@example.com';
        $user = new User();
        $user->setEmail($email);
        $user->setPassword($hasher->hashPassword($user, 'correctpassword'));
        $user->setRoles(['ROLE_USER']);
        $em->persist($user);
        $em->flush();

        // Effectue 3 tentatives échouées (en dessous de la limite de 5)
        for ($i = 1; $i <= 3; ++$i) {
            $crawler = $client->request(Request::METHOD_GET, '/login');
            $form = $crawler->selectButton('Se connecter')->form([
                '_username' => $email,
                '_password' => 'wrongpassword',
            ]);
            $client->submit($form);
            self::assertResponseRedirects('/login');
            $client->followRedirect();
        }

        // La 4ème tentative avec le bon mot de passe doit réussir
        $crawler = $client->request(Request::METHOD_GET, '/login');
        $form = $crawler->selectButton('Se connecter')->form([
            '_username' => $email,
            '_password' => 'correctpassword',
        ]);
        $client->submit($form);

        // Devrait rediriger vers la page d'accueil (connexion réussie)
        self::assertResponseRedirects('/');

        // Nettoyer
        $em->clear();
        $userToDelete = $em->getRepository(User::class)->findOneBy(['email' => $email]);
        if ($userToDelete) {
            $em->remove($userToDelete);
            $em->flush();
        }
    }

    /**
     * Teste que le throttling est aussi appliqué même avec un mot de passe correct après blocage.
     */
    public function testThrottlingBlocksEvenWithCorrectPasswordAfterLimit(): void
    {
        $client = static::createClient();
        $container = static::getContainer();
        /** @var EntityManagerInterface $em */
        $em = $container->get(EntityManagerInterface::class);
        /** @var UserPasswordHasherInterface $hasher */
        $hasher = $container->get(UserPasswordHasherInterface::class);

        // Vide le cache du rate limiter avant le test
        /** @var AdapterInterface $cache */
        $cache = $container->get('cache.rate_limiter');
        $cache->clear();

        // Créer un utilisateur avec email unique
        $email = 'throttle-blocked-'.\uniqid().'@example.com';
        $user = new User();
        $user->setEmail($email);
        $user->setPassword($hasher->hashPassword($user, 'correctpassword'));
        $user->setRoles(['ROLE_USER']);
        $em->persist($user);
        $em->flush();

        // Effectue 5 tentatives échouées pour atteindre la limite
        for ($i = 1; $i <= 5; ++$i) {
            $crawler = $client->request(Request::METHOD_GET, '/login');
            $form = $crawler->selectButton('Se connecter')->form([
                '_username' => $email,
                '_password' => 'wrongpassword',
            ]);
            $client->submit($form);
            $client->followRedirect();
        }

        // La 6ème tentative avec le bon mot de passe doit quand même être bloquée
        $crawler = $client->request(Request::METHOD_GET, '/login');
        $form = $crawler->selectButton('Se connecter')->form([
            '_username' => $email,
            '_password' => 'correctpassword',
        ]);
        $client->submit($form);
        $client->followRedirect();

        // Devrait afficher un message d'erreur de throttling
        self::assertSelectorTextContains('.alert-error', 'Too many');

        // Nettoyer
        $em->clear();
        $userToDelete = $em->getRepository(User::class)->findOneBy(['email' => $email]);
        if ($userToDelete) {
            $em->remove($userToDelete);
            $em->flush();
        }
    }

    /**
     * Teste que le throttling se réinitialise après une connexion réussie.
     *
     * Note: Le test de réinitialisation après expiration de l'intervalle (15 min)
     * n'est pas testé ici car il nécessiterait de manipuler le temps.
     * Ce comportement est garanti par symfony/rate-limiter.
     */
    public function testThrottlingResetsAfterSuccessfulLogin(): void
    {
        $client = static::createClient();
        $container = static::getContainer();
        /** @var EntityManagerInterface $em */
        $em = $container->get(EntityManagerInterface::class);
        /** @var UserPasswordHasherInterface $hasher */
        $hasher = $container->get(UserPasswordHasherInterface::class);

        // Vide le cache du rate limiter avant le test
        /** @var AdapterInterface $cache */
        $cache = $container->get('cache.rate_limiter');
        $cache->clear();

        // Créer un utilisateur avec email unique
        $email = 'throttle-reset-'.\uniqid().'@example.com';
        $user = new User();
        $user->setEmail($email);
        $user->setPassword($hasher->hashPassword($user, 'correctpassword'));
        $user->setRoles(['ROLE_USER']);
        $em->persist($user);
        $em->flush();

        // Effectue 4 tentatives échouées
        for ($i = 1; $i <= 4; ++$i) {
            $crawler = $client->request(Request::METHOD_GET, '/login');
            $form = $crawler->selectButton('Se connecter')->form([
                '_username' => $email,
                '_password' => 'wrongpassword',
            ]);
            $client->submit($form);
            $client->followRedirect();
        }

        // Connexion réussie
        $crawler = $client->request(Request::METHOD_GET, '/login');
        $form = $crawler->selectButton('Se connecter')->form([
            '_username' => $email,
            '_password' => 'correctpassword',
        ]);
        $client->submit($form);
        self::assertResponseRedirects('/');

        // Se déconnecter
        $client->request(Request::METHOD_GET, '/logout');
        $client->followRedirect();

        // Après connexion réussie, le compteur est réinitialisé
        // On peut à nouveau faire 5 tentatives échouées avant blocage
        for ($i = 1; $i <= 5; ++$i) {
            $crawler = $client->request(Request::METHOD_GET, '/login');
            $form = $crawler->selectButton('Se connecter')->form([
                '_username' => $email,
                '_password' => 'wrongpassword',
            ]);
            $client->submit($form);
            self::assertResponseRedirects('/login', null, "Après reset, tentative {$i} devrait être acceptée");
            $client->followRedirect();
        }

        // La 6ème tentative après reset doit être bloquée
        $crawler = $client->request(Request::METHOD_GET, '/login');
        $form = $crawler->selectButton('Se connecter')->form([
            '_username' => $email,
            '_password' => 'wrongpassword',
        ]);
        $client->submit($form);
        $client->followRedirect();
        self::assertSelectorTextContains('.alert-error', 'Too many');

        // Nettoyer
        $em->clear();
        $userToDelete = $em->getRepository(User::class)->findOneBy(['email' => $email]);
        if ($userToDelete) {
            $em->remove($userToDelete);
            $em->flush();
        }
    }
}

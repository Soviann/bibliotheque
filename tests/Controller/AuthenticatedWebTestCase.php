<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Classe de base pour les tests de contrôleurs nécessitant une authentification.
 */
abstract class AuthenticatedWebTestCase extends WebTestCase
{
    protected function createAuthenticatedClient(): KernelBrowser
    {
        $client = static::createClient();
        $container = static::getContainer();
        /** @var EntityManagerInterface $em */
        $em = $container->get(EntityManagerInterface::class);

        // Chercher ou créer un utilisateur de test
        $user = $em->getRepository(User::class)->findOneBy(['email' => 'test@bibliotheque.local']);
        if (!$user) {
            $user = new User();
            $user->setEmail('test@bibliotheque.local');
            $user->setPassword('$2y$04$test'); // Mot de passe hashé quelconque
            $user->setRoles(['ROLE_USER']);
            $em->persist($user);
            $em->flush();
        }

        $client->loginUser($user);

        return $client;
    }
}

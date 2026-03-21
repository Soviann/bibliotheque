<?php

declare(strict_types=1);

namespace App\Tests\Trait;

use ApiPlatform\Symfony\Bundle\Test\Client;
use App\Entity\User;
use App\Repository\UserRepository;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;

/**
 * Fournit un client HTTP authentifié par JWT pour les tests fonctionnels.
 */
trait AuthenticatedTestTrait
{
    private function createAuthenticatedClient(): Client
    {
        $client = static::createClient();
        $container = static::getContainer();

        /** @var UserRepository $userRepo */
        $userRepo = $container->get(UserRepository::class);
        $user = $userRepo->findOneBy(['email' => 'test@example.com']);

        if (!$user instanceof User) {
            throw new \RuntimeException('L\'utilisateur de test (test@example.com) est introuvable. Chargez les fixtures.');
        }

        /** @var JWTTokenManagerInterface $jwtManager */
        $jwtManager = $container->get('lexik_jwt_authentication.jwt_manager');
        $token = $jwtManager->create($user);

        return $client->withOptions([
            'headers' => [
                'Accept' => 'application/ld+json',
                'Authorization' => 'Bearer '.$token,
            ],
        ]);
    }

    private function createUnauthenticatedClient(): Client
    {
        return static::createClient([], [
            'headers' => ['Accept' => 'application/ld+json'],
        ]);
    }
}

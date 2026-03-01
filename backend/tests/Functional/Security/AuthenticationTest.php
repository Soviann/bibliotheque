<?php

declare(strict_types=1);

namespace App\Tests\Functional\Security;

use ApiPlatform\Symfony\Bundle\Test\ApiTestCase;
use App\Repository\UserRepository;
use App\Tests\Factory\EntityFactory;
use App\Tests\Trait\AuthenticatedTestTrait;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Tests fonctionnels pour la sécurité générale de l'API.
 */
final class AuthenticationTest extends ApiTestCase
{
    use AuthenticatedTestTrait;

    protected static ?bool $alwaysBootKernel = true;

    protected function setUp(): void
    {
        $container = static::getContainer();
        $em = $container->get(EntityManagerInterface::class);

        // Réinitialiser le rate limiter pour éviter les 429 entre tests
        $container->get('cache.rate_limiter')->clear();

        /** @var UserRepository $userRepo */
        $userRepo = $container->get(UserRepository::class);

        if (null === $userRepo->findOneBy(['email' => 'test@example.com'])) {
            $user = EntityFactory::createUser();
            $em->persist($user);
            $em->flush();
        }
    }

    // ---------------------------------------------------------------
    // Accès avec token valide
    // ---------------------------------------------------------------

    public function testValidJwtTokenCanAccessProtectedEndpoint(): void
    {
        $client = $this->createAuthenticatedClient();

        $client->request('GET', '/api/comic_series');

        self::assertResponseIsSuccessful();
    }

    // ---------------------------------------------------------------
    // Accès sans token
    // ---------------------------------------------------------------

    public function testNoTokenReturns401(): void
    {
        $client = static::createClient();

        $client->request('GET', '/api/comic_series');

        self::assertResponseStatusCodeSame(401);
    }

    public function testUnauthenticatedAccessToAuthorsReturns401(): void
    {
        $client = $this->createUnauthenticatedClient();

        $client->request('GET', '/api/authors');

        self::assertResponseStatusCodeSame(401);
    }

    public function testUnauthenticatedAccessToTrashReturns401(): void
    {
        $client = $this->createUnauthenticatedClient();

        $client->request('GET', '/api/trash');

        self::assertResponseStatusCodeSame(401);
    }

    public function testUnauthenticatedAccessToLookupReturns401(): void
    {
        $client = $this->createUnauthenticatedClient();

        $client->request('GET', '/api/lookup/isbn', [
            'query' => ['isbn' => '978-2-1234-5678-9'],
        ]);

        self::assertResponseStatusCodeSame(401);
    }

    // ---------------------------------------------------------------
    // Token invalide / garbage
    // ---------------------------------------------------------------

    public function testInvalidGarbageTokenReturns401(): void
    {
        $client = static::createClient();

        $client->request('GET', '/api/comic_series', [
            'headers' => [
                'Authorization' => 'Bearer invalid-garbage-token-12345',
            ],
        ]);

        self::assertResponseStatusCodeSame(401);
    }

    // ---------------------------------------------------------------
    // Token altéré / mal formé
    // ---------------------------------------------------------------

    public function testTamperedTokenReturns401(): void
    {
        $client = $this->createAuthenticatedClient();

        // Extraire un vrai token puis le corrompre
        $container = static::getContainer();
        /** @var \App\Repository\UserRepository $userRepo */
        $userRepo = $container->get(\App\Repository\UserRepository::class);
        $user = $userRepo->findOneBy(['email' => 'test@example.com']);

        /** @var \Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface $jwtManager */
        $jwtManager = $container->get('lexik_jwt_authentication.jwt_manager');
        $validToken = $jwtManager->create($user);

        // Altérer le token en ajoutant du bruit
        $tamperedToken = $validToken.'tampered';

        $client = static::createClient();
        $client->request('GET', '/api/comic_series', [
            'headers' => [
                'Authorization' => 'Bearer '.$tamperedToken,
            ],
        ]);

        self::assertResponseStatusCodeSame(401);
    }

    // ---------------------------------------------------------------
    // Schéma Bearer absent
    // ---------------------------------------------------------------

    public function testTokenWithoutBearerPrefixReturns401(): void
    {
        $client = static::createClient();

        $client->request('GET', '/api/comic_series', [
            'headers' => [
                'Authorization' => 'some-token-without-bearer',
            ],
        ]);

        self::assertResponseStatusCodeSame(401);
    }

    // ---------------------------------------------------------------
    // Endpoints publics
    // ---------------------------------------------------------------

    public function testPublicEndpointsAccessible(): void
    {
        $client = static::createClient();

        // Le endpoint de login Google est public (pas besoin de JWT)
        $client->request('POST', '/api/login/google', [
            'headers' => ['Content-Type' => 'application/json'],
            'json' => [],
        ]);

        // 400 (credential manquant) et non 401 (pas besoin d'auth)
        self::assertResponseStatusCodeSame(400);
    }
}

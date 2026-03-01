<?php

declare(strict_types=1);

namespace App\Tests\Functional\Auth;

use ApiPlatform\Symfony\Bundle\Test\ApiTestCase;
use App\Repository\UserRepository;
use App\Tests\Factory\EntityFactory;
use App\Tests\Trait\AuthenticatedTestTrait;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Tests fonctionnels pour l'authentification JWT.
 */
final class JwtAuthTest extends ApiTestCase
{
    use AuthenticatedTestTrait;

    protected static ?bool $alwaysBootKernel = true;

    protected function setUp(): void
    {
        $em = static::getContainer()->get(EntityManagerInterface::class);

        /** @var UserRepository $userRepo */
        $userRepo = static::getContainer()->get(UserRepository::class);

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

        // Altérer le token en modifiant un caractère
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
}

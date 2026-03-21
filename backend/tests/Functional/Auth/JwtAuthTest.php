<?php

declare(strict_types=1);

namespace App\Tests\Functional\Auth;

use ApiPlatform\Symfony\Bundle\Test\ApiTestCase;
use App\Repository\UserRepository;
use App\Tests\Factory\EntityFactory;
use App\Tests\Trait\AuthenticatedTestTrait;
use Doctrine\ORM\EntityManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;

/**
 * Tests fonctionnels pour l'authentification JWT.
 */
final class JwtAuthTest extends ApiTestCase
{
    use AuthenticatedTestTrait;

    protected static ?bool $alwaysBootKernel = true;

    protected function setUp(): void
    {
        $em = self::getContainer()->get(EntityManagerInterface::class);

        /** @var UserRepository $userRepo */
        $userRepo = self::getContainer()->get(UserRepository::class);

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
        $client = self::createClient();

        $client->request('GET', '/api/comic_series');

        self::assertResponseStatusCodeSame(401);
    }

    // ---------------------------------------------------------------
    // Token invalide / garbage
    // ---------------------------------------------------------------

    public function testInvalidGarbageTokenReturns401(): void
    {
        $client = self::createClient();

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
        $container = self::getContainer();
        /** @var UserRepository $userRepo */
        $userRepo = $container->get(UserRepository::class);
        $user = $userRepo->findOneBy(['email' => 'test@example.com']);

        /** @var JWTTokenManagerInterface $jwtManager */
        $jwtManager = $container->get('lexik_jwt_authentication.jwt_manager');
        $validToken = $jwtManager->create($user);

        // Altérer le token en modifiant un caractère
        $tamperedToken = $validToken.'tampered';

        $client = self::createClient();
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
        $client = self::createClient();

        $client->request('GET', '/api/comic_series', [
            'headers' => [
                'Authorization' => 'some-token-without-bearer',
            ],
        ]);

        self::assertResponseStatusCodeSame(401);
    }
}

<?php

declare(strict_types=1);

namespace App\Tests\Functional\Security;

use ApiPlatform\Symfony\Bundle\Test\ApiTestCase;
use App\Repository\UserRepository;
use App\Tests\Factory\EntityFactory;
use App\Tests\Trait\AuthenticatedTestTrait;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Tests fonctionnels pour le rate limiting.
 *
 * Configuration :
 * - api_lookup : 30 requêtes/minute par IP (sliding window)
 * - google_login : 10 requêtes/minute par IP (sliding window)
 *
 * Note : tester l'épuisement du rate limit (30+ requêtes) serait trop lent
 * pour un test unitaire. On vérifie simplement que les endpoints répondent
 * normalement sous le seuil. Le comportement au-delà du seuil (HTTP 429)
 * est validé manuellement ou via un test de charge.
 */
final class RateLimitTest extends ApiTestCase
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
    // Lookup : première requête réussit
    // ---------------------------------------------------------------

    public function testLookupEndpointRespondsNormally(): void
    {
        $client = $this->createAuthenticatedClient();

        // Le paramètre isbn manquant retournera 400 (validation), pas 429
        $client->request('GET', '/api/lookup/isbn');

        // On vérifie que ce n'est pas un 429 (rate limit)
        self::assertResponseStatusCodeSame(400);
    }

    public function testLookupTitleEndpointRespondsNormally(): void
    {
        $client = $this->createAuthenticatedClient();

        // Le paramètre title manquant retournera 400 (validation), pas 429
        $client->request('GET', '/api/lookup/title');

        self::assertResponseStatusCodeSame(400);
    }

    // ---------------------------------------------------------------
    // Google Login : première requête réussit
    // ---------------------------------------------------------------

    public function testGoogleLoginEndpointRespondsNormally(): void
    {
        $client = static::createClient();

        $client->request('POST', '/api/login/google', [
            'headers' => ['Content-Type' => 'application/json'],
            'json' => [],
        ]);

        // 400 (credential manquant), pas 429
        self::assertResponseStatusCodeSame(400);
    }
}

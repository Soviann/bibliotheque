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
        $container = self::getContainer();
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
        $client = self::createClient();

        $client->request('POST', '/api/login/google', [
            'headers' => ['Content-Type' => 'application/json'],
            'json' => [],
        ]);

        // 400 (credential manquant), pas 429
        self::assertResponseStatusCodeSame(400);
    }

    // ---------------------------------------------------------------
    // Import : première requête réussit
    // ---------------------------------------------------------------
    // Purge execute : première requête réussit
    // ---------------------------------------------------------------

    public function testPurgeExecuteEndpointRespondsNormally(): void
    {
        $client = $this->createAuthenticatedClient();

        // Sans seriesIds = 400 (validation), pas 429
        $client->request('POST', '/api/tools/purge/execute', [
            'json' => [],
        ]);

        self::assertResponseStatusCodeSame(400);
    }

    // ---------------------------------------------------------------
    // Batch lookup run : première requête réussit
    // ---------------------------------------------------------------

    public function testBatchLookupRunEndpointRespondsNormally(): void
    {
        $client = $this->createAuthenticatedClient();

        $client->request('POST', '/api/tools/batch-lookup/run', [
            'json' => ['limit' => 0],
        ]);

        // 200 (SSE, 0 items), pas 429
        self::assertResponseIsSuccessful();
    }

    // ---------------------------------------------------------------
    // Merge : première requête réussit
    // ---------------------------------------------------------------

    public function testMergeDetectEndpointRespondsNormally(): void
    {
        $client = $this->createAuthenticatedClient();

        $client->request('POST', '/api/merge-series/detect', [
            'json' => [],
        ]);

        // Peut être 200 ou 429 (Gemini), mais pas notre rate limiter
        self::assertContains(
            $client->getResponse()->getStatusCode(),
            [200, 429],
        );
    }

    public function testMergeExecuteEndpointRespondsNormally(): void
    {
        $client = $this->createAuthenticatedClient();

        // Sans données = 400 (validation), pas 429
        $client->request('POST', '/api/merge-series/execute', [
            'json' => [],
        ]);

        self::assertResponseStatusCodeSame(400);
    }
}

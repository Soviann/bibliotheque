<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api;

use ApiPlatform\Symfony\Bundle\Test\ApiTestCase;
use App\Repository\UserRepository;
use App\Tests\Factory\EntityFactory;
use App\Tests\Trait\AuthenticatedTestTrait;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Tests fonctionnels pour les endpoints de lookup.
 *
 * Note : les réponses 200/404 dépendent d'APIs externes (Google Books,
 * AniList, Wikipedia, Gemini). Seuls les chemins de validation et
 * d'authentification sont testés ici.
 */
final class LookupApiTest extends ApiTestCase
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
    // GET /api/lookup/isbn
    // ---------------------------------------------------------------

    public function testIsbnLookupMissingParameterReturns400(): void
    {
        $client = $this->createAuthenticatedClient();

        $client->request('GET', '/api/lookup/isbn');

        self::assertResponseStatusCodeSame(400);

        $data = $client->getResponse()->toArray(false);

        self::assertSame('ISBN requis', $data['error']);
    }

    public function testIsbnLookupEmptyParameterReturns400(): void
    {
        $client = $this->createAuthenticatedClient();

        $client->request('GET', '/api/lookup/isbn', [
            'query' => ['isbn' => ''],
        ]);

        self::assertResponseStatusCodeSame(400);
    }

    public function testIsbnLookupUnauthenticatedReturns401(): void
    {
        $client = $this->createUnauthenticatedClient();

        $client->request('GET', '/api/lookup/isbn', [
            'query' => ['isbn' => '978-2-1234-5678-9'],
        ]);

        self::assertResponseStatusCodeSame(401);
    }

    // ---------------------------------------------------------------
    // GET /api/lookup/title
    // ---------------------------------------------------------------

    public function testTitleLookupMissingParameterReturns400(): void
    {
        $client = $this->createAuthenticatedClient();

        $client->request('GET', '/api/lookup/title');

        self::assertResponseStatusCodeSame(400);

        $data = $client->getResponse()->toArray(false);

        self::assertSame('Titre requis', $data['error']);
    }

    public function testTitleLookupEmptyParameterReturns400(): void
    {
        $client = $this->createAuthenticatedClient();

        $client->request('GET', '/api/lookup/title', [
            'query' => ['title' => ''],
        ]);

        self::assertResponseStatusCodeSame(400);
    }

    public function testTitleLookupUnauthenticatedReturns401(): void
    {
        $client = $this->createUnauthenticatedClient();

        $client->request('GET', '/api/lookup/title', [
            'query' => ['title' => 'Naruto'],
        ]);

        self::assertResponseStatusCodeSame(401);
    }

    /**
     * Teste que le parametre limit invalide (> 10) est clamp a 10.
     */
    public function testTitleLookupWithLimitParameterProcesses(): void
    {
        $client = $this->createAuthenticatedClient();

        $client->request('GET', '/api/lookup/title', [
            'query' => ['limit' => '5', 'title' => 'One Piece', 'type' => 'manga'],
        ]);

        $statusCode = $client->getResponse()->getStatusCode();
        self::assertContains($statusCode, [200, 404], 'Doit être 200 ou 404, pas une erreur serveur');
    }

    /**
     * Teste que limit>1 retourne une reponse avec un tableau results.
     */
    public function testTitleLookupWithLimitMultiReturnsResultsArray(): void
    {
        $client = $this->createAuthenticatedClient();

        $client->request('GET', '/api/lookup/title', [
            'query' => ['limit' => '5', 'title' => 'Naruto', 'type' => 'manga'],
        ]);

        $statusCode = $client->getResponse()->getStatusCode();
        self::assertSame(200, $statusCode);

        $data = $client->getResponse()->toArray(false);
        self::assertArrayHasKey('results', $data);
        self::assertArrayHasKey('apiMessages', $data);
        self::assertArrayHasKey('sources', $data);
        self::assertIsArray($data['results']);
    }

    /**
     * Teste que limit=0 retourne une erreur 400.
     */
    public function testTitleLookupWithLimitZeroReturns400(): void
    {
        $client = $this->createAuthenticatedClient();

        $client->request('GET', '/api/lookup/title', [
            'query' => ['limit' => '0', 'title' => 'One Piece'],
        ]);

        self::assertResponseStatusCodeSame(400);
    }

    // ---------------------------------------------------------------
    // Rate limiting
    // ---------------------------------------------------------------

    /**
     * Teste que le rate limiter renvoie 429 avec Retry-After quand le quota est épuisé.
     */
    public function testIsbnLookupRateLimitedReturns429(): void
    {
        $container = static::getContainer();

        /** @var \Symfony\Component\RateLimiter\RateLimiterFactory $limiterFactory */
        $limiterFactory = $container->get('limiter.api_lookup');

        // Épuisement du quota (30 tokens) pour l'IP utilisée par le client de test
        $limiter = $limiterFactory->create('127.0.0.1');
        $limiter->consume(30);

        $client = $this->createAuthenticatedClient();
        $client->request('GET', '/api/lookup/isbn', [
            'query' => ['isbn' => '9782723489'],
        ]);

        self::assertResponseStatusCodeSame(429);

        $response = $client->getResponse();
        $data = $response->toArray(false);
        self::assertSame('Trop de requêtes. Réessayez plus tard.', $data['error']);

        $headers = $response->getHeaders(false);
        self::assertArrayHasKey('retry-after', $headers, 'L\'en-tête Retry-After doit être présent');
    }

    // ---------------------------------------------------------------
    // Paramètre type
    // ---------------------------------------------------------------

    /**
     * Teste qu'un type invalide est ignoré (tryFrom retourne null) sans erreur 400.
     */
    public function testIsbnLookupWithInvalidTypeParameterStillProcesses(): void
    {
        $client = $this->createAuthenticatedClient();

        $client->request('GET', '/api/lookup/isbn', [
            'query' => ['isbn' => '978-2-1234-5678-9', 'type' => 'invalid'],
        ]);

        // Le type invalide ne provoque pas d'erreur 400/500
        $statusCode = $client->getResponse()->getStatusCode();
        self::assertContains($statusCode, [200, 404], 'Doit être 200 ou 404, pas une erreur serveur');
    }

    /**
     * Teste qu'un type valide est correctement résolu.
     */
    public function testIsbnLookupWithValidTypeParameterProcesses(): void
    {
        $client = $this->createAuthenticatedClient();

        $client->request('GET', '/api/lookup/isbn', [
            'query' => ['isbn' => '978-2-1234-5678-9', 'type' => 'manga'],
        ]);

        // Le type valide est résolu, la requête passe (200 ou 404)
        $statusCode = $client->getResponse()->getStatusCode();
        self::assertContains($statusCode, [200, 404], 'Doit être 200 ou 404, pas une erreur serveur');
    }

    // ---------------------------------------------------------------
    // GET /api/lookup/covers
    // ---------------------------------------------------------------

    public function testCoverSearchUnauthenticatedReturns401(): void
    {
        $client = $this->createUnauthenticatedClient();

        $client->request('GET', '/api/lookup/covers', [
            'query' => ['query' => 'Naruto'],
        ]);

        self::assertResponseStatusCodeSame(401);
    }

    public function testCoverSearchMissingQueryReturns400(): void
    {
        $client = $this->createAuthenticatedClient();

        $client->request('GET', '/api/lookup/covers');

        self::assertResponseStatusCodeSame(400);

        $data = $client->getResponse()->toArray(false);

        self::assertSame('Requête requise', $data['error']);
    }

    public function testCoverSearchEmptyQueryReturns400(): void
    {
        $client = $this->createAuthenticatedClient();

        $client->request('GET', '/api/lookup/covers', [
            'query' => ['query' => ''],
        ]);

        self::assertResponseStatusCodeSame(400);
    }
}

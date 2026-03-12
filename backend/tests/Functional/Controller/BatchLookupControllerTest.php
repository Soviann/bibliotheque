<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller;

use ApiPlatform\Symfony\Bundle\Test\ApiTestCase;
use App\Repository\UserRepository;
use App\Tests\Factory\EntityFactory;
use App\Tests\Trait\AuthenticatedTestTrait;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Tests fonctionnels pour BatchLookupController.
 */
final class BatchLookupControllerTest extends ApiTestCase
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
    // Authentification
    // ---------------------------------------------------------------

    public function testPreviewRequiresAuthentication(): void
    {
        $client = $this->createUnauthenticatedClient();

        $client->request('GET', '/api/tools/batch-lookup/preview');

        self::assertResponseStatusCodeSame(401);
    }

    public function testRunRequiresAuthentication(): void
    {
        $client = $this->createUnauthenticatedClient();

        $client->request('POST', '/api/tools/batch-lookup/run', [
            'json' => [],
        ]);

        self::assertResponseStatusCodeSame(401);
    }

    // ---------------------------------------------------------------
    // GET /api/tools/batch-lookup/preview
    // ---------------------------------------------------------------

    public function testPreviewReturnsCount(): void
    {
        $client = $this->createAuthenticatedClient();

        $client->request('GET', '/api/tools/batch-lookup/preview');

        self::assertResponseIsSuccessful();
        $data = $client->getResponse()->toArray();
        self::assertArrayHasKey('count', $data);
        self::assertIsInt($data['count']);
    }

    public function testPreviewWithTypeFilter(): void
    {
        $client = $this->createAuthenticatedClient();

        $client->request('GET', '/api/tools/batch-lookup/preview?type=manga');

        self::assertResponseIsSuccessful();
        $data = $client->getResponse()->toArray();
        self::assertArrayHasKey('count', $data);
    }

    public function testPreviewWithForceFlag(): void
    {
        $client = $this->createAuthenticatedClient();

        $client->request('GET', '/api/tools/batch-lookup/preview?force=true');

        self::assertResponseIsSuccessful();
        $data = $client->getResponse()->toArray();
        self::assertArrayHasKey('count', $data);
    }

    // ---------------------------------------------------------------
    // POST /api/tools/batch-lookup/run
    // ---------------------------------------------------------------

    public function testRunReturnsSuccessfulResponse(): void
    {
        $client = $this->createAuthenticatedClient();

        $client->request('POST', '/api/tools/batch-lookup/run', [
            'json' => ['limit' => 0],
        ]);

        self::assertResponseIsSuccessful();
    }

    public function testRunWithTypeFilter(): void
    {
        $client = $this->createAuthenticatedClient();

        $client->request('POST', '/api/tools/batch-lookup/run', [
            'json' => ['limit' => 0, 'type' => 'manga'],
        ]);

        self::assertResponseIsSuccessful();
    }
}

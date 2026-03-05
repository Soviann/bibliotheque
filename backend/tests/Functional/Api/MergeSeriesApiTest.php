<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api;

use ApiPlatform\Symfony\Bundle\Test\ApiTestCase;
use App\Entity\ComicSeries;
use App\Repository\UserRepository;
use App\Tests\Factory\EntityFactory;
use App\Tests\Trait\AuthenticatedTestTrait;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Tests fonctionnels pour les endpoints de fusion de séries.
 */
final class MergeSeriesApiTest extends ApiTestCase
{
    use AuthenticatedTestTrait;

    protected static ?bool $alwaysBootKernel = true;

    protected function setUp(): void
    {
        $container = static::getContainer();
        $em = $container->get(EntityManagerInterface::class);

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

    public function testDetectRequiresAuthentication(): void
    {
        $client = $this->createUnauthenticatedClient();

        $client->request('POST', '/api/merge-series/detect', [
            'json' => ['type' => 'bd'],
        ]);

        self::assertResponseStatusCodeSame(401);
    }

    public function testPreviewRequiresAuthentication(): void
    {
        $client = $this->createUnauthenticatedClient();

        $client->request('POST', '/api/merge-series/preview', [
            'json' => ['seriesIds' => [1, 2]],
        ]);

        self::assertResponseStatusCodeSame(401);
    }

    public function testExecuteRequiresAuthentication(): void
    {
        $client = $this->createUnauthenticatedClient();

        $client->request('POST', '/api/merge-series/execute', [
            'json' => [],
        ]);

        self::assertResponseStatusCodeSame(401);
    }

    // ---------------------------------------------------------------
    // POST /api/merge-series/preview — validation
    // ---------------------------------------------------------------

    public function testPreviewRequiresAtLeastTwoSeriesIds(): void
    {
        $client = $this->createAuthenticatedClient();

        $client->request('POST', '/api/merge-series/preview', [
            'json' => ['seriesIds' => [1]],
        ]);

        self::assertResponseStatusCodeSame(400);

        $data = $client->getResponse()->toArray(false);
        self::assertSame('Au moins 2 séries sont requises pour la fusion.', $data['error']);
    }

    public function testPreviewRequiresSeriesIdsField(): void
    {
        $client = $this->createAuthenticatedClient();

        $client->request('POST', '/api/merge-series/preview', [
            'json' => [],
        ]);

        self::assertResponseStatusCodeSame(400);

        $data = $client->getResponse()->toArray(false);
        self::assertSame('Au moins 2 séries sont requises pour la fusion.', $data['error']);
    }

    public function testPreviewReturns404ForNonexistentSeries(): void
    {
        $client = $this->createAuthenticatedClient();

        $client->request('POST', '/api/merge-series/preview', [
            'json' => ['seriesIds' => [99999, 99998]],
        ]);

        self::assertResponseStatusCodeSame(404);

        $data = $client->getResponse()->toArray(false);
        self::assertStringContainsString('introuvable', $data['error']);
    }

    // ---------------------------------------------------------------
    // POST /api/merge-series/execute — validation
    // ---------------------------------------------------------------

    public function testExecuteReturns400ForInvalidBody(): void
    {
        $client = $this->createAuthenticatedClient();

        $client->request('POST', '/api/merge-series/execute', [
            'json' => [],
        ]);

        self::assertResponseStatusCodeSame(400);

        $data = $client->getResponse()->toArray(false);
        self::assertArrayHasKey('error', $data);
    }

    public function testExecuteReturns400ForMissingTitle(): void
    {
        $client = $this->createAuthenticatedClient();

        $client->request('POST', '/api/merge-series/execute', [
            'json' => [
                'authors' => [],
                'sourceSeriesIds' => [1, 2],
                'tomes' => [],
                'type' => 'bd',
            ],
        ]);

        self::assertResponseStatusCodeSame(400);
    }
}

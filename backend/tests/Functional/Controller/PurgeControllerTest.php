<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller;

use ApiPlatform\Symfony\Bundle\Test\ApiTestCase;
use App\Entity\ComicSeries;
use App\Repository\UserRepository;
use App\Tests\Factory\EntityFactory;
use App\Tests\Trait\AuthenticatedTestTrait;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Tests fonctionnels pour PurgeController.
 */
final class PurgeControllerTest extends ApiTestCase
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
    // Authentification
    // ---------------------------------------------------------------

    public function testPreviewRequiresAuthentication(): void
    {
        $client = $this->createUnauthenticatedClient();

        $client->request('GET', '/api/tools/purge/preview?days=30');

        self::assertResponseStatusCodeSame(401);
    }

    public function testExecuteRequiresAuthentication(): void
    {
        $client = $this->createUnauthenticatedClient();

        $client->request('POST', '/api/tools/purge/execute', [
            'json' => ['seriesIds' => [1]],
        ]);

        self::assertResponseStatusCodeSame(401);
    }

    // ---------------------------------------------------------------
    // GET /api/tools/purge/preview
    // ---------------------------------------------------------------

    public function testPreviewReturnsEmptyListWhenNoPurgeable(): void
    {
        $client = $this->createAuthenticatedClient();

        $client->request('GET', '/api/tools/purge/preview?days=30');

        self::assertResponseIsSuccessful();
        $data = $client->getResponse()->toArray();
        self::assertSame([], $data);
    }

    public function testPreviewReturnsPurgeableSeries(): void
    {
        $client = $this->createAuthenticatedClient();

        $em = self::getContainer()->get(EntityManagerInterface::class);
        $series = EntityFactory::createComicSeries('Old Deleted');
        $em->persist($series);
        $em->flush();

        $series->delete();
        $em->flush();

        // Simuler une suppression ancienne
        $em->getConnection()->executeStatement(
            'UPDATE comic_series SET deleted_at = :date WHERE id = :id',
            [
                'date' => (new \DateTime('-60 days'))->format('Y-m-d H:i:s'),
                'id' => $series->getId(),
            ]
        );

        $client->request('GET', '/api/tools/purge/preview?days=30');

        self::assertResponseIsSuccessful();
        $data = $client->getResponse()->toArray();
        self::assertNotEmpty($data);
        self::assertSame('Old Deleted', $data[0]['title']);
        self::assertArrayHasKey('deletedAt', $data[0]);
        self::assertArrayHasKey('id', $data[0]);
    }

    public function testPreviewReturns400ForInvalidDays(): void
    {
        $client = $this->createAuthenticatedClient();

        $client->request('GET', '/api/tools/purge/preview?days=0');

        self::assertResponseStatusCodeSame(400);
    }

    public function testPreviewDefaultsTo30Days(): void
    {
        $client = $this->createAuthenticatedClient();

        $client->request('GET', '/api/tools/purge/preview');

        self::assertResponseIsSuccessful();
        $data = $client->getResponse()->toArray();
        self::assertIsArray($data);
    }

    // ---------------------------------------------------------------
    // POST /api/tools/purge/execute
    // ---------------------------------------------------------------

    public function testExecutePurgesSeries(): void
    {
        $client = $this->createAuthenticatedClient();

        $em = self::getContainer()->get(EntityManagerInterface::class);
        $series = EntityFactory::createComicSeries('To Purge');
        $em->persist($series);
        $em->flush();

        $seriesId = $series->getId();
        $series->delete();
        $em->flush();

        $em->getConnection()->executeStatement(
            'UPDATE comic_series SET deleted_at = :date WHERE id = :id',
            [
                'date' => (new \DateTime('-60 days'))->format('Y-m-d H:i:s'),
                'id' => $seriesId,
            ]
        );

        $client->request('POST', '/api/tools/purge/execute', [
            'json' => ['seriesIds' => [$seriesId]],
        ]);

        self::assertResponseIsSuccessful();
        $data = $client->getResponse()->toArray();
        self::assertSame(1, $data['purged']);

        // Vérifier la suppression définitive
        $em->getFilters()->disable('soft_delete');
        $em->clear();
        self::assertNull($em->getRepository(ComicSeries::class)->find($seriesId));
        $em->getFilters()->enable('soft_delete');
    }

    public function testExecuteReturns400ForMissingSeriesIds(): void
    {
        $client = $this->createAuthenticatedClient();

        $client->request('POST', '/api/tools/purge/execute', [
            'json' => [],
        ]);

        self::assertResponseStatusCodeSame(400);
    }

    public function testExecuteReturns400ForEmptySeriesIds(): void
    {
        $client = $this->createAuthenticatedClient();

        $client->request('POST', '/api/tools/purge/execute', [
            'json' => ['seriesIds' => []],
        ]);

        self::assertResponseStatusCodeSame(400);
    }
}

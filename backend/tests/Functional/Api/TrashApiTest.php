<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api;

use ApiPlatform\Symfony\Bundle\Test\ApiTestCase;
use App\Repository\UserRepository;
use App\Tests\Factory\EntityFactory;
use App\Tests\Trait\AuthenticatedTestTrait;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Tests fonctionnels pour l'API Trash (corbeille).
 */
final class TrashApiTest extends ApiTestCase
{
    use AuthenticatedTestTrait;

    protected static ?bool $alwaysBootKernel = true;

    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        $this->em = static::getContainer()->get(EntityManagerInterface::class);

        /** @var UserRepository $userRepo */
        $userRepo = static::getContainer()->get(UserRepository::class);

        if (null === $userRepo->findOneBy(['email' => 'test@example.com'])) {
            $user = EntityFactory::createUser();
            $this->em->persist($user);
            $this->em->flush();
        }
    }

    // ---------------------------------------------------------------
    // GET /api/trash
    // ---------------------------------------------------------------

    public function testGetTrashReturnsOnlySoftDeletedSeries(): void
    {
        $client = $this->createAuthenticatedClient();

        // Créer une série active et une supprimée
        $active = EntityFactory::createComicSeries('Serie Active');
        $this->em->persist($active);

        $deleted = EntityFactory::createComicSeries('Serie Supprimee');
        $this->em->persist($deleted);
        $this->em->flush();

        // Soft delete via l'API
        $client->request('DELETE', '/api/comic_series/'.$deleted->getId());
        self::assertResponseStatusCodeSame(204);

        $client->request('GET', '/api/trash');

        self::assertResponseIsSuccessful();

        $data = $client->getResponse()->toArray();

        self::assertArrayHasKey('member', $data);

        $titles = \array_map(static fn (array $item): string => $item['title'], $data['member']);
        self::assertContains('Serie Supprimee', $titles);
        self::assertNotContains('Serie Active', $titles);
    }

    public function testGetTrashEmptyWhenNothingDeleted(): void
    {
        $client = $this->createAuthenticatedClient();

        $series = EntityFactory::createComicSeries('Serie Active Seule');
        $this->em->persist($series);
        $this->em->flush();

        $client->request('GET', '/api/trash');

        self::assertResponseIsSuccessful();

        $data = $client->getResponse()->toArray();

        self::assertSame(0, $data['totalItems']);
        self::assertEmpty($data['member']);
    }

    public function testGetTrashOrderedByDeletedAtDesc(): void
    {
        $client = $this->createAuthenticatedClient();

        // Créer et supprimer deux séries à des moments différents
        $first = EntityFactory::createComicSeries('Premiere Supprimee');
        $this->em->persist($first);
        $this->em->flush();

        $client->request('DELETE', '/api/comic_series/'.$first->getId());
        self::assertResponseStatusCodeSame(204);

        $second = EntityFactory::createComicSeries('Deuxieme Supprimee');
        $this->em->persist($second);
        $this->em->flush();

        $client->request('DELETE', '/api/comic_series/'.$second->getId());
        self::assertResponseStatusCodeSame(204);

        $client->request('GET', '/api/trash');

        self::assertResponseIsSuccessful();

        $data = $client->getResponse()->toArray();

        self::assertCount(2, $data['member']);
        // La plus récemment supprimée doit apparaître en premier
        self::assertSame('Deuxieme Supprimee', $data['member'][0]['title']);
        self::assertSame('Premiere Supprimee', $data['member'][1]['title']);
    }

    public function testGetTrashUnauthenticatedReturns401(): void
    {
        $client = $this->createUnauthenticatedClient();

        $client->request('GET', '/api/trash');

        self::assertResponseStatusCodeSame(401);
    }

    // ---------------------------------------------------------------
    // PUT /api/comic_series/{id}/restore
    // ---------------------------------------------------------------

    public function testRestoreSoftDeletedSeriesReturns200(): void
    {
        $client = $this->createAuthenticatedClient();

        $series = EntityFactory::createComicSeries('A Restaurer');
        $this->em->persist($series);
        $this->em->flush();

        $id = $series->getId();

        // Soft delete
        $client->request('DELETE', '/api/comic_series/'.$id);
        self::assertResponseStatusCodeSame(204);

        // Restaurer
        $client->request('PUT', '/api/comic_series/'.$id.'/restore');
        self::assertResponseIsSuccessful();

        // Vérifier que la série est de nouveau accessible
        $client->request('GET', '/api/comic_series/'.$id);
        self::assertResponseIsSuccessful();
    }

    public function testRestoreUnauthenticatedReturns401(): void
    {
        $client = $this->createUnauthenticatedClient();

        $series = EntityFactory::createComicSeries('Serie');
        $this->em->persist($series);
        $this->em->flush();

        $client->request('PUT', '/api/comic_series/'.$series->getId().'/restore');

        self::assertResponseStatusCodeSame(401);
    }

    // ---------------------------------------------------------------
    // DELETE /api/trash/{id}/permanent
    // ---------------------------------------------------------------

    public function testPermanentDeleteReturns204(): void
    {
        $client = $this->createAuthenticatedClient();

        $series = EntityFactory::createComicSeries('A Supprimer Definitivement');
        $this->em->persist($series);
        $this->em->flush();

        $id = $series->getId();

        // Soft delete d'abord
        $client->request('DELETE', '/api/comic_series/'.$id);
        self::assertResponseStatusCodeSame(204);

        // Suppression définitive
        $client->request('DELETE', '/api/trash/'.$id.'/permanent');
        self::assertResponseStatusCodeSame(204);

        // La série ne doit plus exister du tout
        $client->request('PUT', '/api/comic_series/'.$id.'/restore');
        self::assertResponseStatusCodeSame(404);
    }

    public function testPermanentDeleteUnauthenticatedReturns401(): void
    {
        $client = $this->createUnauthenticatedClient();

        $series = EntityFactory::createComicSeries('Serie');
        $this->em->persist($series);
        $this->em->flush();

        $client->request('DELETE', '/api/trash/'.$series->getId().'/permanent');

        self::assertResponseStatusCodeSame(401);
    }
}

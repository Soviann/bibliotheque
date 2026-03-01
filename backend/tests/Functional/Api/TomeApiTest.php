<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api;

use ApiPlatform\Symfony\Bundle\Test\ApiTestCase;
use App\Repository\UserRepository;
use App\Tests\Factory\EntityFactory;
use App\Tests\Trait\AuthenticatedTestTrait;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Tests fonctionnels pour l'API Tome.
 */
final class TomeApiTest extends ApiTestCase
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
    // GET /api/comic_series/{id}/tomes (sub-resource collection)
    // ---------------------------------------------------------------

    public function testGetTomesReturnsOrderedByNumberAsc(): void
    {
        $client = $this->createAuthenticatedClient();

        $series = EntityFactory::createComicSeries('Serie Tomes');

        $tome3 = EntityFactory::createTome(3);
        $series->addTome($tome3);

        $tome1 = EntityFactory::createTome(1);
        $series->addTome($tome1);

        $tome2 = EntityFactory::createTome(2);
        $series->addTome($tome2);

        $this->em->persist($series);
        $this->em->flush();

        $seriesId = $series->getId();

        $client->request('GET', '/api/comic_series/'.$seriesId.'/tomes');

        self::assertResponseIsSuccessful();

        $data = $client->getResponse()->toArray();
        $numbers = \array_map(static fn (array $item): int => $item['number'], $data['member']);

        self::assertSame([1, 2, 3], $numbers);
    }

    public function testGetTomesUnauthenticatedReturns401(): void
    {
        $client = $this->createUnauthenticatedClient();

        $series = EntityFactory::createComicSeries('Serie Auth');
        $this->em->persist($series);
        $this->em->flush();

        $client->request('GET', '/api/comic_series/'.$series->getId().'/tomes');

        self::assertResponseStatusCodeSame(401);
    }

    // ---------------------------------------------------------------
    // POST /api/comic_series/{id}/tomes (create)
    // ---------------------------------------------------------------

    public function testPostTomeReturns201(): void
    {
        $client = $this->createAuthenticatedClient();

        $series = EntityFactory::createComicSeries('Serie Pour Tome');
        $this->em->persist($series);
        $this->em->flush();

        $seriesId = $series->getId();

        $client->request('POST', '/api/comic_series/'.$seriesId.'/tomes', [
            'headers' => ['Content-Type' => 'application/ld+json'],
            'json' => [
                'bought' => true,
                'comicSeries' => '/api/comic_series/'.$seriesId,
                'number' => 1,
                'title' => 'Premier tome',
            ],
        ]);

        self::assertResponseStatusCodeSame(201);

        $data = $client->getResponse()->toArray();

        self::assertSame(1, $data['number']);
        self::assertTrue($data['bought']);
        self::assertSame('Premier tome', $data['title']);
    }

    public function testPostTomeValidationNumberRequired(): void
    {
        $client = $this->createAuthenticatedClient();

        $series = EntityFactory::createComicSeries('Serie Validation');
        $this->em->persist($series);
        $this->em->flush();

        $seriesId = $series->getId();

        // number est requis et doit être >= 0, on envoie une valeur négative
        $client->request('POST', '/api/comic_series/'.$seriesId.'/tomes', [
            'headers' => ['Content-Type' => 'application/ld+json'],
            'json' => [
                'comicSeries' => '/api/comic_series/'.$seriesId,
                'number' => -1,
            ],
        ]);

        self::assertResponseStatusCodeSame(422);
    }

    public function testPostTomeUnauthenticatedReturns401(): void
    {
        $client = $this->createUnauthenticatedClient();

        $series = EntityFactory::createComicSeries('Serie Auth');
        $this->em->persist($series);
        $this->em->flush();

        $client->request('POST', '/api/comic_series/'.$series->getId().'/tomes', [
            'headers' => ['Content-Type' => 'application/ld+json'],
            'json' => ['number' => 1],
        ]);

        self::assertResponseStatusCodeSame(401);
    }

    // ---------------------------------------------------------------
    // GET /api/tomes/{id} (single)
    // ---------------------------------------------------------------

    public function testGetSingleTomeReturns200(): void
    {
        $client = $this->createAuthenticatedClient();

        $series = EntityFactory::createComicSeries('Serie Single Tome');

        $tome = EntityFactory::createTome(5);
        $tome->setTitle('Tome Special');
        $tome->setBought(true);
        $series->addTome($tome);

        $this->em->persist($series);
        $this->em->flush();

        $tomeId = $tome->getId();

        $client->request('GET', '/api/tomes/'.$tomeId);

        self::assertResponseIsSuccessful();

        $data = $client->getResponse()->toArray();

        self::assertSame(5, $data['number']);
        self::assertSame('Tome Special', $data['title']);
        self::assertTrue($data['bought']);
    }

    // ---------------------------------------------------------------
    // PUT /api/tomes/{id} (update)
    // ---------------------------------------------------------------

    public function testPutTomeUpdatesBooleanFields(): void
    {
        $client = $this->createAuthenticatedClient();

        $series = EntityFactory::createComicSeries('Serie Update Tome');

        $tome = EntityFactory::createTome(1);
        $series->addTome($tome);

        $this->em->persist($series);
        $this->em->flush();

        $tomeId = $tome->getId();

        $seriesId = $series->getId();

        $client->request('PUT', '/api/tomes/'.$tomeId, [
            'headers' => ['Content-Type' => 'application/ld+json'],
            'json' => [
                'bought' => true,
                'comicSeries' => '/api/comic_series/'.$seriesId,
                'downloaded' => true,
                'onNas' => true,
                'read' => true,
            ],
        ]);

        self::assertResponseIsSuccessful();

        $data = $client->getResponse()->toArray();

        self::assertTrue($data['bought']);
        self::assertTrue($data['downloaded']);
        self::assertTrue($data['onNas']);
        self::assertTrue($data['read']);
    }

    public function testPutTomeUnauthenticatedReturns401(): void
    {
        $client = $this->createUnauthenticatedClient();

        $series = EntityFactory::createComicSeries('Serie Auth');
        $tome = EntityFactory::createTome(1);
        $series->addTome($tome);
        $this->em->persist($series);
        $this->em->flush();

        $client->request('PUT', '/api/tomes/'.$tome->getId(), [
            'headers' => ['Content-Type' => 'application/ld+json'],
            'json' => ['bought' => true],
        ]);

        self::assertResponseStatusCodeSame(401);
    }

    // ---------------------------------------------------------------
    // PATCH /api/tomes/{id} (partial update)
    // ---------------------------------------------------------------

    public function testPatchTomeUpdatesOnlySentFields(): void
    {
        $client = $this->createAuthenticatedClient();

        $series = EntityFactory::createComicSeries('Serie Patch Tome');

        $tome = EntityFactory::createTome(1);
        $tome->setTitle('Titre Original');
        $series->addTome($tome);

        $this->em->persist($series);
        $this->em->flush();

        $tomeId = $tome->getId();

        $client->request('PATCH', '/api/tomes/'.$tomeId, [
            'headers' => ['Content-Type' => 'application/merge-patch+json'],
            'json' => [
                'bought' => true,
            ],
        ]);

        self::assertResponseIsSuccessful();

        $data = $client->getResponse()->toArray();

        self::assertTrue($data['bought']);
        self::assertSame('Titre Original', $data['title']);
        self::assertFalse($data['downloaded']);
        self::assertFalse($data['read']);
        self::assertFalse($data['onNas']);
    }

    public function testPatchTomeUnauthenticatedReturns401(): void
    {
        $client = $this->createUnauthenticatedClient();

        $series = EntityFactory::createComicSeries('Serie Auth Patch');
        $tome = EntityFactory::createTome(1);
        $series->addTome($tome);
        $this->em->persist($series);
        $this->em->flush();

        $client->request('PATCH', '/api/tomes/'.$tome->getId(), [
            'headers' => ['Content-Type' => 'application/merge-patch+json'],
            'json' => ['bought' => true],
        ]);

        self::assertResponseStatusCodeSame(401);
    }

    // ---------------------------------------------------------------
    // DELETE /api/tomes/{id}
    // ---------------------------------------------------------------

    public function testDeleteTomeReturns204(): void
    {
        $client = $this->createAuthenticatedClient();

        $series = EntityFactory::createComicSeries('Serie Delete Tome');

        $tome = EntityFactory::createTome(1);
        $series->addTome($tome);

        $this->em->persist($series);
        $this->em->flush();

        $tomeId = $tome->getId();

        $client->request('DELETE', '/api/tomes/'.$tomeId);

        self::assertResponseStatusCodeSame(204);

        // Le tome ne doit plus exister
        $client->request('GET', '/api/tomes/'.$tomeId);
        self::assertResponseStatusCodeSame(404);
    }

    public function testDeleteTomeUnauthenticatedReturns401(): void
    {
        $client = $this->createUnauthenticatedClient();

        $series = EntityFactory::createComicSeries('Serie Auth');
        $tome = EntityFactory::createTome(1);
        $series->addTome($tome);
        $this->em->persist($series);
        $this->em->flush();

        $client->request('DELETE', '/api/tomes/'.$tome->getId());

        self::assertResponseStatusCodeSame(401);
    }
}

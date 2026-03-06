<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api;

use ApiPlatform\Symfony\Bundle\Test\ApiTestCase;
use App\Repository\UserRepository;
use App\Tests\Factory\EntityFactory;
use App\Tests\Trait\AuthenticatedTestTrait;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Tests fonctionnels pour l'API ComicSeries.
 */
final class ComicSeriesApiTest extends ApiTestCase
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
    // GET /api/comic_series (collection)
    // ---------------------------------------------------------------

    public function testGetCollectionReturnsJsonLd(): void
    {
        $client = $this->createAuthenticatedClient();

        $client->request('GET', '/api/comic_series');

        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('content-type', 'application/ld+json; charset=utf-8');

        $data = $client->getResponse()->toArray();

        self::assertArrayHasKey('member', $data);
        self::assertArrayHasKey('totalItems', $data);
        self::assertIsArray($data['member']);
    }

    public function testGetCollectionUnauthenticatedReturns401(): void
    {
        $client = $this->createUnauthenticatedClient();

        $client->request('GET', '/api/comic_series');

        self::assertResponseStatusCodeSame(401);
    }

    public function testGetCollectionOrderedByTitleAsc(): void
    {
        $client = $this->createAuthenticatedClient();

        $seriesC = EntityFactory::createComicSeries('Charlie');
        $seriesA = EntityFactory::createComicSeries('Alpha');
        $seriesB = EntityFactory::createComicSeries('Bravo');

        $this->em->persist($seriesC);
        $this->em->persist($seriesA);
        $this->em->persist($seriesB);
        $this->em->flush();

        $client->request('GET', '/api/comic_series');

        self::assertResponseIsSuccessful();

        $data = $client->getResponse()->toArray();
        $titles = \array_map(static fn (array $item): string => $item['title'], $data['member']);

        self::assertSame(['Alpha', 'Bravo', 'Charlie'], $titles);
    }

    // ---------------------------------------------------------------
    // GET /api/comic_series/{id} (single)
    // ---------------------------------------------------------------

    public function testGetSingleReturnsCorrectData(): void
    {
        $client = $this->createAuthenticatedClient();

        $series = EntityFactory::createComicSeries('One Piece');
        $this->em->persist($series);
        $this->em->flush();

        $id = $series->getId();

        $client->request('GET', '/api/comic_series/'.$id);

        self::assertResponseIsSuccessful();

        $data = $client->getResponse()->toArray();

        self::assertSame('One Piece', $data['title']);
        self::assertSame($id, $data['id']);
    }

    public function testGetSingleNotFoundReturns404(): void
    {
        $client = $this->createAuthenticatedClient();

        $client->request('GET', '/api/comic_series/999999');

        self::assertResponseStatusCodeSame(404);
    }

    // ---------------------------------------------------------------
    // POST /api/comic_series (create)
    // ---------------------------------------------------------------

    public function testPostCreateReturns201(): void
    {
        $client = $this->createAuthenticatedClient();

        $client->request('POST', '/api/comic_series', [
            'headers' => ['Content-Type' => 'application/ld+json'],
            'json' => [
                'status' => 'buying',
                'title' => 'Nouvelle Série',
                'type' => 'manga',
            ],
        ]);

        self::assertResponseStatusCodeSame(201);

        $data = $client->getResponse()->toArray();

        self::assertSame('Nouvelle Série', $data['title']);
        self::assertSame('buying', $data['status']);
        self::assertSame('manga', $data['type']);
    }

    public function testPostCreateWithAuthorsLinksAuthors(): void
    {
        $client = $this->createAuthenticatedClient();

        // Créer un auteur d'abord
        $client->request('POST', '/api/authors', [
            'headers' => ['Content-Type' => 'application/ld+json'],
            'json' => ['name' => 'Eiichiro Oda'],
        ]);
        self::assertResponseStatusCodeSame(201);
        $authorIri = $client->getResponse()->toArray()['@id'];

        // Créer la série avec l'auteur
        $client->request('POST', '/api/comic_series', [
            'headers' => ['Content-Type' => 'application/ld+json'],
            'json' => [
                'authors' => [$authorIri],
                'title' => 'One Piece',
                'type' => 'manga',
            ],
        ]);

        self::assertResponseStatusCodeSame(201);

        $data = $client->getResponse()->toArray();

        self::assertCount(1, $data['authors']);
        self::assertSame('Eiichiro Oda', $data['authors'][0]['name']);
    }

    public function testPostCreateValidationErrorBlankTitleReturns422(): void
    {
        $client = $this->createAuthenticatedClient();

        $client->request('POST', '/api/comic_series', [
            'headers' => ['Content-Type' => 'application/ld+json'],
            'json' => [
                'title' => '',
                'type' => 'bd',
            ],
        ]);

        self::assertResponseStatusCodeSame(422);
    }

    public function testPostCreateUnauthenticatedReturns401(): void
    {
        $client = $this->createUnauthenticatedClient();

        $client->request('POST', '/api/comic_series', [
            'headers' => ['Content-Type' => 'application/ld+json'],
            'json' => [
                'title' => 'Test',
                'type' => 'bd',
            ],
        ]);

        self::assertResponseStatusCodeSame(401);
    }

    // ---------------------------------------------------------------
    // PUT /api/comic_series/{id} (update)
    // ---------------------------------------------------------------

    public function testPutUpdateReturns200(): void
    {
        $client = $this->createAuthenticatedClient();

        $series = EntityFactory::createComicSeries('Ancien Titre');
        $this->em->persist($series);
        $this->em->flush();

        $id = $series->getId();

        $client->request('PUT', '/api/comic_series/'.$id, [
            'headers' => ['Content-Type' => 'application/ld+json'],
            'json' => [
                'status' => 'finished',
                'title' => 'Nouveau Titre',
            ],
        ]);

        self::assertResponseIsSuccessful();

        $data = $client->getResponse()->toArray();

        self::assertSame('Nouveau Titre', $data['title']);
        self::assertSame('finished', $data['status']);
    }

    public function testPatchWithoutTomesPreservesExisting(): void
    {
        $client = $this->createAuthenticatedClient();

        $series = EntityFactory::createComicSeries('Série avec tomes');
        for ($i = 1; $i <= 5; $i++) {
            $tome = EntityFactory::createTome($i, bought: true);
            $series->addTome($tome);
            $this->em->persist($tome);
        }
        $this->em->persist($series);
        $this->em->flush();

        $id = $series->getId();

        // PATCH sans tomes — les tomes existants doivent rester intacts
        $client->request('PATCH', '/api/comic_series/'.$id, [
            'headers' => ['Content-Type' => 'application/merge-patch+json'],
            'json' => [
                'title' => 'Série avec tomes modifiée',
            ],
        ]);

        self::assertResponseIsSuccessful();

        $data = $client->getResponse()->toArray();

        self::assertSame('Série avec tomes modifiée', $data['title']);
        self::assertCount(5, $data['tomes']);
    }

    public function testPatchPreservesExistingTomes(): void
    {
        $client = $this->createAuthenticatedClient();

        $series = EntityFactory::createComicSeries('Série avec tomes');
        for ($i = 1; $i <= 5; $i++) {
            $tome = EntityFactory::createTome($i, bought: true);
            $series->addTome($tome);
            $this->em->persist($tome);
        }
        $this->em->persist($series);
        $this->em->flush();

        $id = $series->getId();
        $tomeIds = $series->getTomes()->map(static fn ($t) => $t->getId())->toArray();

        // PATCH avec @id (IRI) pour identifier les tomes existants
        $tomesPayload = [];
        foreach ($series->getTomes() as $tome) {
            $tomesPayload[] = [
                '@id' => '/api/tomes/'.$tome->getId(),
                'bought' => $tome->isBought(),
                'downloaded' => $tome->isDownloaded(),
                'isbn' => null,
                'number' => $tome->getNumber(),
                'onNas' => $tome->isOnNas(),
                'read' => $tome->isRead(),
                'title' => null,
                'tomeEnd' => null,
            ];
        }

        $client->request('PATCH', '/api/comic_series/'.$id, [
            'headers' => ['Content-Type' => 'application/merge-patch+json'],
            'json' => [
                'tomes' => $tomesPayload,
            ],
        ]);

        self::assertResponseIsSuccessful();

        $data = $client->getResponse()->toArray();

        self::assertCount(5, $data['tomes']);
        // Vérifier que les données des tomes sont préservées
        $numbers = \array_map(static fn ($t) => $t['number'], $data['tomes']);
        self::assertSame([1, 2, 3, 4, 5], $numbers);
        // Tous les tomes doivent être achetés (comme les originaux)
        self::assertTrue(\array_reduce($data['tomes'], static fn ($carry, $t) => $carry && $t['bought'], true));
    }

    public function testPatchAddsNewTomesToExisting(): void
    {
        $client = $this->createAuthenticatedClient();

        $series = EntityFactory::createComicSeries('Série existante');
        for ($i = 1; $i <= 3; $i++) {
            $tome = EntityFactory::createTome($i, bought: true);
            $series->addTome($tome);
            $this->em->persist($tome);
        }
        $this->em->persist($series);
        $this->em->flush();

        $id = $series->getId();

        // Tomes existants (@id) + 2 nouveaux (sans @id)
        $tomesPayload = [];
        foreach ($series->getTomes() as $tome) {
            $tomesPayload[] = [
                '@id' => '/api/tomes/'.$tome->getId(),
                'bought' => true,
                'downloaded' => false,
                'isbn' => null,
                'number' => $tome->getNumber(),
                'onNas' => false,
                'read' => false,
                'title' => null,
                'tomeEnd' => null,
            ];
        }
        $tomesPayload[] = ['bought' => false, 'downloaded' => false, 'isbn' => null, 'number' => 4, 'onNas' => false, 'read' => false, 'title' => null, 'tomeEnd' => null];
        $tomesPayload[] = ['bought' => false, 'downloaded' => false, 'isbn' => null, 'number' => 5, 'onNas' => false, 'read' => false, 'title' => null, 'tomeEnd' => null];

        $client->request('PATCH', '/api/comic_series/'.$id, [
            'headers' => ['Content-Type' => 'application/merge-patch+json'],
            'json' => [
                'tomes' => $tomesPayload,
            ],
        ]);

        self::assertResponseIsSuccessful();

        $data = $client->getResponse()->toArray();

        self::assertCount(5, $data['tomes']);
        $numbers = \array_map(static fn ($t) => $t['number'], $data['tomes']);
        self::assertSame([1, 2, 3, 4, 5], $numbers);
    }

    public function testTomesReturnedSortedByNumber(): void
    {
        $client = $this->createAuthenticatedClient();

        $series = EntityFactory::createComicSeries('Série triée');
        foreach ([5, 1, 3] as $num) {
            $tome = EntityFactory::createTome($num);
            $series->addTome($tome);
            $this->em->persist($tome);
        }
        $this->em->persist($series);
        $this->em->flush();

        $client->request('GET', '/api/comic_series/'.$series->getId());

        self::assertResponseIsSuccessful();

        $data = $client->getResponse()->toArray();

        $numbers = \array_map(static fn ($t) => $t['number'], $data['tomes']);
        self::assertSame([1, 3, 5], $numbers);
    }

    public function testPutUpdateUnauthenticatedReturns401(): void
    {
        $client = $this->createUnauthenticatedClient();

        $series = EntityFactory::createComicSeries('Serie');
        $this->em->persist($series);
        $this->em->flush();

        $client->request('PUT', '/api/comic_series/'.$series->getId(), [
            'headers' => ['Content-Type' => 'application/ld+json'],
            'json' => ['title' => 'Modifié'],
        ]);

        self::assertResponseStatusCodeSame(401);
    }

    // ---------------------------------------------------------------
    // DELETE /api/comic_series/{id} (soft delete)
    // ---------------------------------------------------------------

    public function testDeleteSoftReturns204(): void
    {
        $client = $this->createAuthenticatedClient();

        $series = EntityFactory::createComicSeries('A Supprimer');
        $this->em->persist($series);
        $this->em->flush();

        $id = $series->getId();

        $client->request('DELETE', '/api/comic_series/'.$id);

        self::assertResponseStatusCodeSame(204);

        // La série ne doit plus apparaître dans la collection
        $client->request('GET', '/api/comic_series');
        $data = $client->getResponse()->toArray();
        $ids = \array_map(static fn (array $item): int => $item['id'], $data['member']);

        self::assertNotContains($id, $ids);
    }

    public function testGetDeletedSeriesReturns404(): void
    {
        $client = $this->createAuthenticatedClient();

        $series = EntityFactory::createComicSeries('Supprimee');
        $this->em->persist($series);
        $this->em->flush();

        $id = $series->getId();

        $client->request('DELETE', '/api/comic_series/'.$id);
        self::assertResponseStatusCodeSame(204);

        // Tenter d'accéder à la série supprimée
        $client->request('GET', '/api/comic_series/'.$id);
        self::assertResponseStatusCodeSame(404);
    }

    public function testDeleteUnauthenticatedReturns401(): void
    {
        $client = $this->createUnauthenticatedClient();

        $series = EntityFactory::createComicSeries('Serie');
        $this->em->persist($series);
        $this->em->flush();

        $client->request('DELETE', '/api/comic_series/'.$series->getId());

        self::assertResponseStatusCodeSame(401);
    }

    // ---------------------------------------------------------------
    // PUT /api/comic_series/{id}/restore
    // ---------------------------------------------------------------

    public function testRestoreReturns200(): void
    {
        $client = $this->createAuthenticatedClient();

        $series = EntityFactory::createComicSeries('A Restaurer');
        $this->em->persist($series);
        $this->em->flush();

        $id = $series->getId();

        // Supprimer (soft)
        $client->request('DELETE', '/api/comic_series/'.$id);
        self::assertResponseStatusCodeSame(204);

        // Restaurer
        $client->request('PUT', '/api/comic_series/'.$id.'/restore');
        self::assertResponseIsSuccessful();

        // La série doit réapparaître dans la collection
        $client->request('GET', '/api/comic_series/'.$id);
        self::assertResponseIsSuccessful();

        $data = $client->getResponse()->toArray();
        self::assertSame('A Restaurer', $data['title']);
    }

    public function testRestoreNonDeletedSeriesReturns404(): void
    {
        $client = $this->createAuthenticatedClient();

        $series = EntityFactory::createComicSeries('Active');
        $this->em->persist($series);
        $this->em->flush();

        $id = $series->getId();

        // Tenter de restaurer une série non supprimée
        $client->request('PUT', '/api/comic_series/'.$id.'/restore');
        self::assertResponseStatusCodeSame(404);
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

        // La série ne doit plus exister du tout (même la restauration échoue)
        $client->request('PUT', '/api/comic_series/'.$id.'/restore');
        self::assertResponseStatusCodeSame(404);
    }
}

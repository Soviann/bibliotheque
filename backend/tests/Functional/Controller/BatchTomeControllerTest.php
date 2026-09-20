<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller;

use ApiPlatform\Symfony\Bundle\Test\ApiTestCase;
use App\Entity\Tome;
use App\Repository\UserRepository;
use App\Tests\Factory\EntityFactory;
use App\Tests\Trait\AuthenticatedTestTrait;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Tests fonctionnels pour BatchTomeController.
 */
final class BatchTomeControllerTest extends ApiTestCase
{
    use AuthenticatedTestTrait;

    protected static ?bool $alwaysBootKernel = true;

    protected function setUp(): void
    {
        $container = self::getContainer();
        $em = $container->get(EntityManagerInterface::class);

        /** @var UserRepository $userRepo */
        $userRepo = $container->get(UserRepository::class);

        if (null === $userRepo->findOneBy(['email' => 'test@example.com'])) {
            $user = EntityFactory::createUser();
            $em->persist($user);
            $em->flush();
        }
    }

    public function testRequiresAuthentication(): void
    {
        $client = $this->createUnauthenticatedClient();

        $client->request('POST', '/api/comic_series/1/tomes/batch', [
            'json' => ['tomes' => [['number' => 1]]],
        ]);

        self::assertResponseStatusCodeSame(401);
    }

    public function testNonExistentSeriesReturns404(): void
    {
        $client = $this->createAuthenticatedClient();

        $client->request('POST', '/api/comic_series/999999/tomes/batch', [
            'json' => ['tomes' => [['number' => 1]]],
        ]);

        self::assertResponseStatusCodeSame(404);
        $response = $client->getResponse();
        self::assertNotNull($response);
        $data = $response->toArray(false);
        self::assertSame('Série non trouvée.', $data['error']);
    }

    public function testSoftDeletedSeriesReturns404(): void
    {
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $series = EntityFactory::createComicSeries('Deleted Series');
        $series->setDeletedAt(new \DateTime());
        $em->persist($series);
        $em->flush();

        $client = $this->createAuthenticatedClient();
        $client->request('POST', '/api/comic_series/'.$series->getId().'/tomes/batch', [
            'json' => ['tomes' => [['number' => 1]]],
        ]);

        self::assertResponseStatusCodeSame(404);
    }

    public function testEmptyTomesListReturns400(): void
    {
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $series = EntityFactory::createComicSeries('Series for Empty');
        $em->persist($series);
        $em->flush();

        $client = $this->createAuthenticatedClient();
        $client->request('POST', '/api/comic_series/'.$series->getId().'/tomes/batch', [
            'json' => ['tomes' => []],
        ]);

        self::assertResponseStatusCodeSame(400);
        $response = $client->getResponse();
        self::assertNotNull($response);
        $data = $response->toArray(false);
        self::assertSame('Aucun tome fourni.', $data['error']);
    }

    public function testInvalidTomeNumberReturns400(): void
    {
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $series = EntityFactory::createComicSeries('Series for Invalid Number');
        $em->persist($series);
        $em->flush();

        $client = $this->createAuthenticatedClient();
        $client->request('POST', '/api/comic_series/'.$series->getId().'/tomes/batch', [
            'json' => ['tomes' => [['number' => -5]]],
        ]);

        self::assertResponseStatusCodeSame(400);
    }

    public function testValidationConstraintFailureReturns400(): void
    {
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $series = EntityFactory::createComicSeries('Series for Constraint');
        $em->persist($series);
        $em->flush();

        $client = $this->createAuthenticatedClient();
        // tomeEnd < number violates GreaterThanOrEqual constraint
        $client->request('POST', '/api/comic_series/'.$series->getId().'/tomes/batch', [
            'json' => ['tomes' => [['number' => 5, 'tomeEnd' => 3]]],
        ]);

        self::assertResponseStatusCodeSame(400);
    }

    public function testBatchCreateWithObjectPayload(): void
    {
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $series = EntityFactory::createComicSeries('Batch Create Object Series');
        $em->persist($series);
        $em->flush();

        $client = $this->createAuthenticatedClient();
        $client->request('POST', '/api/comic_series/'.$series->getId().'/tomes/batch', [
            'json' => [
                'tomes' => [
                    [
                        'bought' => true,
                        'isHorsSerie' => false,
                        'isbn' => '9782012345678',
                        'number' => 1,
                        'onNas' => true,
                        'read' => true,
                        'title' => 'Tome Premier',
                    ],
                    [
                        'bought' => false,
                        'isHorsSerie' => false,
                        'number' => 2,
                        'onNas' => false,
                        'read' => false,
                    ],
                ],
            ],
        ]);

        self::assertResponseStatusCodeSame(201);
        $response = $client->getResponse();
        self::assertNotNull($response);
        $data = $response->toArray();
        self::assertCount(2, $data);

        self::assertSame(1, $data[0]['number']);
        self::assertTrue($data[0]['bought']);
        self::assertTrue($data[0]['onNas']);
        self::assertTrue($data[0]['read']);
        self::assertSame('9782012345678', $data[0]['isbn']);
        self::assertSame('Tome Premier', $data[0]['title']);

        self::assertSame(2, $data[1]['number']);
        self::assertFalse($data[1]['bought']);

        // Verify in DB
        $tomes = $em->getRepository(Tome::class)->findBy(['comicSeries' => $series]);
        self::assertCount(2, $tomes);
    }

    public function testBatchCreateWithDirectArrayPayload(): void
    {
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $series = EntityFactory::createComicSeries('Batch Create Array Series');
        $em->persist($series);
        $em->flush();

        $client = $this->createAuthenticatedClient();
        $client->request('POST', '/api/comic_series/'.$series->getId().'/tomes/batch', [
            'json' => [
                [
                    'bought' => false,
                    'isHorsSerie' => true,
                    'number' => 1,
                    'title' => 'Hors-Série 1',
                ],
            ],
        ]);

        self::assertResponseStatusCodeSame(201);
        $response = $client->getResponse();
        self::assertNotNull($response);
        $data = $response->toArray();
        self::assertCount(1, $data);
        self::assertSame(1, $data[0]['number']);
        self::assertTrue($data[0]['isHorsSerie']);
        self::assertSame('Hors-Série 1', $data[0]['title']);
    }
}

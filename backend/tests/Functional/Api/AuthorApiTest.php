<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api;

use ApiPlatform\Symfony\Bundle\Test\ApiTestCase;
use App\Repository\UserRepository;
use App\Tests\Factory\EntityFactory;
use App\Tests\Trait\AuthenticatedTestTrait;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Tests fonctionnels pour l'API Author.
 */
final class AuthorApiTest extends ApiTestCase
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
    // GET /api/authors (collection)
    // ---------------------------------------------------------------

    public function testGetCollectionIsPaginated(): void
    {
        $client = $this->createAuthenticatedClient();

        // Créer quelques auteurs
        for ($i = 1; $i <= 3; ++$i) {
            $author = EntityFactory::createAuthor('Auteur '.$i);
            $this->em->persist($author);
        }
        $this->em->flush();

        $client->request('GET', '/api/authors');

        self::assertResponseIsSuccessful();

        $data = $client->getResponse()->toArray();

        self::assertArrayHasKey('member', $data);
        self::assertArrayHasKey('totalItems', $data);
        // La pagination est activée avec 50 items par page
        self::assertGreaterThanOrEqual(3, $data['totalItems']);
    }

    public function testGetCollectionReturnsJsonLd(): void
    {
        $client = $this->createAuthenticatedClient();

        $client->request('GET', '/api/authors');

        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('content-type', 'application/ld+json; charset=utf-8');
    }

    public function testGetCollectionWithNameFilterPartialSearch(): void
    {
        $client = $this->createAuthenticatedClient();

        $author1 = EntityFactory::createAuthor('Akira Toriyama');
        $this->em->persist($author1);

        $author2 = EntityFactory::createAuthor('Goscinny');
        $this->em->persist($author2);

        $author3 = EntityFactory::createAuthor('Akira Himekawa');
        $this->em->persist($author3);

        $this->em->flush();

        $client->request('GET', '/api/authors', [
            'query' => ['name' => 'Akira'],
        ]);

        self::assertResponseIsSuccessful();

        $data = $client->getResponse()->toArray();

        self::assertSame(2, $data['totalItems']);

        $names = \array_map(static fn (array $item): string => $item['name'], $data['member']);
        self::assertContains('Akira Toriyama', $names);
        self::assertContains('Akira Himekawa', $names);
    }

    public function testGetCollectionUnauthenticatedReturns401(): void
    {
        $client = $this->createUnauthenticatedClient();

        $client->request('GET', '/api/authors');

        self::assertResponseStatusCodeSame(401);
    }

    // ---------------------------------------------------------------
    // GET /api/authors/{id} (single)
    // ---------------------------------------------------------------

    public function testGetSingleReturns200(): void
    {
        $client = $this->createAuthenticatedClient();

        $author = EntityFactory::createAuthor('Naoki Urasawa');
        $this->em->persist($author);
        $this->em->flush();

        $id = $author->getId();

        $client->request('GET', '/api/authors/'.$id);

        self::assertResponseIsSuccessful();

        $data = $client->getResponse()->toArray();

        self::assertSame('Naoki Urasawa', $data['name']);
    }

    public function testGetSingleNotFoundReturns404(): void
    {
        $client = $this->createAuthenticatedClient();

        $client->request('GET', '/api/authors/999999');

        self::assertResponseStatusCodeSame(404);
    }

    public function testGetSingleUnauthenticatedReturns401(): void
    {
        $client = $this->createUnauthenticatedClient();

        $author = EntityFactory::createAuthor('Test Author');
        $this->em->persist($author);
        $this->em->flush();

        $client->request('GET', '/api/authors/'.$author->getId());

        self::assertResponseStatusCodeSame(401);
    }

    // ---------------------------------------------------------------
    // POST /api/authors (create)
    // ---------------------------------------------------------------

    public function testPostCreateReturns201(): void
    {
        $client = $this->createAuthenticatedClient();

        $client->request('POST', '/api/authors', [
            'headers' => ['Content-Type' => 'application/ld+json'],
            'json' => ['name' => 'Hajime Isayama'],
        ]);

        self::assertResponseStatusCodeSame(201);

        $data = $client->getResponse()->toArray();

        self::assertSame('Hajime Isayama', $data['name']);
        self::assertArrayHasKey('id', $data);
    }

    public function testPostCreateValidationBlankNameReturns422(): void
    {
        $client = $this->createAuthenticatedClient();

        $client->request('POST', '/api/authors', [
            'headers' => ['Content-Type' => 'application/ld+json'],
            'json' => ['name' => ''],
        ]);

        self::assertResponseStatusCodeSame(422);
    }
}

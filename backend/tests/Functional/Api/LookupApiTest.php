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
        $em = static::getContainer()->get(EntityManagerInterface::class);

        /** @var UserRepository $userRepo */
        $userRepo = static::getContainer()->get(UserRepository::class);

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
}

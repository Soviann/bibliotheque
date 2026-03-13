<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api;

use ApiPlatform\Symfony\Bundle\Test\ApiTestCase;
use App\Repository\UserRepository;
use App\Tests\Factory\EntityFactory;
use App\Tests\Trait\AuthenticatedTestTrait;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Tests fonctionnels pour le cache HTTP (ETag) sur les endpoints de lecture.
 */
final class HttpCacheApiTest extends ApiTestCase
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

    public function testGetCollectionReturnsEtag(): void
    {
        $client = $this->createAuthenticatedClient();

        $client->request('GET', '/api/comic_series');

        self::assertResponseIsSuccessful();
        self::assertResponseHasHeader('etag');
    }

    public function testGetItemReturnsEtag(): void
    {
        $client = $this->createAuthenticatedClient();

        $series = EntityFactory::createComicSeries('Test ETag');
        $this->em->persist($series);
        $this->em->flush();

        $client->request('GET', '/api/comic_series/'.$series->getId());

        self::assertResponseIsSuccessful();
        self::assertResponseHasHeader('etag');
    }

    public function testGetCollectionReturns304WithMatchingEtag(): void
    {
        $client = $this->createAuthenticatedClient();

        $client->request('GET', '/api/comic_series');
        $response = $client->getResponse();
        self::assertNotNull($response);
        $etag = $response->getHeaders()['etag'][0] ?? null;
        self::assertNotNull($etag);

        $client->request('GET', '/api/comic_series', ['headers' => ['If-None-Match' => $etag]]);

        self::assertResponseStatusCodeSame(304);
    }

    public function testGetItemReturns304WithMatchingEtag(): void
    {
        $client = $this->createAuthenticatedClient();

        $series = EntityFactory::createComicSeries('Test 304');
        $this->em->persist($series);
        $this->em->flush();

        $url = '/api/comic_series/'.$series->getId();

        $client->request('GET', $url);
        $response = $client->getResponse();
        self::assertNotNull($response);
        $etag = $response->getHeaders()['etag'][0] ?? null;
        self::assertNotNull($etag);

        $client->request('GET', $url, ['headers' => ['If-None-Match' => $etag]]);

        self::assertResponseStatusCodeSame(304);
    }

    public function testGetCollectionReturns200WithStaleEtag(): void
    {
        $client = $this->createAuthenticatedClient();

        $client->request('GET', '/api/comic_series', ['headers' => ['If-None-Match' => '"stale-etag"']]);

        self::assertResponseIsSuccessful();
        self::assertResponseHasHeader('etag');
    }
}

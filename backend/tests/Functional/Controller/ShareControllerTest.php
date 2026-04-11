<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller;

use ApiPlatform\Symfony\Bundle\Test\ApiTestCase;
use App\Entity\Tome;
use App\Repository\UserRepository;
use App\Service\Lookup\Contract\LookupResult;
use App\Service\Lookup\LookupOrchestrator;
use App\Tests\Factory\EntityFactory;
use App\Tests\Trait\AuthenticatedTestTrait;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Transport\InMemory\InMemoryTransport;

/**
 * Tests fonctionnels pour ShareController.
 */
final class ShareControllerTest extends ApiTestCase
{
    use AuthenticatedTestTrait;

    protected static ?bool $alwaysBootKernel = true;

    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        $container = self::getContainer();
        $this->em = $container->get(EntityManagerInterface::class);

        $container->get('cache.rate_limiter')->clear();

        /** @var UserRepository $userRepo */
        $userRepo = $container->get(UserRepository::class);

        if (null === $userRepo->findOneBy(['email' => 'test@example.com'])) {
            $user = EntityFactory::createUser();
            $this->em->persist($user);
            $this->em->flush();
        }
    }

    // ---------------------------------------------------------------
    // Authentification
    // ---------------------------------------------------------------

    public function testShareRequiresAuthentication(): void
    {
        $client = $this->createUnauthenticatedClient();

        $client->request('POST', '/api/share', [
            'json' => ['url' => 'https://www.amazon.fr/dp/2723492532'],
        ]);

        self::assertResponseStatusCodeSame(401);
    }

    // ---------------------------------------------------------------
    // Validation de la requête
    // ---------------------------------------------------------------

    public function testShareReturnsBadRequestWhenUrlMissing(): void
    {
        $client = $this->createAuthenticatedClient();

        $client->request('POST', '/api/share', [
            'json' => [],
        ]);

        self::assertResponseStatusCodeSame(400);
        $response = $client->getResponse();
        self::assertNotNull($response);
        $data = $response->toArray(false);
        self::assertArrayHasKey('error', $data);
    }

    // ---------------------------------------------------------------
    // Match par ISBN
    // ---------------------------------------------------------------

    public function testShareReturnsMatchedWhenSeriesFoundByIsbn(): void
    {
        // Créer une série avec un tome ISBN correspondant
        $series = EntityFactory::createComicSeries('Astérix');
        $this->em->persist($series);

        $tome = EntityFactory::createTome(1);
        $tome->setIsbn('2723492532');
        $tome->setComicSeries($series);
        $this->em->persist($tome);
        $this->em->flush();

        $seriesId = $series->getId();
        self::assertNotNull($seriesId);

        // Créer le client d'abord (le kernel est déjà booté via $alwaysBootKernel)
        $client = $this->createAuthenticatedClient();

        // Mocker LookupOrchestrator après création du client (le container est partagé)
        $mockOrchestrator = $this->createStub(LookupOrchestrator::class);
        $mockOrchestrator->method('lookup')->willReturn(
            new LookupResult(isbn: '2723492532', title: 'Astérix', source: 'test'),
        );

        self::getContainer()->set(LookupOrchestrator::class, $mockOrchestrator);

        $client->request('POST', '/api/share', [
            'json' => ['url' => 'https://www.amazon.fr/dp/2723492532'],
        ]);

        self::assertResponseIsSuccessful();
        $response = $client->getResponse();
        self::assertNotNull($response);
        $data = $response->toArray();
        self::assertTrue($data['matched']);
        self::assertSame($seriesId, $data['seriesId']);

        // Vérifier que le message est en queue
        /** @var InMemoryTransport $transport */
        $transport = self::getContainer()->get('messenger.transport.async');
        self::assertCount(1, $transport->getSent());
    }

    // ---------------------------------------------------------------
    // Pas de match (unmatched)
    // ---------------------------------------------------------------

    public function testShareReturnsUnmatchedWhenSeriesNotFound(): void
    {
        $client = $this->createAuthenticatedClient();

        // Mocker pour retourner un résultat sans ISBN connu en base
        $mockOrchestrator = $this->createStub(LookupOrchestrator::class);
        $mockOrchestrator->method('lookupByTitle')->willReturn(
            new LookupResult(title: 'Série inconnue', source: 'test'),
        );

        self::getContainer()->set(LookupOrchestrator::class, $mockOrchestrator);

        $client->request('POST', '/api/share', [
            'json' => ['url' => 'https://fr.wikipedia.org/wiki/S%C3%A9rie_inconnue'],
        ]);

        self::assertResponseIsSuccessful();
        $response = $client->getResponse();
        self::assertNotNull($response);
        $data = $response->toArray();
        self::assertFalse($data['matched']);
        self::assertArrayHasKey('lookupResult', $data);
        self::assertIsArray($data['lookupResult']);
    }

    // ---------------------------------------------------------------
    // 404 si rien trouvé
    // ---------------------------------------------------------------

    public function testShareReturns404WhenOrchestratorReturnsNull(): void
    {
        $client = $this->createAuthenticatedClient();

        $mockOrchestrator = $this->createStub(LookupOrchestrator::class);
        $mockOrchestrator->method('lookupByTitle')->willReturn(null);

        self::getContainer()->set(LookupOrchestrator::class, $mockOrchestrator);

        $client->request('POST', '/api/share', [
            'json' => ['url' => 'https://example.com/foo'],
        ]);

        self::assertResponseStatusCodeSame(404);
        $response = $client->getResponse();
        self::assertNotNull($response);
        $data = $response->toArray(false);
        self::assertArrayHasKey('error', $data);
    }
}

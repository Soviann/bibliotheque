<?php

declare(strict_types=1);

namespace App\Tests\State;

use App\Entity\ComicSeries;
use App\Entity\User;
use App\Enum\ComicStatus;
use App\Enum\ComicType;
use App\Repository\ComicSeriesRepository;
use Doctrine\ORM\EntityManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\User\UserProviderInterface;

/**
 * Tests fonctionnels de l'API ComicSeries (API Platform 4).
 */
final class ComicSeriesApiTest extends WebTestCase
{
    private KernelBrowser $client;
    private string $jwtToken;

    protected function setUp(): void
    {
        $this->client = self::createClient();

        /** @var UserProviderInterface<User> $userProvider */
        $userProvider = self::getContainer()->get('security.user.provider.concrete.app_user_provider');
        $user = $userProvider->loadUserByIdentifier('test@example.com');

        /** @var JWTTokenManagerInterface $jwtManager */
        $jwtManager = self::getContainer()->get(JWTTokenManagerInterface::class);
        $this->jwtToken = $jwtManager->create($user);
    }

    public function testGetCollectionRequiresAuth(): void
    {
        $this->client->request(Request::METHOD_GET, '/api/comic_series');

        self::assertResponseStatusCodeSame(401);
    }

    public function testGetCollectionReturnsJsonLd(): void
    {
        $this->createComicSeries('Test Collection BD');

        $this->apiRequest('GET', '/api/comic_series');

        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('content-type', 'application/ld+json; charset=utf-8');

        $data = $this->getJsonResponse();
        self::assertArrayHasKey('member', $data);
        self::assertArrayHasKey('totalItems', $data);
        self::assertGreaterThanOrEqual(1, $data['totalItems']);
    }

    public function testGetSingleComicSeries(): void
    {
        $comic = $this->createComicSeries('Test Single BD');

        $this->apiRequest('GET', '/api/comic_series/'.$comic->getId());

        self::assertResponseIsSuccessful();

        $data = $this->getJsonResponse();
        self::assertSame('Test Single BD', $data['title']);
        self::assertSame('buying', $data['status']);
        self::assertSame('bd', $data['type']);
    }

    public function testCreateComicSeries(): void
    {
        $this->apiRequest('POST', '/api/comic_series', [
            'isOneShot' => false,
            'latestPublishedIssue' => 5,
            'latestPublishedIssueComplete' => false,
            'status' => 'buying',
            'title' => 'Nouvelle Série API',
            'type' => 'manga',
        ]);

        self::assertResponseStatusCodeSame(201);

        $data = $this->getJsonResponse();
        self::assertSame('Nouvelle Série API', $data['title']);
        self::assertSame('manga', $data['type']);
        self::assertSame(5, $data['latestPublishedIssue']);
    }

    public function testCreateComicSeriesValidationError(): void
    {
        $this->apiRequest('POST', '/api/comic_series', [
            'title' => '',
        ]);

        self::assertResponseStatusCodeSame(422);
    }

    public function testUpdateComicSeries(): void
    {
        $comic = $this->createComicSeries('Avant Modif');

        $this->apiRequest('PUT', '/api/comic_series/'.$comic->getId(), [
            'description' => 'Description ajoutée',
            'isOneShot' => false,
            'latestPublishedIssue' => 10,
            'latestPublishedIssueComplete' => true,
            'publisher' => 'Glénat',
            'status' => 'buying',
            'title' => 'Après Modif',
            'type' => 'bd',
        ]);

        self::assertResponseIsSuccessful();

        $data = $this->getJsonResponse();
        self::assertSame('Après Modif', $data['title']);
        self::assertSame('Glénat', $data['publisher']);
        self::assertSame(10, $data['latestPublishedIssue']);
        self::assertTrue($data['latestPublishedIssueComplete']);
    }

    public function testSoftDeleteComicSeries(): void
    {
        $comic = $this->createComicSeries('À Supprimer');
        $id = $comic->getId();

        $this->apiRequest('DELETE', '/api/comic_series/'.$id);

        self::assertResponseStatusCodeSame(204);

        // La série ne doit plus apparaître dans la collection (filtre soft-delete)
        $this->apiRequest('GET', '/api/comic_series/'.$id);

        self::assertResponseStatusCodeSame(404);
    }

    public function testRestoreComicSeries(): void
    {
        $comic = $this->createComicSeries('À Restaurer');
        $id = $comic->getId();

        // Soft-delete d'abord
        $this->apiRequest('DELETE', '/api/comic_series/'.$id);
        self::assertResponseStatusCodeSame(204);

        // Restaurer (le SoftDeletedComicSeriesProvider désactive le filtre)
        $this->apiRequest('PUT', '/api/comic_series/'.$id.'/restore', []);

        self::assertResponseIsSuccessful();

        $data = $this->getJsonResponse();
        self::assertSame('À Restaurer', $data['title']);

        // Vérifier que la série est de nouveau visible
        $this->apiRequest('GET', '/api/comic_series/'.$id);
        self::assertResponseIsSuccessful();
    }

    public function testPermanentDeleteComicSeries(): void
    {
        $comic = $this->createComicSeries('À Supprimer Définitivement');
        $id = $comic->getId();

        // Soft-delete d'abord
        $this->apiRequest('DELETE', '/api/comic_series/'.$id);
        self::assertResponseStatusCodeSame(204);

        // Suppression définitive (le SoftDeletedComicSeriesProvider désactive le filtre)
        $this->apiRequest('DELETE', '/api/trash/'.$id.'/permanent');

        self::assertResponseStatusCodeSame(204);

        // Vérifier que la série n'existe plus du tout en base
        /** @var EntityManagerInterface $em */
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $em->clear();
        $em->getFilters()->disable('soft_delete');

        /** @var ComicSeriesRepository $repo */
        $repo = self::getContainer()->get(ComicSeriesRepository::class);
        $result = $repo->find($id);
        self::assertNull($result);
    }

    public function testCreateComicSeriesRequiresAuth(): void
    {
        $this->client->request(Request::METHOD_POST, '/api/comic_series', [], [], [
            'CONTENT_TYPE' => 'application/ld+json',
        ], \json_encode(['title' => 'No Auth'], \JSON_THROW_ON_ERROR));

        self::assertResponseStatusCodeSame(401);
    }

    /**
     * Crée une série en base pour les tests.
     */
    private function createComicSeries(string $title): ComicSeries
    {
        /** @var EntityManagerInterface $em */
        $em = self::getContainer()->get(EntityManagerInterface::class);

        $comic = new ComicSeries();
        $comic->setTitle($title);
        $comic->setStatus(ComicStatus::BUYING);
        $comic->setType(ComicType::BD);

        $em->persist($comic);
        $em->flush();

        return $comic;
    }

    /**
     * Exécute une requête API authentifiée.
     *
     * @param array<string, mixed>|null $body
     */
    private function apiRequest(string $method, string $uri, ?array $body = null): void
    {
        $headers = [
            'CONTENT_TYPE' => 'application/ld+json',
            'HTTP_ACCEPT' => 'application/ld+json',
            'HTTP_AUTHORIZATION' => 'Bearer '.$this->jwtToken,
        ];

        $content = null !== $body ? \json_encode($body, \JSON_THROW_ON_ERROR) : null;

        $this->client->request($method, $uri, [], [], $headers, $content);
    }

    /**
     * Décode la réponse JSON.
     *
     * @return array<string, mixed>
     */
    private function getJsonResponse(): array
    {
        $content = $this->client->getResponse()->getContent();
        self::assertNotFalse($content);

        /** @var array<string, mixed> $data */
        $data = \json_decode($content, true, 512, \JSON_THROW_ON_ERROR);

        return $data;
    }
}

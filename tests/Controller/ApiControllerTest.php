<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\ComicSeries;
use App\Entity\Tome;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Tests fonctionnels pour ApiController.
 */
class ApiControllerTest extends AuthenticatedWebTestCase
{
    /**
     * Teste que l'API comics retourne du JSON.
     */
    public function testComicsApiReturnsJson(): void
    {
        $client = $this->createAuthenticatedClient();

        $client->request('GET', '/api/comics');

        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('content-type', 'application/json');
    }

    /**
     * Teste que l'API comics retourne un tableau.
     */
    public function testComicsApiReturnsArray(): void
    {
        $client = $this->createAuthenticatedClient();

        $client->request('GET', '/api/comics');

        $response = $client->getResponse();
        $data = \json_decode($response->getContent(), true);

        self::assertIsArray($data);
    }

    /**
     * Teste que l'API comics retourne la structure attendue.
     */
    public function testComicsApiReturnsExpectedStructure(): void
    {
        $client = $this->createAuthenticatedClient();
        $container = static::getContainer();
        /** @var EntityManagerInterface $em */
        $em = $container->get(EntityManagerInterface::class);

        $series = new ComicSeries();
        $series->setTitle('API Test Series');
        $series->setDescription('Test description');
        $series->setPublisher('Test Publisher');
        $series->setLatestPublishedIssue(10);

        $tome = new Tome();
        $tome->setNumber(1);
        $tome->setBought(true);
        $series->addTome($tome);

        $em->persist($series);
        $em->flush();

        $client->request('GET', '/api/comics');

        $response = $client->getResponse();
        $data = \json_decode($response->getContent(), true);

        // Trouver notre série dans la réponse
        $testSeries = null;
        foreach ($data as $comic) {
            if ('API Test Series' === $comic['title']) {
                $testSeries = $comic;
                break;
            }
        }

        self::assertNotNull($testSeries);
        self::assertArrayHasKey('id', $testSeries);
        self::assertArrayHasKey('title', $testSeries);
        self::assertArrayHasKey('status', $testSeries);
        self::assertArrayHasKey('type', $testSeries);
        self::assertArrayHasKey('authors', $testSeries);
        self::assertArrayHasKey('coverUrl', $testSeries);
        self::assertArrayHasKey('currentIssue', $testSeries);
        self::assertArrayHasKey('currentIssueComplete', $testSeries);
        self::assertArrayHasKey('description', $testSeries);
        self::assertArrayHasKey('isWishlist', $testSeries);
        self::assertArrayHasKey('lastBought', $testSeries);
        self::assertArrayHasKey('lastBoughtComplete', $testSeries);
        self::assertArrayHasKey('lastDownloaded', $testSeries);
        self::assertArrayHasKey('lastDownloadedComplete', $testSeries);
        self::assertArrayHasKey('latestPublishedIssue', $testSeries);
        self::assertArrayHasKey('latestPublishedIssueComplete', $testSeries);
        self::assertArrayHasKey('missingTomesNumbers', $testSeries);
        self::assertArrayHasKey('ownedTomesNumbers', $testSeries);
        self::assertArrayHasKey('publishedDate', $testSeries);
        self::assertArrayHasKey('publisher', $testSeries);
        self::assertArrayHasKey('tomesCount', $testSeries);
        self::assertArrayHasKey('updatedAt', $testSeries);

        // Nettoyer
        $em->remove($series);
        $em->flush();
    }

    /**
     * Teste l'API ISBN lookup sans ISBN.
     */
    public function testIsbnLookupWithoutIsbn(): void
    {
        $client = $this->createAuthenticatedClient();

        $client->request('GET', '/api/isbn-lookup');

        self::assertResponseStatusCodeSame(400);

        $response = $client->getResponse();
        $data = \json_decode($response->getContent(), true);

        self::assertSame('ISBN requis', $data['error']);
    }

    /**
     * Teste l'API ISBN lookup avec ISBN vide.
     */
    public function testIsbnLookupWithEmptyIsbn(): void
    {
        $client = $this->createAuthenticatedClient();

        $client->request('GET', '/api/isbn-lookup?isbn=');

        self::assertResponseStatusCodeSame(400);

        $response = $client->getResponse();
        $data = \json_decode($response->getContent(), true);

        self::assertSame('ISBN requis', $data['error']);
    }

    /**
     * Teste l'API title lookup sans titre.
     */
    public function testTitleLookupWithoutTitle(): void
    {
        $client = $this->createAuthenticatedClient();

        $client->request('GET', '/api/title-lookup');

        self::assertResponseStatusCodeSame(400);

        $response = $client->getResponse();
        $data = \json_decode($response->getContent(), true);

        self::assertSame('Titre requis', $data['error']);
    }

    /**
     * Teste l'API title lookup avec titre vide.
     */
    public function testTitleLookupWithEmptyTitle(): void
    {
        $client = $this->createAuthenticatedClient();

        $client->request('GET', '/api/title-lookup?title=');

        self::assertResponseStatusCodeSame(400);

        $response = $client->getResponse();
        $data = \json_decode($response->getContent(), true);

        self::assertSame('Titre requis', $data['error']);
    }

    /**
     * Teste que l'API comics est triée par titre.
     */
    public function testComicsApiIsSortedByTitle(): void
    {
        $client = $this->createAuthenticatedClient();
        $container = static::getContainer();
        /** @var EntityManagerInterface $em */
        $em = $container->get(EntityManagerInterface::class);

        // Créer des séries dans un ordre non alphabétique
        $seriesZ = new ComicSeries();
        $seriesZ->setTitle('Zorro Test');
        $em->persist($seriesZ);

        $seriesA = new ComicSeries();
        $seriesA->setTitle('Asterix Test');
        $em->persist($seriesA);

        $em->flush();

        $client->request('GET', '/api/comics');

        $response = $client->getResponse();
        $data = \json_decode($response->getContent(), true);

        // Trouver les index de nos séries
        $asterixIndex = null;
        $zorroIndex = null;
        foreach ($data as $index => $comic) {
            if ('Asterix Test' === $comic['title']) {
                $asterixIndex = $index;
            }
            if ('Zorro Test' === $comic['title']) {
                $zorroIndex = $index;
            }
        }

        self::assertNotNull($asterixIndex);
        self::assertNotNull($zorroIndex);
        self::assertLessThan($zorroIndex, $asterixIndex);

        // Nettoyer
        $em->remove($seriesZ);
        $em->remove($seriesA);
        $em->flush();
    }
}

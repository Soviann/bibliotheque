<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\ComicSeries;
use App\Entity\Tome;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Tests fonctionnels pour SearchController.
 */
class SearchControllerTest extends AuthenticatedWebTestCase
{
    /**
     * Teste que la page de recherche est accessible.
     */
    public function testSearchPageIsAccessible(): void
    {
        $client = $this->createAuthenticatedClient();

        $client->request(Request::METHOD_GET, '/search');

        self::assertResponseIsSuccessful();
    }

    /**
     * Teste la recherche avec une requête vide.
     */
    public function testSearchWithEmptyQuery(): void
    {
        $client = $this->createAuthenticatedClient();

        $client->request(Request::METHOD_GET, '/search?q=');

        self::assertResponseIsSuccessful();
    }

    /**
     * Teste la recherche par titre de série.
     */
    public function testSearchByTitle(): void
    {
        $client = $this->createAuthenticatedClient();
        $container = static::getContainer();
        /** @var EntityManagerInterface $em */
        $em = $container->get(EntityManagerInterface::class);

        $series = new ComicSeries();
        $series->setTitle('Unique Search Title XYZ');
        $em->persist($series);
        $em->flush();

        $crawler = $client->request(Request::METHOD_GET, '/search?q=Unique+Search+Title');

        self::assertResponseIsSuccessful();
        self::assertStringContainsString('Unique Search Title XYZ', $crawler->text());
    }

    /**
     * Teste la recherche par ISBN de tome.
     */
    public function testSearchByIsbn(): void
    {
        $client = $this->createAuthenticatedClient();
        $container = static::getContainer();
        /** @var EntityManagerInterface $em */
        $em = $container->get(EntityManagerInterface::class);

        $series = new ComicSeries();
        $series->setTitle('Series With ISBN');

        $tome = new Tome();
        $tome->setNumber(1);
        $tome->setIsbn('978-2-505-99999-9');
        $series->addTome($tome);

        $em->persist($series);
        $em->flush();

        $crawler = $client->request(Request::METHOD_GET, '/search?q=978-2-505-99999-9');

        self::assertResponseIsSuccessful();
        self::assertStringContainsString('Series With ISBN', $crawler->text());
    }

    /**
     * Teste la recherche sans résultat.
     */
    public function testSearchWithNoResults(): void
    {
        $client = $this->createAuthenticatedClient();

        $client->request(Request::METHOD_GET, '/search?q=xyznonexistentquery123');

        self::assertResponseIsSuccessful();
        // La page devrait s'afficher mais sans résultats
    }

    /**
     * Teste la recherche partielle.
     */
    public function testSearchPartialMatch(): void
    {
        $client = $this->createAuthenticatedClient();
        $container = static::getContainer();
        /** @var EntityManagerInterface $em */
        $em = $container->get(EntityManagerInterface::class);

        $series = new ComicSeries();
        $series->setTitle('Naruto Shippuden');
        $em->persist($series);
        $em->flush();

        $crawler = $client->request(Request::METHOD_GET, '/search?q=Shippuden');

        self::assertResponseIsSuccessful();
        self::assertStringContainsString('Naruto Shippuden', $crawler->text());
    }

    /**
     * Teste que la recherche est insensible à la casse.
     */
    public function testSearchIsCaseInsensitive(): void
    {
        $client = $this->createAuthenticatedClient();
        $container = static::getContainer();
        /** @var EntityManagerInterface $em */
        $em = $container->get(EntityManagerInterface::class);

        $series = new ComicSeries();
        $series->setTitle('One Piece');
        $em->persist($series);
        $em->flush();

        $crawler = $client->request(Request::METHOD_GET, '/search?q=one+piece');

        self::assertResponseIsSuccessful();
        self::assertStringContainsString('One Piece', $crawler->text());
    }
}

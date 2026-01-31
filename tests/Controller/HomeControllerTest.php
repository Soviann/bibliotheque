<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\ComicSeries;
use App\Entity\Tome;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Tests fonctionnels pour HomeController.
 */
class HomeControllerTest extends AuthenticatedWebTestCase
{

    /**
     * Teste que la page d'accueil est accessible.
     */
    public function testHomePageIsAccessible(): void
    {
        $client = $this->createAuthenticatedClient();

        $client->request('GET', '/');

        self::assertResponseIsSuccessful();
    }

    /**
     * Teste le filtre par type.
     */
    public function testHomePageWithTypeFilter(): void
    {
        $client = $this->createAuthenticatedClient();

        $client->request('GET', '/?type=manga');

        self::assertResponseIsSuccessful();
    }

    /**
     * Teste le filtre par statut.
     */
    public function testHomePageWithStatusFilter(): void
    {
        $client = $this->createAuthenticatedClient();

        $client->request('GET', '/?status=buying');

        self::assertResponseIsSuccessful();
    }

    /**
     * Teste le filtre NAS.
     */
    public function testHomePageWithNasFilter(): void
    {
        $client = $this->createAuthenticatedClient();

        $client->request('GET', '/?nas=1');
        self::assertResponseIsSuccessful();

        $client->request('GET', '/?nas=0');
        self::assertResponseIsSuccessful();
    }

    /**
     * Teste le filtre de recherche.
     */
    public function testHomePageWithSearchFilter(): void
    {
        $client = $this->createAuthenticatedClient();

        $client->request('GET', '/?q=naruto');

        self::assertResponseIsSuccessful();
    }

    /**
     * Teste les différentes options de tri.
     */
    public function testHomePageWithSortOptions(): void
    {
        $client = $this->createAuthenticatedClient();

        $sortOptions = ['title_asc', 'title_desc', 'updated_asc', 'updated_desc', 'status'];

        foreach ($sortOptions as $sort) {
            $client->request('GET', '/?sort='.$sort);
            self::assertResponseIsSuccessful();
        }
    }

    /**
     * Teste la combinaison de plusieurs filtres.
     */
    public function testHomePageWithMultipleFilters(): void
    {
        $client = $this->createAuthenticatedClient();

        $client->request('GET', '/?type=bd&status=buying&sort=title_desc&q=asterix');

        self::assertResponseIsSuccessful();
    }

    /**
     * Teste qu'une valeur de type invalide est ignorée.
     */
    public function testHomePageWithInvalidTypeFilter(): void
    {
        $client = $this->createAuthenticatedClient();

        $client->request('GET', '/?type=invalid');

        self::assertResponseIsSuccessful();
    }

    /**
     * Teste qu'une valeur de status invalide est ignorée.
     */
    public function testHomePageWithInvalidStatusFilter(): void
    {
        $client = $this->createAuthenticatedClient();

        $client->request('GET', '/?status=invalid');

        self::assertResponseIsSuccessful();
    }

    /**
     * Teste que la page affiche les séries de la bibliothèque (pas wishlist).
     */
    public function testHomePageShowsLibraryNotWishlist(): void
    {
        $client = $this->createAuthenticatedClient();
        $container = static::getContainer();
        /** @var EntityManagerInterface $em */
        $em = $container->get(EntityManagerInterface::class);

        // Créer une série dans la bibliothèque
        $librarySeries = new ComicSeries();
        $librarySeries->setTitle('Test Library Series');
        $librarySeries->setIsWishlist(false);
        $em->persist($librarySeries);

        // Créer une série dans la wishlist
        $wishlistSeries = new ComicSeries();
        $wishlistSeries->setTitle('Test Wishlist Series');
        $wishlistSeries->setIsWishlist(true);
        $em->persist($wishlistSeries);

        $em->flush();

        $crawler = $client->request('GET', '/');

        self::assertResponseIsSuccessful();
        self::assertStringContainsString('Test Library Series', $crawler->text());
        self::assertStringNotContainsString('Test Wishlist Series', $crawler->text());

        // Nettoyer
        $em->remove($librarySeries);
        $em->remove($wishlistSeries);
        $em->flush();
    }

    /**
     * Teste que le filtre NAS=1 retourne seulement les séries avec des tomes sur NAS.
     */
    public function testNasFilterShowsSeriesWithTomesOnNas(): void
    {
        $client = $this->createAuthenticatedClient();
        $container = static::getContainer();
        /** @var EntityManagerInterface $em */
        $em = $container->get(EntityManagerInterface::class);

        // Série avec tome sur NAS
        $seriesOnNas = new ComicSeries();
        $seriesOnNas->setTitle('Series On NAS Test');
        $seriesOnNas->setIsWishlist(false);
        $tomeOnNas = new Tome();
        $tomeOnNas->setNumber(1);
        $tomeOnNas->setOnNas(true);
        $seriesOnNas->addTome($tomeOnNas);
        $em->persist($seriesOnNas);

        // Série sans tome sur NAS
        $seriesNotOnNas = new ComicSeries();
        $seriesNotOnNas->setTitle('Series Not On NAS Test');
        $seriesNotOnNas->setIsWishlist(false);
        $tomeNotOnNas = new Tome();
        $tomeNotOnNas->setNumber(1);
        $tomeNotOnNas->setOnNas(false);
        $seriesNotOnNas->addTome($tomeNotOnNas);
        $em->persist($seriesNotOnNas);

        $em->flush();

        $crawler = $client->request('GET', '/?nas=1');

        self::assertResponseIsSuccessful();
        self::assertStringContainsString('Series On NAS Test', $crawler->text());
        self::assertStringNotContainsString('Series Not On NAS Test', $crawler->text());

        // Nettoyer
        $em->remove($seriesOnNas);
        $em->remove($seriesNotOnNas);
        $em->flush();
    }

    /**
     * Teste que les utilisateurs non authentifiés sont redirigés vers login.
     */
    public function testUnauthenticatedUserIsRedirectedToLogin(): void
    {
        $client = static::createClient();

        $client->request('GET', '/');

        self::assertResponseRedirects('/login');
    }
}

<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\ComicSeries;
use App\Enum\ComicStatus;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Tests fonctionnels pour WishlistController.
 */
class WishlistControllerTest extends AuthenticatedWebTestCase
{
    /**
     * Teste que la page wishlist est accessible.
     */
    public function testWishlistPageIsAccessible(): void
    {
        $client = $this->createAuthenticatedClient();

        $client->request('GET', '/wishlist');

        self::assertResponseIsSuccessful();
    }

    /**
     * Teste le filtre par type.
     */
    public function testWishlistPageWithTypeFilter(): void
    {
        $client = $this->createAuthenticatedClient();

        $client->request('GET', '/wishlist?type=manga');

        self::assertResponseIsSuccessful();
    }

    /**
     * Teste le filtre NAS.
     */
    public function testWishlistPageWithNasFilter(): void
    {
        $client = $this->createAuthenticatedClient();

        $client->request('GET', '/wishlist?nas=1');
        self::assertResponseIsSuccessful();

        $client->request('GET', '/wishlist?nas=0');
        self::assertResponseIsSuccessful();
    }

    /**
     * Teste le filtre de recherche.
     */
    public function testWishlistPageWithSearchFilter(): void
    {
        $client = $this->createAuthenticatedClient();

        $client->request('GET', '/wishlist?q=test');

        self::assertResponseIsSuccessful();
    }

    /**
     * Teste les différentes options de tri.
     */
    public function testWishlistPageWithSortOptions(): void
    {
        $client = $this->createAuthenticatedClient();

        $sortOptions = ['title_asc', 'title_desc', 'updated_asc', 'updated_desc'];

        foreach ($sortOptions as $sort) {
            $client->request('GET', '/wishlist?sort='.$sort);
            self::assertResponseIsSuccessful();
        }
    }

    /**
     * Teste que la page wishlist affiche uniquement les séries wishlist.
     */
    public function testWishlistShowsOnlyWishlistSeries(): void
    {
        $client = $this->createAuthenticatedClient();
        $container = static::getContainer();
        /** @var EntityManagerInterface $em */
        $em = $container->get(EntityManagerInterface::class);

        // Créer une série wishlist
        $wishlistSeries = new ComicSeries();
        $wishlistSeries->setTitle('Wishlist Series Test');
        $wishlistSeries->setStatus(ComicStatus::WISHLIST);
        $em->persist($wishlistSeries);

        // Créer une série bibliothèque (statut BUYING par défaut)
        $librarySeries = new ComicSeries();
        $librarySeries->setTitle('Library Series Test');
        $em->persist($librarySeries);

        $em->flush();

        $crawler = $client->request('GET', '/wishlist');

        self::assertResponseIsSuccessful();
        self::assertStringContainsString('Wishlist Series Test', $crawler->text());
        self::assertStringNotContainsString('Library Series Test', $crawler->text());

        // Nettoyer
        $em->remove($wishlistSeries);
        $em->remove($librarySeries);
        $em->flush();
    }

    /**
     * Teste la combinaison de filtres.
     */
    public function testWishlistWithMultipleFilters(): void
    {
        $client = $this->createAuthenticatedClient();

        $client->request('GET', '/wishlist?type=bd&sort=title_desc&q=asterix');

        self::assertResponseIsSuccessful();
    }

    /**
     * Teste qu'un type invalide est ignoré.
     */
    public function testWishlistWithInvalidTypeFilter(): void
    {
        $client = $this->createAuthenticatedClient();

        $client->request('GET', '/wishlist?type=invalid');

        self::assertResponseIsSuccessful();
    }
}

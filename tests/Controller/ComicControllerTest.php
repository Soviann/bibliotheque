<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\ComicSeries;
use App\Enum\ComicStatus;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Tests fonctionnels pour ComicController.
 */
class ComicControllerTest extends AuthenticatedWebTestCase
{
    /**
     * Teste l'affichage d'une série.
     */
    public function testShowAction(): void
    {
        $client = $this->createAuthenticatedClient();
        $container = static::getContainer();
        /** @var EntityManagerInterface $em */
        $em = $container->get(EntityManagerInterface::class);

        $series = new ComicSeries();
        $series->setTitle('Test Show Series');
        $em->persist($series);
        $em->flush();

        $client->request('GET', '/comic/'.$series->getId());

        self::assertResponseIsSuccessful();

        // Nettoyer
        $em->remove($series);
        $em->flush();
    }

    /**
     * Teste l'affichage du formulaire de création.
     */
    public function testNewActionGetRequest(): void
    {
        $client = $this->createAuthenticatedClient();

        $client->request('GET', '/comic/new');

        self::assertResponseIsSuccessful();
    }

    /**
     * Teste la création d'une nouvelle série.
     */
    public function testNewActionPostRequest(): void
    {
        $client = $this->createAuthenticatedClient();
        $container = static::getContainer();
        /** @var EntityManagerInterface $em */
        $em = $container->get(EntityManagerInterface::class);

        $crawler = $client->request('GET', '/comic/new');

        $form = $crawler->selectButton('Enregistrer')->form([
            'comic_series[title]' => 'New Test Series',
            'comic_series[type]' => 'bd',
            'comic_series[status]' => 'buying',
        ]);

        $client->submit($form);

        self::assertResponseRedirects('/');

        // Vérifier que la série a été créée
        $series = $em->getRepository(ComicSeries::class)->findOneBy(['title' => 'New Test Series']);
        self::assertNotNull($series);

        // Nettoyer
        if ($series) {
            $em->remove($series);
            $em->flush();
        }
    }

    /**
     * Teste la création d'une série wishlist redirige vers wishlist.
     */
    public function testNewWishlistSeriesRedirectsToWishlist(): void
    {
        $client = $this->createAuthenticatedClient();
        $container = static::getContainer();
        /** @var EntityManagerInterface $em */
        $em = $container->get(EntityManagerInterface::class);

        $crawler = $client->request('GET', '/comic/new');

        $form = $crawler->selectButton('Enregistrer')->form([
            'comic_series[title]' => 'New Wishlist Series',
            'comic_series[type]' => 'manga',
            'comic_series[status]' => 'wishlist',
            'comic_series[isWishlist]' => true,
        ]);

        $client->submit($form);

        self::assertResponseRedirects('/wishlist');

        // Nettoyer
        $series = $em->getRepository(ComicSeries::class)->findOneBy(['title' => 'New Wishlist Series']);
        if ($series) {
            $em->remove($series);
            $em->flush();
        }
    }

    /**
     * Teste l'affichage du formulaire d'édition.
     */
    public function testEditActionGetRequest(): void
    {
        $client = $this->createAuthenticatedClient();
        $container = static::getContainer();
        /** @var EntityManagerInterface $em */
        $em = $container->get(EntityManagerInterface::class);

        $series = new ComicSeries();
        $series->setTitle('Test Edit Series');
        $em->persist($series);
        $em->flush();

        $client->request('GET', '/comic/'.$series->getId().'/edit');

        self::assertResponseIsSuccessful();

        // Nettoyer
        $em->remove($series);
        $em->flush();
    }

    /**
     * Teste la modification d'une série.
     */
    public function testEditActionPostRequest(): void
    {
        $client = $this->createAuthenticatedClient();
        $container = static::getContainer();
        /** @var EntityManagerInterface $em */
        $em = $container->get(EntityManagerInterface::class);

        $series = new ComicSeries();
        $series->setTitle('Original Title');
        $em->persist($series);
        $em->flush();

        $seriesId = $series->getId();

        $crawler = $client->request('GET', '/comic/'.$seriesId.'/edit');

        $form = $crawler->selectButton('Enregistrer')->form([
            'comic_series[title]' => 'Modified Title',
        ]);

        $client->submit($form);

        self::assertResponseRedirects('/');

        // Vérifier le nouveau titre en rechargeant depuis la base
        $em->clear();
        $updatedSeries = $em->getRepository(ComicSeries::class)->find($seriesId);
        self::assertNotNull($updatedSeries);
        self::assertSame('Modified Title', $updatedSeries->getTitle());

        // Nettoyer
        $em->remove($updatedSeries);
        $em->flush();
    }

    /**
     * Teste la suppression d'une série avec CSRF valide.
     */
    public function testDeleteActionWithValidCsrf(): void
    {
        $client = $this->createAuthenticatedClient();
        $container = static::getContainer();
        /** @var EntityManagerInterface $em */
        $em = $container->get(EntityManagerInterface::class);

        $series = new ComicSeries();
        $series->setTitle('Test Delete Series');
        $em->persist($series);
        $em->flush();

        $seriesId = $series->getId();

        // Charger la page qui contient le formulaire de suppression
        $crawler = $client->request('GET', '/comic/'.$seriesId);

        // Extraire le token CSRF du formulaire de suppression dans la page
        $deleteForm = $crawler->filter('form[action$="/delete"]');
        $csrfToken = $deleteForm->filter('input[name="_token"]')->attr('value');

        $client->request('POST', '/comic/'.$seriesId.'/delete', [
            '_token' => $csrfToken,
        ]);

        self::assertResponseRedirects('/');

        // Vérifier que la série a été supprimée
        $em->clear();
        $deletedSeries = $em->getRepository(ComicSeries::class)->find($seriesId);
        self::assertNull($deletedSeries);
    }

    /**
     * Teste la suppression d'une série avec CSRF invalide affiche un message d'erreur.
     */
    public function testDeleteActionWithInvalidCsrfShowsErrorFlash(): void
    {
        $client = $this->createAuthenticatedClient();
        $container = static::getContainer();
        /** @var EntityManagerInterface $em */
        $em = $container->get(EntityManagerInterface::class);

        $series = new ComicSeries();
        $series->setTitle('Test Invalid CSRF Delete');
        $em->persist($series);
        $em->flush();

        $seriesId = $series->getId();

        $client->request('POST', '/comic/'.$seriesId.'/delete', [
            '_token' => 'invalid_token',
        ]);

        self::assertResponseRedirects('/');

        // Suivre la redirection pour voir le message flash
        $client->followRedirect();

        // Vérifier qu'un message flash d'erreur est affiché
        self::assertSelectorExists('.alert-error');

        // Vérifier que la série n'a PAS été supprimée
        $stillExists = $em->getRepository(ComicSeries::class)->find($seriesId);
        self::assertNotNull($stillExists);

        // Nettoyer
        $em->remove($stillExists);
        $em->flush();
    }

    /**
     * Teste la suppression d'une série wishlist redirige vers wishlist.
     */
    public function testDeleteWishlistSeriesRedirectsToWishlist(): void
    {
        $client = $this->createAuthenticatedClient();
        $container = static::getContainer();
        /** @var EntityManagerInterface $em */
        $em = $container->get(EntityManagerInterface::class);

        $series = new ComicSeries();
        $series->setTitle('Test Wishlist Delete');
        $series->setIsWishlist(true);
        $em->persist($series);
        $em->flush();

        $seriesId = $series->getId();

        // Charger la page qui contient le formulaire de suppression
        $crawler = $client->request('GET', '/comic/'.$seriesId);

        // Extraire le token CSRF du formulaire de suppression dans la page
        $deleteForm = $crawler->filter('form[action$="/delete"]');
        $csrfToken = $deleteForm->filter('input[name="_token"]')->attr('value');

        $client->request('POST', '/comic/'.$seriesId.'/delete', [
            '_token' => $csrfToken,
        ]);

        self::assertResponseRedirects('/wishlist');
    }

    /**
     * Teste le déplacement d'une série de wishlist vers bibliothèque.
     */
    public function testToLibraryAction(): void
    {
        $client = $this->createAuthenticatedClient();
        $container = static::getContainer();
        /** @var EntityManagerInterface $em */
        $em = $container->get(EntityManagerInterface::class);

        $series = new ComicSeries();
        $series->setTitle('Test To Library Unique');
        $series->setIsWishlist(true);
        $series->setStatus(ComicStatus::WISHLIST);
        $em->persist($series);
        $em->flush();

        $seriesId = $series->getId();

        // Charger la page wishlist qui contient le formulaire de déplacement vers bibliothèque
        $crawler = $client->request('GET', '/wishlist');

        // Extraire le token CSRF du formulaire to-library de notre série dans la page
        $toLibraryForm = $crawler->filter('form[action="/comic/'.$seriesId.'/to-library"]');
        $csrfToken = $toLibraryForm->filter('input[name="_token"]')->attr('value');

        $client->request('POST', '/comic/'.$seriesId.'/to-library', [
            '_token' => $csrfToken,
        ]);

        self::assertResponseRedirects('/');

        // Vérifier en rechargeant depuis la base
        $em->clear();
        $updatedSeries = $em->getRepository(ComicSeries::class)->find($seriesId);
        self::assertNotNull($updatedSeries);
        self::assertFalse($updatedSeries->isWishlist());
        self::assertSame(ComicStatus::BUYING, $updatedSeries->getStatus());

        // Nettoyer
        $em->remove($updatedSeries);
        $em->flush();
    }

    /**
     * Teste le déplacement avec CSRF invalide affiche un message d'erreur.
     */
    public function testToLibraryWithInvalidCsrfShowsErrorFlash(): void
    {
        $client = $this->createAuthenticatedClient();
        $container = static::getContainer();
        /** @var EntityManagerInterface $em */
        $em = $container->get(EntityManagerInterface::class);

        $series = new ComicSeries();
        $series->setTitle('Test Invalid CSRF To Library');
        $series->setIsWishlist(true);
        $series->setStatus(ComicStatus::WISHLIST);
        $em->persist($series);
        $em->flush();

        $seriesId = $series->getId();

        $client->request('POST', '/comic/'.$seriesId.'/to-library', [
            '_token' => 'invalid_token',
        ]);

        self::assertResponseRedirects('/');

        // Suivre la redirection pour voir le message flash
        $client->followRedirect();

        // Vérifier qu'un message flash d'erreur est affiché
        self::assertSelectorExists('.alert-error');

        // Recharger l'entity manager après followRedirect (kernel rebooted)
        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get(EntityManagerInterface::class);

        // Recharger l'entité et vérifier que rien n'a changé
        $series = $em->getRepository(ComicSeries::class)->find($seriesId);
        self::assertNotNull($series);
        self::assertTrue($series->isWishlist());
        self::assertSame(ComicStatus::WISHLIST, $series->getStatus());

        // Nettoyer
        $em->remove($series);
        $em->flush();
    }

    /**
     * Teste que show retourne 404 pour une série inexistante.
     */
    public function testShowActionReturns404ForNonExistentSeries(): void
    {
        $client = $this->createAuthenticatedClient();

        $client->request('GET', '/comic/99999');

        self::assertResponseStatusCodeSame(404);
    }

}

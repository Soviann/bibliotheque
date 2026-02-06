<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\ComicSeries;
use App\Enum\ComicStatus;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;

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

        $client->request(Request::METHOD_GET, '/comic/'.$series->getId());

        self::assertResponseIsSuccessful();
    }

    /**
     * Teste l'affichage du formulaire de création.
     */
    public function testNewActionGetRequest(): void
    {
        $client = $this->createAuthenticatedClient();

        $client->request(Request::METHOD_GET, '/comic/new');

        self::assertResponseIsSuccessful();
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

        $client->request(Request::METHOD_GET, '/comic/'.$series->getId().'/edit');

        self::assertResponseIsSuccessful();
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
        $crawler = $client->request(Request::METHOD_GET, '/comic/'.$seriesId);

        // Extraire le token CSRF du formulaire de suppression dans la page
        $deleteForm = $crawler->filter('form[action$="/delete"]');
        $csrfToken = $deleteForm->filter('input[name="_token"]')->attr('value');

        $client->request(Request::METHOD_POST, '/comic/'.$seriesId.'/delete', [
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

        $client->request(Request::METHOD_POST, '/comic/'.$seriesId.'/delete', [
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
        $series->setStatus(ComicStatus::WISHLIST);
        $em->persist($series);
        $em->flush();

        $seriesId = $series->getId();

        // Charger la page qui contient le formulaire de suppression
        $crawler = $client->request(Request::METHOD_GET, '/comic/'.$seriesId);

        // Extraire le token CSRF du formulaire de suppression dans la page
        $deleteForm = $crawler->filter('form[action$="/delete"]');
        $csrfToken = $deleteForm->filter('input[name="_token"]')->attr('value');

        $client->request(Request::METHOD_POST, '/comic/'.$seriesId.'/delete', [
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
        $series->setStatus(ComicStatus::WISHLIST);
        $em->persist($series);
        $em->flush();

        $seriesId = $series->getId();

        // Charger la page wishlist qui contient le formulaire de déplacement vers bibliothèque
        $crawler = $client->request(Request::METHOD_GET, '/wishlist');

        // Extraire le token CSRF du formulaire to-library de notre série dans la page
        $toLibraryForm = $crawler->filter('form[action="/comic/'.$seriesId.'/to-library"]');
        $csrfToken = $toLibraryForm->filter('input[name="_token"]')->attr('value');

        $client->request(Request::METHOD_POST, '/comic/'.$seriesId.'/to-library', [
            '_token' => $csrfToken,
        ]);

        self::assertResponseRedirects('/');

        // Vérifier en rechargeant depuis la base
        $em->clear();
        $updatedSeries = $em->getRepository(ComicSeries::class)->find($seriesId);
        self::assertNotNull($updatedSeries);
        self::assertFalse($updatedSeries->isWishlist());
        self::assertSame(ComicStatus::BUYING, $updatedSeries->getStatus());
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
        $series->setStatus(ComicStatus::WISHLIST);
        $em->persist($series);
        $em->flush();

        $seriesId = $series->getId();

        $client->request(Request::METHOD_POST, '/comic/'.$seriesId.'/to-library', [
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
    }

    /**
     * Teste que show retourne 404 pour une série inexistante.
     */
    public function testShowActionReturns404ForNonExistentSeries(): void
    {
        $client = $this->createAuthenticatedClient();

        $client->request(Request::METHOD_GET, '/comic/99999');

        self::assertResponseStatusCodeSame(404);
    }

    /**
     * Teste que le formulaire de création utilise le formulaire standard (pas un wizard).
     */
    public function testNewActionUsesStandardForm(): void
    {
        $client = $this->createAuthenticatedClient();

        $crawler = $client->request(Request::METHOD_GET, '/comic/new');
        self::assertResponseIsSuccessful();

        // Le formulaire standard a le préfixe "comic_series"
        self::assertSelectorExists('[name="comic_series[title]"]');
        self::assertSelectorExists('[name="comic_series[type]"]');
        self::assertSelectorExists('[name="comic_series[status]"]');
        self::assertSelectorExists('[name="comic_series[description]"]');

        // Le bouton Enregistrer est présent
        self::assertSelectorExists('button[type="submit"]');
    }

    /**
     * Teste que l'édition utilise le même formulaire standard que la création.
     */
    public function testEditActionUsesStandardForm(): void
    {
        $client = $this->createAuthenticatedClient();
        $container = static::getContainer();
        /** @var EntityManagerInterface $em */
        $em = $container->get(EntityManagerInterface::class);

        $series = new ComicSeries();
        $series->setTitle('Test Edit Standard Form');
        $em->persist($series);
        $em->flush();

        $crawler = $client->request(Request::METHOD_GET, '/comic/'.$series->getId().'/edit');
        self::assertResponseIsSuccessful();

        // Le formulaire standard a le préfixe "comic_series"
        self::assertSelectorExists('[name="comic_series[title]"]');
        self::assertSelectorExists('[name="comic_series[type]"]');
        self::assertSelectorExists('[name="comic_series[status]"]');
        self::assertSelectorExists('[name="comic_series[description]"]');
    }

    /**
     * Teste la création d'une série avec des données valides.
     */
    public function testNewWithValidDataCreatesEntity(): void
    {
        $client = $this->createAuthenticatedClient();

        $crawler = $client->request(Request::METHOD_GET, '/comic/new');
        self::assertResponseIsSuccessful();

        $form = $crawler->selectButton('Enregistrer')->form([
            'comic_series[title]' => 'Ma BD Test Création',
            'comic_series[type]' => 'bd',
        ]);
        $client->submit($form);

        self::assertResponseRedirects('/');

        $client->followRedirect();
        self::assertSelectorExists('.alert-success');

        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $series = $em->getRepository(ComicSeries::class)->findOneBy(['title' => 'Ma BD Test Création']);
        self::assertNotNull($series, 'La série devrait avoir été créée en base de données');
    }

    /**
     * Teste que la création échoue si le titre est vide.
     */
    public function testNewFailsWithEmptyTitle(): void
    {
        $client = $this->createAuthenticatedClient();

        $crawler = $client->request(Request::METHOD_GET, '/comic/new');
        self::assertResponseIsSuccessful();

        $form = $crawler->selectButton('Enregistrer')->form([
            'comic_series[title]' => '',
        ]);
        $client->submit($form);

        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('ul li', 'Le titre est obligatoire');
    }

    /**
     * Teste que le statut est présélectionné à WISHLIST quand on vient de la page wishlist.
     */
    public function testNewFromWishlistPreselectsWishlistStatus(): void
    {
        $client = $this->createAuthenticatedClient();

        $crawler = $client->request(Request::METHOD_GET, '/comic/new?wishlist=1');
        self::assertResponseIsSuccessful();

        $selectedOption = $crawler->filter('[name="comic_series[status]"] option[selected]');
        self::assertSame('wishlist', $selectedOption->attr('value'));
    }

    /**
     * Teste que le statut par défaut est BUYING quand on n'a pas le paramètre wishlist.
     */
    public function testNewWithoutWishlistParamDefaultsToBuying(): void
    {
        $client = $this->createAuthenticatedClient();

        $crawler = $client->request(Request::METHOD_GET, '/comic/new');
        self::assertResponseIsSuccessful();

        $selectedOption = $crawler->filter('[name="comic_series[status]"] option[selected]');
        self::assertSame('buying', $selectedOption->attr('value'));
    }
}

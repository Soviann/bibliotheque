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

        // Nettoyer
        $em->remove($series);
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

        $client->request(Request::METHOD_GET, '/comic/99999');

        self::assertResponseStatusCodeSame(404);
    }

    /**
     * Teste que revenir sur /comic/new après avoir commencé le formulaire réinitialise à l'étape 1.
     *
     * Scénario : L'utilisateur commence à remplir le formulaire (avance à l'étape 2),
     * navigue ailleurs, puis revient sur /comic/new. Le formulaire doit reprendre à l'étape 1.
     */
    public function testNewActionResetsFlowOnGetRequest(): void
    {
        $client = $this->createAuthenticatedClient();

        // 1. Charger le formulaire pour être à l'étape 1
        $crawler = $client->request(Request::METHOD_GET, '/comic/new');
        self::assertResponseIsSuccessful();

        // Vérifier qu'on est à l'étape 1 (format) - le champ type est visible
        self::assertSelectorExists('[name="comic_series_flow[format][type]"]');

        // 2. Soumettre l'étape 1 pour avancer à l'étape 2
        $form = $crawler->selectButton('Suivant')->form([
            'comic_series_flow[format][type]' => 'bd',
        ]);
        $crawler = $client->submit($form);
        self::assertResponseIsSuccessful();

        // Vérifier qu'on est maintenant à l'étape 2 (identification_series) - le champ title est visible
        self::assertSelectorExists('[name="comic_series_flow[identification_series][title]"]');
        self::assertSelectorNotExists('[name="comic_series_flow[format][type]"]');

        // 3. Simuler une navigation ailleurs puis retour sur /comic/new (nouvelle requête GET)
        $crawler = $client->request(Request::METHOD_GET, '/comic/new');
        self::assertResponseIsSuccessful();

        // 4. Vérifier qu'on est de retour à l'étape 1
        self::assertSelectorExists('[name="comic_series_flow[format][type]"]');
        self::assertSelectorNotExists('[name="comic_series_flow[identification_series][title]"]');
    }

    /**
     * Teste que naviguer vers l'édition d'un autre comic réinitialise le flow à l'étape 1.
     *
     * Scénario : L'utilisateur commence à éditer Comic A (avance à l'étape 2),
     * puis navigue vers l'édition de Comic B. Le formulaire de B doit commencer à l'étape 1.
     */
    public function testEditActionResetsFlowOnGetRequest(): void
    {
        $client = $this->createAuthenticatedClient();
        $container = static::getContainer();
        /** @var EntityManagerInterface $em */
        $em = $container->get(EntityManagerInterface::class);

        // Créer deux séries pour le test
        $seriesA = new ComicSeries();
        $seriesA->setTitle('Test Series A');
        $seriesB = new ComicSeries();
        $seriesB->setTitle('Test Series B');
        $em->persist($seriesA);
        $em->persist($seriesB);
        $em->flush();

        $seriesAId = $seriesA->getId();
        $seriesBId = $seriesB->getId();

        try {
            // 1. Charger le formulaire d'édition de A (étape 1)
            $crawler = $client->request(Request::METHOD_GET, '/comic/'.$seriesAId.'/edit');
            self::assertResponseIsSuccessful();

            // Vérifier qu'on est à l'étape 1 (format)
            self::assertSelectorExists('[name="comic_series_flow[format][type]"]');

            // 2. Soumettre l'étape 1 pour avancer à l'étape 2
            $form = $crawler->selectButton('Suivant')->form([
                'comic_series_flow[format][type]' => 'bd',
            ]);
            $crawler = $client->submit($form);
            self::assertResponseIsSuccessful();

            // Vérifier qu'on est maintenant à l'étape 2
            self::assertSelectorExists('[name="comic_series_flow[identification_series][title]"]');

            // 3. Naviguer vers l'édition de Comic B (nouvelle requête GET)
            $crawler = $client->request(Request::METHOD_GET, '/comic/'.$seriesBId.'/edit');
            self::assertResponseIsSuccessful();

            // 4. Vérifier que le formulaire de B commence à l'étape 1 (pas l'étape 2 de A)
            self::assertSelectorExists('[name="comic_series_flow[format][type]"]');
            self::assertSelectorNotExists('[name="comic_series_flow[identification_series][title]"]');
        } finally {
            // Nettoyer - recharger les entités pour éviter les problèmes de détachement
            $em->clear();
            $seriesA = $em->getRepository(ComicSeries::class)->find($seriesAId);
            $seriesB = $em->getRepository(ComicSeries::class)->find($seriesBId);
            if ($seriesA) {
                $em->remove($seriesA);
            }
            if ($seriesB) {
                $em->remove($seriesB);
            }
            $em->flush();
        }
    }

    /**
     * Teste que le bouton Enregistrer n'est PAS visible à l'étape 1 (format).
     */
    public function testSaveButtonNotVisibleAtFormatStep(): void
    {
        $client = $this->createAuthenticatedClient();

        $crawler = $client->request(Request::METHOD_GET, '/comic/new');
        self::assertResponseIsSuccessful();

        // Le bouton "Enregistrer" ne doit pas être présent à l'étape format
        self::assertSelectorNotExists('button[name="comic_series_flow[finish]"]');
    }

    /**
     * Teste que le bouton Enregistrer EST visible à l'étape 2 (identification_series).
     */
    public function testSaveButtonVisibleAtIdentificationStep(): void
    {
        $client = $this->createAuthenticatedClient();

        // 1. Charger l'étape 1
        $crawler = $client->request(Request::METHOD_GET, '/comic/new');
        self::assertResponseIsSuccessful();

        // 2. Passer à l'étape 2
        $form = $crawler->selectButton('Suivant')->form([
            'comic_series_flow[format][type]' => 'bd',
        ]);
        $crawler = $client->submit($form);
        self::assertResponseIsSuccessful();

        // Le bouton "Enregistrer" doit être présent à l'étape identification_series
        self::assertSelectorExists('button[name="comic_series_flow[finish]"]');
    }

    /**
     * Teste que la sauvegarde depuis l'étape 2 fonctionne avec des données valides.
     */
    public function testSaveFromStep2WithValidDataCreatesEntity(): void
    {
        $client = $this->createAuthenticatedClient();

        // 1. Charger l'étape 1
        $crawler = $client->request(Request::METHOD_GET, '/comic/new');
        self::assertResponseIsSuccessful();

        // 2. Passer à l'étape 2
        $form = $crawler->selectButton('Suivant')->form([
            'comic_series_flow[format][type]' => 'bd',
        ]);
        $crawler = $client->submit($form);
        self::assertResponseIsSuccessful();

        // 3. Remplir le titre et cliquer sur Enregistrer
        $form = $crawler->selectButton('Enregistrer')->form([
            'comic_series_flow[identification_series][title]' => 'Ma BD Test Sauvegarde Étape 2',
        ]);
        $client->submit($form);

        // 4. Vérifier la redirection (succès)
        self::assertResponseRedirects('/');

        // Suivre la redirection pour vérifier le message flash
        $client->followRedirect();

        // Vérifier qu'un message de succès est affiché (pas d'erreur)
        self::assertSelectorExists('.alert-success');

        // 5. Vérifier que l'entité a été créée en base (obtenir un EntityManager frais après la requête)
        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $series = $em->getRepository(ComicSeries::class)->findOneBy(['title' => 'Ma BD Test Sauvegarde Étape 2']);
        self::assertNotNull($series, 'La série devrait avoir été créée en base de données');

        // Nettoyer
        $em->remove($series);
        $em->flush();
    }

    /**
     * Teste que la sauvegarde depuis l'étape 2 échoue si le titre est vide.
     */
    public function testSaveFromStep2FailsWithEmptyTitle(): void
    {
        $client = $this->createAuthenticatedClient();

        // 1. Charger l'étape 1
        $crawler = $client->request(Request::METHOD_GET, '/comic/new');
        self::assertResponseIsSuccessful();

        // 2. Passer à l'étape 2
        $form = $crawler->selectButton('Suivant')->form([
            'comic_series_flow[format][type]' => 'bd',
        ]);
        $crawler = $client->submit($form);
        self::assertResponseIsSuccessful();

        // 3. Soumettre sans titre (champ vide)
        $form = $crawler->selectButton('Enregistrer')->form([
            'comic_series_flow[identification_series][title]' => '',
        ]);
        $crawler = $client->submit($form);

        // 4. Le formulaire doit rester sur la même page avec erreur de validation (422 Unprocessable Content)
        self::assertResponseStatusCodeSame(422);

        // 5. Vérifier qu'une erreur de validation est affichée
        self::assertSelectorTextContains('ul li', 'Le titre est obligatoire');
    }

    /**
     * Teste que la sauvegarde depuis l'étape 3 (details) fonctionne.
     */
    public function testSaveFromStep3WithValidDataCreatesEntity(): void
    {
        $client = $this->createAuthenticatedClient();

        // 1. Charger l'étape 1
        $crawler = $client->request(Request::METHOD_GET, '/comic/new');

        // 2. Passer à l'étape 2
        $form = $crawler->selectButton('Suivant')->form([
            'comic_series_flow[format][type]' => 'manga',
        ]);
        $crawler = $client->submit($form);

        // 3. Passer à l'étape 3 avec un titre
        $form = $crawler->selectButton('Suivant')->form([
            'comic_series_flow[identification_series][title]' => 'Mon Manga Test Étape 3',
        ]);
        $crawler = $client->submit($form);
        self::assertResponseIsSuccessful();

        // 4. Vérifier qu'on est à l'étape details et que le bouton Enregistrer est présent
        self::assertSelectorExists('button[name="comic_series_flow[finish]"]');

        // 5. Cliquer sur Enregistrer depuis l'étape 3
        $form = $crawler->selectButton('Enregistrer')->form();
        $client->submit($form);

        // 6. Vérifier la redirection (succès)
        self::assertResponseRedirects('/');

        // 7. Vérifier que l'entité a été créée (obtenir un EntityManager frais)
        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $series = $em->getRepository(ComicSeries::class)->findOneBy(['title' => 'Mon Manga Test Étape 3']);
        self::assertNotNull($series, 'La série devrait avoir été créée en base de données');

        // Nettoyer
        $em->remove($series);
        $em->flush();
    }

    /**
     * Teste que la sauvegarde depuis l'étape 4 (cover) fonctionne.
     */
    public function testSaveFromStep4WithValidDataCreatesEntity(): void
    {
        $client = $this->createAuthenticatedClient();

        // 1. Charger l'étape 1
        $crawler = $client->request(Request::METHOD_GET, '/comic/new');

        // 2. Passer à l'étape 2
        $form = $crawler->selectButton('Suivant')->form([
            'comic_series_flow[format][type]' => 'comics',
        ]);
        $crawler = $client->submit($form);

        // 3. Passer à l'étape 3
        $form = $crawler->selectButton('Suivant')->form([
            'comic_series_flow[identification_series][title]' => 'Mon Comics Test Étape 4',
        ]);
        $crawler = $client->submit($form);

        // 4. Passer à l'étape 4
        $form = $crawler->selectButton('Suivant')->form();
        $crawler = $client->submit($form);
        self::assertResponseIsSuccessful();

        // 5. Vérifier qu'on est à l'étape cover
        self::assertSelectorExists('[name="comic_series_flow[cover][coverUrl]"]');

        // 6. Cliquer sur Enregistrer depuis l'étape 4
        $form = $crawler->selectButton('Enregistrer')->form();
        $client->submit($form);

        // 7. Vérifier la redirection (succès)
        self::assertResponseRedirects('/');

        // 8. Vérifier que l'entité a été créée (obtenir un EntityManager frais)
        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $series = $em->getRepository(ComicSeries::class)->findOneBy(['title' => 'Mon Comics Test Étape 4']);
        self::assertNotNull($series, 'La série devrait avoir été créée en base de données');

        // Nettoyer
        $em->remove($series);
        $em->flush();
    }
}

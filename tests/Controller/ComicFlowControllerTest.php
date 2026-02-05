<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\ComicSeries;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Tests fonctionnels pour le wizard FormFlow de création/édition de séries.
 */
class ComicFlowControllerTest extends AuthenticatedWebTestCase
{
    /**
     * Teste que la page de création affiche la première étape (format).
     */
    public function testNewWizardShowsFormatStep(): void
    {
        $client = $this->createAuthenticatedClient();

        $crawler = $client->request(Request::METHOD_GET, '/comic/new');

        self::assertResponseIsSuccessful();
        // Vérifie que l'étape format est affichée avec les champs type et isOneShot
        self::assertSelectorExists('input[name="comic_series_flow[format][isOneShot]"]');
        self::assertSelectorExists('select[name="comic_series_flow[format][type]"]');
    }

    /**
     * Teste la navigation vers l'étape suivante (identification_series).
     */
    public function testWizardNavigatesToIdentificationSeriesStep(): void
    {
        $client = $this->createAuthenticatedClient();

        $crawler = $client->request(Request::METHOD_GET, '/comic/new');

        // Soumettre l'étape format avec isOneShot = false (série normale)
        // Le bouton "next" du NavigatorFlowType
        $form = $crawler->selectButton('comic_series_flow[next]')->form([
            'comic_series_flow[format][type]' => 'bd',
        ]);

        $crawler = $client->submit($form);

        self::assertResponseIsSuccessful();
        // Vérifie que l'étape identification_series est affichée
        self::assertSelectorExists('input[name="comic_series_flow[identification_series][title]"]');
    }

    /**
     * Teste la navigation vers l'étape identification_oneshot pour un one-shot.
     */
    public function testWizardNavigatesToIdentificationOneShotStep(): void
    {
        $client = $this->createAuthenticatedClient();

        $crawler = $client->request(Request::METHOD_GET, '/comic/new');

        // Soumettre l'étape format avec isOneShot = true
        $form = $crawler->selectButton('comic_series_flow[next]')->form([
            'comic_series_flow[format][type]' => 'manga',
            'comic_series_flow[format][isOneShot]' => '1',
        ]);

        $crawler = $client->submit($form);

        self::assertResponseIsSuccessful();
        // Vérifie que l'étape identification_oneshot est affichée
        self::assertSelectorExists('input[name="comic_series_flow[identification_oneshot][title]"]');
    }

    /**
     * Teste la création complète d'une série via le wizard.
     *
     * @todo Investiguer le problème de persistance de session FormFlow dans les tests
     */
    public function testWizardCompletesSeriesCreation(): void
    {
        self::markTestIncomplete('La persistance de session FormFlow entre requêtes de test nécessite une investigation.');
        // @phpstan-ignore deadCode.unreachable (code conservé pour référence future)
        $client = $this->createAuthenticatedClient();
        $container = static::getContainer();
        /** @var EntityManagerInterface $em */
        $em = $container->get(EntityManagerInterface::class);

        // Étape 1: Format
        $crawler = $client->request(Request::METHOD_GET, '/comic/new');
        $form = $crawler->selectButton('comic_series_flow[next]')->form([
            'comic_series_flow[format][type]' => 'bd',
        ]);
        $crawler = $client->submit($form);

        // Étape 2: Identification (série)
        $form = $crawler->selectButton('comic_series_flow[next]')->form([
            'comic_series_flow[identification_series][title]' => 'Wizard Test Series',
        ]);
        $crawler = $client->submit($form);

        // Étape 3: Détails
        $form = $crawler->selectButton('comic_series_flow[next]')->form([
            'comic_series_flow[details][publisher]' => 'Test Publisher',
        ]);
        $crawler = $client->submit($form);

        // Étape 4: Couverture
        $form = $crawler->selectButton('comic_series_flow[next]')->form();
        $crawler = $client->submit($form);

        // Étape 5: Tomes (finale) - utilise le bouton finish
        $form = $crawler->selectButton('comic_series_flow[finish]')->form([
            'comic_series_flow[tomes][status]' => 'buying',
        ]);
        $client->submit($form);

        self::assertResponseRedirects('/');

        // Vérifier que la série a été créée
        $series = $em->getRepository(ComicSeries::class)->findOneBy(['title' => 'Wizard Test Series']);
        self::assertNotNull($series);
        self::assertSame('Test Publisher', $series->getPublisher());

        // Nettoyer
        if ($series) {
            $em->remove($series);
            $em->flush();
        }
    }

    /**
     * Teste que soumettre l'étape format sans modification fonctionne (pas de validation requise).
     */
    public function testWizardFirstStepWithEmptyFormProceedsToNextStep(): void
    {
        $client = $this->createAuthenticatedClient();

        $crawler = $client->request(Request::METHOD_GET, '/comic/new');

        // Soumettre l'étape format en cliquant juste sur "Suivant"
        // (le type a une valeur par défaut)
        $form = $crawler->selectButton('comic_series_flow[next]')->form();

        $crawler = $client->submit($form);

        // Doit être une réponse HTTP valide (200 ou 422)
        self::assertResponseStatusCodeSame(200);
        // Doit passer à l'étape identification_series
        self::assertSelectorExists('input[name="comic_series_flow[identification_series][title]"]');
    }

    /**
     * Teste que soumettre l'étape identification sans titre affiche une erreur de validation.
     */
    public function testWizardValidationErrorOnEmptyTitleShowsFormAgain(): void
    {
        $client = $this->createAuthenticatedClient();

        // Étape 1: Format
        $crawler = $client->request(Request::METHOD_GET, '/comic/new');
        $form = $crawler->selectButton('comic_series_flow[next]')->form([
            'comic_series_flow[format][type]' => 'bd',
        ]);
        $crawler = $client->submit($form);

        // Étape 2: Identification - soumettre sans titre (doit échouer la validation)
        $form = $crawler->selectButton('comic_series_flow[next]')->form([
            'comic_series_flow[identification_series][title]' => '',
        ]);
        $crawler = $client->submit($form);

        // HTTP 422 est le code standard pour les erreurs de validation en Symfony
        self::assertResponseStatusCodeSame(422);
        // Doit rester sur l'étape identification_series
        self::assertSelectorExists('input[name="comic_series_flow[identification_series][title]"]');
    }

    /**
     * Teste le bouton Précédent pour revenir à l'étape précédente.
     */
    public function testWizardPreviousButtonGoesBack(): void
    {
        $client = $this->createAuthenticatedClient();

        // Étape 1: Format
        $crawler = $client->request(Request::METHOD_GET, '/comic/new');
        $form = $crawler->selectButton('comic_series_flow[next]')->form([
            'comic_series_flow[format][type]' => 'bd',
        ]);
        $crawler = $client->submit($form);

        // Maintenant on est à l'étape 2, on clique sur Précédent
        $form = $crawler->selectButton('comic_series_flow[previous]')->form();
        $crawler = $client->submit($form);

        self::assertResponseIsSuccessful();
        // Vérifie qu'on est revenu à l'étape format
        self::assertSelectorExists('input[name="comic_series_flow[format][isOneShot]"]');
    }

    /**
     * Teste que le champ titre à l'étape identification a l'attribut required.
     */
    public function testWizardIdentificationStepTitleFieldHasRequiredAttribute(): void
    {
        $client = $this->createAuthenticatedClient();

        // Étape 1: Format
        $crawler = $client->request(Request::METHOD_GET, '/comic/new');
        $form = $crawler->selectButton('comic_series_flow[next]')->form([
            'comic_series_flow[format][type]' => 'bd',
        ]);
        $crawler = $client->submit($form);

        // Vérifie que le champ titre a l'attribut required
        $titleInput = $crawler->filter('input[name="comic_series_flow[identification_series][title]"]');
        self::assertCount(1, $titleInput);
        self::assertNotNull($titleInput->attr('required'));
    }

    /**
     * Teste que le champ titre à l'étape identification_oneshot a l'attribut required.
     */
    public function testWizardIdentificationOneShotStepTitleFieldHasRequiredAttribute(): void
    {
        $client = $this->createAuthenticatedClient();

        // Étape 1: Format avec one-shot
        $crawler = $client->request(Request::METHOD_GET, '/comic/new');
        $form = $crawler->selectButton('comic_series_flow[next]')->form([
            'comic_series_flow[format][type]' => 'manga',
            'comic_series_flow[format][isOneShot]' => '1',
        ]);
        $crawler = $client->submit($form);

        // Vérifie que le champ titre a l'attribut required
        $titleInput = $crawler->filter('input[name="comic_series_flow[identification_oneshot][title]"]');
        self::assertCount(1, $titleInput);
        self::assertNotNull($titleInput->attr('required'));
    }

    /**
     * Teste que le bouton Enregistrer n'apparaît PAS à l'étape 1 (format).
     */
    public function testWizardFinishButtonNotVisibleOnFormatStep(): void
    {
        $client = $this->createAuthenticatedClient();

        $crawler = $client->request(Request::METHOD_GET, '/comic/new');

        self::assertResponseIsSuccessful();
        // Le bouton finish ne doit PAS être présent à l'étape format
        self::assertSelectorNotExists('button[name="comic_series_flow[finish]"]');
    }

    /**
     * Teste que le bouton Enregistrer apparaît dès l'étape 2 (identification).
     */
    public function testWizardFinishButtonVisibleOnIdentificationStep(): void
    {
        $client = $this->createAuthenticatedClient();

        // Étape 1: Format
        $crawler = $client->request(Request::METHOD_GET, '/comic/new');
        $form = $crawler->selectButton('comic_series_flow[next]')->form([
            'comic_series_flow[format][type]' => 'bd',
        ]);
        $crawler = $client->submit($form);

        self::assertResponseIsSuccessful();
        // Le bouton finish DOIT être présent dès l'étape identification
        self::assertSelectorExists('button[name="comic_series_flow[finish]"]');
    }

    /**
     * Teste que l'étape format possède la cible Stimulus type pour la persistance via sessionStorage.
     */
    public function testWizardFormatStepHasTypeTarget(): void
    {
        $client = $this->createAuthenticatedClient();

        $crawler = $client->request(Request::METHOD_GET, '/comic/new');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('[data-comic-form-target="type"]');
    }

    /**
     * Teste que l'étape identification (série) possède la cible Stimulus title et le bouton lookup.
     */
    public function testWizardIdentificationSeriesStepHasLookupTargets(): void
    {
        $client = $this->createAuthenticatedClient();

        $crawler = $client->request(Request::METHOD_GET, '/comic/new');
        $form = $crawler->selectButton('comic_series_flow[next]')->form([
            'comic_series_flow[format][type]' => 'bd',
        ]);
        $crawler = $client->submit($form);

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('[data-comic-form-target="title"]');
        self::assertSelectorExists('[data-action="comic-form#lookupByTitle"]');
    }

    /**
     * Teste que l'étape identification (one-shot) possède la cible Stimulus title et le bouton lookup.
     */
    public function testWizardIdentificationOneShotStepHasLookupTargets(): void
    {
        $client = $this->createAuthenticatedClient();

        $crawler = $client->request(Request::METHOD_GET, '/comic/new');
        $form = $crawler->selectButton('comic_series_flow[next]')->form([
            'comic_series_flow[format][type]' => 'manga',
            'comic_series_flow[format][isOneShot]' => '1',
        ]);
        $crawler = $client->submit($form);

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('[data-comic-form-target="title"]');
        self::assertSelectorExists('[data-action="comic-form#lookupByTitle"]');
    }

    /**
     * Teste que l'étape détails possède les cibles Stimulus nécessaires au remplissage différé.
     */
    public function testWizardDetailsStepHasDeferredFillTargets(): void
    {
        $client = $this->createAuthenticatedClient();

        // Étape 1: Format
        $crawler = $client->request(Request::METHOD_GET, '/comic/new');
        $form = $crawler->selectButton('comic_series_flow[next]')->form([
            'comic_series_flow[format][type]' => 'bd',
        ]);
        $crawler = $client->submit($form);

        // Étape 2: Identification (série) → Suivant
        $form = $crawler->selectButton('comic_series_flow[next]')->form([
            'comic_series_flow[identification_series][title]' => 'Test Targets',
        ]);
        $crawler = $client->submit($form);

        self::assertResponseIsSuccessful();
        // Vérifie les cibles Stimulus nécessaires au remplissage différé
        self::assertSelectorExists('[data-comic-form-target="authorsWrapper"]');
        self::assertSelectorExists('[data-comic-form-target="publisher"]');
        self::assertSelectorExists('[data-comic-form-target="publishedDate"]');
        self::assertSelectorExists('[data-comic-form-target="description"]');
    }

    /**
     * Teste l'édition d'une série existante via le formulaire standard.
     *
     * Note: L'édition utilise le formulaire standard (ComicSeriesType) et non le wizard.
     */
    public function testEditShowsStandardFormWithExistingData(): void
    {
        $client = $this->createAuthenticatedClient();
        $container = static::getContainer();
        /** @var EntityManagerInterface $em */
        $em = $container->get(EntityManagerInterface::class);

        $series = new ComicSeries();
        $series->setTitle('Existing Series');
        $series->setPublisher('Existing Publisher');
        $em->persist($series);
        $em->flush();

        $crawler = $client->request(Request::METHOD_GET, '/comic/'.$series->getId().'/edit');

        self::assertResponseIsSuccessful();
        // Vérifie que le formulaire standard est affiché (pas le wizard)
        self::assertSelectorExists('input[name="comic_series[isOneShot]"]');
        self::assertSelectorExists('input[name="comic_series[title]"]');

        // Nettoyer
        $em->remove($series);
        $em->flush();
    }
}

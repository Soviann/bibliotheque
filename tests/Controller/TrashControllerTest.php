<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\ComicSeries;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Tests fonctionnels pour TrashController.
 */
class TrashControllerTest extends AuthenticatedWebTestCase
{
    /**
     * Teste que la page corbeille affiche les séries soft-deleted.
     */
    public function testIndexShowsSoftDeletedSeries(): void
    {
        $client = $this->createAuthenticatedClient();
        $container = static::getContainer();
        /** @var EntityManagerInterface $em */
        $em = $container->get(EntityManagerInterface::class);

        // Créer et soft-delete une série
        $series = new ComicSeries();
        $series->setTitle('Série Dans Corbeille');
        $em->persist($series);
        $em->flush();

        $em->remove($series);
        $em->flush();

        $crawler = $client->request(Request::METHOD_GET, '/trash');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'Série Dans Corbeille');
    }

    /**
     * Teste que la page corbeille exclut les séries actives.
     */
    public function testIndexExcludesActiveSeries(): void
    {
        $client = $this->createAuthenticatedClient();
        $container = static::getContainer();
        /** @var EntityManagerInterface $em */
        $em = $container->get(EntityManagerInterface::class);

        $series = new ComicSeries();
        $series->setTitle('Série Active Unique XYZ123');
        $em->persist($series);
        $em->flush();

        $crawler = $client->request(Request::METHOD_GET, '/trash');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextNotContains('body', 'Série Active Unique XYZ123');
    }

    /**
     * Teste la restauration d'une série avec CSRF valide.
     */
    public function testRestoreWithValidCsrf(): void
    {
        $client = $this->createAuthenticatedClient();
        $container = static::getContainer();
        /** @var EntityManagerInterface $em */
        $em = $container->get(EntityManagerInterface::class);

        $series = new ComicSeries();
        $series->setTitle('Série À Restaurer');
        $em->persist($series);
        $em->flush();

        $seriesId = $series->getId();

        $em->remove($series);
        $em->flush();

        // Charger la page corbeille pour obtenir le token CSRF
        $crawler = $client->request(Request::METHOD_GET, '/trash');
        $restoreForm = $crawler->filter('form[action$="/restore"]');
        $csrfToken = $restoreForm->filter('input[name="_token"]')->attr('value');

        $client->request(Request::METHOD_POST, '/trash/'.$seriesId.'/restore', [
            '_token' => $csrfToken,
        ]);

        self::assertResponseRedirects('/trash');

        // Vérifier que la série est restaurée (visible normalement)
        $em->clear();
        $restored = $em->getRepository(ComicSeries::class)->find($seriesId);
        self::assertNotNull($restored, 'La série restaurée doit être visible');
        self::assertNull($restored->getDeletedAt());
    }

    /**
     * Teste la restauration avec CSRF invalide.
     */
    public function testRestoreWithInvalidCsrf(): void
    {
        $client = $this->createAuthenticatedClient();
        $container = static::getContainer();
        /** @var EntityManagerInterface $em */
        $em = $container->get(EntityManagerInterface::class);

        $series = new ComicSeries();
        $series->setTitle('Série CSRF Invalide Restore');
        $em->persist($series);
        $em->flush();

        $seriesId = $series->getId();

        $em->remove($series);
        $em->flush();

        $client->request(Request::METHOD_POST, '/trash/'.$seriesId.'/restore', [
            '_token' => 'invalid_token',
        ]);

        self::assertResponseRedirects('/trash');

        $client->followRedirect();
        self::assertSelectorExists('.alert-error');

        // Vérifier que la série est toujours soft-deleted
        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $row = $em->getConnection()->fetchAssociative(
            'SELECT deleted_at FROM comic_series WHERE id = ?',
            [$seriesId]
        );
        self::assertNotNull($row['deleted_at'], 'La série doit rester soft-deleted');
    }

    /**
     * Teste la suppression définitive avec CSRF valide.
     */
    public function testPermanentDeleteWithValidCsrf(): void
    {
        $client = $this->createAuthenticatedClient();
        $container = static::getContainer();
        /** @var EntityManagerInterface $em */
        $em = $container->get(EntityManagerInterface::class);

        $series = new ComicSeries();
        $series->setTitle('Série Suppression Définitive');
        $em->persist($series);
        $em->flush();

        $seriesId = $series->getId();

        $em->remove($series);
        $em->flush();

        // Charger la page corbeille pour obtenir le token CSRF
        $crawler = $client->request(Request::METHOD_GET, '/trash');
        $deleteForm = $crawler->filter('form[action$="/permanent-delete"]');
        $csrfToken = $deleteForm->filter('input[name="_token"]')->attr('value');

        $client->request(Request::METHOD_POST, '/trash/'.$seriesId.'/permanent-delete', [
            '_token' => $csrfToken,
        ]);

        self::assertResponseRedirects('/trash');
    }

    /**
     * Teste que la suppression définitive supprime réellement de la base.
     */
    public function testPermanentDeleteRemovesFromDatabase(): void
    {
        $client = $this->createAuthenticatedClient();
        $container = static::getContainer();
        /** @var EntityManagerInterface $em */
        $em = $container->get(EntityManagerInterface::class);

        $series = new ComicSeries();
        $series->setTitle('Série Hard Delete Test');
        $em->persist($series);
        $em->flush();

        $seriesId = $series->getId();

        $em->remove($series);
        $em->flush();

        // Charger la page corbeille pour obtenir le token CSRF
        $crawler = $client->request(Request::METHOD_GET, '/trash');
        $deleteForm = $crawler->filter('form[action$="/permanent-delete"]');
        $csrfToken = $deleteForm->filter('input[name="_token"]')->attr('value');

        $client->request(Request::METHOD_POST, '/trash/'.$seriesId.'/permanent-delete', [
            '_token' => $csrfToken,
        ]);

        // Vérifier via DBAL que la série n'existe plus du tout
        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $row = $em->getConnection()->fetchAssociative(
            'SELECT id FROM comic_series WHERE id = ?',
            [$seriesId]
        );
        self::assertFalse($row, 'La série doit être complètement supprimée de la base');
    }
}

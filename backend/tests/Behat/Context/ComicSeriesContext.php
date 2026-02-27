<?php

declare(strict_types=1);

namespace App\Tests\Behat\Context;

use App\Repository\ComicSeriesRepository;
use Behat\Behat\Context\Context;
use Behat\Mink\Exception\ElementNotFoundException;
use Behat\Mink\Exception\ExpectationException;
use Behat\MinkExtension\Context\RawMinkContext;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Contexte pour les tests CRUD des séries.
 */
final class ComicSeriesContext extends RawMinkContext implements Context
{
    public function __construct(
        private readonly ComicSeriesRepository $comicSeriesRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * Accède à la page de création d'une série.
     *
     * @Given je suis sur la page de création d'une série
     *
     * @When je vais sur la page de création d'une série
     */
    public function jeSuisSurLaPageDeCreationDUneSerie(): void
    {
        $this->visitPath('/comic/new');
    }

    /**
     * Accède à la page d'édition d'une série.
     *
     * @Given je suis sur la page d'édition de la série :title
     *
     * @When je vais sur la page d'édition de la série :title
     */
    public function jeSuisSurLaPageDEditionDeLaSerie(string $title): void
    {
        $comic = $this->comicSeriesRepository->findOneBy(['title' => $title]);

        if (null === $comic) {
            throw new \RuntimeException(\sprintf('Série "%s" non trouvée.', $title));
        }

        $this->visitPath(\sprintf('/comic/%d/edit', $comic->getId()));
    }

    /**
     * Remplit le champ titre.
     *
     * @When je remplis le titre avec :title
     */
    public function jeRemplisLeTitreAvec(string $title): void
    {
        $this->getSession()->getPage()->fillField('comic_series_title', $title);
    }

    /**
     * Sélectionne le type de série.
     *
     * @When je sélectionne le type :type
     */
    public function jeSelectionneLetype(string $type): void
    {
        $typeValue = match ($type) {
            'BD' => 'bd',
            'Manga' => 'manga',
            'Comics' => 'comics',
            'Livre' => 'livre',
            default => throw new \InvalidArgumentException(\sprintf('Type inconnu: %s', $type)),
        };

        $this->getSession()->getPage()->selectFieldOption('comic_series_type', $typeValue);
    }

    /**
     * Sélectionne le statut de la série.
     *
     * @When je sélectionne le statut :status
     */
    public function jeSelectionneLeStatut(string $status): void
    {
        $statusValue = match ($status) {
            'En cours d\'achat' => 'buying',
            'Terminée' => 'finished',
            'Arrêtée' => 'stopped',
            'Liste de souhaits' => 'wishlist',
            default => throw new \InvalidArgumentException(\sprintf('Statut inconnu: %s', $status)),
        };

        $this->getSession()->getPage()->selectFieldOption('comic_series_status', $statusValue);
    }

    /**
     * Sélectionne le statut wishlist.
     *
     * @When je coche la case wishlist
     */
    public function jeCocheLaCaseWishlist(): void
    {
        $this->getSession()->getPage()->selectFieldOption('comic_series_status', 'wishlist');
    }

    /**
     * Coche la case one-shot.
     *
     * @When je coche la case one-shot
     */
    public function jeCocheLaCaseOneShot(): void
    {
        $this->getSession()->getPage()->checkField('comic_series_isOneShot');
    }

    /**
     * Décoche la case one-shot.
     *
     * @When je décoche la case one-shot
     */
    public function jeDeCocheLaCaseOneShot(): void
    {
        $this->getSession()->getPage()->uncheckField('comic_series_isOneShot');
    }

    /**
     * Soumet le formulaire.
     *
     * @When je soumets le formulaire
     */
    public function jeSoumetsLeFormulaire(): void
    {
        $this->getSession()->getPage()->pressButton('Enregistrer');
    }

    /**
     * Vérifie qu'une série existe dans la base de données.
     *
     * @Then la série :title devrait exister
     */
    public function laSerieDevraitExister(string $title): void
    {
        // Rafraîchit le contexte Doctrine
        $this->entityManager->clear();
        $comic = $this->comicSeriesRepository->findOneBy(['title' => $title]);

        if (null === $comic) {
            throw new ExpectationException(\sprintf('La série "%s" n\'existe pas.', $title), $this->getSession()->getDriver());
        }
    }

    /**
     * Vérifie qu'une série n'existe pas dans la base de données.
     *
     * @Then la série :title ne devrait pas exister
     */
    public function laSerieNeDevraitPasExister(string $title): void
    {
        // Rafraîchit le contexte Doctrine
        $this->entityManager->clear();
        $comic = $this->comicSeriesRepository->findOneBy(['title' => $title]);

        if (null !== $comic) {
            throw new ExpectationException(\sprintf('La série "%s" existe alors qu\'elle ne devrait pas.', $title), $this->getSession()->getDriver());
        }
    }

    /**
     * Vérifie le type d'une série.
     *
     * @Then la série :title devrait être de type :type
     */
    public function laSerieDevraitEtreDeType(string $title, string $type): void
    {
        $this->entityManager->clear();
        $comic = $this->comicSeriesRepository->findOneBy(['title' => $title]);

        if (null === $comic) {
            throw new ExpectationException(\sprintf('La série "%s" n\'existe pas.', $title), $this->getSession()->getDriver());
        }

        $expectedType = match ($type) {
            'BD' => 'bd',
            'Manga' => 'manga',
            'Comics' => 'comics',
            'Livre' => 'livre',
            default => throw new \InvalidArgumentException(\sprintf('Type inconnu: %s', $type)),
        };

        if ($comic->getType()->value !== $expectedType) {
            throw new ExpectationException(\sprintf('La série "%s" est de type "%s" au lieu de "%s".', $title, $comic->getType()->value, $expectedType), $this->getSession()->getDriver());
        }
    }

    /**
     * Vérifie le statut d'une série.
     *
     * @Then la série :title devrait avoir le statut :status
     */
    public function laSerieDevraitAvoirLeStatut(string $title, string $status): void
    {
        $this->entityManager->clear();
        $comic = $this->comicSeriesRepository->findOneBy(['title' => $title]);

        if (null === $comic) {
            throw new ExpectationException(\sprintf('La série "%s" n\'existe pas.', $title), $this->getSession()->getDriver());
        }

        $expectedStatus = match ($status) {
            'En cours d\'achat' => 'buying',
            'Terminée' => 'finished',
            'Arrêtée' => 'stopped',
            'Liste de souhaits' => 'wishlist',
            default => throw new \InvalidArgumentException(\sprintf('Statut inconnu: %s', $status)),
        };

        if ($comic->getStatus()->value !== $expectedStatus) {
            throw new ExpectationException(\sprintf('La série "%s" a le statut "%s" au lieu de "%s".', $title, $comic->getStatus()->value, $expectedStatus), $this->getSession()->getDriver());
        }
    }

    /**
     * Vérifie qu'une série est dans la wishlist.
     *
     * @Then la série :title devrait être dans la wishlist
     */
    public function laSerieDevraitEtreDansLaWishlist(string $title): void
    {
        $this->entityManager->clear();
        $comic = $this->comicSeriesRepository->findOneBy(['title' => $title]);

        if (null === $comic) {
            throw new ExpectationException(\sprintf('La série "%s" n\'existe pas.', $title), $this->getSession()->getDriver());
        }

        if (!$comic->isWishlist()) {
            throw new ExpectationException(\sprintf('La série "%s" n\'est pas dans la wishlist.', $title), $this->getSession()->getDriver());
        }
    }

    /**
     * Vérifie qu'une série n'est pas dans la wishlist.
     *
     * @Then la série :title ne devrait pas être dans la wishlist
     */
    public function laSerieNeDevraitPasEtreDansLaWishlist(string $title): void
    {
        $this->entityManager->clear();
        $comic = $this->comicSeriesRepository->findOneBy(['title' => $title]);

        if (null === $comic) {
            throw new ExpectationException(\sprintf('La série "%s" n\'existe pas.', $title), $this->getSession()->getDriver());
        }

        if ($comic->isWishlist()) {
            throw new ExpectationException(\sprintf('La série "%s" est dans la wishlist alors qu\'elle ne devrait pas.', $title), $this->getSession()->getDriver());
        }
    }

    /**
     * Vérifie qu'une série est un one-shot.
     *
     * @Then la série :title devrait être un one-shot
     */
    public function laSerieDevraitEtreUnOneShot(string $title): void
    {
        $this->entityManager->clear();
        $comic = $this->comicSeriesRepository->findOneBy(['title' => $title]);

        if (null === $comic) {
            throw new ExpectationException(\sprintf('La série "%s" n\'existe pas.', $title), $this->getSession()->getDriver());
        }

        if (!$comic->isOneShot()) {
            throw new ExpectationException(\sprintf('La série "%s" n\'est pas un one-shot.', $title), $this->getSession()->getDriver());
        }
    }

    /**
     * Supprime une série via le bouton de suppression.
     *
     * @When je supprime la série :title
     */
    public function jeSupprimeLaSerie(string $title): void
    {
        $comic = $this->comicSeriesRepository->findOneBy(['title' => $title]);

        if (null === $comic) {
            throw new \RuntimeException(\sprintf('Série "%s" non trouvée.', $title));
        }

        // Va sur la page de détail où se trouve le formulaire de suppression
        $this->visitPath(\sprintf('/comic/%d', $comic->getId()));

        // Soumet le formulaire de suppression
        $page = $this->getSession()->getPage();
        $deleteForm = $page->find('css', 'form[action*="/delete"]');

        if (null === $deleteForm) {
            throw new ElementNotFoundException($this->getSession()->getDriver(), 'delete form');
        }

        $deleteForm->submit();
    }

    /**
     * Déplace une série vers la bibliothèque.
     *
     * @When je déplace la série :title vers la bibliothèque
     */
    public function jeDeplaceLaSerieVersLaBibliotheque(string $title): void
    {
        $comic = $this->comicSeriesRepository->findOneBy(['title' => $title]);

        if (null === $comic) {
            throw new \RuntimeException(\sprintf('Série "%s" non trouvée.', $title));
        }

        // Va sur la page wishlist où le bouton "Ajouter à la bibliothèque" est visible
        $this->visitPath('/wishlist');

        $page = $this->getSession()->getPage();
        $toLibraryForm = $page->find('css', \sprintf('form[action*="/comic/%d/to-library"]', $comic->getId()));

        if (null === $toLibraryForm) {
            throw new ElementNotFoundException($this->getSession()->getDriver(), 'to-library form');
        }

        $toLibraryForm->submit();
    }

    /**
     * Vérifie le nombre de séries affichées.
     *
     * @Then je devrais voir :count série(s)
     */
    public function jeDevraisVoirSeries(int $count): void
    {
        $page = $this->getSession()->getPage();
        $cards = $page->findAll('css', '.comic-card');

        if (\count($cards) !== $count) {
            throw new ExpectationException(\sprintf('Attendu %d série(s), mais %d affichée(s).', $count, \count($cards)), $this->getSession()->getDriver());
        }
    }
}

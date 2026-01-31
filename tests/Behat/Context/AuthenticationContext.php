<?php

declare(strict_types=1);

namespace App\Tests\Behat\Context;

use Behat\Behat\Context\Context;
use Behat\Mink\Exception\ExpectationException;
use Behat\MinkExtension\Context\RawMinkContext;

/**
 * Contexte pour les tests d'authentification.
 */
final class AuthenticationContext extends RawMinkContext implements Context
{
    /**
     * Se connecte avec les identifiants par défaut.
     *
     * @Given je suis connecté
     */
    public function jeSuisConnecte(): void
    {
        $this->jeMeConnecteAvec('test@example.com', 'password');
    }

    /**
     * Se connecte avec des identifiants spécifiques.
     *
     * @When je me connecte avec :email et :password
     */
    public function jeMeConnecteAvec(string $email, string $password): void
    {
        $this->visitPath('/login');

        $page = $this->getSession()->getPage();
        $page->fillField('_username', $email);
        $page->fillField('_password', $password);
        $page->pressButton('Se connecter');
    }

    /**
     * Visite la page de connexion.
     *
     * @Given je suis sur la page de connexion
     *
     * @When je vais sur la page de connexion
     */
    public function jeSuisSurLaPageDeConnexion(): void
    {
        $this->visitPath('/login');
    }

    /**
     * Vérifie qu'on est sur la page de connexion.
     *
     * @Then je devrais être sur la page de connexion
     */
    public function jeDevraisEtreSurLaPageDeConnexion(): void
    {
        $this->assertSession()->addressMatches('/\/login/');
    }

    /**
     * Se déconnecte.
     *
     * @When je me déconnecte
     */
    public function jeMeDeconnecte(): void
    {
        $this->visitPath('/logout');
    }

    /**
     * Vérifie qu'un message d'erreur d'authentification est affiché.
     *
     * @Then je devrais voir une erreur d'authentification
     */
    public function jeDevraisVoirUneErreurDAuthentification(): void
    {
        $page = $this->getSession()->getPage();
        $errorElement = $page->find('css', '.alert-error');

        if (null === $errorElement) {
            throw new ExpectationException('Aucune erreur d\'authentification affichée.', $this->getSession()->getDriver());
        }
    }

    /**
     * Vérifie qu'on est redirigé vers la page de connexion (pour les pages protégées).
     *
     * @Then je devrais être redirigé vers la page de connexion
     */
    public function jeDevraisEtreRedirigeVersLaPageDeConnexion(): void
    {
        $this->assertSession()->addressMatches('/\/login/');
    }

    /**
     * Accède à une page sans être connecté.
     *
     * @When j'accède à la page :path sans être connecté
     */
    public function jAccedeALaPageSansEtreConnecte(string $path): void
    {
        // S'assure qu'on n'est pas connecté
        $this->getSession()->reset();
        $this->visitPath($path);
    }
}

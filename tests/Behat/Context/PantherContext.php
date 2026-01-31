<?php

declare(strict_types=1);

namespace App\Tests\Behat\Context;

use Behat\Behat\Context\Context;
use Behat\Behat\Hook\Scope\AfterScenarioScope;
use Behat\Behat\Hook\Scope\BeforeScenarioScope;
use Behat\Mink\Mink;
use Behat\Mink\Session;
use Behat\MinkExtension\Context\MinkAwareContext;
use Behat\Mink\Driver\PantherDriver;
use Symfony\Component\Panther\Client;

/**
 * Contexte pour utiliser Symfony Panther comme driver.
 *
 * Ce contexte initialise une session Panther pour les tests @javascript
 * car le driver Selenium2 standard a des problèmes de timeout.
 */
final class PantherContext implements Context, MinkAwareContext
{
    private ?Mink $mink = null;

    private array $minkParameters = [];

    private ?Client $pantherClient = null;

    public function setMink(Mink $mink): void
    {
        $this->mink = $mink;
    }

    public function getMink(): Mink
    {
        if (null === $this->mink) {
            throw new \RuntimeException('Mink n\'est pas initialisé.');
        }

        return $this->mink;
    }

    public function setMinkParameters(array $parameters): void
    {
        $this->minkParameters = $parameters;
    }

    /**
     * Initialise le client Panther avant chaque scénario @javascript.
     *
     * @BeforeScenario @javascript
     */
    public function initializePanther(BeforeScenarioScope $scope): void
    {
        // Définit les capabilities Chrome
        $desiredCapabilities = new \Facebook\WebDriver\Remote\DesiredCapabilities();
        $desiredCapabilities->setBrowserName('chrome');
        $desiredCapabilities->setCapability('goog:chromeOptions', [
            'args' => [
                '--headless',
                '--disable-gpu',
                '--no-sandbox',
                '--disable-dev-shm-usage',
                '--window-size=1920,1080',
                '--ignore-certificate-errors',
            ],
        ]);

        // Crée un client Panther qui se connecte au serveur Selenium externe
        $this->pantherClient = Client::createSeleniumClient(
            'http://ddev-bibliotheque-chrome:4444',
            $desiredCapabilities,
            'https://test.bibliotheque.ddev.site'
        );

        // Crée un driver Mink basé sur Panther
        $driver = new PantherDriver($this->pantherClient);
        $session = new Session($driver);

        // Enregistre la session Panther dans Mink
        $this->getMink()->registerSession('panther', $session);
        $this->getMink()->setDefaultSessionName('panther');
    }

    /**
     * Ferme le client Panther après chaque scénario @javascript.
     *
     * @AfterScenario @javascript
     */
    public function closePanther(AfterScenarioScope $scope): void
    {
        if (null !== $this->pantherClient) {
            $this->pantherClient->quit();
            $this->pantherClient = null;
        }
    }
}

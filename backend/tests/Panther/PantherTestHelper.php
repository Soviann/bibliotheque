<?php

declare(strict_types=1);

namespace App\Tests\Panther;

use Facebook\WebDriver\Remote\DesiredCapabilities;
use Facebook\WebDriver\Remote\RemoteWebDriver;
use Facebook\WebDriver\WebDriverBy;
use Facebook\WebDriver\WebDriverExpectedCondition;
use Symfony\Component\Process\Process;

/**
 * Trait partagé pour les tests Panther (Selenium distant).
 *
 * Fournit la gestion du driver, la connexion et l'exécution SQL
 * sans passer par le kernel Symfony (évite les conflits avec DAMA).
 */
trait PantherTestHelper
{
    private const string BASE_URL = 'https://test.bibliotheque.ddev.site';
    private const string SELENIUM_URL = 'http://ddev-bibliotheque-chrome:4444/wd/hub';

    private ?RemoteWebDriver $driver = null;

    /**
     * Crée un driver Chrome distant.
     */
    private function createDriver(): RemoteWebDriver
    {
        $capabilities = DesiredCapabilities::chrome();
        $capabilities->setCapability('goog:chromeOptions', [
            'args' => [
                '--disable-gpu',
                '--ignore-certificate-errors',
                '--no-sandbox',
            ],
        ]);

        return RemoteWebDriver::create(self::SELENIUM_URL, $capabilities);
    }

    /**
     * Retourne le driver WebDriver (non-null).
     */
    private function getDriver(): RemoteWebDriver
    {
        if (!$this->driver instanceof RemoteWebDriver) {
            throw new \RuntimeException('WebDriver non initialisé.');
        }

        return $this->driver;
    }

    /**
     * Effectue la connexion via Selenium.
     *
     * Utilise executeScript pour remplir et soumettre le formulaire.
     * La soumission se fait via form.submit() plutôt que button.click()
     * pour contourner les problèmes de Turbo avec les événements DOM.
     */
    private function login(): void
    {
        $driver = $this->getDriver();

        $driver->get(self::BASE_URL.'/login');

        $driver->wait(10)->until(
            WebDriverExpectedCondition::presenceOfElementLocated(WebDriverBy::name('_username'))
        );

        // Attend que Turbo ait fini de modifier le DOM
        \sleep(1);

        // Remplit et soumet via JavaScript + form.submit()
        $driver->executeScript("
            document.querySelector('[name=\"_username\"]').value = 'test@example.com';
            document.querySelector('[name=\"_password\"]').value = 'password';
            document.querySelector('form').submit();
        ");

        // Attend que l'URL ne contienne plus /login
        $driver->wait(10)->until(
            WebDriverExpectedCondition::not(
                WebDriverExpectedCondition::urlContains('/login')
            )
        );
    }

    /**
     * Exécute une requête SQL via bin/console doctrine:query:sql.
     */
    private static function runSql(string $sql): string
    {
        $process = new Process(
            ['bin/console', 'doctrine:query:sql', $sql, '--no-interaction'],
            null,
            ['APP_ENV' => 'test']
        );
        $process->mustRun();

        return $process->getOutput();
    }
}

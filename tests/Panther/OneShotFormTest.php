<?php

declare(strict_types=1);

namespace App\Tests\Panther;

use Facebook\WebDriver\Remote\DesiredCapabilities;
use Facebook\WebDriver\Remote\RemoteWebDriver;
use Facebook\WebDriver\WebDriverBy;
use Facebook\WebDriver\WebDriverExpectedCondition;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

/**
 * Tests du comportement du formulaire pour les one-shots.
 *
 * Ces tests vérifient le comportement dynamique du formulaire qui masque/affiche
 * la section tomes selon que la case one-shot est cochée ou non.
 * Utilise Selenium distant (ddev chrome service).
 */
final class OneShotFormTest extends TestCase
{
    private const BASE_URL = 'https://test.bibliotheque.ddev.site';
    private const SELENIUM_URL = 'http://ddev-bibliotheque-chrome:4444/wd/hub';

    private ?RemoteWebDriver $driver = null;

    /**
     * Réinitialise la base de données de test avant tous les tests de cette classe.
     */
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        // Recharge les fixtures dans la base de données de test
        $process = new Process(['bin/console', 'doctrine:fixtures:load', '--no-interaction'], null, ['APP_ENV' => 'test']);
        $process->run();

        if (!$process->isSuccessful()) {
            throw new \RuntimeException('Échec du chargement des fixtures: '.$process->getErrorOutput());
        }
    }

    protected function setUp(): void
    {
        $capabilities = DesiredCapabilities::chrome();
        $capabilities->setCapability('goog:chromeOptions', [
            'args' => [
                '--disable-gpu',
                '--ignore-certificate-errors',
                '--no-sandbox',
            ],
        ]);

        $this->driver = RemoteWebDriver::create(self::SELENIUM_URL, $capabilities);
    }

    protected function tearDown(): void
    {
        $this->driver?->quit();
    }

    /**
     * Teste que cocher one-shot masque la section tomes.
     */
    public function testCheckOneShotHidesTomesSection(): void
    {
        $this->login();
        $this->goToNewSeriesPage();

        // Vérifie que la section tomes est visible initialement
        $this->assertTomesSectionVisible(true, 'La section tomes devrait être visible initialement');

        // Coche la case one-shot
        $this->checkOneShot();
        \usleep(500000);

        // Vérifie que la section tomes est masquée
        $this->assertTomesSectionVisible(false, 'La section tomes ne devrait pas être visible après avoir coché one-shot');
    }

    /**
     * Teste que décocher one-shot affiche la section tomes.
     */
    public function testUncheckOneShotShowsTomesSection(): void
    {
        $this->login();
        $this->goToNewSeriesPage();

        // Coche la case one-shot
        $this->checkOneShot();
        \usleep(500000);

        // Vérifie que la section tomes est masquée
        $this->assertTomesSectionVisible(false, 'La section tomes ne devrait pas être visible après avoir coché one-shot');

        // Décoche la case one-shot
        $this->uncheckOneShot();
        \usleep(500000);

        // Vérifie que la section tomes est à nouveau visible
        $this->assertTomesSectionVisible(true, 'La section tomes devrait être visible après avoir décoché one-shot');
    }

    /**
     * Retourne le driver WebDriver (non-null).
     */
    private function getDriver(): RemoteWebDriver
    {
        if (null === $this->driver) {
            throw new \RuntimeException('WebDriver non initialisé.');
        }

        return $this->driver;
    }

    /**
     * Effectue la connexion.
     */
    private function login(): void
    {
        $driver = $this->getDriver();

        $driver->get(self::BASE_URL.'/login');

        // Attend que le formulaire soit stable (Turbo peut modifier la page)
        \sleep(1);

        $driver->wait(10)->until(
            WebDriverExpectedCondition::presenceOfElementLocated(WebDriverBy::name('_username'))
        );

        // Attend que le formulaire soit interactif
        $driver->wait(10)->until(
            WebDriverExpectedCondition::elementToBeClickable(WebDriverBy::cssSelector('button[type="submit"]'))
        );

        // Utilise WebDriver pour remplir les champs
        $driver->findElement(WebDriverBy::name('_username'))->sendKeys('test@example.com');
        $driver->findElement(WebDriverBy::name('_password'))->sendKeys('password');
        $driver->findElement(WebDriverBy::cssSelector('button[type="submit"]'))->click();

        // Attend la redirection
        \sleep(3);

        $currentUrl = $driver->getCurrentURL();
        if (\str_contains($currentUrl, '/login')) {
            throw new \RuntimeException("Login failed. Still on: $currentUrl");
        }
    }

    /**
     * Navigue vers la page de création d'une série.
     */
    private function goToNewSeriesPage(): void
    {
        $driver = $this->getDriver();

        $driver->get(self::BASE_URL.'/comic/new');

        $driver->wait(10)->until(
            WebDriverExpectedCondition::presenceOfElementLocated(WebDriverBy::cssSelector('.comic-form'))
        );
    }

    /**
     * Coche la case one-shot.
     */
    private function checkOneShot(): void
    {
        $this->getDriver()->executeScript("
            const checkbox = document.querySelector('#comic_series_isOneShot');
            if (!checkbox.checked) {
                checkbox.click();
            }
        ");
    }

    /**
     * Décoche la case one-shot.
     */
    private function uncheckOneShot(): void
    {
        $this->getDriver()->executeScript("
            const checkbox = document.querySelector('#comic_series_isOneShot');
            if (checkbox.checked) {
                checkbox.click();
            }
        ");
    }

    /**
     * Vérifie la visibilité du bouton "Ajouter un tome".
     *
     * Le comportement one-shot masque ce bouton, pas la section entière.
     */
    private function assertTomesSectionVisible(bool $expected, string $message): void
    {
        $isVisible = $this->getDriver()->executeScript("
            const addButton = document.querySelector('[data-action=\"tomes-collection#addTome\"]');
            if (!addButton) return false;
            const style = window.getComputedStyle(addButton);
            return style.display !== 'none' && style.visibility !== 'hidden';
        ");

        if ($expected) {
            $this->assertTrue($isVisible, $message);
        } else {
            $this->assertFalse($isVisible, $message);
        }
    }
}

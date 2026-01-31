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
 * Tests de gestion des tomes via JavaScript.
 *
 * Ces tests vérifient l'ajout dynamique de tomes via le contrôleur Stimulus.
 * Utilise Selenium distant (ddev chrome service).
 */
final class TomeManagementTest extends TestCase
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
     * Teste l'ajout d'un tome avec numéro, titre et ISBN.
     */
    public function testAddTomeWithNumberTitleAndIsbn(): void
    {
        $this->login();
        $this->goToNewSeriesPage();

        // Remplit le titre de la série
        $this->fillField('#comic_series_title', 'Ma Série Test');

        // Sélectionne le type BD
        $this->selectOption('#comic_series_type', 'bd');

        // Clique sur ajouter un tome
        $this->clickAddTomeButton();
        \usleep(500000); // 0.5 seconde

        // Remplit le tome 1
        $this->fillTomeNumber(0, 1);
        $this->fillTomeTitle(0, 'Premier tome');
        $this->fillTomeIsbn(0, '978-2-1234-5678-9');
        $this->checkTomeBought(0);

        // Soumet le formulaire
        $this->submitForm();

        // Vérifie que la série a été créée
        $this->assertSeriesExists('Ma Série Test');
    }

    /**
     * Teste le marquage d'un tome sur le NAS.
     */
    public function testMarkTomeOnNas(): void
    {
        $this->login();
        $this->goToNewSeriesPage();

        // Remplit le titre de la série
        $this->fillField('#comic_series_title', 'Série avec NAS');

        // Sélectionne le type BD
        $this->selectOption('#comic_series_type', 'bd');

        // Clique sur ajouter un tome
        $this->clickAddTomeButton();
        \usleep(500000);

        // Remplit le tome 1 et le marque comme acheté et sur NAS
        $this->fillTomeNumber(0, 1);
        $this->checkTomeBought(0);
        $this->checkTomeOnNas(0);

        // Soumet le formulaire
        $this->submitForm();

        // Vérifie que la série a été créée
        $this->assertSeriesExists('Série avec NAS');
    }

    /**
     * Teste l'ajout de plusieurs tomes.
     */
    public function testAddMultipleTomes(): void
    {
        $this->login();
        $this->goToNewSeriesPage();

        // Remplit le titre de la série
        $this->fillField('#comic_series_title', 'Série Multi-Tomes');

        // Sélectionne le type Manga
        $this->selectOption('#comic_series_type', 'manga');

        // Ajoute le premier tome
        $this->clickAddTomeButton();
        \usleep(500000);
        $this->fillTomeNumber(0, 1);

        // Ajoute le second tome
        $this->clickAddTomeButton();
        \usleep(500000);
        $this->fillTomeNumber(1, 2);

        // Soumet le formulaire
        $this->submitForm();

        // Vérifie que la série a été créée
        $this->assertSeriesExists('Série Multi-Tomes');
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
     * Remplit un champ de formulaire.
     */
    private function fillField(string $selector, string $value): void
    {
        $this->getDriver()->executeScript(\sprintf(
            "document.querySelector('%s').value = '%s';",
            $selector,
            \addslashes($value)
        ));
    }

    /**
     * Sélectionne une option dans un select.
     */
    private function selectOption(string $selector, string $value): void
    {
        $this->getDriver()->executeScript(\sprintf(
            "document.querySelector('%s').value = '%s';",
            $selector,
            $value
        ));
    }

    /**
     * Clique sur le bouton "Ajouter un tome".
     */
    private function clickAddTomeButton(): void
    {
        $this->getDriver()->executeScript("
            document.querySelector('[data-action=\"tomes-collection#addTome\"]').click();
        ");
    }

    /**
     * Remplit le numéro d'un tome.
     */
    private function fillTomeNumber(int $index, int $number): void
    {
        $this->getDriver()->executeScript(\sprintf(
            "document.querySelectorAll('.tome-number-input')[%d].value = %d;",
            $index,
            $number
        ));
    }

    /**
     * Remplit le titre d'un tome.
     */
    private function fillTomeTitle(int $index, string $title): void
    {
        $this->getDriver()->executeScript(\sprintf(
            "document.querySelectorAll('.tome-title input')[%d].value = '%s';",
            $index,
            \addslashes($title)
        ));
    }

    /**
     * Remplit l'ISBN d'un tome.
     */
    private function fillTomeIsbn(int $index, string $isbn): void
    {
        $this->getDriver()->executeScript(\sprintf(
            "document.querySelectorAll('.tome-isbn-input')[%d].value = '%s';",
            $index,
            $isbn
        ));
    }

    /**
     * Coche la case "Acheté" d'un tome.
     */
    private function checkTomeBought(int $index): void
    {
        $this->getDriver()->executeScript(\sprintf(
            "document.querySelectorAll('.tome-checkboxes input[type=\"checkbox\"]')[%d * 3].checked = true;",
            $index
        ));
    }

    /**
     * Coche la case "Sur NAS" d'un tome.
     */
    private function checkTomeOnNas(int $index): void
    {
        $this->getDriver()->executeScript(\sprintf(
            "document.querySelectorAll('.tome-checkboxes input[type=\"checkbox\"]')[%d * 3 + 2].checked = true;",
            $index
        ));
    }

    /**
     * Soumet le formulaire.
     */
    private function submitForm(): void
    {
        $this->getDriver()->executeScript("
            document.querySelector('button[type=\"submit\"]').click();
        ");

        \sleep(2);
    }

    /**
     * Vérifie qu'une série existe en base de données.
     */
    private function assertSeriesExists(string $title): void
    {
        $driver = $this->getDriver();

        // Recherche la série via la page de recherche
        $driver->get(self::BASE_URL.'/search?q='.\urlencode($title));

        $driver->wait(10)->until(
            WebDriverExpectedCondition::presenceOfElementLocated(WebDriverBy::cssSelector('.comic-card, .search-results'))
        );

        $pageSource = $driver->getPageSource();
        $this->assertStringContainsString($title, $pageSource, "La série '$title' devrait exister");
    }
}

<?php

declare(strict_types=1);

namespace App\Tests\Panther;

use App\Entity\ComicSeries;
use App\Enum\ComicStatus;
use App\Enum\ComicType;
use Doctrine\ORM\EntityManagerInterface;
use Facebook\WebDriver\Remote\DesiredCapabilities;
use Facebook\WebDriver\Remote\RemoteWebDriver;
use Facebook\WebDriver\WebDriverBy;
use Facebook\WebDriver\WebDriverExpectedCondition;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Tests du comportement du formulaire pour les one-shots.
 *
 * Ces tests vérifient le comportement dynamique du formulaire d'édition qui masque/affiche
 * la section tomes selon que la case one-shot est cochée ou non.
 *
 * Note: La page de création utilise un wizard multi-étapes où le one-shot détermine
 * le chemin (étapes différentes), pas une section masquée. Le comportement dynamique
 * de masquage est uniquement sur le formulaire d'édition (formulaire standard).
 *
 * Utilise Selenium distant (ddev chrome service).
 */
final class OneShotFormTest extends KernelTestCase
{
    private const string BASE_URL = 'https://test.bibliotheque.ddev.site';
    private const string SELENIUM_URL = 'http://ddev-bibliotheque-chrome:4444/wd/hub';

    private ?RemoteWebDriver $driver = null;
    private static ?int $testSeriesId = null;

    /**
     * Crée une série de test pour les tests de cette classe.
     */
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        self::bootKernel(['environment' => 'test']);

        /** @var EntityManagerInterface $em */
        $em = self::getContainer()->get(EntityManagerInterface::class);

        // Crée une série de test pour les tests de one-shot
        $series = new ComicSeries();
        $series->setTitle('Série Test One-Shot');
        $series->setType(ComicType::BD);
        $series->setStatus(ComicStatus::BUYING);

        $em->persist($series);
        $em->flush();

        self::$testSeriesId = $series->getId();

        self::ensureKernelShutdown();
    }

    /**
     * Supprime la série de test après tous les tests.
     */
    public static function tearDownAfterClass(): void
    {
        if (null !== self::$testSeriesId) {
            self::bootKernel(['environment' => 'test']);

            /** @var EntityManagerInterface $em */
            $em = self::getContainer()->get(EntityManagerInterface::class);

            $series = $em->find(ComicSeries::class, self::$testSeriesId);
            if ($series) {
                $em->remove($series);
                $em->flush();
            }

            self::ensureKernelShutdown();
        }

        parent::tearDownAfterClass();
    }

    protected function setUp(): void
    {
        if (null === self::$testSeriesId) {
            self::markTestSkipped('Aucune série de test disponible dans la base de données.');
        }

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
        $this->goToEditSeriesPage();

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
        $this->goToEditSeriesPage();

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
        if (!$this->driver instanceof RemoteWebDriver) {
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
     * Navigue vers la page d'édition d'une série.
     */
    private function goToEditSeriesPage(): void
    {
        $driver = $this->getDriver();

        $driver->get(self::BASE_URL.'/comic/'.self::$testSeriesId.'/edit');

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

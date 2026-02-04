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
 * Tests de gestion des tomes via JavaScript.
 *
 * Ces tests vérifient l'ajout dynamique de tomes via le contrôleur Stimulus
 * sur le formulaire d'édition (formulaire standard).
 *
 * Note: La page de création utilise un wizard multi-étapes. Le comportement
 * d'ajout de tomes est testé sur le formulaire d'édition qui est identique.
 *
 * Utilise Selenium distant (ddev chrome service).
 */
final class TomeManagementTest extends KernelTestCase
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

        // Crée une série de test pour les tests de gestion des tomes
        $series = new ComicSeries();
        $series->setTitle('Série Test Tomes');
        $series->setType(ComicType::MANGA);
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
     * Teste l'ajout d'un tome avec numéro, titre et ISBN.
     */
    public function testAddTomeWithNumberTitleAndIsbn(): void
    {
        $this->login();
        $this->goToEditSeriesPage();

        // Compte les tomes existants
        $initialCount = $this->getTomeCount();

        // Clique sur ajouter un tome
        $this->clickAddTomeButton();
        \usleep(500000); // 0.5 seconde

        // Remplit le nouveau tome
        $newIndex = $initialCount;
        $this->fillTomeNumber($newIndex, 99);
        $this->fillTomeTitle($newIndex, 'Tome de test');
        $this->fillTomeIsbn($newIndex, '978-2-1234-5678-9');
        $this->checkTomeBought($newIndex);

        // Vérifie que le tome a été ajouté dans le DOM
        $finalCount = $this->getTomeCount();
        $this->assertSame($initialCount + 1, $finalCount, 'Un tome devrait avoir été ajouté');
    }

    /**
     * Teste le marquage d'un tome sur le NAS.
     */
    public function testMarkTomeOnNas(): void
    {
        $this->login();
        $this->goToEditSeriesPage();

        // Compte les tomes existants
        $initialCount = $this->getTomeCount();

        // Clique sur ajouter un tome
        $this->clickAddTomeButton();
        \usleep(500000);

        // Remplit le tome et le marque comme acheté et sur NAS
        $newIndex = $initialCount;
        $this->fillTomeNumber($newIndex, 98);
        $this->checkTomeBought($newIndex);
        $this->checkTomeOnNas($newIndex);

        // Vérifie que le tome a été ajouté dans le DOM
        $finalCount = $this->getTomeCount();
        $this->assertSame($initialCount + 1, $finalCount, 'Un tome devrait avoir été ajouté');

        // Vérifie que la case NAS est cochée
        $isOnNas = $this->isTomeOnNasChecked($newIndex);
        $this->assertTrue($isOnNas, 'La case NAS devrait être cochée');
    }

    /**
     * Teste l'ajout de plusieurs tomes.
     */
    public function testAddMultipleTomes(): void
    {
        $this->login();
        $this->goToEditSeriesPage();

        // Compte les tomes existants
        $initialCount = $this->getTomeCount();

        // Ajoute le premier tome
        $this->clickAddTomeButton();
        \usleep(500000);
        $this->fillTomeNumber($initialCount, 96);

        // Ajoute le second tome
        $this->clickAddTomeButton();
        \usleep(500000);
        $this->fillTomeNumber($initialCount + 1, 97);

        // Vérifie que les deux tomes ont été ajoutés
        $finalCount = $this->getTomeCount();
        $this->assertSame($initialCount + 2, $finalCount, 'Deux tomes devraient avoir été ajoutés');
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
     * Compte le nombre de tomes dans le formulaire.
     */
    private function getTomeCount(): int
    {
        $count = $this->getDriver()->executeScript(
            "return document.querySelectorAll('.tome-entry').length;"
        );

        return \is_numeric($count) ? (int) $count : 0;
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
     * Vérifie si la case NAS d'un tome est cochée.
     */
    private function isTomeOnNasChecked(int $index): bool
    {
        return (bool) $this->getDriver()->executeScript(\sprintf(
            "return document.querySelectorAll('.tome-checkboxes input[type=\"checkbox\"]')[%d * 3 + 2].checked;",
            $index
        ));
    }
}

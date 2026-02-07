<?php

declare(strict_types=1);

namespace App\Tests\Panther;

use Facebook\WebDriver\WebDriverBy;
use Facebook\WebDriver\WebDriverExpectedCondition;
use PHPUnit\Framework\TestCase;

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
final class TomeManagementTest extends TestCase
{
    use PantherTestHelper;

    private const string TEST_TITLE = 'Série Test Tomes Panther';

    private static ?int $testSeriesId = null;

    /**
     * Crée une série de test via SQL (visible par le processus Selenium).
     */
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        // Nettoie d'éventuels résidus d'un run précédent
        self::runSql(\sprintf("DELETE FROM comic_series WHERE title = '%s'", self::TEST_TITLE));

        $now = (new \DateTimeImmutable())->format('Y-m-d H:i:s');

        self::runSql(\sprintf(
            "INSERT INTO comic_series (title, status, type, created_at, is_one_shot, latest_published_issue_complete, updated_at) VALUES ('%s', 'buying', 'manga', '%s', 0, 0, '%s')",
            self::TEST_TITLE,
            $now,
            $now,
        ));

        $output = self::runSql(\sprintf(
            "SELECT id FROM comic_series WHERE title = '%s' ORDER BY id DESC LIMIT 1",
            self::TEST_TITLE,
        ));

        if (\preg_match('/(\d+)/', $output, $matches)) {
            self::$testSeriesId = (int) $matches[1];
        }
    }

    /**
     * Supprime la série de test après tous les tests.
     */
    public static function tearDownAfterClass(): void
    {
        self::runSql(\sprintf("DELETE FROM comic_series WHERE title = '%s'", self::TEST_TITLE));

        parent::tearDownAfterClass();
    }

    protected function setUp(): void
    {
        if (null === self::$testSeriesId) {
            self::markTestSkipped('Aucune série de test disponible dans la base de données.');
        }

        $this->driver = $this->createDriver();
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
        $this->waitForTomeCount($initialCount + 1);

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
        $this->waitForTomeCount($initialCount + 1);

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
        $this->waitForTomeCount($initialCount + 1);
        $this->fillTomeNumber($initialCount, 96);

        // Ajoute le second tome
        $this->clickAddTomeButton();
        $this->waitForTomeCount($initialCount + 2);
        $this->fillTomeNumber($initialCount + 1, 97);

        // Vérifie que les deux tomes ont été ajoutés
        $finalCount = $this->getTomeCount();
        $this->assertSame($initialCount + 2, $finalCount, 'Deux tomes devraient avoir été ajoutés');
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
     * Attend que le nombre de tomes atteigne la valeur attendue.
     */
    private function waitForTomeCount(int $expected): void
    {
        $this->getDriver()->wait(5)->until(function () use ($expected) {
            return $this->getTomeCount() === $expected;
        });
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

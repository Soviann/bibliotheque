<?php

declare(strict_types=1);

namespace App\Tests\Panther;

use Facebook\WebDriver\WebDriverBy;
use Facebook\WebDriver\WebDriverExpectedCondition;
use PHPUnit\Framework\TestCase;

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
final class OneShotFormTest extends TestCase
{
    use PantherTestHelper;

    private const string TEST_TITLE = 'Série Test One-Shot Panther';

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
            "INSERT INTO comic_series (title, status, type, created_at, is_one_shot, latest_published_issue_complete, updated_at) VALUES ('%s', 'buying', 'bd', '%s', 0, 0, '%s')",
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

        // Attend que la section tomes soit masquée
        $this->getDriver()->wait(5)->until(function () {
            return !$this->isTomesSectionVisible();
        });

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

        // Attend que la section tomes soit masquée
        $this->getDriver()->wait(5)->until(function () {
            return !$this->isTomesSectionVisible();
        });

        // Vérifie que la section tomes est masquée
        $this->assertTomesSectionVisible(false, 'La section tomes ne devrait pas être visible après avoir coché one-shot');

        // Décoche la case one-shot
        $this->uncheckOneShot();

        // Attend que la section tomes soit visible
        $this->getDriver()->wait(5)->until(function () {
            return $this->isTomesSectionVisible();
        });

        // Vérifie que la section tomes est à nouveau visible
        $this->assertTomesSectionVisible(true, 'La section tomes devrait être visible après avoir décoché one-shot');
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
     * Vérifie si le bouton "Ajouter un tome" est visible.
     */
    private function isTomesSectionVisible(): bool
    {
        return (bool) $this->getDriver()->executeScript("
            const addButton = document.querySelector('[data-action=\"tomes-collection#addTome\"]');
            if (!addButton) return false;
            const style = window.getComputedStyle(addButton);
            return style.display !== 'none' && style.visibility !== 'hidden';
        ");
    }

    /**
     * Vérifie la visibilité du bouton "Ajouter un tome".
     *
     * Le comportement one-shot masque ce bouton, pas la section entière.
     */
    private function assertTomesSectionVisible(bool $expected, string $message): void
    {
        $isVisible = $this->isTomesSectionVisible();

        if ($expected) {
            $this->assertTrue($isVisible, $message);
        } else {
            $this->assertFalse($isVisible, $message);
        }
    }
}

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
     * Teste que le champ ISBN apparaît quand on coche one-shot et disparaît quand on décoche.
     */
    public function testOneShotIsbnFieldVisibility(): void
    {
        $this->login();
        $this->goToEditSeriesPage();

        // Le champ ISBN n'est pas visible initialement
        self::assertFalse($this->isOneShotIsbnVisible(), 'Le champ ISBN ne devrait pas être visible initialement');

        // Coche one-shot → ISBN visible
        $this->checkOneShot();
        $this->getDriver()->wait(5)->until(fn () => $this->isOneShotIsbnVisible());
        self::assertTrue($this->isOneShotIsbnVisible(), 'Le champ ISBN devrait être visible après avoir coché one-shot');

        // Décoche one-shot → ISBN masqué
        $this->uncheckOneShot();
        $this->getDriver()->wait(5)->until(fn () => !$this->isOneShotIsbnVisible());
        self::assertFalse($this->isOneShotIsbnVisible(), 'Le champ ISBN devrait être masqué après avoir décoché one-shot');
    }

    /**
     * Teste que le bouton de recherche ISBN apparaît à côté du champ ISBN one-shot.
     */
    public function testOneShotIsbnLookupButtonVisible(): void
    {
        $this->login();
        $this->goToEditSeriesPage();

        // Coche one-shot
        $this->checkOneShot();
        $this->getDriver()->wait(5)->until(fn () => $this->isOneShotIsbnVisible());

        // Vérifie que le bouton de recherche ISBN est visible
        $hasButton = (bool) $this->getDriver()->executeScript("
            const row = document.querySelector('[data-comic-form-target=\"oneShotIsbnRow\"]');
            if (!row) return false;
            const btn = row.querySelector('[data-action*=\"lookupOneShotIsbn\"]');
            return btn !== null;
        ");

        self::assertTrue($hasButton, 'Le bouton de recherche ISBN devrait être présent dans le champ ISBN one-shot');
    }

    /**
     * Teste que saisir un ISBN dans le champ virtuel le synchronise vers le tome #1.
     */
    public function testOneShotIsbnSyncsToTome(): void
    {
        $this->login();
        $this->goToEditSeriesPage();

        // Coche one-shot (crée le tome #1)
        $this->checkOneShot();
        $this->getDriver()->wait(5)->until(fn () => $this->isOneShotIsbnVisible());

        // Saisit un ISBN dans le champ virtuel
        $this->getDriver()->executeScript("
            const isbnField = document.querySelector('[data-comic-form-target=\"oneShotIsbn\"]');
            isbnField.value = '978-2-1234-5678-9';
            isbnField.dispatchEvent(new Event('input', { bubbles: true }));
        ");

        // Vérifie que l'ISBN est copié dans le tome #1
        $tomeIsbn = $this->getDriver()->executeScript("
            const tomeIsbn = document.querySelector('.tome-isbn-input');
            return tomeIsbn ? tomeIsbn.value : null;
        ");

        self::assertSame('978-2-1234-5678-9', $tomeIsbn, 'L\'ISBN devrait être synchronisé vers le tome #1');
    }

    /**
     * Teste que le champ ISBN est pré-rempli en édition d'un one-shot avec ISBN existant.
     */
    public function testOneShotIsbnPrefillOnEdit(): void
    {
        // Crée un one-shot avec un tome ayant un ISBN via SQL
        $title = 'Test ISBN Prefill Panther';
        $now = (new \DateTimeImmutable())->format('Y-m-d H:i:s');

        self::runSql(\sprintf("DELETE FROM tome WHERE comic_series_id IN (SELECT id FROM comic_series WHERE title = '%s')", $title));
        self::runSql(\sprintf("DELETE FROM comic_series WHERE title = '%s'", $title));
        self::runSql(\sprintf(
            "INSERT INTO comic_series (title, status, type, created_at, is_one_shot, latest_published_issue, latest_published_issue_complete, updated_at) VALUES ('%s', 'buying', 'bd', '%s', 1, 1, 1, '%s')",
            $title,
            $now,
            $now,
        ));

        $output = self::runSql(\sprintf("SELECT id FROM comic_series WHERE title = '%s' ORDER BY id DESC LIMIT 1", $title));
        \preg_match('/(\d+)/', $output, $matches);
        $seriesId = (int) $matches[1];

        self::runSql(\sprintf(
            "INSERT INTO tome (comic_series_id, number, bought, downloaded, on_nas, isbn, created_at, updated_at) VALUES (%d, 1, 1, 0, 0, '978-2-1234-0000-0', '%s', '%s')",
            $seriesId,
            $now,
            $now,
        ));

        try {
            $this->login();
            $this->getDriver()->get(self::BASE_URL.'/comic/'.$seriesId.'/edit');
            $this->getDriver()->wait(10)->until(
                WebDriverExpectedCondition::presenceOfElementLocated(WebDriverBy::cssSelector('.comic-form'))
            );

            // Le champ ISBN virtuel doit être pré-rempli
            $isbnValue = $this->getDriver()->executeScript("
                const isbnField = document.querySelector('[data-comic-form-target=\"oneShotIsbn\"]');
                return isbnField ? isbnField.value : null;
            ");

            self::assertSame('978-2-1234-0000-0', $isbnValue, 'Le champ ISBN devrait être pré-rempli depuis le tome #1');
        } finally {
            self::runSql(\sprintf("DELETE FROM tome WHERE comic_series_id IN (SELECT id FROM comic_series WHERE title = '%s')", $title));
            self::runSql(\sprintf("DELETE FROM comic_series WHERE title = '%s'", $title));
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
     * Vérifie si la section tomes est visible.
     */
    private function isTomesSectionVisible(): bool
    {
        return (bool) $this->getDriver()->executeScript("
            const section = document.querySelector('[data-comic-form-target=\"tomesSection\"]');
            if (!section) return false;
            const style = window.getComputedStyle(section);
            return style.display !== 'none' && style.visibility !== 'hidden';
        ");
    }

    /**
     * Vérifie la visibilité de la section tomes.
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

    /**
     * Vérifie si le champ ISBN one-shot est visible.
     */
    private function isOneShotIsbnVisible(): bool
    {
        return (bool) $this->getDriver()->executeScript("
            const row = document.querySelector('[data-comic-form-target=\"oneShotIsbnRow\"]');
            if (!row) return false;
            const style = window.getComputedStyle(row);
            return style.display !== 'none' && style.visibility !== 'hidden';
        ");
    }
}

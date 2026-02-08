<?php

declare(strict_types=1);

namespace App\Tests\Panther;

use Facebook\WebDriver\WebDriverBy;
use Facebook\WebDriver\WebDriverExpectedCondition;
use PHPUnit\Framework\TestCase;

/**
 * Tests des actions (supprimer, ajouter à la bibliothèque) depuis la liste.
 *
 * Le library_controller.js remplace le HTML Twig par du HTML JS-rendered
 * (card-renderer.js). Ces tests vérifient que les actions fonctionnent
 * dans ce contexte (tokens CSRF via API, forms avec data-turbo-frame="_top").
 *
 * Utilise Selenium distant (ddev chrome service).
 */
final class ListActionsTest extends TestCase
{
    use PantherTestHelper;

    private const string DELETE_TITLE = 'Série Test Suppression Liste';
    private const string TO_LIBRARY_TITLE = 'Série Test To-Library Liste';

    protected function setUp(): void
    {
        $this->driver = $this->createDriver();
    }

    protected function tearDown(): void
    {
        // Nettoyage des séries de test éventuellement restantes
        self::runSql(\sprintf("DELETE FROM comic_series WHERE title = '%s'", self::DELETE_TITLE));
        self::runSql(\sprintf("DELETE FROM comic_series WHERE title = '%s'", self::TO_LIBRARY_TITLE));

        $this->driver?->quit();
    }

    /**
     * Teste la suppression d'une série depuis la liste (HTML JS-rendered).
     */
    public function testDeleteFromList(): void
    {
        $now = (new \DateTimeImmutable())->format('Y-m-d H:i:s');

        self::runSql(\sprintf(
            "INSERT INTO comic_series (title, status, type, created_at, is_one_shot, latest_published_issue_complete, updated_at) VALUES ('%s', 'buying', 'bd', '%s', 0, 0, '%s')",
            self::DELETE_TITLE,
            $now,
            $now,
        ));

        $driver = $this->getDriver();
        $this->login();

        // Aller sur la page d'accueil (liste)
        $driver->get(self::BASE_URL.'/');

        // Attendre que library_controller.js remplace le HTML par les cartes JS
        // Les cartes JS contiennent des <form> avec deleteToken
        $driver->wait(10)->until(
            WebDriverExpectedCondition::presenceOfElementLocated(
                WebDriverBy::cssSelector('form[action*="/delete"] input[name="_token"]')
            )
        );

        // Trouver et soumettre le formulaire de suppression via executeScript
        // (atomique, évite StaleElementReferenceException lors du re-rendu API)
        $found = $driver->executeScript("
            const cards = document.querySelectorAll('.comic-card');
            for (const card of cards) {
                if (card.textContent.includes(arguments[0])) {
                    const form = card.querySelector('form[action*=\"/delete\"]');
                    if (form) {
                        form.submit();
                        return true;
                    }
                }
            }
            return false;
        ", [self::DELETE_TITLE]);

        self::assertTrue($found, 'La carte avec le formulaire de suppression devrait être présente');

        // Note: form.submit() contourne le onsubmit handler (pas de confirm()),
        // ce qui est souhaité en test automatisé.

        // Attendre la redirection et le flash message
        $driver->wait(10)->until(
            WebDriverExpectedCondition::presenceOfElementLocated(
                WebDriverBy::cssSelector('.alert-success')
            )
        );

        $flashText = $driver->findElement(WebDriverBy::cssSelector('.alert-success'))->getText();
        self::assertStringContainsString('corbeille', $flashText);

        // Vérifier en base que la série est soft-deleted (pas hard-deleted)
        $output = self::runSql(\sprintf(
            "SELECT COUNT(*) as cnt FROM comic_series WHERE title = '%s' AND deleted_at IS NOT NULL",
            self::DELETE_TITLE,
        ));
        self::assertStringContainsString('1', $output);
    }

    /**
     * Teste l'ajout à la bibliothèque depuis la wishlist (HTML JS-rendered).
     */
    public function testToLibraryFromWishlist(): void
    {
        $now = (new \DateTimeImmutable())->format('Y-m-d H:i:s');

        self::runSql(\sprintf(
            "INSERT INTO comic_series (title, status, type, created_at, is_one_shot, latest_published_issue_complete, updated_at) VALUES ('%s', 'wishlist', 'manga', '%s', 0, 0, '%s')",
            self::TO_LIBRARY_TITLE,
            $now,
            $now,
        ));

        $driver = $this->getDriver();
        $this->login();

        // Aller sur la page wishlist
        $driver->get(self::BASE_URL.'/wishlist');

        // Attendre que library_controller.js charge les cartes JS
        $driver->wait(10)->until(
            WebDriverExpectedCondition::presenceOfElementLocated(
                WebDriverBy::cssSelector('form[action*="/to-library"] input[name="_token"]')
            )
        );

        // Trouver et soumettre le formulaire to-library via executeScript
        // (atomique, évite StaleElementReferenceException lors du re-rendu API)
        $found = $driver->executeScript("
            const cards = document.querySelectorAll('.comic-card');
            for (const card of cards) {
                if (card.textContent.includes(arguments[0])) {
                    const form = card.querySelector('form[action*=\"/to-library\"]');
                    if (form) {
                        form.submit();
                        return true;
                    }
                }
            }
            return false;
        ", [self::TO_LIBRARY_TITLE]);

        self::assertTrue($found, 'La carte wishlist avec le formulaire to-library devrait être présente');

        // Attendre le flash message de succès
        $driver->wait(10)->until(
            WebDriverExpectedCondition::presenceOfElementLocated(
                WebDriverBy::cssSelector('.alert-success')
            )
        );

        $flashText = $driver->findElement(WebDriverBy::cssSelector('.alert-success'))->getText();
        self::assertStringContainsString('bibliothèque', $flashText);

        // Vérifier en base que le statut a changé (plus wishlist)
        $output = self::runSql(\sprintf(
            "SELECT status FROM comic_series WHERE title = '%s'",
            self::TO_LIBRARY_TITLE,
        ));
        self::assertStringContainsString('buying', $output);
    }
}

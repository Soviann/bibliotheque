<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Tests fonctionnels pour OfflineController.
 *
 * La page offline est publique (PWA fallback).
 */
class OfflineControllerTest extends WebTestCase
{
    /**
     * Teste que la page offline est accessible sans authentification.
     */
    public function testOfflinePageIsAccessibleWithoutAuthentication(): void
    {
        $client = static::createClient();

        $client->request('GET', '/offline');

        self::assertResponseIsSuccessful();
    }

    /**
     * Teste que la page offline contient le titre attendu.
     */
    public function testOfflinePageContainsExpectedTitle(): void
    {
        $client = static::createClient();

        $crawler = $client->request('GET', '/offline');

        self::assertSelectorTextContains('title', 'Hors ligne');
        self::assertSelectorTextContains('h1', 'Vous etes hors ligne');
    }

    /**
     * Teste que la page offline contient le message d'information.
     */
    public function testOfflinePageContainsInformationMessage(): void
    {
        $client = static::createClient();

        $crawler = $client->request('GET', '/offline');

        self::assertSelectorTextContains('p', 'Impossible de charger cette page');
        self::assertSelectorTextContains('p', 'connexion internet');
    }

    /**
     * Teste que la page offline contient le bouton retour.
     */
    public function testOfflinePageContainsBackButton(): void
    {
        $client = static::createClient();

        $crawler = $client->request('GET', '/offline');

        $backButton = $crawler->filter('.btn-secondary');
        self::assertCount(1, $backButton);
        self::assertSame('Retour', $backButton->text());
    }

    /**
     * Teste que la page offline contient le bouton réessayer.
     */
    public function testOfflinePageContainsRetryButton(): void
    {
        $client = static::createClient();

        $crawler = $client->request('GET', '/offline');

        $retryButton = $crawler->filter('#retry-btn');
        self::assertCount(1, $retryButton);
        self::assertSame('Reessayer', $retryButton->text());
    }

    /**
     * Teste que la page offline contient l'icône SVG.
     */
    public function testOfflinePageContainsOfflineIcon(): void
    {
        $client = static::createClient();

        $crawler = $client->request('GET', '/offline');

        $icon = $crawler->filter('.offline-icon');
        self::assertCount(1, $icon);
    }

    /**
     * Teste que la page offline est accessible même authentifié.
     */
    public function testOfflinePageIsAccessibleWhenAuthenticated(): void
    {
        $client = static::createClient();

        // Créer et connecter un utilisateur
        $container = static::getContainer();
        $em = $container->get('doctrine.orm.entity_manager');

        $user = $em->getRepository(\App\Entity\User::class)->findOneBy(['email' => 'test@bibliotheque.local']);
        if (!$user) {
            $user = new \App\Entity\User();
            $user->setEmail('test@bibliotheque.local');
            $user->setPassword('$2y$04$test');
            $user->setRoles(['ROLE_USER']);
            $em->persist($user);
            $em->flush();
        }

        $client->loginUser($user);

        $client->request('GET', '/offline');

        self::assertResponseIsSuccessful();
    }

    /**
     * Teste que la page offline a le bon theme-color meta.
     */
    public function testOfflinePageHasCorrectThemeColor(): void
    {
        $client = static::createClient();

        $crawler = $client->request('GET', '/offline');

        $themeColorMeta = $crawler->filter('meta[name="theme-color"]');
        self::assertCount(1, $themeColorMeta);
        self::assertSame('#1976d2', $themeColorMeta->attr('content'));
    }

    /**
     * Teste que la page offline a le viewport configuré pour mobile.
     */
    public function testOfflinePageHasMobileViewport(): void
    {
        $client = static::createClient();

        $crawler = $client->request('GET', '/offline');

        $viewportMeta = $crawler->filter('meta[name="viewport"]');
        self::assertCount(1, $viewportMeta);
        $content = $viewportMeta->attr('content');
        self::assertNotNull($content);
        self::assertStringContainsString('width=device-width', $content);
    }

    /**
     * Teste que le script JavaScript de retry est présent.
     */
    public function testOfflinePageContainsRetryScript(): void
    {
        $client = static::createClient();

        $crawler = $client->request('GET', '/offline');

        $scripts = $crawler->filter('script');
        $scriptFound = false;

        foreach ($scripts as $script) {
            $content = $script->textContent;
            if (\str_contains($content, 'retry-btn') && \str_contains($content, 'navigator.onLine')) {
                $scriptFound = true;
                break;
            }
        }

        self::assertTrue($scriptFound, 'Le script de retry devrait être présent');
    }
}

<?php

declare(strict_types=1);

namespace App\Tests\Behat\Context;

use Behat\Behat\Context\Context;
use Behat\Mink\Element\NodeElement;
use Behat\Mink\Exception\ExpectationException;
use Behat\MinkExtension\Context\RawMinkContext;

/**
 * Contexte pour les tests de navigation, filtres et recherche.
 */
final class NavigationContext extends RawMinkContext implements Context
{
    /**
     * Accède à la page d'accueil.
     *
     * @Given je suis sur la page d'accueil
     *
     * @When je vais sur la page d'accueil
     */
    public function jeSuisSurLaPageDAccueil(): void
    {
        $this->visitPath('/');
    }

    /**
     * Accède à la page wishlist.
     *
     * @Given je suis sur la page wishlist
     *
     * @When je vais sur la page wishlist
     */
    public function jeSuisSurLaPageWishlist(): void
    {
        $this->visitPath('/wishlist');
    }

    /**
     * Vérifie qu'on est sur la page wishlist.
     *
     * @Then je devrais être sur la page wishlist
     */
    public function jeDevraisEtreSurLaPageWishlist(): void
    {
        $this->assertSession()->addressMatches('/\/wishlist/');
    }

    /**
     * Filtre par type.
     *
     * @When je filtre par type :type
     */
    public function jeFiltreParType(string $type): void
    {
        $typeValue = match ($type) {
            'BD' => 'bd',
            'Manga' => 'manga',
            'Comics' => 'comics',
            'Livre' => 'livre',
            default => throw new \InvalidArgumentException(\sprintf('Type inconnu: %s', $type)),
        };

        $currentUrl = $this->getSession()->getCurrentUrl();
        $separator = \str_contains($currentUrl, '?') ? '&' : '?';
        $this->visitPath($this->extractPath($currentUrl).$separator.'type='.$typeValue);
    }

    /**
     * Filtre par statut.
     *
     * @When je filtre par statut :status
     */
    public function jeFiltreParStatut(string $status): void
    {
        $statusValue = match ($status) {
            'En cours d\'achat' => 'buying',
            'Terminée' => 'finished',
            'Arrêtée' => 'stopped',
            default => throw new \InvalidArgumentException(\sprintf('Statut inconnu: %s', $status)),
        };

        $currentUrl = $this->getSession()->getCurrentUrl();
        $separator = \str_contains($currentUrl, '?') ? '&' : '?';
        $this->visitPath($this->extractPath($currentUrl).$separator.'status='.$statusValue);
    }

    /**
     * Filtre par présence sur le NAS.
     *
     * @When je filtre les séries sur le NAS
     */
    public function jeFiltreLeSeriesSurLeNas(): void
    {
        $currentUrl = $this->getSession()->getCurrentUrl();
        $separator = \str_contains($currentUrl, '?') ? '&' : '?';
        $this->visitPath($this->extractPath($currentUrl).$separator.'nas=1');
    }

    /**
     * Filtre par absence sur le NAS.
     *
     * @When je filtre les séries non présentes sur le NAS
     */
    public function jeFiltreLeSeriesNonPresentesSurLeNas(): void
    {
        $currentUrl = $this->getSession()->getCurrentUrl();
        $separator = \str_contains($currentUrl, '?') ? '&' : '?';
        $this->visitPath($this->extractPath($currentUrl).$separator.'nas=0');
    }

    /**
     * Recherche par titre.
     *
     * @When je recherche :query
     */
    public function jeRecherche(string $query): void
    {
        $page = $this->getSession()->getPage();

        // Cherche un champ de recherche
        $searchField = $page->find('css', 'input[name="q"]') ??
                       $page->find('css', 'input[type="search"]') ??
                       $page->find('css', '.search-input');

        if (null !== $searchField) {
            $searchField->setValue($query);

            // Soumet le formulaire ou déclenche la recherche
            /** @var NodeElement|null $form */
            $form = $searchField->getParent();
            while (null !== $form && 'form' !== $form->getTagName()) {
                /** @var NodeElement|null $form */
                $form = $form->getParent();
            }

            if (null !== $form && 'form' === $form->getTagName()) {
                $form->submit();

                return;
            }
        }

        // Fallback: ajoute le paramètre de recherche à l'URL
        $currentUrl = $this->getSession()->getCurrentUrl();
        $separator = \str_contains($currentUrl, '?') ? '&' : '?';
        $this->visitPath($this->extractPath($currentUrl).$separator.'q='.\urlencode($query));
    }

    /**
     * Vérifie que le titre de la série est visible.
     *
     * @Then je devrais voir la série :title
     */
    public function jeDevraisVoirLaSerie(string $title): void
    {
        $this->assertSession()->pageTextContains($title);
    }

    /**
     * Vérifie que le titre de la série n'est pas visible.
     *
     * @Then je ne devrais pas voir la série :title
     */
    public function jeNeDevraisPasVoirLaSerie(string $title): void
    {
        $this->assertSession()->pageTextNotContains($title);
    }

    /**
     * Vérifie le message flash de succès.
     *
     * @Then je devrais voir le message de succès :message
     */
    public function jeDevraisVoirLeMessageDeSucces(string $message): void
    {
        $page = $this->getSession()->getPage();
        $flashElement = $page->find('css', '.flash-success, .alert-success, [data-flash="success"]');

        if (null === $flashElement) {
            // Vérifie dans le texte de la page
            $this->assertSession()->pageTextContains($message);

            return;
        }

        if (!\str_contains($flashElement->getText(), $message)) {
            throw new ExpectationException(\sprintf('Le message de succès "%s" n\'a pas été trouvé.', $message), $this->getSession()->getDriver());
        }
    }

    /**
     * Vérifie un message affiché sur la page.
     *
     * @Then je devrais voir :text
     */
    public function jeDevraisVoir(string $text): void
    {
        $this->assertSession()->pageTextContains($text);
    }

    /**
     * Vérifie qu'un texte n'est pas affiché sur la page.
     *
     * @Then je ne devrais pas voir :text
     */
    public function jeNeDevraisPasVoir(string $text): void
    {
        $this->assertSession()->pageTextNotContains($text);
    }

    /**
     * Clique sur un lien.
     *
     * @When je clique sur le lien :link
     */
    public function jeCliqueSurLeLien(string $link): void
    {
        $this->getSession()->getPage()->clickLink($link);
    }

    /**
     * Accède à la page de recherche.
     *
     * @Given je suis sur la page de recherche
     *
     * @When je vais sur la page de recherche
     */
    public function jeSuisSurLaPageDeRecherche(): void
    {
        $this->visitPath('/search');
    }

    /**
     * Extrait le chemin d'une URL complète.
     */
    private function extractPath(string $url): string
    {
        $parsed = \parse_url($url);

        return $parsed['path'] ?? '/';
    }
}

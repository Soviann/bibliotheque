<?php

declare(strict_types=1);

namespace App\Tests\Behat\Context;

use Behat\Behat\Context\Context;
use Behat\Mink\Exception\ElementNotFoundException;
use Behat\Mink\Exception\ExpectationException;
use Behat\MinkExtension\Context\RawMinkContext;

/**
 * Contexte de base avec des méthodes utilitaires communes.
 */
final class FeatureContext extends RawMinkContext implements Context
{
    /**
     * Attend qu'un élément soit visible sur la page.
     *
     * @When j'attends que l'élément :selector soit visible
     */
    public function jAttendsQueLElementSoitVisible(string $selector): void
    {
        $this->spin(function () use ($selector): bool {
            $element = $this->getSession()->getPage()->find('css', $selector);

            return null !== $element && $element->isVisible();
        });
    }

    /**
     * Attend qu'un élément ne soit plus visible sur la page.
     *
     * @When j'attends que l'élément :selector ne soit plus visible
     */
    public function jAttendsQueLElementNeSoitPlusVisible(string $selector): void
    {
        $this->spin(function () use ($selector): bool {
            $element = $this->getSession()->getPage()->find('css', $selector);

            return null === $element || !$element->isVisible();
        });
    }

    /**
     * Attend que le texte soit présent sur la page.
     *
     * @When j'attends de voir :text
     */
    public function jAttendsDeVoir(string $text): void
    {
        $this->spin(function () use ($text): bool {
            return \str_contains($this->getSession()->getPage()->getText(), $text);
        });
    }

    /**
     * Vérifie le nombre d'éléments correspondant à un sélecteur CSS.
     *
     * @Then je devrais voir :count élément(s) :selector
     */
    public function jeDevraisVoirElements(int $count, string $selector): void
    {
        $elements = $this->getSession()->getPage()->findAll('css', $selector);
        $actual = \count($elements);

        if ($actual !== $count) {
            throw new \RuntimeException(\sprintf('Attendu %d élément(s) "%s", mais %d trouvé(s).', $count, $selector, $actual));
        }
    }

    /**
     * Vérifie qu'un élément CSS est visible.
     *
     * @Then l'élément :selector devrait être visible
     */
    public function lElementDevraitEtreVisible(string $selector): void
    {
        $element = $this->getSession()->getPage()->find('css', $selector);

        if (null === $element) {
            throw new ElementNotFoundException($this->getSession()->getDriver(), 'element', 'css', $selector);
        }

        if (!$element->isVisible()) {
            throw new \RuntimeException(\sprintf('L\'élément "%s" existe mais n\'est pas visible.', $selector));
        }
    }

    /**
     * Vérifie qu'un élément CSS n'est pas visible.
     *
     * @Then l'élément :selector ne devrait pas être visible
     */
    public function lElementNeDevraitPasEtreVisible(string $selector): void
    {
        $element = $this->getSession()->getPage()->find('css', $selector);

        if (null !== $element && $element->isVisible()) {
            throw new \RuntimeException(\sprintf('L\'élément "%s" ne devrait pas être visible.', $selector));
        }
    }

    /**
     * Clique sur un élément CSS.
     *
     * @When je clique sur l'élément :selector
     */
    public function jeCliqueSurLElement(string $selector): void
    {
        $element = $this->getSession()->getPage()->find('css', $selector);

        if (null === $element) {
            throw new ElementNotFoundException($this->getSession()->getDriver(), 'element', 'css', $selector);
        }

        $element->click();
    }

    /**
     * Attend brièvement (pour les animations/transitions).
     *
     * @When j'attends :seconds seconde(s)
     */
    public function jAttends(float $seconds): void
    {
        \usleep((int) ($seconds * 1000000));
    }

    /**
     * Vérifie qu'on est sur la page d'accueil.
     *
     * @Then je devrais être sur la page d'accueil
     */
    public function jeDevraisEtreSurLaPageDAccueil(): void
    {
        $currentUrl = $this->getSession()->getCurrentUrl();
        $parsedUrl = \parse_url($currentUrl);
        $path = $parsedUrl['path'] ?? '/';

        if ('/' !== $path) {
            throw new ExpectationException(\sprintf('Attendu la page d\'accueil ("/"), mais sur: %s', $path), $this->getSession()->getDriver());
        }
    }

    /**
     * Méthode spin pour les attentes avec timeout.
     */
    private function spin(callable $callback, int $timeout = 10): void
    {
        $start = \time();

        while (\time() - $start < $timeout) {
            try {
                if ($callback()) {
                    return;
                }
            } catch (\Exception) {
                // On continue à essayer
            }

            \usleep(250000); // 250ms
        }

        throw new \RuntimeException(\sprintf('Timeout après %d secondes.', $timeout));
    }
}

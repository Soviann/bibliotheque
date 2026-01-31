<?php

declare(strict_types=1);

namespace App\Tests\Behat\Context;

use Behat\Behat\Context\Context;
use Behat\Mink\Exception\ElementNotFoundException;
use Behat\MinkExtension\Context\RawMinkContext;

/**
 * Contexte pour les interactions JavaScript avec les formulaires.
 */
final class FormContext extends RawMinkContext implements Context
{
    /**
     * Clique sur le bouton d'ajout de tome.
     *
     * @When je clique sur ajouter un tome
     */
    public function jeCliqueSurAjouterUnTome(): void
    {
        $page = $this->getSession()->getPage();
        $addButton = $page->find('css', '[data-action*="add"]') ??
                     $page->find('css', '.add-tome') ??
                     $page->findButton('Ajouter un tome');

        if (null === $addButton) {
            throw new ElementNotFoundException($this->getSession()->getDriver(), 'add tome button');
        }

        $addButton->click();
        $this->waitForAjax();
    }

    /**
     * Remplit les informations d'un tome.
     *
     * @When je remplis le tome :index avec le numéro :number
     */
    public function jeRemplisLeTomeAvecLeNumero(int $index, int $number): void
    {
        $page = $this->getSession()->getPage();
        $numberField = $page->find('css', \sprintf('[name*="tomes][%d][number]"]', $index - 1)) ??
                       $page->find('css', \sprintf('#comic_series_tomes_%d_number', $index - 1));

        if (null === $numberField) {
            throw new ElementNotFoundException($this->getSession()->getDriver(), 'tome number field');
        }

        $numberField->setValue((string) $number);
    }

    /**
     * Remplit le titre d'un tome.
     *
     * @When je remplis le titre du tome :index avec :title
     */
    public function jeRemplisLeTitreDuTomeAvec(int $index, string $title): void
    {
        $page = $this->getSession()->getPage();
        $titleField = $page->find('css', \sprintf('[name*="tomes][%d][title]"]', $index - 1)) ??
                      $page->find('css', \sprintf('#comic_series_tomes_%d_title', $index - 1));

        if (null === $titleField) {
            throw new ElementNotFoundException($this->getSession()->getDriver(), 'tome title field');
        }

        $titleField->setValue($title);
    }

    /**
     * Remplit l'ISBN d'un tome.
     *
     * @When je remplis l'ISBN du tome :index avec :isbn
     */
    public function jeRemplisLIsbnDuTomeAvec(int $index, string $isbn): void
    {
        $page = $this->getSession()->getPage();
        $isbnField = $page->find('css', \sprintf('[name*="tomes][%d][isbn]"]', $index - 1)) ??
                     $page->find('css', \sprintf('#comic_series_tomes_%d_isbn', $index - 1));

        if (null === $isbnField) {
            throw new ElementNotFoundException($this->getSession()->getDriver(), 'tome ISBN field');
        }

        $isbnField->setValue($isbn);
    }

    /**
     * Coche la case "acheté" d'un tome.
     *
     * @When je coche le tome :index comme acheté
     */
    public function jeCocheLeTomeCommeAchete(int $index): void
    {
        $page = $this->getSession()->getPage();
        $boughtField = $page->find('css', \sprintf('[name*="tomes][%d][bought]"]', $index - 1)) ??
                       $page->find('css', \sprintf('#comic_series_tomes_%d_bought', $index - 1));

        if (null === $boughtField) {
            throw new ElementNotFoundException($this->getSession()->getDriver(), 'tome bought field');
        }

        $boughtField->check();
    }

    /**
     * Coche la case "téléchargé" d'un tome.
     *
     * @When je coche le tome :index comme téléchargé
     */
    public function jeCocheLeTomeCommeTelecharge(int $index): void
    {
        $page = $this->getSession()->getPage();
        $downloadedField = $page->find('css', \sprintf('[name*="tomes][%d][downloaded]"]', $index - 1)) ??
                           $page->find('css', \sprintf('#comic_series_tomes_%d_downloaded', $index - 1));

        if (null === $downloadedField) {
            throw new ElementNotFoundException($this->getSession()->getDriver(), 'tome downloaded field');
        }

        $downloadedField->check();
    }

    /**
     * Coche la case "sur NAS" d'un tome.
     *
     * @When je coche le tome :index comme sur le NAS
     */
    public function jeCocheLeTomeCommeSurLeNas(int $index): void
    {
        $page = $this->getSession()->getPage();
        $onNasField = $page->find('css', \sprintf('[name*="tomes][%d][onNas]"]', $index - 1)) ??
                      $page->find('css', \sprintf('#comic_series_tomes_%d_onNas', $index - 1));

        if (null === $onNasField) {
            throw new ElementNotFoundException($this->getSession()->getDriver(), 'tome onNas field');
        }

        $onNasField->check();
    }

    /**
     * Vérifie que la section tomes est visible.
     *
     * @Then la section tomes devrait être visible
     */
    public function laSectionTomesDevraitEtreVisible(): void
    {
        $page = $this->getSession()->getPage();
        $tomesSection = $page->find('css', '[data-tomes-target], .tomes-section, #tomes-container');

        if (null === $tomesSection || !$tomesSection->isVisible()) {
            throw new \RuntimeException('La section tomes n\'est pas visible.');
        }
    }

    /**
     * Vérifie que la section tomes n'est pas visible.
     *
     * @Then la section tomes ne devrait pas être visible
     */
    public function laSectionTomesNeDevraitPasEtreVisible(): void
    {
        $page = $this->getSession()->getPage();
        $tomesSection = $page->find('css', '[data-tomes-target], .tomes-section, #tomes-container');

        if (null !== $tomesSection && $tomesSection->isVisible()) {
            throw new \RuntimeException('La section tomes est visible alors qu\'elle ne devrait pas l\'être.');
        }
    }

    /**
     * Vérifie le nombre de tomes affichés dans le formulaire.
     *
     * @Then je devrais voir :count tome(s) dans le formulaire
     */
    public function jeDevraisVoirTomesDansLeFormulaire(int $count): void
    {
        $page = $this->getSession()->getPage();
        $tomes = $page->findAll('css', '[data-tome-item], .tome-item, [id^="comic_series_tomes_"]');

        // Compte les items uniques (pas les champs individuels)
        $uniqueIndices = [];
        foreach ($tomes as $tome) {
            $id = $tome->getAttribute('id') ?? '';
            if (\preg_match('/tomes_(\d+)/', $id, $matches)) {
                $uniqueIndices[$matches[1]] = true;
            }
        }

        $actualCount = \count($uniqueIndices) ?: \count($tomes);

        if ($actualCount !== $count) {
            throw new \RuntimeException(\sprintf('Attendu %d tome(s), mais %d trouvé(s).', $count, $actualCount));
        }
    }

    /**
     * Attend que les requêtes AJAX soient terminées.
     */
    private function waitForAjax(int $timeout = 10): void
    {
        $start = \time();

        while (\time() - $start < $timeout) {
            try {
                $ajaxComplete = $this->getSession()->evaluateScript(
                    'return (typeof jQuery === "undefined" || jQuery.active === 0)'
                );

                if ($ajaxComplete) {
                    return;
                }
            } catch (\Exception) {
                // Ignore les erreurs (driver BrowserKit ne supporte pas evaluateScript)
                break;
            }

            \usleep(250000); // 250ms
        }

        // Fallback: simple attente
        \usleep(500000); // 500ms
    }
}

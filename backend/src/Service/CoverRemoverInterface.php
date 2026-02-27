<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\ComicSeries;

/**
 * Interface pour la suppression des couvertures.
 * Permet de découpler le mapper de VichUploader pour les tests.
 */
interface CoverRemoverInterface
{
    /**
     * Supprime la couverture d'une série.
     */
    public function remove(ComicSeries $entity): void;
}

<?php

declare(strict_types=1);

namespace App\DeployTask;

use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Interface pour les tâches de déploiement one-shot.
 *
 * Chaque tâche s'exécute une seule fois puis est marquée comme exécutée
 * dans var/deploy-tasks-executed.json.
 */
interface DeployTaskInterface
{
    /**
     * Description affichée dans le récapitulatif du runner.
     */
    public function getDescription(): string;

    /**
     * Exécute la tâche. Throw \RuntimeException en cas d'échec.
     */
    public function execute(SymfonyStyle $io): void;
}

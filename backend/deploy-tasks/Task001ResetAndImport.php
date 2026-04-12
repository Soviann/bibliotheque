<?php

declare(strict_types=1);

namespace DeployTask;

use App\DeployTask\AbstractDeployTask;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Réinitialise la BDD et lance un import complet.
 */
final class Task001ResetAndImport extends AbstractDeployTask
{
    public function getDescription(): string
    {
        return 'Réinitialisation de la base de données et import depuis var/import.xlsx';
    }

    public function execute(SymfonyStyle $io): void
    {
        $importFile = $this->projectDir.'/var/import.xlsx';

        if (!\file_exists($importFile)) {
            throw new \RuntimeException(\sprintf('Fichier %s introuvable. Copier le fichier avant de déployer.', $importFile));
        }

        $this->runMake('db-reset', $io);
        $this->runConsole('app:import', [$importFile, '--env=prod'], $io);

        $io->success('BDD réinitialisée et import terminé.');
    }
}

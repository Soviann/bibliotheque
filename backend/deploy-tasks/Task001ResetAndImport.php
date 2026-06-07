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

        // Réinitialise la base : suppression, recréation puis migrations
        // (équivalent de `make db-reset`, mais sans Makefile en production).
        $this->runConsole('doctrine:database:drop', ['--force', '--if-exists', '--env=prod'], $io);
        $this->runConsole('doctrine:database:create', ['--env=prod'], $io);
        $this->runConsole('doctrine:migrations:migrate', ['-n', '--env=prod'], $io);

        $this->runConsole('app:import', [$importFile, '--env=prod'], $io);

        $io->success('BDD réinitialisée et import terminé.');
    }
}

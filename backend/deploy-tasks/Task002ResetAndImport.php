<?php

declare(strict_types=1);

namespace DeployTask;

use App\DeployTask\AbstractDeployTask;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Réinitialise la BDD, purge les médias et réimporte depuis var/import.xlsx.
 */
class Task002ResetAndImport extends AbstractDeployTask
{
    public function getDescription(): string
    {
        return 'Réinitialisation de la base de données, purge des médias, import depuis var/import.xlsx et mise en file de l\'enrichissement';
    }

    public function execute(SymfonyStyle $io): void
    {
        $importFile = $this->projectDir.'/var/import.xlsx';

        if (!\file_exists($importFile)) {
            throw new \RuntimeException(\sprintf('Fichier %s introuvable. Copier le fichier avant de déployer.', $importFile));
        }

        // Réinitialise la base : suppression, recréation puis migrations
        $this->runConsole('doctrine:database:drop', ['--force', '--if-exists', '--env=prod'], $io);
        $this->runConsole('doctrine:database:create', ['--env=prod'], $io);
        $this->runConsole('doctrine:migrations:migrate', ['-n', '--env=prod'], $io);

        // Purge des couvertures existantes et du cache des miniatures
        $this->cleanMedia($io);

        // Import unifié du catalogue
        $this->runConsole('app:import', [$importFile, '--env=prod'], $io);

        // Mise en file de l'enrichissement pour traitement asynchrone par le worker Messenger
        $this->runConsole('app:auto-enrich', ['--queue', '--env=prod'], $io);

        $io->success('BDD réinitialisée, médias purgés, import terminé et enrichissement mis en file.');
    }

    private function cleanMedia(SymfonyStyle $io): void
    {
        $coversDir = $this->projectDir.'/public/uploads/covers';
        if (\is_dir($coversDir)) {
            $files = \glob($coversDir.'/*') ?: [];
            $deleted = 0;
            foreach ($files as $file) {
                if (\is_file($file)) {
                    \unlink($file);
                    ++$deleted;
                }
            }
            $io->text(\sprintf('Couvertures purgées (%d fichier(s) supprimé(s)).', $deleted));
        }

        $mediaCacheDir = $this->projectDir.'/public/media/cache';
        if (\is_dir($mediaCacheDir)) {
            $this->runProcess(['rm', '-rf', 'public/media/cache'], $io);
            $io->text('Cache des miniatures purgé.');
        }
    }
}

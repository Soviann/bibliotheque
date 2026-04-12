<?php

declare(strict_types=1);

namespace App\Command;

use App\DeployTask\DeployTaskInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

#[AsCommand(name: 'app:deploy:run-tasks', description: 'Exécute les tâches de déploiement en attente')]
final class RunDeployTasksCommand extends Command
{
    public function __construct(
        #[Autowire('%kernel.project_dir%')] private readonly string $projectDir,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $trackingFile = $this->projectDir.'/var/deploy-tasks-executed.json';
        $executed = [];

        if (\file_exists($trackingFile)) {
            $decoded = \json_decode((string) \file_get_contents($trackingFile), true);
            $executed = \is_array($decoded) ? $decoded : [];
        }

        $files = \glob($this->projectDir.'/deploy-tasks/Task*.php') ?: [];
        \sort($files);

        $tasks = [];
        foreach ($files as $file) {
            require_once $file;
            $className = 'DeployTask\\'.\basename($file, '.php');

            if (!\class_exists($className)) {
                $io->warning(\sprintf('Classe %s introuvable dans %s, ignorée.', $className, $file));
                continue;
            }

            $task = new $className($this->projectDir);

            if (!$task instanceof DeployTaskInterface) {
                $io->warning(\sprintf('Classe %s n\'implémente pas DeployTaskInterface, ignorée.', $className));
                continue;
            }

            $tasks[] = ['shortName' => \basename($file, '.php'), 'task' => $task];
        }

        $rows = [];
        foreach ($tasks as ['shortName' => $shortName, 'task' => $task]) {
            $rows[] = [
                $shortName,
                $task->getDescription(),
                \in_array($shortName, $executed, true) ? '✓ exécutée' : '→ en attente',
            ];
        }

        $io->table(['Tâche', 'Description', 'Statut'], $rows);

        $pending = \array_filter($tasks, static fn (array $t) => !\in_array($t['shortName'], $executed, true));

        if ([] === $pending) {
            $io->success('Aucune tâche de déploiement en attente.');

            return Command::SUCCESS;
        }

        foreach ($pending as ['shortName' => $shortName, 'task' => $task]) {
            $io->section($task->getDescription());

            try {
                $task->execute($io);
            } catch (\Throwable $e) {
                $io->error(\sprintf('Échec de %s : %s', $shortName, $e->getMessage()));

                return Command::FAILURE;
            }

            $executed[] = $shortName;
            $tmpFile = $trackingFile.'.tmp';
            \file_put_contents($tmpFile, \json_encode($executed, \JSON_PRETTY_PRINT | \JSON_THROW_ON_ERROR));
            \rename($tmpFile, $trackingFile);
        }

        $io->success(\sprintf('%d tâche(s) exécutée(s).', \count($pending)));

        return Command::SUCCESS;
    }
}

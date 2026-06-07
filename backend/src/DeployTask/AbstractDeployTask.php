<?php

declare(strict_types=1);

namespace App\DeployTask;

use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Process\Process;

/**
 * Classe de base pour les tâches de déploiement one-shot.
 */
abstract class AbstractDeployTask implements DeployTaskInterface
{
    private const array ALLOWED_CONSOLE_PREFIXES = [
        'app:', 'cache:', 'doctrine:', 'lexik:', 'messenger:',
    ];

    public function __construct(
        protected readonly string $projectDir,
    ) {
    }

    /**
     * @param array<string> $arguments
     */
    protected function runConsole(string $command, array $arguments, SymfonyStyle $io): void
    {
        $allowed = false;
        foreach (self::ALLOWED_CONSOLE_PREFIXES as $prefix) {
            if (\str_starts_with($command, $prefix)) {
                $allowed = true;
                break;
            }
        }

        if (!$allowed) {
            throw new \InvalidArgumentException(\sprintf('Commande console non autorisée : %s', $command));
        }

        $this->doRun(\array_merge(['php', 'bin/console', $command], $arguments), $this->projectDir, $io);
    }

    /**
     * Exécute une commande système arbitraire dans le conteneur (cwd = projectDir).
     *
     * Réservé au code first-party des tâches de déploiement : la commande
     * n'est pas filtrée, contrairement à runConsole().
     *
     * @param list<string> $command ex. ['tar', '-xzf', $archive, '-C', $dest]
     */
    protected function runProcess(array $command, SymfonyStyle $io): void
    {
        $this->doRun($command, $this->projectDir, $io);
    }

    /**
     * @param array<string> $command
     */
    private function doRun(array $command, string $cwd, SymfonyStyle $io): void
    {
        $process = new Process($command, $cwd);
        $process->setTimeout(null);
        $process->run(static function (string $_type, string $buffer) use ($io): void {
            $io->write($buffer);
        });

        if (!$process->isSuccessful()) {
            throw new \RuntimeException(\sprintf('Commande échouée (code %d): %s', $process->getExitCode(), $process->getErrorOutput()));
        }
    }
}

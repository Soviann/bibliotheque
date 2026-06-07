<?php

declare(strict_types=1);

namespace App\Tests\Unit\DeployTask;

use App\DeployTask\AbstractDeployTask;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Tests unitaires pour AbstractDeployTask.
 */
final class AbstractDeployTaskTest extends TestCase
{
    public function testRunProcessSucceedsOnZeroExit(): void
    {
        $task = $this->createTask();

        $task->callProcess(['true'], $this->createIo());

        $this->addToAssertionCount(1); // aucune exception levée
    }

    public function testRunProcessThrowsOnNonZeroExit(): void
    {
        $task = $this->createTask();

        $this->expectException(\RuntimeException::class);

        $task->callProcess(['false'], $this->createIo());
    }

    public function testRunConsoleRejectsNonWhitelistedCommand(): void
    {
        $task = $this->createTask();

        $this->expectException(\InvalidArgumentException::class);

        $task->callConsole('server:run', [], $this->createIo());
    }

    /**
     * Sous-classe de test exposant les helpers protégés.
     */
    private function createTask(): AbstractDeployTask&TestDeployTaskProxy
    {
        return new class(\sys_get_temp_dir()) extends AbstractDeployTask implements TestDeployTaskProxy {
            public function getDescription(): string
            {
                return 'Tâche de test';
            }

            public function execute(SymfonyStyle $io): void
            {
            }

            public function callProcess(array $command, SymfonyStyle $io): void
            {
                $this->runProcess($command, $io);
            }

            public function callConsole(string $command, array $arguments, SymfonyStyle $io): void
            {
                $this->runConsole($command, $arguments, $io);
            }
        };
    }

    private function createIo(): SymfonyStyle
    {
        return new SymfonyStyle(new ArrayInput([]), new NullOutput());
    }
}

/**
 * Contrat exposant les helpers protégés d'AbstractDeployTask pour les tests.
 */
interface TestDeployTaskProxy
{
    /**
     * @param list<string> $command
     */
    public function callProcess(array $command, SymfonyStyle $io): void;

    /**
     * @param array<string> $arguments
     */
    public function callConsole(string $command, array $arguments, SymfonyStyle $io): void;
}

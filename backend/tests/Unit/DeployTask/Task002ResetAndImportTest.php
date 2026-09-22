<?php

declare(strict_types=1);

namespace App\Tests\Unit\DeployTask;

require_once __DIR__.'/../../../deploy-tasks/Task002ResetAndImport.php';

use DeployTask\Task002ResetAndImport;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Tests unitaires pour Task002ResetAndImport.
 */
final class Task002ResetAndImportTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        $this->tempDir = \sys_get_temp_dir().'/test_task002_'.\bin2hex(\random_bytes(6));
        \mkdir($this->tempDir.'/var', 0777, true);
        \mkdir($this->tempDir.'/public/uploads/covers', 0777, true);
        \mkdir($this->tempDir.'/public/media/cache', 0777, true);
    }

    protected function tearDown(): void
    {
        $this->removeDirRecursive($this->tempDir);
    }

    public function testExecuteThrowsWhenImportFileMissing(): void
    {
        $task = new Task002ResetAndImport($this->tempDir);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Fichier '.$this->tempDir.'/var/import.xlsx introuvable');

        $task->execute($this->createIo());
    }

    public function testExecuteRunsExpectedCommandsAndCleansMedia(): void
    {
        // Créer le fichier d'import et des fichiers média factices
        \file_put_contents($this->tempDir.'/var/import.xlsx', 'dummy content');
        \file_put_contents($this->tempDir.'/public/uploads/covers/cover1.webp', 'image 1');
        \file_put_contents($this->tempDir.'/public/uploads/covers/cover2.webp', 'image 2');

        /** @var list<array{command: string, arguments: array<string>}> $consoleCalls */
        $consoleCalls = [];
        /** @var list<list<string>> $processCalls */
        $processCalls = [];

        $task = new class($this->tempDir, $consoleCalls, $processCalls) extends Task002ResetAndImport {
            /**
             * @param list<array{command: string, arguments: array<string>}> $consoleCalls
             * @param list<list<string>>                                     $processCalls
             */
            public function __construct(
                string $projectDir,
                public array &$consoleCalls,
                public array &$processCalls,
            ) {
                parent::__construct($projectDir);
            }

            /**
             * @param array<string> $arguments
             */
            protected function runConsole(string $command, array $arguments, SymfonyStyle $io): void
            {
                $this->consoleCalls[] = ['arguments' => $arguments, 'command' => $command];
            }

            /**
             * @param list<string> $command
             */
            protected function runProcess(array $command, SymfonyStyle $io): void
            {
                $this->processCalls[] = $command;
            }
        };

        $task->execute($this->createIo());

        // Vérifier la purge des couvertures
        self::assertFileDoesNotExist($this->tempDir.'/public/uploads/covers/cover1.webp');
        self::assertFileDoesNotExist($this->tempDir.'/public/uploads/covers/cover2.webp');
        self::assertDirectoryExists($this->tempDir.'/public/uploads/covers');

        // Vérifier l'appel de suppression du cache des miniatures
        self::assertSame([['rm', '-rf', 'public/media/cache']], $processCalls);

        // Vérifier l'ordre des commandes console
        $expectedCommands = [
            [
                'arguments' => ['--force', '--if-exists', '--env=prod'],
                'command' => 'doctrine:database:drop',
            ],
            [
                'arguments' => ['--env=prod'],
                'command' => 'doctrine:database:create',
            ],
            [
                'arguments' => ['-n', '--env=prod'],
                'command' => 'doctrine:migrations:migrate',
            ],
            [
                'arguments' => [$this->tempDir.'/var/import.xlsx', '--env=prod'],
                'command' => 'app:import',
            ],
            [
                'arguments' => ['--queue', '--env=prod'],
                'command' => 'app:auto-enrich',
            ],
        ];

        self::assertSame($expectedCommands, $consoleCalls);
    }

    public function testGetDescriptionReturnsMeaningfulText(): void
    {
        $task = new Task002ResetAndImport($this->tempDir);

        self::assertNotEmpty($task->getDescription());
        self::assertStringContainsString('enrichissement', $task->getDescription());
    }

    private function createIo(): SymfonyStyle
    {
        return new SymfonyStyle(new ArrayInput([]), new NullOutput());
    }

    private function removeDirRecursive(string $dir): void
    {
        if (!\is_dir($dir)) {
            return;
        }

        $items = \scandir($dir) ?: [];
        foreach ($items as $item) {
            if ('.' === $item || '..' === $item) {
                continue;
            }
            $path = $dir.'/'.$item;
            if (\is_dir($path)) {
                $this->removeDirRecursive($path);
            } else {
                \unlink($path);
            }
        }

        \rmdir($dir);
    }
}

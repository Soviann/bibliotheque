<?php

declare(strict_types=1);

namespace App\Command;

use App\EventListener\EnrichOnCreateListener;
use App\Service\Import\ImportBooksService;
use PhpOffice\PhpSpreadsheet\Reader\Exception as ReaderException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Importe des livres depuis un fichier Excel (format Livres.xlsx).
 */
#[AsCommand(
    name: 'app:import-books',
    description: 'Importe des livres depuis un fichier Excel (format Livres.xlsx)',
)]
final class ImportBooksCommand extends Command
{
    public function __construct(
        private readonly ImportBooksService $importBooksService,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('file', InputArgument::REQUIRED, 'Chemin vers le fichier Excel')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Simuler l\'import sans persister')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        /** @var string $filePath */
        $filePath = $input->getArgument('file');
        /** @var bool $dryRun */
        $dryRun = $input->getOption('dry-run');

        if (!\file_exists($filePath)) {
            $io->error(\sprintf('Le fichier "%s" n\'existe pas.', $filePath));

            return Command::FAILURE;
        }

        $io->title('Import des livres depuis Excel');

        if ($dryRun) {
            $io->warning('Mode simulation activé (--dry-run). Aucune donnée ne sera persistée.');
        }

        EnrichOnCreateListener::disable();

        try {
            $result = $this->importBooksService->import($filePath, $dryRun);
        } catch (ReaderException $e) {
            $io->error(\sprintf('Impossible de lire le fichier Excel : %s', $e->getMessage()));

            return Command::FAILURE;
        } finally {
            EnrichOnCreateListener::enable();
        }

        $io->section(\sprintf('%d groupes détectés', $result->groupCount));

        $io->success(\sprintf(
            'Import terminé. %d créés, %d enrichis.',
            $result->created,
            $result->enriched
        ));

        return Command::SUCCESS;
    }
}

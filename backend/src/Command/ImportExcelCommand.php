<?php

declare(strict_types=1);

namespace App\Command;

use App\Service\Import\ImportExcelService;
use PhpOffice\PhpSpreadsheet\Reader\Exception as ReaderException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Commande d'import des données depuis un fichier Excel.
 */
#[AsCommand(
    name: 'app:import-excel',
    description: 'Importe les données depuis un fichier Excel',
)]
class ImportExcelCommand extends Command
{
    public function __construct(
        private readonly ImportExcelService $importExcelService,
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

        $io->title('Import des données depuis Excel');

        if ($dryRun) {
            $io->warning('Mode simulation activé (--dry-run). Aucune donnée ne sera persistée.');
        }

        try {
            $result = $this->importExcelService->import($filePath, $dryRun);
        } catch (ReaderException $e) {
            $io->error(\sprintf('Impossible de lire le fichier Excel : %s', $e->getMessage()));

            return Command::FAILURE;
        }

        foreach ($result->sheetDetails as $sheetName => $details) {
            $io->success(\sprintf(
                '"%s" : %d créées, %d mises à jour, %d nouveaux tomes.',
                $sheetName,
                $details['created'],
                $details['updated'],
                $details['tomes']
            ));
        }

        $io->success(\sprintf(
            'Import terminé. Total : %d créées, %d mises à jour, %d nouveaux tomes.',
            $result->totalCreated,
            $result->totalUpdated,
            $result->totalTomes
        ));

        if (($result->totalCreated > 0) && !$dryRun) {
            $io->info('Pour compléter les données des séries importées, exécutez : bin/console app:lookup-missing');
        }

        return Command::SUCCESS;
    }
}

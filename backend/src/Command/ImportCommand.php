<?php

declare(strict_types=1);

namespace App\Command;

use App\EventListener\EnrichOnCreateListener;
use App\Service\Import\ImportService;
use PhpOffice\PhpSpreadsheet\Reader\Exception as ReaderException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Commande d'import unifié depuis un fichier Excel.
 */
#[AsCommand(
    name: 'app:import',
    description: 'Importe les données depuis un fichier Excel unifié (tracking + métadonnées)',
)]
final class ImportCommand extends Command
{
    public function __construct(
        private readonly ImportService $importService,
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

        EnrichOnCreateListener::disable();

        try {
            $result = $this->importService->import($filePath, $dryRun);
        } catch (ReaderException $e) {
            $io->error(\sprintf('Impossible de lire le fichier Excel : %s', $e->getMessage()));

            return Command::FAILURE;
        } finally {
            EnrichOnCreateListener::enable();
        }

        foreach ($result->typeDetails as $typeName => $details) {
            $io->success(\sprintf(
                '"%s" : %d créées, %d mises à jour, %d enrichies, %d nouveaux tomes.',
                $typeName,
                $details['created'],
                $details['updated'],
                $details['enriched'],
                $details['tomes']
            ));
        }

        $io->success(\sprintf(
            'Import terminé. Total : %d créées, %d mises à jour, %d enrichies, %d nouveaux tomes.',
            $result->totalCreated,
            $result->totalUpdated,
            $result->totalEnriched,
            $result->totalTomes
        ));

        return Command::SUCCESS;
    }
}

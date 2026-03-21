<?php

declare(strict_types=1);

namespace App\Command;

use App\Service\Recommendation\MissingTomeDetectorService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Détecte les tomes manquants et crée des notifications.
 */
#[AsCommand(
    name: 'app:detect-missing-tomes',
    description: 'Détecte les tomes manquants pour les séries en cours d\'achat ou terminées',
)]
final class DetectMissingTomesCommand extends Command
{
    public function __construct(
        private readonly MissingTomeDetectorService $detectorService,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('dry-run', null, InputOption::VALUE_NONE, 'Simuler sans créer de notifications');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        /** @var bool $dryRun */
        $dryRun = $input->getOption('dry-run');

        $io->title('Détection des tomes manquants');

        if ($dryRun) {
            $io->warning('Mode dry-run activé.');
        }

        $count = 0;

        foreach ($this->detectorService->detect($dryRun) as $result) {
            $io->text(\sprintf(
                '%s — manquants : %s',
                $result->seriesTitle,
                \implode(', ', $result->missingNumbers),
            ));
            ++$count;
        }

        $io->success(\sprintf('%d série(s) avec tomes manquants.', $count));

        return Command::SUCCESS;
    }
}

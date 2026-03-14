<?php

declare(strict_types=1);

namespace App\Command;

use App\Enum\BatchLookupStatus;
use App\Service\NewReleaseCheckerService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Commande de vérification des nouvelles parutions pour les séries en cours d'achat.
 */
#[AsCommand(
    name: 'app:check-new-releases',
    description: 'Vérifie les nouvelles parutions pour les séries en cours d\'achat',
)]
final class CheckNewReleasesCommand extends Command
{
    public function __construct(
        private readonly NewReleaseCheckerService $newReleaseCheckerService,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Simuler sans persister')
            ->addOption('limit', 'l', InputOption::VALUE_REQUIRED, 'Nombre maximum de séries à vérifier (0 = illimité)', '0')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        /** @var bool $dryRun */
        $dryRun = $input->getOption('dry-run');
        /** @var string $limitOption */
        $limitOption = $input->getOption('limit');
        $limit = (int) $limitOption;

        $io->title('Vérification des nouvelles parutions');

        if ($dryRun) {
            $io->warning('Mode dry-run activé. Aucune donnée ne sera persistée.');
        }

        $failed = 0;
        $processed = 0;
        $updated = 0;

        foreach ($this->newReleaseCheckerService->run(dryRun: $dryRun, limit: $limit) as $progress) {
            ++$processed;

            if ($progress->stoppedByRateLimit) {
                $io->warning('Arrêt : quota API atteint (rate limit).');
                ++$failed;

                break;
            }

            $detail = match ($progress->status) {
                BatchLookupStatus::UPDATED => \sprintf(
                    'UPDATED (latestPublishedIssue: %s → %d)',
                    $progress->previousLatestIssue ?? '?',
                    $progress->newLatestIssue,
                ),
                BatchLookupStatus::FAILED => 'FAILED',
                BatchLookupStatus::SKIPPED => 'SKIPPED',
            };

            $io->text(\sprintf(
                '[%d/%d] %s — %s',
                $progress->current,
                $progress->total,
                $progress->seriesTitle,
                $detail,
            ));

            match ($progress->status) {
                BatchLookupStatus::FAILED => ++$failed,
                BatchLookupStatus::SKIPPED => null,
                BatchLookupStatus::UPDATED => ++$updated,
            };
        }

        if (0 === $processed) {
            $io->success('Aucune série à vérifier.');

            return Command::SUCCESS;
        }

        $io->newLine();
        $io->success(\sprintf(
            '%d série(s) vérifiée(s) : %d mise(s) à jour, %d en erreur.',
            $processed,
            $updated,
            $failed,
        ));

        return Command::SUCCESS;
    }
}

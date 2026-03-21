<?php

declare(strict_types=1);

namespace App\Command;

use App\Service\ComicSeries\PurgeService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:purge-deleted',
    description: 'Purge les séries supprimées depuis plus de N jours',
)]
final class PurgeDeletedCommand extends Command
{
    public function __construct(
        private readonly PurgeService $purgeService,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('days', 'd', InputOption::VALUE_REQUIRED, 'Nombre de jours avant la purge', '30')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Affiche les séries éligibles sans les supprimer')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        /** @var string $daysOption */
        $daysOption = $input->getOption('days');
        $days = (int) $daysOption;
        if ($days <= 0) {
            $io->error('Le nombre de jours doit être supérieur à 0.');

            return Command::FAILURE;
        }

        $dryRun = (bool) $input->getOption('dry-run');
        $purgeable = $this->purgeService->findPurgeable($days);

        if ([] === $purgeable) {
            $io->success('Aucune série à purger.');

            return Command::SUCCESS;
        }

        $io->section(\sprintf('%d série(s) éligible(s) à la purge (supprimées depuis plus de %d jours)', \count($purgeable), $days));

        foreach ($purgeable as $series) {
            $io->writeln(\sprintf('  - %s (supprimée le %s)', $series->title, $series->deletedAt->format('d/m/Y')));
        }

        if ($dryRun) {
            $io->note('Mode dry-run : aucune suppression effectuée.');
        } else {
            $ids = \array_map(static fn ($s): int => $s->id, $purgeable);
            $count = $this->purgeService->executePurge($ids);
            $io->success(\sprintf('%d série(s) purgée(s).', $count));
        }

        return Command::SUCCESS;
    }
}

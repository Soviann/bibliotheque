<?php

declare(strict_types=1);

namespace App\Command;

use App\Service\AuthorReleaseCheckerService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Vérifie les nouvelles séries des auteurs suivis.
 */
#[AsCommand(
    name: 'app:check-author-releases',
    description: 'Vérifie si les auteurs suivis ont publié de nouvelles séries',
)]
final class CheckAuthorReleasesCommand extends Command
{
    public function __construct(
        private readonly AuthorReleaseCheckerService $checkerService,
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

        $io->title('Vérification des nouvelles séries d\'auteurs suivis');

        if ($dryRun) {
            $io->warning('Mode dry-run activé.');
        }

        $count = 0;

        foreach ($this->checkerService->check($dryRun) as $result) {
            $io->text(\sprintf(
                '%s — « %s » (%s)',
                $result->authorName,
                $result->newSeriesTitle,
                $result->type->getLabel(),
            ));
            ++$count;
        }

        $io->success(\sprintf('%d nouvelle(s) série(s) trouvée(s).', $count));

        return Command::SUCCESS;
    }
}

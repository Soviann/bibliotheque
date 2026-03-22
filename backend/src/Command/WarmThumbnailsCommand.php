<?php

declare(strict_types=1);

namespace App\Command;

use App\Repository\ComicSeriesRepository;
use App\Service\Cover\ThumbnailGenerator;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Génère les miniatures LiipImagine pour toutes les couvertures existantes.
 */
#[AsCommand(
    name: 'app:warm-thumbnails',
    description: 'Génère les miniatures de couverture pour le cache LiipImagine',
)]
final class WarmThumbnailsCommand extends Command
{
    public function __construct(
        private readonly ComicSeriesRepository $comicSeriesRepository,
        private readonly ThumbnailGenerator $thumbnailGenerator,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Simuler sans générer')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        /** @var bool $dryRun */
        $dryRun = $input->getOption('dry-run');

        $io->title('Génération des miniatures de couverture');

        $series = $this->comicSeriesRepository->findWithLocalCover();

        if ([] === $series) {
            $io->success('Aucune série avec couverture locale.');

            return Command::SUCCESS;
        }

        $io->info(\sprintf('%d série(s) avec couverture locale.', \count($series)));

        if ($dryRun) {
            $io->warning('Mode dry-run activé. Aucune miniature ne sera générée.');

            return Command::SUCCESS;
        }

        $generated = 0;

        foreach ($series as $comic) {
            $coverImage = $comic->getCoverImage();

            if (null === $coverImage) {
                continue;
            }

            $this->thumbnailGenerator->generate($coverImage);
            ++$generated;
        }

        $io->success(\sprintf('%d miniature(s) générée(s).', $generated));

        return Command::SUCCESS;
    }
}

<?php

declare(strict_types=1);

namespace App\Command;

use App\Message\WarmThumbnailsMessage;
use App\Repository\ComicSeriesRepository;
use App\Service\Cover\ThumbnailGenerator;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Messenger\MessageBusInterface;

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
        private readonly MessageBusInterface $messageBus,
        private readonly ThumbnailGenerator $thumbnailGenerator,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('async', null, InputOption::VALUE_NONE, 'Déléguer la génération au worker Messenger (non bloquant)')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Simuler sans générer')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        /** @var bool $async */
        $async = $input->getOption('async');
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

        $count = 0;

        foreach ($series as $comic) {
            $coverImage = $comic->getCoverImage();

            if (null === $coverImage) {
                continue;
            }

            if ($async) {
                $this->messageBus->dispatch(new WarmThumbnailsMessage($coverImage));
            } else {
                $this->thumbnailGenerator->generate($coverImage);
            }

            ++$count;
        }

        $io->success($async
            ? \sprintf('%d génération(s) de miniature déléguée(s) au worker.', $count)
            : \sprintf('%d miniature(s) générée(s).', $count));

        return Command::SUCCESS;
    }
}

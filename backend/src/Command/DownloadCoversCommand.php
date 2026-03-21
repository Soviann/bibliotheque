<?php

declare(strict_types=1);

namespace App\Command;

use App\Repository\ComicSeriesRepository;
use App\Service\Cover\CoverDownloader;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Télécharge les couvertures externes en fichiers locaux WebP.
 */
#[AsCommand(
    name: 'app:download-covers',
    description: 'Télécharge les couvertures externes et les convertit en WebP local',
)]
final class DownloadCoversCommand extends Command
{
    private const int FLUSH_BATCH_SIZE = 10;

    public function __construct(
        private readonly ComicSeriesRepository $comicSeriesRepository,
        private readonly CoverDownloader $coverDownloader,
        private readonly EntityManagerInterface $entityManager,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('delay', 'd', InputOption::VALUE_REQUIRED, 'Délai en secondes entre chaque téléchargement', '1')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Simuler sans persister')
            ->addOption('limit', 'l', InputOption::VALUE_REQUIRED, 'Nombre maximum de séries à traiter (0 = illimité)', '0')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        /** @var string $delayOption */
        $delayOption = $input->getOption('delay');
        $delay = (int) $delayOption;
        /** @var bool $dryRun */
        $dryRun = $input->getOption('dry-run');
        /** @var string $limitOption */
        $limitOption = $input->getOption('limit');
        $limit = (int) $limitOption;

        $io->title('Téléchargement des couvertures externes');

        if ($dryRun) {
            $io->warning('Mode dry-run activé. Aucune donnée ne sera persistée.');
        }

        $series = $this->comicSeriesRepository->findWithExternalCoverOnly($limit > 0 ? $limit : null);

        if ([] === $series) {
            $io->success('Aucune série avec couverture externe uniquement.');

            return Command::SUCCESS;
        }

        $io->info(\sprintf('%d série(s) à traiter.', \count($series)));

        $failed = 0;
        $processed = 0;
        $success = 0;

        foreach ($series as $i => $comic) {
            ++$processed;
            $coverUrl = $comic->getCoverUrl();

            if (null === $coverUrl) {
                continue;
            }

            $io->text(\sprintf('[%d/%d] %s', $processed, \count($series), $comic->getTitle()));

            if ($dryRun) {
                ++$success;
                continue;
            }

            if ($this->coverDownloader->downloadAndStore($comic, $coverUrl)) {
                ++$success;
                $io->text('  → Couverture téléchargée');
            } else {
                ++$failed;
                $io->text('  → Échec du téléchargement');
            }

            if (0 === ($i + 1) % self::FLUSH_BATCH_SIZE) {
                $this->entityManager->flush();
            }

            if ($delay > 0 && $i < \count($series) - 1) {
                \sleep($delay);
            }
        }

        $this->entityManager->flush();

        $io->newLine();
        $io->success(\sprintf(
            '%d série(s) traitée(s) : %d réussie(s), %d en erreur.',
            $processed,
            $success,
            $failed,
        ));

        return Command::SUCCESS;
    }
}

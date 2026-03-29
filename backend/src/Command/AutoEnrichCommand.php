<?php

declare(strict_types=1);

namespace App\Command;

use App\Enum\ComicType;
use App\Enum\EnrichmentConfidence;
use App\Enum\LookupMode;
use App\Repository\ComicSeriesRepository;
use App\Service\Enrichment\EnrichmentService;
use App\Service\Lookup\LookupOrchestrator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Commande d'enrichissement automatique avec scoring de confiance.
 *
 * Remplace app:lookup-missing en ajoutant le scoring, la file de revue
 * et le journal d'audit.
 */
#[AsCommand(
    name: 'app:auto-enrich',
    description: 'Enrichit automatiquement les séries avec des données manquantes (scoring de confiance)',
)]
final class AutoEnrichCommand extends Command
{
    private const int STALE_DAYS = 30;

    public function __construct(
        private readonly ComicSeriesRepository $comicSeriesRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly EnrichmentService $enrichmentService,
        private readonly LookupOrchestrator $lookupOrchestrator,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('delay', 'd', InputOption::VALUE_REQUIRED, 'Délai en secondes entre chaque lookup', '2')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Simuler sans persister')
            ->addOption('force', 'f', InputOption::VALUE_NONE, 'Ignorer lookupCompletedAt')
            ->addOption('limit', 'l', InputOption::VALUE_REQUIRED, 'Nombre maximum de séries (0 = illimité)', '0')
            ->addOption('type', 't', InputOption::VALUE_REQUIRED, 'Filtrer par type (bd, manga, comics, livre)')
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
        /** @var bool $force */
        $force = $input->getOption('force');
        /** @var string $limitOption */
        $limitOption = $input->getOption('limit');
        $limit = (int) $limitOption;
        /** @var string|null $typeValue */
        $typeValue = $input->getOption('type');

        $type = \is_string($typeValue) ? ComicType::tryFrom($typeValue) : null;

        $io->title('Enrichissement automatique');

        if ($dryRun) {
            $io->warning('Mode dry-run activé. Aucune donnée ne sera persistée.');
        }

        $staleDate = new \DateTimeImmutable(\sprintf('-%d days', self::STALE_DAYS));
        $seriesList = $this->comicSeriesRepository->findForAutoEnrich(
            force: $force,
            limit: $limit > 0 ? $limit : null,
            staleDate: $staleDate,
            type: $type,
        );

        if ([] === $seriesList) {
            $io->success('Aucune série à enrichir.');

            return Command::SUCCESS;
        }

        $io->info(\sprintf('%d série(s) à traiter.', \count($seriesList)));

        $autoApplied = 0;
        $failed = 0;
        $processed = 0;
        $queued = 0;
        $skipped = 0;

        foreach ($seriesList as $index => $series) {
            ++$processed;

            try {
                $result = $this->lookupOrchestrator->lookupByTitle(
                    $series->getTitle(),
                    $series->getType(),
                );

                if (null === $result) {
                    $io->text(\sprintf('[%d/%d] %s — aucun résultat', $processed, \count($seriesList), $series->getTitle()));
                    ++$skipped;

                    if (!$dryRun) {
                        $series->setLookupCompletedAt(new \DateTimeImmutable());
                    }
                } else {
                    $sources = $this->lookupOrchestrator->getLastSources();

                    if (!$dryRun) {
                        $confidence = $this->enrichmentService->enrich(
                            $series,
                            $result,
                            LookupMode::TITLE,
                            $sources,
                            'command:auto-enrich',
                        );

                        $series->setLookupCompletedAt(new \DateTimeImmutable());

                        match ($confidence) {
                            EnrichmentConfidence::HIGH => ++$autoApplied,
                            EnrichmentConfidence::MEDIUM => ++$queued,
                            EnrichmentConfidence::LOW => ++$skipped,
                        };

                        $io->text(\sprintf(
                            '[%d/%d] %s — %s',
                            $processed,
                            \count($seriesList),
                            $series->getTitle(),
                            $confidence->value,
                        ));
                    } else {
                        $io->text(\sprintf('[%d/%d] %s — dry-run', $processed, \count($seriesList), $series->getTitle()));
                    }
                }

                if (!$dryRun && 0 === $processed % 10) {
                    $this->entityManager->flush();
                }
            } catch (\Throwable $e) {
                $io->error(\sprintf('[%d/%d] %s — erreur : %s', $processed, \count($seriesList), $series->getTitle(), $e->getMessage()));
                ++$failed;
            }

            if ($index < \count($seriesList) - 1 && $delay > 0) {
                \sleep($delay);
            }
        }

        if (!$dryRun) {
            $this->entityManager->flush();
        }

        $io->newLine();
        $io->success(\sprintf(
            '%d série(s) traitée(s) : %d auto-appliquée(s), %d en file de revue, %d ignorée(s), %d en erreur.',
            $processed,
            $autoApplied,
            $queued,
            $skipped,
            $failed,
        ));

        return Command::SUCCESS;
    }
}

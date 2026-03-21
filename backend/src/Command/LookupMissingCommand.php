<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\ComicSeries;
use App\Enum\BatchLookupStatus;
use App\Enum\ComicType;
use App\Repository\ComicSeriesRepository;
use App\Service\BatchLookupService;
use App\Service\Lookup\LookupApplier;
use App\Service\Lookup\LookupOrchestrator;
use App\Service\Lookup\LookupResult;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Commande de lookup automatique pour les séries avec données manquantes.
 */
#[AsCommand(
    name: 'app:lookup-missing',
    description: 'Recherche les métadonnées manquantes pour les séries sans données',
)]
final class LookupMissingCommand extends Command
{
    public function __construct(
        private readonly BatchLookupService $batchLookupService,
        private readonly ComicSeriesRepository $comicSeriesRepository,
        private readonly LookupApplier $lookupApplier,
        private readonly LookupOrchestrator $lookupOrchestrator,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('delay', 'd', InputOption::VALUE_REQUIRED, 'Délai en secondes entre chaque lookup', '2')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Simuler sans persister')
            ->addOption('force', 'f', InputOption::VALUE_NONE, 'Ignorer lookupCompletedAt (re-lookup)')
            ->addOption('limit', 'l', InputOption::VALUE_REQUIRED, 'Nombre maximum de lookups (0 = illimité)', '0')
            ->addOption('series', 's', InputOption::VALUE_REQUIRED, 'ID d\'une série spécifique')
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
        /** @var int|string|null $seriesId */
        $seriesId = $input->getOption('series');
        /** @var string|null $typeValue */
        $typeValue = $input->getOption('type');

        $type = \is_string($typeValue) ? ComicType::tryFrom($typeValue) : null;

        $io->title('Lookup des métadonnées manquantes');

        if ($dryRun) {
            $io->warning('Mode dry-run activé. Aucune donnée ne sera persistée.');
        }

        // Mode série spécifique — ne passe pas par le service batch
        if (null !== $seriesId) {
            return $this->processSpecificSeries($io, (int) $seriesId, $dryRun);
        }

        $count = $this->batchLookupService->countSeriesToProcess($type, $force);

        if (0 === $count) {
            $io->success('Aucune série avec des données manquantes.');

            return Command::SUCCESS;
        }

        $io->info(\sprintf('%d série(s) à traiter.', $count));

        $failed = 0;
        $processed = 0;
        $skipped = 0;
        $updated = 0;

        foreach ($this->batchLookupService->run(
            delay: $delay,
            dryRun: $dryRun,
            force: $force,
            limit: $limit,
            type: $type,
        ) as $progress) {
            $io->text(\sprintf(
                '[%d/%d] %s — %s%s',
                $progress->current,
                $progress->total,
                $progress->seriesTitle,
                $progress->status->value,
                [] !== $progress->updatedFields ? ' ('.\implode(', ', $progress->updatedFields).')' : '',
            ));

            ++$processed;

            match ($progress->status) {
                BatchLookupStatus::FAILED => ++$failed,
                BatchLookupStatus::SKIPPED => ++$skipped,
                BatchLookupStatus::UPDATED => ++$updated,
            };
        }

        $io->newLine();
        $io->success(\sprintf(
            '%d série(s) traitée(s) : %d mise(s) à jour, %d ignorée(s), %d en erreur.',
            $processed,
            $updated,
            $skipped,
            $failed,
        ));

        return Command::SUCCESS;
    }

    private function processSpecificSeries(SymfonyStyle $io, int $seriesId, bool $dryRun): int
    {
        $series = $this->comicSeriesRepository->find($seriesId);

        if (!$series instanceof ComicSeries) {
            $io->error(\sprintf('Série #%d introuvable.', $seriesId));

            return Command::FAILURE;
        }

        $title = $series->getTitle();
        $type = $series->getType();

        $io->text(\sprintf('Lookup de « %s » (%s)...', $title, $type->getLabel()));

        $result = $this->lookupOrchestrator->lookupByTitle($title, $type);

        if (!$result instanceof LookupResult) {
            $io->text('Aucun résultat trouvé.');

            if (!$dryRun) {
                $series->setLookupCompletedAt(new \DateTimeImmutable());
            }

            $io->success('Terminé (aucune mise à jour).');

            return Command::SUCCESS;
        }

        $updatedFields = $this->lookupApplier->apply($series, $result);

        if ([] !== $updatedFields) {
            $io->text(\sprintf('Champs mis à jour : %s', \implode(', ', $updatedFields)));
        } else {
            $io->text('Résultat trouvé mais aucun champ vide à remplir.');
        }

        if (!$dryRun) {
            $series->setLookupCompletedAt(new \DateTimeImmutable());
        }

        $io->success('Terminé.');

        return Command::SUCCESS;
    }
}

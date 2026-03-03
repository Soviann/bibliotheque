<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\ComicSeries;
use App\Enum\ApiLookupStatus;
use App\Enum\ComicType;
use App\Repository\ComicSeriesRepository;
use App\Service\Lookup\LookupApplier;
use App\Service\Lookup\LookupOrchestrator;
use Doctrine\ORM\EntityManagerInterface;
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
class LookupMissingCommand extends Command
{
    private const int BATCH_SIZE = 10;
    private const int MAX_DELAY = 60;

    public function __construct(
        private readonly ComicSeriesRepository $comicSeriesRepository,
        private readonly EntityManagerInterface $entityManager,
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

        // Mode série spécifique
        if (null !== $seriesId) {
            return $this->processSpecificSeries($io, (int) $seriesId, $delay, $dryRun);
        }

        $seriesToProcess = $this->comicSeriesRepository->findWithMissingLookupData(
            force: $force,
            limit: $limit > 0 ? $limit : null,
            type: $type,
        );

        if ([] === $seriesToProcess) {
            $io->success('Aucune série avec des données manquantes.');

            return Command::SUCCESS;
        }

        $io->info(\sprintf('%d série(s) à traiter.', \count($seriesToProcess)));

        return $this->processSeriesList($io, $seriesToProcess, $delay, $dryRun);
    }

    private function processSpecificSeries(SymfonyStyle $io, int $seriesId, int $delay, bool $dryRun): int
    {
        $series = $this->comicSeriesRepository->find($seriesId);

        if (!$series instanceof ComicSeries) {
            $io->error(\sprintf('Série #%d introuvable.', $seriesId));

            return Command::FAILURE;
        }

        return $this->processSeriesList($io, [$series], $delay, $dryRun);
    }

    /**
     * @param ComicSeries[] $seriesList
     */
    private function processSeriesList(SymfonyStyle $io, array $seriesList, int $initialDelay, bool $dryRun): int
    {
        $currentDelay = $initialDelay;
        $failed = 0;
        $processed = 0;
        $skipped = 0;
        $updated = 0;

        foreach ($seriesList as $index => $series) {
            $title = $series->getTitle();
            $type = $series->getType();

            $io->text(\sprintf('[%d/%d] %s (%s)', $index + 1, \count($seriesList), $title, $type->getLabel()));

            $result = $this->lookupOrchestrator->lookupByTitle($title, $type);

            // Vérifier le rate limiting
            if ($this->hasRateLimitError()) {
                $io->warning(\sprintf('  Rate limit détecté, attente %ds...', \min($currentDelay * 2, self::MAX_DELAY)));
                $currentDelay = \min($currentDelay * 2, self::MAX_DELAY);
                \sleep($currentDelay);

                // Retry
                $result = $this->lookupOrchestrator->lookupByTitle($title, $type);

                if ($this->hasRateLimitError()) { // @phpstan-ignore if.alwaysTrue (état dépend de l'appel API)
                    $io->error(\sprintf('  Rate limit persistant pour « %s », passage à la suivante.', $title));
                    ++$failed;
                    ++$processed;

                    continue;
                }
            }

            if (null === $result) {
                $io->text('  Aucun résultat trouvé.');
                ++$skipped;

                if (!$dryRun) {
                    $series->setLookupCompletedAt(new \DateTimeImmutable());
                }
            } else {
                $updatedFields = $this->lookupApplier->apply($series, $result);

                if ([] !== $updatedFields) {
                    $io->text(\sprintf('  Champs mis à jour : %s', \implode(', ', $updatedFields)));
                    ++$updated;
                } else {
                    $io->text('  Résultat trouvé mais aucun champ vide à remplir.');
                    ++$skipped;
                }

                if (!$dryRun) {
                    $series->setLookupCompletedAt(new \DateTimeImmutable());
                }

                // Reset delay après un succès
                $currentDelay = $initialDelay;
            }

            ++$processed;

            // Flush par batch
            if (!$dryRun && 0 === $processed % self::BATCH_SIZE) {
                $this->entityManager->flush();
            }

            // Délai entre les lookups (sauf dernier)
            if ($index < \count($seriesList) - 1 && $currentDelay > 0) {
                \sleep($currentDelay);
            }
        }

        // Flush final
        if (!$dryRun) {
            $this->entityManager->flush();
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

    /**
     * Vérifie si un des messages API indique un rate limit.
     */
    private function hasRateLimitError(): bool
    {
        foreach ($this->lookupOrchestrator->getLastApiMessages() as $message) {
            if (ApiLookupStatus::RATE_LIMITED->value === $message['status']) {
                return true;
            }
        }

        return false;
    }
}

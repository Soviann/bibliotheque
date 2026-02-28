<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\ComicSeries;
use App\Service\ComicSeriesService;
use Doctrine\ORM\EntityManagerInterface;
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
class PurgeDeletedCommand extends Command
{
    public function __construct(
        private readonly ComicSeriesService $comicSeriesService,
        private readonly EntityManagerInterface $entityManager,
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
        $cutoffDate = new \DateTime(\sprintf('-%d days', $days));

        // Désactiver le filtre pour accéder aux séries soft-deleted
        $this->entityManager->getFilters()->disable('soft_delete');

        /** @var ComicSeries[] $seriesToPurge */
        $seriesToPurge = $this->entityManager->getRepository(ComicSeries::class)
            ->createQueryBuilder('c')
            ->where('c.deletedAt IS NOT NULL')
            ->andWhere('c.deletedAt <= :cutoff')
            ->setParameter('cutoff', $cutoffDate)
            ->getQuery()
            ->getResult();

        $this->entityManager->getFilters()->enable('soft_delete');

        if ([] === $seriesToPurge) {
            $io->success('Aucune série à purger.');

            return Command::SUCCESS;
        }

        $io->section(\sprintf('%d série(s) éligible(s) à la purge (supprimées depuis plus de %d jours)', \count($seriesToPurge), $days));

        foreach ($seriesToPurge as $series) {
            $deletedAt = $series->getDeletedAt();
            $io->writeln(\sprintf('  - %s (supprimée le %s)', $series->getTitle(), $deletedAt instanceof \DateTimeInterface ? $deletedAt->format('d/m/Y') : '?'));

            if (!$dryRun) {
                /** @var int $seriesId already persisted entity, getId() cannot be null */
                $seriesId = $series->getId();
                $this->comicSeriesService->permanentDelete($seriesId, $series);
            }
        }

        if ($dryRun) {
            $io->note('Mode dry-run : aucune suppression effectuée.');
        } else {
            $io->success(\sprintf('%d série(s) purgée(s).', \count($seriesToPurge)));
        }

        return Command::SUCCESS;
    }
}

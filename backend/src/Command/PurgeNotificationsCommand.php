<?php

declare(strict_types=1);

namespace App\Command;

use App\Repository\NotificationRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Purge les notifications anciennes.
 */
#[AsCommand(
    name: 'app:purge-notifications',
    description: 'Supprime les notifications plus anciennes que le nombre de jours spécifié',
)]
final class PurgeNotificationsCommand extends Command
{
    private const int DEFAULT_DAYS = 90;

    public function __construct(
        private readonly NotificationRepository $notificationRepository,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('days', 'd', InputOption::VALUE_REQUIRED, 'Nombre de jours de rétention', (string) self::DEFAULT_DAYS);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        /** @var string $daysOption */
        $daysOption = $input->getOption('days');
        $days = (int) $daysOption;

        $before = new \DateTimeImmutable(\sprintf('-%d days', $days));
        $count = $this->notificationRepository->purgeOlderThan($before);

        $io->success(\sprintf('%d notification(s) supprimée(s) (plus de %d jours).', $count, $days));

        return Command::SUCCESS;
    }
}

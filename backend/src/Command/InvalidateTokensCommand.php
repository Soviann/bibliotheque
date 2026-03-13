<?php

declare(strict_types=1);

namespace App\Command;

use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Invalide les tokens JWT en incrémentant la version des utilisateurs.
 */
#[AsCommand(
    name: 'app:invalidate-tokens',
    description: 'Invalide tous les tokens JWT (ou ceux d\'un utilisateur spécifique)',
)]
final class InvalidateTokensCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly UserRepository $userRepository,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('email', null, InputOption::VALUE_OPTIONAL, 'Email de l\'utilisateur (tous si omis)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        /** @var string|null $email */
        $email = $input->getOption('email');

        if (null !== $email) {
            return $this->invalidateForUser($io, $email);
        }

        return $this->invalidateAll($io);
    }

    private function invalidateAll(SymfonyStyle $io): int
    {
        $users = $this->userRepository->findAll();

        foreach ($users as $user) {
            $user->incrementTokenVersion();
        }

        $this->entityManager->flush();

        $io->success(\sprintf('Tokens invalidés pour %d utilisateur(s).', \count($users)));

        return Command::SUCCESS;
    }

    private function invalidateForUser(SymfonyStyle $io, string $email): int
    {
        $user = $this->userRepository->findOneBy(['email' => $email]);

        if (null === $user) {
            $io->error(\sprintf('Utilisateur "%s" introuvable.', $email));

            return Command::FAILURE;
        }

        $user->incrementTokenVersion();
        $this->entityManager->flush();

        $io->success(\sprintf('Tokens invalidés pour "%s".', $email));

        return Command::SUCCESS;
    }
}

<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Fixtures pour les utilisateurs de test.
 */
final class UserFixtures extends Fixture
{
    public function __construct(
        #[Autowire('%kernel.environment%')]
        private readonly string $environment,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function load(ObjectManager $manager): void
    {
        if ('test' !== $this->environment) {
            $this->logger->warning('Les fixtures ne doivent être chargées qu\'en environnement de test. Environnement actuel: {env}', [
                'env' => $this->environment,
            ]);

            return;
        }

        $user = new User();
        $user->setEmail('test@example.com');
        $user->setGoogleId('test-google-id');
        $user->setRoles(['ROLE_USER']);

        $manager->persist($user);
        $manager->flush();
    }
}

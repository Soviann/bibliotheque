<?php

declare(strict_types=1);

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Entity\NotificationPreference;
use App\Enum\NotificationChannel;
use App\Enum\NotificationType;
use App\Repository\NotificationPreferenceRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * Initialise les préférences de notification par défaut (IN_APP) au premier accès.
 *
 * @implements ProviderInterface<NotificationPreference>
 */
final readonly class NotificationPreferenceInitializer implements ProviderInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private NotificationPreferenceRepository $notificationPreferenceRepository,
        private Security $security,
    ) {
    }

    /**
     * @return list<NotificationPreference>
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): array
    {
        $user = $this->security->getUser();

        if (null === $user) {
            return [];
        }

        /** @var \App\Entity\User $user */
        $existing = $this->notificationPreferenceRepository->findByUser($user);

        if (\count($existing) >= \count(NotificationType::cases())) {
            return $existing;
        }

        // Créer les préférences manquantes
        $existingTypes = \array_map(
            static fn (NotificationPreference $p) => $p->getType(),
            $existing,
        );

        foreach (NotificationType::cases() as $type) {
            if (\in_array($type, $existingTypes, true)) {
                continue;
            }

            $pref = new NotificationPreference(
                channel: NotificationChannel::IN_APP,
                type: $type,
                user: $user,
            );
            $this->entityManager->persist($pref);
            $existing[] = $pref;
        }

        $this->entityManager->flush();

        return $existing;
    }
}

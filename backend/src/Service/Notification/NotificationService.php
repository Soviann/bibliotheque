<?php

declare(strict_types=1);

namespace App\Service\Notification;

use App\Entity\Notification;
use App\Entity\User;
use App\Enum\NotificationChannel;
use App\Enum\NotificationEntityType;
use App\Enum\NotificationType;
use App\Repository\NotificationPreferenceRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * Crée des notifications en respectant les préférences utilisateur.
 */
class NotificationService implements NotifierInterface
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly LoggerInterface $logger,
        private readonly NotificationPreferenceRepository $notificationPreferenceRepository,
        private readonly WebPushService $webPushService,
    ) {
    }

    /**
     * Crée une notification selon les préférences de l'utilisateur.
     *
     * @param array<string, mixed>|null $metadata
     */
    public function create(
        User $user,
        NotificationType $type,
        string $title,
        string $message,
        ?NotificationEntityType $relatedEntityType = null,
        ?int $relatedEntityId = null,
        ?array $metadata = null,
    ): ?Notification {
        $channel = $this->getChannel($user, $type);

        if (NotificationChannel::OFF === $channel) {
            return null;
        }

        $notification = null;

        // Créer la notification in-app
        if (NotificationChannel::IN_APP === $channel || NotificationChannel::BOTH === $channel) {
            $notification = new Notification(
                message: $message,
                metadata: $metadata,
                relatedEntityId: $relatedEntityId,
                relatedEntityType: $relatedEntityType,
                title: $title,
                type: $type,
                user: $user,
            );
            $this->entityManager->persist($notification);
            $this->entityManager->flush();
        }

        // Envoyer le push
        if (NotificationChannel::PUSH === $channel || NotificationChannel::BOTH === $channel) {
            $url = $this->buildEntityUrl($relatedEntityType, $relatedEntityId);
            $this->webPushService->sendToUser($user, $title, $message, $url);
        }

        $this->logger->info('Notification "{type}" créée pour {email} via {channel}', [
            'channel' => $channel->value,
            'email' => $user->getEmail(),
            'type' => $type->value,
        ]);

        return $notification;
    }

    private function buildEntityUrl(?NotificationEntityType $entityType, ?int $entityId): ?string
    {
        if (null === $entityType || null === $entityId) {
            return null;
        }

        return match ($entityType) {
            NotificationEntityType::AUTHOR => null,
            NotificationEntityType::COMIC_SERIES => '/comic/'.$entityId,
            NotificationEntityType::ENRICHMENT_PROPOSAL => '/tools/enrichment-review',
        };
    }

    private function getChannel(User $user, NotificationType $type): NotificationChannel
    {
        $pref = $this->notificationPreferenceRepository->findByUserAndType($user, $type);

        return $pref?->getChannel() ?? NotificationChannel::IN_APP;
    }
}

<?php

declare(strict_types=1);

namespace App\Controller;

use App\Repository\NotificationRepository;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Endpoints personnalisés pour les notifications.
 */
#[IsGranted('ROLE_USER')]
#[Route('/api/notifications')]
final class NotificationController
{
    /**
     * Retourne le nombre de notifications non lues.
     */
    #[Route('/unread-count', name: 'api_notifications_unread_count', methods: ['GET'])]
    public function unreadCount(
        #[CurrentUser] UserInterface $user,
        NotificationRepository $notificationRepository,
    ): JsonResponse {
        /** @var \App\Entity\User $user */
        $count = $notificationRepository->countUnread($user);

        return new JsonResponse(['count' => $count]);
    }

    /**
     * Marque toutes les notifications de l'utilisateur comme lues.
     */
    #[Route('/read-all', name: 'api_notifications_read_all', methods: ['PATCH'])]
    public function readAll(
        #[CurrentUser] UserInterface $user,
        NotificationRepository $notificationRepository,
    ): JsonResponse {
        /** @var \App\Entity\User $user */
        $updated = $notificationRepository->markAllRead($user);

        return new JsonResponse(['updated' => $updated]);
    }
}

<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\User;
use App\Repository\PushSubscriptionRepository;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Envoie des notifications push via Web Push API.
 */
class WebPushService
{
    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly PushSubscriptionRepository $pushSubscriptionRepository,
        #[Autowire('%env(VAPID_PRIVATE_KEY)%')]
        private readonly string $vapidPrivateKey,
        #[Autowire('%env(VAPID_PUBLIC_KEY)%')]
        private readonly string $vapidPublicKey,
        #[Autowire('%env(VAPID_SUBJECT)%')]
        private readonly string $vapidSubject,
    ) {
    }

    /**
     * Envoie une notification push à tous les appareils de l'utilisateur.
     */
    public function sendToUser(User $user, string $title, string $body, ?string $url = null): void
    {
        $subscriptions = $this->pushSubscriptionRepository->findByUser($user);

        if ([] === $subscriptions) {
            return;
        }

        try {
            $webPush = new WebPush([
                'VAPID' => [
                    'privateKey' => $this->vapidPrivateKey,
                    'publicKey' => $this->vapidPublicKey,
                    'subject' => $this->vapidSubject,
                ],
            ]);

            $payload = \json_encode([
                'body' => $body,
                'title' => $title,
                'url' => $url,
            ], \JSON_THROW_ON_ERROR);

            foreach ($subscriptions as $sub) {
                $webPush->queueNotification(
                    Subscription::create([
                        'contentEncoding' => 'aesgcm',
                        'endpoint' => $sub->getEndpoint(),
                        'keys' => [
                            'auth' => $sub->getAuthToken(),
                            'p256dh' => $sub->getPublicKey(),
                        ],
                    ]),
                    $payload,
                );
            }

            /** @var \Minishlink\WebPush\MessageSentReport $report */
            foreach ($webPush->flush() as $report) {
                if (!$report->isSuccess()) {
                    $this->logger->warning('Push échoué pour {endpoint} : {reason}', [
                        'endpoint' => $report->getEndpoint(),
                        'reason' => $report->getReason(),
                    ]);
                }
            }
        } catch (\Throwable $e) {
            $this->logger->error('Erreur WebPush : {error}', [
                'error' => $e->getMessage(),
            ]);
        }
    }
}

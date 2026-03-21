<?php

declare(strict_types=1);

namespace App\EventListener;

use App\Entity\User;
use App\Repository\UserRepository;
use Lexik\Bundle\JWTAuthenticationBundle\Event\JWTCreatedEvent;
use Lexik\Bundle\JWTAuthenticationBundle\Event\JWTDecodedEvent;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

/**
 * Ajoute et vérifie la version du token JWT pour permettre l'invalidation.
 *
 * La version est ajoutée au payload lors de la création du token,
 * et vérifiée lors du décodage. L'incrémentation manuelle via
 * la commande app:invalidate-tokens permet d'invalider les tokens existants.
 */
#[AsEventListener(event: 'lexik_jwt_authentication.on_jwt_created', method: 'onJWTCreated')]
#[AsEventListener(event: 'lexik_jwt_authentication.on_jwt_decoded', method: 'onJWTDecoded')]
final readonly class JwtTokenVersionListener
{
    public function __construct(
        private UserRepository $userRepository,
    ) {
    }

    /**
     * Ajoute la tokenVersion actuelle au payload du JWT lors de la création.
     */
    public function onJWTCreated(JWTCreatedEvent $event): void
    {
        $user = $event->getUser();

        if (!$user instanceof User) {
            return;
        }

        $data = $event->getData();
        $data['tokenVersion'] = $user->getTokenVersion();
        $event->setData($data);
    }

    /**
     * Vérifie que la tokenVersion du JWT correspond à celle de l'utilisateur.
     */
    public function onJWTDecoded(JWTDecodedEvent $event): void
    {
        $payload = $event->getPayload();

        if (!isset($payload['tokenVersion'], $payload['username'])) {
            $event->markAsInvalid();

            return;
        }

        $user = $this->userRepository->findOneBy(['email' => $payload['username']]);

        if (null === $user || $payload['tokenVersion'] !== $user->getTokenVersion()) {
            $event->markAsInvalid();
        }
    }
}

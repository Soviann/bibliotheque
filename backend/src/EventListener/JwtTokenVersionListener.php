<?php

declare(strict_types=1);

namespace App\EventListener;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Event\JWTCreatedEvent;
use Lexik\Bundle\JWTAuthenticationBundle\Event\JWTDecodedEvent;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

/**
 * Ajoute et vérifie la version du token JWT pour permettre l'invalidation.
 *
 * À chaque création de token (login), la version est incrémentée,
 * ce qui invalide automatiquement tous les tokens précédents.
 */
#[AsEventListener(event: 'lexik_jwt_authentication.on_jwt_created', method: 'onJWTCreated')]
#[AsEventListener(event: 'lexik_jwt_authentication.on_jwt_decoded', method: 'onJWTDecoded')]
class JwtTokenVersionListener
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly UserRepository $userRepository,
    ) {
    }

    /**
     * Incrémente la tokenVersion et l'ajoute au payload du JWT lors de la création.
     * Chaque nouveau token invalide les précédents.
     */
    public function onJWTCreated(JWTCreatedEvent $event): void
    {
        $user = $event->getUser();

        if (!$user instanceof User) {
            return;
        }

        $user->incrementTokenVersion();
        $this->entityManager->flush();

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

<?php

declare(strict_types=1);

namespace App\EventListener;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Vérifie que les secrets cryptographiques ne sont pas des placeholders en production.
 *
 * En environnement prod, si APP_SECRET ou JWT_PASSPHRASE contiennent encore
 * les valeurs par défaut du fichier .env, une exception est levée pour empêcher
 * le démarrage de l'application avec des secrets non sécurisés.
 */
#[AsEventListener(event: KernelEvents::REQUEST, method: 'onKernelRequest', priority: 255)]
final class PlaceholderSecretChecker
{
    private const array PLACEHOLDERS = [
        'APP_SECRET' => 'change_this_secret_in_env_local',
        'JWT_PASSPHRASE' => 'change_this_passphrase_in_env_local',
    ];

    public function __construct(
        #[Autowire('%kernel.secret%')]
        private readonly string $appSecret,
        #[Autowire('%kernel.environment%')]
        private readonly string $env,
        #[Autowire('%env(JWT_PASSPHRASE)%')]
        private readonly string $jwtPassphrase,
    ) {
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest() || 'prod' !== $this->env) {
            return;
        }

        $detected = [];
        $values = [
            'APP_SECRET' => $this->appSecret,
            'JWT_PASSPHRASE' => $this->jwtPassphrase,
        ];

        foreach (self::PLACEHOLDERS as $name => $placeholder) {
            if ($values[$name] === $placeholder) {
                $detected[] = $name;
            }
        }

        if ([] !== $detected) {
            throw new \RuntimeException(\sprintf('Secret(s) placeholder détecté(s) en production : %s. Configurez le vault Symfony Secrets ou définissez ces variables d\'environnement.', \implode(', ', $detected)));
        }
    }
}

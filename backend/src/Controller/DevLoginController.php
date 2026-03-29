<?php

declare(strict_types=1);

namespace App\Controller;

use App\Repository\UserRepository;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Connexion développeur — uniquement disponible en environnement dev.
 *
 * Permet l'authentification par identifiant/mot de passe définis dans les
 * variables d'environnement, afin de faciliter l'automatisation navigateur
 * (Chrome DevTools MCP, Playwright).
 */
final readonly class DevLoginController
{
    public function __construct(
        #[Autowire('%env(OAUTH_ALLOWED_EMAIL)%')]
        private string $allowedEmail,
        #[Autowire('%env(bool:APP_DEBUG_LOGIN)%')]
        private bool $enabled,
        #[Autowire('%env(APP_DEBUG_LOGIN_PASSWORD)%')]
        private string $debugPassword,
        #[Autowire('%env(APP_DEBUG_LOGIN_USER)%')]
        private string $debugUser,
        #[Autowire('%kernel.environment%')]
        private string $environment,
        private JWTTokenManagerInterface $jwtManager,
        private LoggerInterface $logger,
        private RateLimiterFactoryInterface $devLoginLimiter,
        private UserRepository $userRepository,
    ) {
    }

    #[Route('/api/login/dev', name: 'api_login_dev', methods: ['POST'])]
    public function __invoke(Request $request): Response
    {
        if (!$this->enabled || 'dev' !== $this->environment) {
            return new Response(status: Response::HTTP_NOT_FOUND);
        }

        if ($this->hasDefaultCredentials()) {
            $this->logger->warning('Dev login: identifiants par défaut non modifiés.');

            return new Response(status: Response::HTTP_NOT_FOUND);
        }

        $limiter = $this->devLoginLimiter->create($request->getClientIp() ?? 'unknown');
        if (false === $limiter->consume()->isAccepted()) {
            return new Response(status: Response::HTTP_NOT_FOUND);
        }

        /** @var array{username?: string, password?: string} $data */
        $data = \json_decode($request->getContent(), true);
        $username = $data['username'] ?? '';
        $password = $data['password'] ?? '';

        if (!\hash_equals($this->debugUser, $username) || !\hash_equals($this->debugPassword, $password)) {
            $this->logger->notice('Dev login: tentative échouée depuis {ip}.', [
                'ip' => $request->getClientIp(),
            ]);

            return new Response(status: Response::HTTP_NOT_FOUND);
        }

        $user = $this->userRepository->findOneBy(['email' => \strtolower($this->allowedEmail)]);

        if (null === $user) {
            $this->logger->error('Dev login: utilisateur introuvable pour {email}.', [
                'email' => $this->allowedEmail,
            ]);

            return new Response(status: Response::HTTP_NOT_FOUND);
        }

        $token = $this->jwtManager->create($user);

        $this->logger->info('Dev login: connexion réussie pour {email} depuis {ip}.', [
            'email' => $this->allowedEmail,
            'ip' => $request->getClientIp(),
        ]);

        return new JsonResponse(['token' => $token]);
    }

    private function hasDefaultCredentials(): bool
    {
        return '' === \trim($this->debugUser)
            || '' === \trim($this->debugPassword)
            || ('dev' === $this->debugUser && 'dev' === $this->debugPassword);
    }
}

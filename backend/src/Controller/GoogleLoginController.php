<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Google\Client as GoogleClient;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class GoogleLoginController
{
    public function __construct(
        #[Autowire('%env(OAUTH_ALLOWED_EMAIL)%')]
        private readonly string $allowedEmail,
        private readonly EntityManagerInterface $entityManager,
        private readonly GoogleClient $googleClient,
        private readonly JWTTokenManagerInterface $jwtManager,
        private readonly UserRepository $userRepository,
        private readonly ValidatorInterface $validator,
    ) {
    }

    #[Route('/api/login/google', name: 'api_login_google', methods: ['POST'])]
    public function __invoke(Request $request): JsonResponse
    {
        /** @var array{credential?: string} $data */
        $data = \json_decode($request->getContent(), true);
        $credential = $data['credential'] ?? null;

        if (null === $credential) {
            return new JsonResponse(['error' => 'Paramètre "credential" manquant.'], Response::HTTP_BAD_REQUEST);
        }

        $payload = $this->googleClient->verifyIdToken($credential);

        if (false === $payload) {
            return new JsonResponse(['error' => 'Token Google invalide.'], Response::HTTP_UNAUTHORIZED);
        }

        $email = $payload['email'] ?? '';

        if ($email !== $this->allowedEmail) {
            return new JsonResponse(['error' => 'Adresse email non autorisée.'], Response::HTTP_FORBIDDEN);
        }

        $user = $this->userRepository->findOneBy(['email' => $email]);

        if (null === $user) {
            $user = new User();
            $user->setEmail($email);
            $user->setGoogleId($payload['sub']);
            $user->setRoles(['ROLE_USER']);

            $errors = $this->validator->validate($user);
            if (\count($errors) > 0) {
                return new JsonResponse(['error' => (string) $errors], Response::HTTP_BAD_REQUEST);
            }

            $this->entityManager->persist($user);
            $this->entityManager->flush();
        } elseif (null === $user->getGoogleId()) {
            $user->setGoogleId($payload['sub']);
            $this->entityManager->flush();
        }

        $token = $this->jwtManager->create($user);

        return new JsonResponse(['token' => $token]);
    }
}

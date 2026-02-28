<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Controller\GoogleLoginController;
use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Google\Client as GoogleClient;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class GoogleLoginControllerTest extends TestCase
{
    private const ALLOWED_EMAIL = 'admin@bibliotheque.fr';
    private const GOOGLE_ID = 'google-sub-123';
    private const JWT_TOKEN = 'jwt.token.value';

    private EntityManagerInterface&MockObject $entityManager;
    private GoogleClient&MockObject $googleClient;
    private JWTTokenManagerInterface&MockObject $jwtManager;
    private UserRepository&MockObject $userRepository;
    private ValidatorInterface&MockObject $validator;

    private GoogleLoginController $controller;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->googleClient = $this->createMock(GoogleClient::class);
        $this->jwtManager = $this->createMock(JWTTokenManagerInterface::class);
        $this->userRepository = $this->createMock(UserRepository::class);
        $this->validator = $this->createMock(ValidatorInterface::class);

        $this->controller = new GoogleLoginController(
            self::ALLOWED_EMAIL,
            $this->entityManager,
            $this->googleClient,
            $this->jwtManager,
            $this->userRepository,
            $this->validator,
        );
    }

    public function testLoginWithValidToken(): void
    {
        $user = new User();
        $user->setEmail(self::ALLOWED_EMAIL);
        $user->setGoogleId(self::GOOGLE_ID);

        $this->googleClient->method('verifyIdToken')
            ->with('valid-credential')
            ->willReturn(['email' => self::ALLOWED_EMAIL, 'sub' => self::GOOGLE_ID]);

        $this->userRepository->method('findOneBy')
            ->with(['email' => self::ALLOWED_EMAIL])
            ->willReturn($user);

        $this->jwtManager->method('create')
            ->with($user)
            ->willReturn(self::JWT_TOKEN);

        $request = new Request(content: \json_encode(['credential' => 'valid-credential']));
        $response = ($this->controller)($request);

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        self::assertSame(['token' => self::JWT_TOKEN], \json_decode($response->getContent(), true));
    }

    public function testLoginWithUnauthorizedEmail(): void
    {
        $this->googleClient->method('verifyIdToken')
            ->willReturn(['email' => 'intruder@example.com', 'sub' => 'sub-456']);

        $request = new Request(content: \json_encode(['credential' => 'valid-credential']));
        $response = ($this->controller)($request);

        self::assertSame(Response::HTTP_FORBIDDEN, $response->getStatusCode());
        self::assertStringContainsString('non autoris', $response->getContent());
    }

    public function testLoginWithInvalidToken(): void
    {
        $this->googleClient->method('verifyIdToken')
            ->willReturn(false);

        $request = new Request(content: \json_encode(['credential' => 'bad-credential']));
        $response = ($this->controller)($request);

        self::assertSame(Response::HTTP_UNAUTHORIZED, $response->getStatusCode());
        self::assertStringContainsString('invalide', $response->getContent());
    }

    public function testLoginWithMissingCredential(): void
    {
        $request = new Request(content: \json_encode([]));
        $response = ($this->controller)($request);

        self::assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
        self::assertStringContainsString('manquant', $response->getContent());
    }

    public function testLoginCreatesUserIfNotExists(): void
    {
        $this->googleClient->method('verifyIdToken')
            ->willReturn(['email' => self::ALLOWED_EMAIL, 'sub' => self::GOOGLE_ID]);

        $this->userRepository->method('findOneBy')
            ->with(['email' => self::ALLOWED_EMAIL])
            ->willReturn(null);

        $this->validator->method('validate')
            ->willReturn(new ConstraintViolationList());

        $this->entityManager->expects(self::once())
            ->method('persist')
            ->with(self::callback(static function (User $user): bool {
                return self::ALLOWED_EMAIL === $user->getEmail()
                    && self::GOOGLE_ID === $user->getGoogleId();
            }));

        $this->entityManager->expects(self::once())
            ->method('flush');

        $this->jwtManager->method('create')
            ->willReturn(self::JWT_TOKEN);

        $request = new Request(content: \json_encode(['credential' => 'valid-credential']));
        $response = ($this->controller)($request);

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        self::assertSame(['token' => self::JWT_TOKEN], \json_decode($response->getContent(), true));
    }

    public function testLoginUpdatesGoogleIdIfMissing(): void
    {
        $user = new User();
        $user->setEmail(self::ALLOWED_EMAIL);

        self::assertNull($user->getGoogleId());

        $this->googleClient->method('verifyIdToken')
            ->willReturn(['email' => self::ALLOWED_EMAIL, 'sub' => self::GOOGLE_ID]);

        $this->userRepository->method('findOneBy')
            ->with(['email' => self::ALLOWED_EMAIL])
            ->willReturn($user);

        $this->entityManager->expects(self::once())
            ->method('flush');

        $this->jwtManager->method('create')
            ->with($user)
            ->willReturn(self::JWT_TOKEN);

        $request = new Request(content: \json_encode(['credential' => 'valid-credential']));
        $response = ($this->controller)($request);

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        self::assertSame(self::GOOGLE_ID, $user->getGoogleId());
    }
}

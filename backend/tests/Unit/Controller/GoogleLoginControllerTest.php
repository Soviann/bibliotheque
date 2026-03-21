<?php

declare(strict_types=1);

namespace App\Tests\Unit\Controller;

use App\Controller\GoogleLoginController;
use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Google\Client as GoogleClient;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\RateLimiter\Storage\InMemoryStorage;
use Symfony\Component\Validator\ConstraintViolationInterface;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * Tests unitaires pour GoogleLoginController.
 */
final class GoogleLoginControllerTest extends TestCase
{
    private const string ALLOWED_EMAIL = 'allowed@example.com';

    private EntityManagerInterface $entityManager;
    private GoogleClient&Stub $googleClient;
    private JWTTokenManagerInterface&Stub $jwtManager;
    private UserRepository&Stub $userRepository;
    private ValidatorInterface&Stub $validator;

    protected function setUp(): void
    {
        $this->entityManager = $this->createStub(EntityManagerInterface::class);
        $this->googleClient = $this->createStub(GoogleClient::class);
        $this->jwtManager = $this->createStub(JWTTokenManagerInterface::class);
        $this->userRepository = $this->createStub(UserRepository::class);
        $this->validator = $this->createStub(ValidatorInterface::class);
    }

    public function testMissingCredentialReturns400(): void
    {
        $controller = $this->createController();
        $request = $this->createJsonRequest('{}');

        $response = $controller($request);

        self::assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
        self::assertStringContainsString('credential', $response->getContent());
    }

    public function testInvalidGoogleTokenReturns401(): void
    {
        $this->googleClient->method('verifyIdToken')->willReturn(false);

        $controller = $this->createController();
        $request = $this->createJsonRequest('{"credential": "invalid-token"}');

        $response = $controller($request);

        self::assertSame(Response::HTTP_UNAUTHORIZED, $response->getStatusCode());
        self::assertStringContainsString('Token Google invalide', $response->getContent());
    }

    public function testEmailNotMatchingAllowedEmailReturns403(): void
    {
        $this->googleClient->method('verifyIdToken')->willReturn([
            'email' => 'unauthorized@example.com',
            'sub' => 'google-id-123',
        ]);

        $controller = $this->createController();
        $request = $this->createJsonRequest('{"credential": "valid-token"}');

        $response = $controller($request);

        self::assertSame(Response::HTTP_FORBIDDEN, $response->getStatusCode());
        $data = \json_decode($response->getContent(), true);
        self::assertSame('Adresse email non autorisée.', $data['error']);
    }

    public function testNewUserCreationReturnsJwt(): void
    {
        $this->googleClient->method('verifyIdToken')->willReturn([
            'email' => self::ALLOWED_EMAIL,
            'sub' => 'google-id-123',
        ]);
        $this->userRepository->method('findOneBy')->willReturn(null);
        $this->validator->method('validate')->willReturn(new ConstraintViolationList());
        $em = $this->createEntityManagerMock();
        $em->expects(self::once())->method('persist');
        $em->expects(self::once())->method('flush');
        $this->jwtManager->method('create')->willReturn('jwt-token-new');

        $controller = $this->createController();
        $request = $this->createJsonRequest('{"credential": "valid-token"}');

        $response = $controller($request);

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        $data = \json_decode($response->getContent(), true);
        self::assertSame('jwt-token-new', $data['token']);
    }

    public function testExistingUserWithNullGoogleIdUpdatesGoogleId(): void
    {
        $user = new User();
        $user->setEmail(self::ALLOWED_EMAIL);
        $user->setRoles(['ROLE_USER']);

        $this->googleClient->method('verifyIdToken')->willReturn([
            'email' => self::ALLOWED_EMAIL,
            'sub' => 'google-id-456',
        ]);
        $this->userRepository->method('findOneBy')->willReturn($user);
        $em = $this->createEntityManagerMock();
        $em->expects(self::never())->method('persist');
        $em->expects(self::once())->method('flush');
        $this->jwtManager->method('create')->willReturn('jwt-token-existing');

        $controller = $this->createController();
        $request = $this->createJsonRequest('{"credential": "valid-token"}');

        $response = $controller($request);

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        self::assertSame('google-id-456', $user->getGoogleId());
    }

    public function testExistingUserWithGoogleIdAlreadySetReturnsJwtWithoutUpdate(): void
    {
        $user = new User();
        $user->setEmail(self::ALLOWED_EMAIL);
        $user->setGoogleId('existing-google-id');
        $user->setRoles(['ROLE_USER']);

        $this->googleClient->method('verifyIdToken')->willReturn([
            'email' => self::ALLOWED_EMAIL,
            'sub' => 'existing-google-id',
        ]);
        $this->userRepository->method('findOneBy')->willReturn($user);
        $em = $this->createEntityManagerMock();
        $em->expects(self::never())->method('persist');
        $em->expects(self::never())->method('flush');
        $this->jwtManager->method('create')->willReturn('jwt-token-nochange');

        $controller = $this->createController();
        $request = $this->createJsonRequest('{"credential": "valid-token"}');

        $response = $controller($request);

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        $data = \json_decode($response->getContent(), true);
        self::assertSame('jwt-token-nochange', $data['token']);
    }

    public function testValidationErrorsDuringUserCreationReturns400(): void
    {
        $this->googleClient->method('verifyIdToken')->willReturn([
            'email' => self::ALLOWED_EMAIL,
            'sub' => 'google-id-789',
        ]);
        $this->userRepository->method('findOneBy')->willReturn(null);

        $violation = $this->createStub(ConstraintViolationInterface::class);
        $violations = new ConstraintViolationList([$violation]);
        $this->validator->method('validate')->willReturn($violations);

        $em = $this->createEntityManagerMock();
        $em->expects(self::never())->method('persist');

        $controller = $this->createController();
        $request = $this->createJsonRequest('{"credential": "valid-token"}');

        $response = $controller($request);

        self::assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
        self::assertStringContainsString('validation', $response->getContent());
    }

    public function testRateLimitExceededReturns429(): void
    {
        // Créer un rate limiter avec une limite de 1 requête
        $rateLimiterFactory = new RateLimiterFactory(
            ['id' => 'test', 'policy' => 'fixed_window', 'interval' => '1 hour', 'limit' => 1],
            new InMemoryStorage(),
        );

        // Consommer la seule requête autorisée (getClientIp() retourne null → clé 'unknown')
        $rateLimiterFactory->create('unknown')->consume();

        $controller = new GoogleLoginController(
            self::ALLOWED_EMAIL,
            $this->entityManager,
            $this->googleClient,
            $this->jwtManager,
            new NullLogger(),
            $rateLimiterFactory,
            $this->userRepository,
            $this->validator,
        );

        $request = $this->createJsonRequest('{"credential": "valid-token"}');

        $response = $controller($request);

        self::assertSame(Response::HTTP_TOO_MANY_REQUESTS, $response->getStatusCode());
        self::assertStringContainsString('Trop de tentatives', $response->getContent());
    }

    private function createEntityManagerMock(): EntityManagerInterface&MockObject
    {
        $mock = $this->createMock(EntityManagerInterface::class);
        $this->entityManager = $mock;

        return $mock;
    }

    private function createController(): GoogleLoginController
    {
        $rateLimiterFactory = new RateLimiterFactory(
            ['id' => 'test', 'policy' => 'fixed_window', 'interval' => '1 hour', 'limit' => 100],
            new InMemoryStorage(),
        );

        return new GoogleLoginController(
            self::ALLOWED_EMAIL,
            $this->entityManager,
            $this->googleClient,
            $this->jwtManager,
            new NullLogger(),
            $rateLimiterFactory,
            $this->userRepository,
            $this->validator,
        );
    }

    private function createJsonRequest(string $content): Request
    {
        return new Request([], [], [], [], [], ['CONTENT_TYPE' => 'application/json'], $content);
    }
}

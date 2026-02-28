<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Controller\ApiController;
use App\Service\Lookup\LookupOrchestrator;
use App\Service\Lookup\LookupResult;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\RateLimiter\Storage\InMemoryStorage;

#[AllowMockObjectsWithoutExpectations]
class ApiControllerTest extends TestCase
{
    private LookupOrchestrator&MockObject $lookupOrchestrator;

    protected function setUp(): void
    {
        $this->lookupOrchestrator = $this->createMock(LookupOrchestrator::class);
        $this->lookupOrchestrator->method('getLastApiMessages')->willReturn([]);
        $this->lookupOrchestrator->method('getLastSources')->willReturn([]);
    }

    public function testIsbnLookupRateLimited(): void
    {
        $controller = $this->createController(rateLimitMax: 1);

        $request = new Request(
            query: ['isbn' => '978-2-1234-5678-0', 'type' => 'bd'],
            server: ['REMOTE_ADDR' => '127.0.0.1'],
        );

        $this->lookupOrchestrator->method('lookup')->willReturn(null);

        // Première requête : consomme le quota
        $controller->isbnLookup($request, $this->lookupOrchestrator);

        // Deuxième requête : rate limited
        $response = $controller->isbnLookup($request, $this->lookupOrchestrator);

        self::assertSame(Response::HTTP_TOO_MANY_REQUESTS, $response->getStatusCode());

        /** @var array{error: string} $body */
        $body = \json_decode((string) $response->getContent(), true);
        self::assertSame('Trop de requêtes. Réessayez plus tard.', $body['error']);
    }

    public function testTitleLookupRateLimited(): void
    {
        $controller = $this->createController(rateLimitMax: 1);

        $request = new Request(
            query: ['title' => 'One Piece', 'type' => 'manga'],
            server: ['REMOTE_ADDR' => '127.0.0.1'],
        );

        $this->lookupOrchestrator->method('lookupByTitle')->willReturn(null);

        // Première requête : consomme le quota
        $controller->titleLookup($request, $this->lookupOrchestrator);

        // Deuxième requête : rate limited
        $response = $controller->titleLookup($request, $this->lookupOrchestrator);

        self::assertSame(Response::HTTP_TOO_MANY_REQUESTS, $response->getStatusCode());

        /** @var array{error: string} $body */
        $body = \json_decode((string) $response->getContent(), true);
        self::assertSame('Trop de requêtes. Réessayez plus tard.', $body['error']);
    }

    public function testIsbnLookupAllowedWithinLimit(): void
    {
        $controller = $this->createController(rateLimitMax: 10);

        $result = new LookupResult(title: 'One Piece');

        $this->lookupOrchestrator->method('lookup')->willReturn($result);

        $request = new Request(
            query: ['isbn' => '978-2-1234-5678-0', 'type' => 'manga'],
            server: ['REMOTE_ADDR' => '127.0.0.1'],
        );

        $response = $controller->isbnLookup($request, $this->lookupOrchestrator);

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
    }

    public function testRateLimitIsPerIp(): void
    {
        $controller = $this->createController(rateLimitMax: 1);

        $this->lookupOrchestrator->method('lookup')->willReturn(null);

        // IP 1 consomme son quota
        $request1 = new Request(
            query: ['isbn' => '978-2-1234-5678-0'],
            server: ['REMOTE_ADDR' => '10.0.0.1'],
        );
        $controller->isbnLookup($request1, $this->lookupOrchestrator);

        // IP 2 a son propre quota
        $request2 = new Request(
            query: ['isbn' => '978-2-1234-5678-0'],
            server: ['REMOTE_ADDR' => '10.0.0.2'],
        );
        $response = $controller->isbnLookup($request2, $this->lookupOrchestrator);

        self::assertNotSame(Response::HTTP_TOO_MANY_REQUESTS, $response->getStatusCode());
    }

    private function createController(int $rateLimitMax = 100): ApiController
    {
        $rateLimiterFactory = new RateLimiterFactory(
            ['id' => 'api_lookup', 'interval' => '1 minute', 'limit' => $rateLimitMax, 'policy' => 'sliding_window'],
            new InMemoryStorage(),
        );

        return new ApiController($rateLimiterFactory);
    }
}

<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Lookup\Provider;

use App\Enum\ComicType;
use App\Enum\LookupMode;
use App\Service\Lookup\Contract\LookupResult;
use App\Service\Lookup\Gemini\GeminiClientPool;
use App\Service\Lookup\Provider\BedethequeLookup;
use Gemini\Contracts\ClientContract as GeminiClient;
use Gemini\Exceptions\ErrorException;
use Gemini\Responses\GenerativeModel\GenerateContentResponse;
use Gemini\Testing\ClientFake;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Cache\Adapter\AdapterInterface;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\RateLimiter\Storage\InMemoryStorage;

/**
 * Tests unitaires pour BedethequeLookup.
 */
final class BedethequeLookupTest extends TestCase
{
    private AdapterInterface $cache;
    private LoggerInterface $logger;

    protected function setUp(): void
    {
        $this->cache = $this->createStub(AdapterInterface::class);
        $this->logger = $this->createStub(LoggerInterface::class);
    }

    /**
     * Teste que getName retourne 'bedetheque'.
     */
    public function testGetNameReturnsBedetheque(): void
    {
        self::assertSame('bedetheque', $this->createProvider()->getName());
    }

    /**
     * Teste que supports retourne true pour isbn et title.
     */
    public function testSupportsIsbnAndTitle(): void
    {
        $provider = $this->createProvider();

        self::assertTrue($provider->supports(LookupMode::TITLE, null));
        self::assertTrue($provider->supports(LookupMode::TITLE, ComicType::BD));
        self::assertTrue($provider->supports(LookupMode::ISBN, null));
        self::assertTrue($provider->supports(LookupMode::ISBN, ComicType::BD));
    }

    /**
     * Teste les priorites par champ pour le type BD (reference francophone).
     */
    public function testGetFieldPriorityForBdType(): void
    {
        $provider = $this->createProvider();

        self::assertSame(150, $provider->getFieldPriority('authors', ComicType::BD));
        self::assertSame(150, $provider->getFieldPriority('description', ComicType::BD));
        self::assertSame(150, $provider->getFieldPriority('publisher', ComicType::BD));
        self::assertSame(150, $provider->getFieldPriority('latestPublishedIssue', ComicType::BD));
        self::assertSame(150, $provider->getFieldPriority('isOneShot', ComicType::BD));
        self::assertSame(150, $provider->getFieldPriority('thumbnail', ComicType::BD));
    }

    /**
     * Teste les priorites par champ pour les types non-BD.
     */
    public function testGetFieldPriorityForNonBdTypes(): void
    {
        $provider = $this->createProvider();

        self::assertSame(110, $provider->getFieldPriority('authors', ComicType::MANGA));
        self::assertSame(110, $provider->getFieldPriority('description', ComicType::COMICS));
        self::assertSame(110, $provider->getFieldPriority('publisher'));
    }

    /**
     * Teste que thumbnail a une priorite basse pour les types non-BD.
     */
    public function testGetFieldPriorityThumbnailIsLowForNonBd(): void
    {
        $provider = $this->createProvider();

        self::assertSame(50, $provider->getFieldPriority('thumbnail', ComicType::MANGA));
        self::assertSame(50, $provider->getFieldPriority('thumbnail'));
    }

    /**
     * Teste que prepareLookup retourne le resultat depuis le cache.
     */
    public function testPrepareLookupReturnsCachedResult(): void
    {
        $cachedResult = new LookupResult(source: 'bedetheque', title: 'Blacksad');

        $realCache = new ArrayAdapter();
        $realCache->get('test_key', static fn (): LookupResult => $cachedResult);
        $this->cache->method('getItem')->willReturn($realCache->getItem('test_key'));

        $provider = $this->createProvider();
        $state = $provider->prepareLookup('Blacksad', ComicType::BD, LookupMode::TITLE);

        self::assertInstanceOf(LookupResult::class, $state);
        self::assertSame('Blacksad', $state->title);

        $apiMessage = $provider->getLastApiMessage();
        self::assertSame('success', $apiMessage->status);
    }

    /**
     * Teste que prepareLookup retourne null quand le rate limit est depasse.
     */
    public function testPrepareLookupReturnsNullWhenRateLimited(): void
    {
        $realCache = new ArrayAdapter();
        $this->cache->method('getItem')->willReturn($realCache->getItem('test_key'));

        $storage = new InMemoryStorage();
        $limiterFactory = new RateLimiterFactory(
            ['id' => 'test', 'policy' => 'fixed_window', 'interval' => '1 minute', 'limit' => 1],
            $storage,
        );
        $limiterFactory->create('gemini_global')->consume();

        $provider = $this->createProvider(limiterFactory: $limiterFactory);
        $state = $provider->prepareLookup('Blacksad', ComicType::BD, LookupMode::TITLE);

        self::assertNull($state);

        $apiMessage = $provider->getLastApiMessage();
        self::assertSame('rate_limited', $apiMessage->status);
    }

    /**
     * Teste que prepareLookup retourne un tableau avec cacheKey et prompt.
     */
    public function testPrepareLookupReturnsPromptState(): void
    {
        $realCache = new ArrayAdapter();
        $this->cache->method('getItem')->willReturn($realCache->getItem('test_key'));

        $provider = $this->createProvider();
        $state = $provider->prepareLookup('Blacksad', ComicType::BD, LookupMode::TITLE);

        self::assertIsArray($state);
        self::assertArrayHasKey('cacheKey', $state);
        self::assertArrayHasKey('prompt', $state);
        self::assertStringContainsString('Blacksad', $state['prompt']);
        self::assertStringContainsString('Bedetheque', $state['prompt']);
    }

    /**
     * Teste que le prompt ISBN contient l'ISBN et mentionne Bedetheque.
     */
    public function testPrepareLookupIsbnPromptContainsIsbn(): void
    {
        $realCache = new ArrayAdapter();
        $this->cache->method('getItem')->willReturn($realCache->getItem('test_key'));

        $provider = $this->createProvider();
        $state = $provider->prepareLookup('9782205049831', ComicType::BD, LookupMode::ISBN);

        self::assertIsArray($state);
        self::assertStringContainsString('9782205049831', $state['prompt']);
        self::assertStringContainsString('Bedetheque', $state['prompt']);
        self::assertStringContainsString('ISBN', $state['prompt']);
    }

    /**
     * Teste que le prompt contient le type quand specifie.
     */
    public function testPrepareLookupPromptContainsType(): void
    {
        $realCache = new ArrayAdapter();
        $this->cache->method('getItem')->willReturn($realCache->getItem('test_key'));

        $provider = $this->createProvider();
        $state = $provider->prepareLookup('One Piece', ComicType::MANGA, LookupMode::TITLE);

        self::assertIsArray($state);
        self::assertStringContainsString('manga', $state['prompt']);
    }

    /**
     * Teste resolveLookup avec une reponse Gemini valide.
     */
    public function testResolveLookupSuccessWithValidResponse(): void
    {
        $jsonResponse = \json_encode([
            'authors' => 'Juan Diaz Canales, Juanjo Guarnido',
            'description' => 'Blacksad est une serie de bande dessinee policiere',
            'isOneShot' => false,
            'latestPublishedIssue' => 7,
            'publisher' => 'Dargaud',
            'title' => 'Blacksad',
        ]);

        $fakeResponse = GenerateContentResponse::fake([
            'candidates' => [
                [
                    'content' => [
                        'parts' => [
                            ['text' => $jsonResponse],
                        ],
                        'role' => 'model',
                    ],
                    'finishReason' => 'STOP',
                ],
            ],
        ]);

        $geminiClient = new ClientFake([$fakeResponse]);

        $realCache = new ArrayAdapter();
        $cache = $this->createMock(AdapterInterface::class);
        $cache->method('getItem')->willReturn($realCache->getItem('test_key'));
        $cache->expects(self::once())->method('save');
        $this->cache = $cache;

        $provider = $this->createProvider(pool: $this->createPoolFromClient($geminiClient));

        $state = ['cacheKey' => 'test_key', 'prompt' => 'Test prompt'];
        $result = $provider->resolveLookup($state);

        self::assertNotNull($result);
        self::assertSame('Blacksad', $result->title);
        self::assertSame('Juan Diaz Canales, Juanjo Guarnido', $result->authors);
        self::assertSame('Dargaud', $result->publisher);
        self::assertSame(7, $result->latestPublishedIssue);
        self::assertSame('bedetheque', $result->source);
    }

    /**
     * Teste resolveLookup retourne directement un LookupResult passe en etat.
     */
    public function testResolveLookupReturnsCachedResultDirectly(): void
    {
        $cachedResult = new LookupResult(source: 'bedetheque', title: 'Cached');
        $provider = $this->createProvider();

        $result = $provider->resolveLookup($cachedResult);

        self::assertSame($cachedResult, $result);
    }

    /**
     * Teste resolveLookup retourne null quand l'etat est null.
     */
    public function testResolveLookupReturnsNullForNullState(): void
    {
        self::assertNull($this->createProvider()->resolveLookup(null));
    }

    /**
     * Teste resolveLookup avec une reponse JSON dans un bloc markdown.
     */
    public function testResolveLookupHandlesMarkdownJsonBlock(): void
    {
        $jsonResponse = "```json\n{\"title\": \"Blacksad\", \"authors\": \"Canales, Guarnido\"}\n```";

        $fakeResponse = GenerateContentResponse::fake([
            'candidates' => [
                [
                    'content' => [
                        'parts' => [
                            ['text' => $jsonResponse],
                        ],
                        'role' => 'model',
                    ],
                    'finishReason' => 'STOP',
                ],
            ],
        ]);

        $geminiClient = new ClientFake([$fakeResponse]);

        $realCache = new ArrayAdapter();
        $this->cache->method('getItem')->willReturn($realCache->getItem('test_key'));

        $provider = $this->createProvider(pool: $this->createPoolFromClient($geminiClient));

        $state = ['cacheKey' => 'test_key', 'prompt' => 'Test prompt'];
        $result = $provider->resolveLookup($state);

        self::assertNotNull($result);
        self::assertSame('Blacksad', $result->title);
    }

    /**
     * Teste resolveLookup retourne null quand la reponse JSON est invalide.
     */
    public function testResolveLookupReturnsNullForInvalidJson(): void
    {
        $fakeResponse = GenerateContentResponse::fake([
            'candidates' => [
                [
                    'content' => [
                        'parts' => [
                            ['text' => 'Not JSON'],
                        ],
                        'role' => 'model',
                    ],
                    'finishReason' => 'STOP',
                ],
            ],
        ]);

        $geminiClient = new ClientFake([$fakeResponse]);

        $realCache = new ArrayAdapter();
        $this->cache->method('getItem')->willReturn($realCache->getItem('test_key'));

        $provider = $this->createProvider(pool: $this->createPoolFromClient($geminiClient));

        $state = ['cacheKey' => 'test_key', 'prompt' => 'Test prompt'];
        $result = $provider->resolveLookup($state);

        self::assertNull($result);
        self::assertSame('error', $provider->getLastApiMessage()->status);
    }

    /**
     * Teste resolveLookup retourne null quand tous les champs sont null.
     */
    public function testResolveLookupReturnsNullWhenNoUsefulData(): void
    {
        $jsonResponse = \json_encode([
            'authors' => null,
            'description' => null,
            'title' => null,
        ]);

        $fakeResponse = GenerateContentResponse::fake([
            'candidates' => [
                [
                    'content' => [
                        'parts' => [
                            ['text' => $jsonResponse],
                        ],
                        'role' => 'model',
                    ],
                    'finishReason' => 'STOP',
                ],
            ],
        ]);

        $geminiClient = new ClientFake([$fakeResponse]);

        $realCache = new ArrayAdapter();
        $this->cache->method('getItem')->willReturn($realCache->getItem('test_key'));

        $provider = $this->createProvider(pool: $this->createPoolFromClient($geminiClient));

        $state = ['cacheKey' => 'test_key', 'prompt' => 'Test prompt'];
        $result = $provider->resolveLookup($state);

        self::assertNull($result);
        self::assertSame('not_found', $provider->getLastApiMessage()->status);
    }

    /**
     * Teste resolveLookup en cas d'ErrorException avec code 429 (toutes les clés épuisées).
     */
    public function testResolveLookupErrorException429(): void
    {
        $exception = new ErrorException([
            'code' => 429,
            'message' => 'Resource exhausted',
            'status' => 'RESOURCE_EXHAUSTED',
        ]);

        $pool = $this->createMock(GeminiClientPool::class);
        $pool->method('executeWithRetry')->willThrowException($exception);

        $realCache = new ArrayAdapter();
        $this->cache->method('getItem')->willReturn($realCache->getItem('test_key'));

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('error');
        $this->logger = $logger;

        $provider = $this->createProvider(pool: $pool);

        $state = ['cacheKey' => 'test_key', 'prompt' => 'Test prompt'];
        $result = $provider->resolveLookup($state);

        self::assertNull($result);
        self::assertSame('rate_limited', $provider->getLastApiMessage()->status);
    }

    /**
     * Teste resolveLookup en cas d'ErrorException avec code autre que 429.
     */
    public function testResolveLookupErrorExceptionOtherCode(): void
    {
        $exception = new ErrorException([
            'code' => 500,
            'message' => 'Internal error',
            'status' => 'INTERNAL',
        ]);

        $pool = $this->createMock(GeminiClientPool::class);
        $pool->method('executeWithRetry')->willThrowException($exception);

        $realCache = new ArrayAdapter();
        $this->cache->method('getItem')->willReturn($realCache->getItem('test_key'));

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('error');
        $this->logger = $logger;

        $provider = $this->createProvider(pool: $pool);

        $state = ['cacheKey' => 'test_key', 'prompt' => 'Test prompt'];
        $result = $provider->resolveLookup($state);

        self::assertNull($result);
        self::assertSame('error', $provider->getLastApiMessage()->status);
    }

    /**
     * Teste resolveLookup en cas de Throwable generique.
     */
    public function testResolveLookupGenericThrowable(): void
    {
        $pool = $this->createMock(GeminiClientPool::class);
        $pool->method('executeWithRetry')->willThrowException(new \RuntimeException('Connection lost'));

        $realCache = new ArrayAdapter();
        $this->cache->method('getItem')->willReturn($realCache->getItem('test_key'));

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('error');
        $this->logger = $logger;

        $provider = $this->createProvider(pool: $pool);

        $state = ['cacheKey' => 'test_key', 'prompt' => 'Test prompt'];
        $result = $provider->resolveLookup($state);

        self::assertNull($result);
        self::assertSame('error', $provider->getLastApiMessage()->status);
        self::assertSame('Erreur de connexion', $provider->getLastApiMessage()->message);
    }

    /**
     * Teste que la reponse Gemini indiquant "pas de resultat sur bedetheque" retourne null.
     */
    public function testResolveLookupReturnsNullWhenGeminiIndicatesNoResult(): void
    {
        $jsonResponse = \json_encode([
            'authors' => null,
            'description' => null,
            'isOneShot' => null,
            'latestPublishedIssue' => null,
            'publisher' => null,
            'thumbnail' => null,
            'title' => null,
        ]);

        $fakeResponse = GenerateContentResponse::fake([
            'candidates' => [
                [
                    'content' => [
                        'parts' => [
                            ['text' => $jsonResponse],
                        ],
                        'role' => 'model',
                    ],
                    'finishReason' => 'STOP',
                ],
            ],
        ]);

        $geminiClient = new ClientFake([$fakeResponse]);

        $realCache = new ArrayAdapter();
        $this->cache->method('getItem')->willReturn($realCache->getItem('test_key'));

        $provider = $this->createProvider(pool: $this->createPoolFromClient($geminiClient));

        $state = ['cacheKey' => 'test_key', 'prompt' => 'Test prompt'];
        $result = $provider->resolveLookup($state);

        self::assertNull($result);
        self::assertSame('not_found', $provider->getLastApiMessage()->status);
    }

    /**
     * Teste que resolveLookup gère les réponses bloquées par les filtres de sécurité Gemini
     * sans lever de ValueError, et retourne un message diagnostique incluant la raison.
     */
    public function testResolveLookupHandlesBlockedPromptWithSafetyFeedback(): void
    {
        $fakeResponse = GenerateContentResponse::from([
            'candidates' => [],
            'promptFeedback' => [
                'blockReason' => 'SAFETY',
                'safetyRatings' => [
                    ['category' => 'HARM_CATEGORY_DANGEROUS_CONTENT', 'probability' => 'HIGH', 'blocked' => true],
                ],
            ],
            'usageMetadata' => ['candidatesTokenCount' => 0, 'promptTokenCount' => 10, 'totalTokenCount' => 10],
        ]);

        $geminiClient = new ClientFake([$fakeResponse]);

        $realCache = new ArrayAdapter();
        $this->cache->method('getItem')->willReturn($realCache->getItem('test_key'));

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('warning')
            ->with(self::stringContains('Bedetheque'), self::callback(
                static fn (array $context): bool => isset($context['blockReason']) && 'SAFETY' === $context['blockReason'],
            ));
        $this->logger = $logger;

        $provider = $this->createProvider(pool: $this->createPoolFromClient($geminiClient));

        $state = ['cacheKey' => 'test_key', 'prompt' => 'Test prompt'];
        $result = $provider->resolveLookup($state);

        self::assertNull($result);

        $apiMessage = $provider->getLastApiMessage();
        self::assertNotNull($apiMessage);
        self::assertSame('not_found', $apiMessage->status);
        self::assertStringContainsString('SAFETY', $apiMessage->message);
    }

    /**
     * Teste que resolveLookup gère une réponse vide (sans candidats ni feedback).
     */
    public function testResolveLookupHandlesEmptyCandidatesWithoutFeedback(): void
    {
        $fakeResponse = GenerateContentResponse::from([
            'candidates' => [],
            'promptFeedback' => null,
            'usageMetadata' => ['candidatesTokenCount' => 0, 'promptTokenCount' => 10, 'totalTokenCount' => 10],
        ]);

        $geminiClient = new ClientFake([$fakeResponse]);

        $realCache = new ArrayAdapter();
        $this->cache->method('getItem')->willReturn($realCache->getItem('test_key'));

        $provider = $this->createProvider(pool: $this->createPoolFromClient($geminiClient));

        $state = ['cacheKey' => 'test_key', 'prompt' => 'Test prompt'];
        $result = $provider->resolveLookup($state);

        self::assertNull($result);

        $apiMessage = $provider->getLastApiMessage();
        self::assertNotNull($apiMessage);
        self::assertSame('not_found', $apiMessage->status);
    }

    /**
     * Crée un GeminiClientPool mock qui délègue au client fourni.
     */
    private function createPoolFromClient(GeminiClient $client): GeminiClientPool
    {
        $pool = $this->createMock(GeminiClientPool::class);
        $pool->method('executeWithRetry')->willReturnCallback(
            static fn (callable $callback) => $callback($client, 'gemini-2.5-flash'),
        );

        return $pool;
    }

    /**
     * Cree une instance de BedethequeLookup avec des dependances configurables.
     */
    private function createProvider(
        ?GeminiClientPool $pool = null,
        ?RateLimiterFactory $limiterFactory = null,
    ): BedethequeLookup {
        $pool ??= $this->createStub(GeminiClientPool::class);

        $limiterFactory ??= new RateLimiterFactory(
            ['id' => 'test', 'policy' => 'fixed_window', 'interval' => '1 minute', 'limit' => 100],
            new InMemoryStorage(),
        );

        return new BedethequeLookup(
            $this->cache,
            $pool,
            $limiterFactory,
            $this->logger,
        );
    }
}

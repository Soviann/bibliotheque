<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Lookup;

use App\Enum\ComicType;
use App\Service\Lookup\GeminiLookup;
use App\Service\Lookup\LookupResult;
use Gemini\Contracts\ClientContract as GeminiClient;
use Gemini\Contracts\Resources\GenerativeModelContract;
use Gemini\Exceptions\ErrorException;
use Gemini\Responses\GenerativeModel\GenerateContentResponse;
use Gemini\Testing\ClientFake;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Cache\Adapter\AdapterInterface;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Cache\CacheItem;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\RateLimiter\Storage\InMemoryStorage;

/**
 * Tests unitaires pour GeminiLookup.
 */
final class GeminiLookupTest extends TestCase
{
    private AdapterInterface&MockObject $cache;
    private LoggerInterface&MockObject $logger;

    protected function setUp(): void
    {
        $this->cache = $this->createMock(AdapterInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);
    }

    /**
     * Teste que getFieldPriority retourne toujours 40.
     */
    public function testGetFieldPriorityReturns40(): void
    {
        $provider = $this->createProvider();

        self::assertSame(40, $provider->getFieldPriority('title'));
        self::assertSame(40, $provider->getFieldPriority('description'));
        self::assertSame(40, $provider->getFieldPriority('thumbnail', ComicType::MANGA));
    }

    /**
     * Teste que getName retourne 'gemini'.
     */
    public function testGetNameReturnsGemini(): void
    {
        self::assertSame('gemini', $this->createProvider()->getName());
    }

    /**
     * Teste que supports retourne true pour isbn et title.
     */
    public function testSupportsIsbnAndTitle(): void
    {
        $provider = $this->createProvider();

        self::assertTrue($provider->supports('isbn', null));
        self::assertTrue($provider->supports('title', null));
        self::assertTrue($provider->supports('isbn', ComicType::MANGA));
        self::assertTrue($provider->supports('title', ComicType::BD));
    }

    /**
     * Teste que supports retourne false pour les modes non supportes.
     */
    public function testDoesNotSupportOtherModes(): void
    {
        self::assertFalse($this->createProvider()->supports('author', null));
    }

    /**
     * Teste que prepareLookup retourne le resultat depuis le cache quand disponible.
     */
    public function testPrepareLookupReturnsCachedResult(): void
    {
        $cachedResult = new LookupResult(title: 'One Piece', source: 'gemini');

        $cacheItem = $this->createCacheItem('test_key', $cachedResult, true);
        $this->cache->method('getItem')->willReturn($cacheItem);

        $provider = $this->createProvider();
        $state = $provider->prepareLookup('One Piece', ComicType::MANGA, 'title');

        self::assertInstanceOf(LookupResult::class, $state);
        self::assertSame('One Piece', $state->title);

        $apiMessage = $provider->getLastApiMessage();
        self::assertSame('success', $apiMessage['status']);
    }

    /**
     * Teste que resolveLookup retourne directement un LookupResult passe en etat.
     */
    public function testResolveLookupReturnsCachedResultDirectly(): void
    {
        $cachedResult = new LookupResult(title: 'Cached', source: 'gemini');
        $provider = $this->createProvider();

        $result = $provider->resolveLookup($cachedResult);

        self::assertSame($cachedResult, $result);
    }

    /**
     * Teste que resolveLookup retourne null quand l'etat est null.
     */
    public function testResolveLookupReturnsNullForNullState(): void
    {
        $provider = $this->createProvider();

        $result = $provider->resolveLookup(null);

        self::assertNull($result);
    }

    /**
     * Teste que prepareLookup retourne null quand le rate limit est depasse.
     */
    public function testPrepareLookupReturnsNullWhenRateLimited(): void
    {
        $cacheItem = $this->createCacheItem('test_key', null, false);
        $this->cache->method('getItem')->willReturn($cacheItem);

        // Creer un rate limiter avec limit=1, puis le consommer pour qu'il refuse
        $storage = new InMemoryStorage();
        $limiterFactory = new RateLimiterFactory(
            ['id' => 'test', 'policy' => 'fixed_window', 'interval' => '1 minute', 'limit' => 1],
            $storage,
        );

        // Consommer le seul token disponible
        $limiterFactory->create('gemini_global')->consume();

        $provider = $this->createProvider(limiterFactory: $limiterFactory);
        $state = $provider->prepareLookup('One Piece', ComicType::MANGA, 'title');

        self::assertNull($state);

        $apiMessage = $provider->getLastApiMessage();
        self::assertSame('rate_limited', $apiMessage['status']);
    }

    /**
     * Teste que prepareLookup retourne un tableau avec cacheKey et prompt quand pas en cache et pas rate limited.
     */
    public function testPrepareLookupReturnsPromptState(): void
    {
        $cacheItem = $this->createCacheItem('test_key', null, false);
        $this->cache->method('getItem')->willReturn($cacheItem);

        $provider = $this->createProvider();
        $state = $provider->prepareLookup('9782723489003', null, 'isbn');

        self::assertIsArray($state);
        self::assertArrayHasKey('cacheKey', $state);
        self::assertArrayHasKey('prompt', $state);
        self::assertStringContainsString('9782723489003', $state['prompt']);
    }

    /**
     * Teste resolveLookup avec une reponse Gemini valide.
     */
    public function testResolveLookupSuccessWithValidResponse(): void
    {
        $jsonResponse = \json_encode([
            'authors' => 'Eiichiro Oda',
            'description' => 'Un manga de pirates',
            'isOneShot' => false,
            'latestPublishedIssue' => 107,
            'publishedDate' => '1997',
            'publisher' => 'Glenat',
            'thumbnail' => 'https://example.com/cover.jpg',
            'title' => 'One Piece',
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
        $cacheItem = $realCache->getItem('test_key');

        $this->cache->method('getItem')->willReturn($cacheItem);
        $this->cache->expects(self::once())->method('save');

        $provider = $this->createProvider(geminiClient: $geminiClient);

        $state = ['cacheKey' => 'test_key', 'prompt' => 'Test prompt'];
        $result = $provider->resolveLookup($state);

        self::assertNotNull($result);
        self::assertSame('One Piece', $result->title);
        self::assertSame('Eiichiro Oda', $result->authors);
        self::assertSame('Glenat', $result->publisher);
        self::assertSame('gemini', $result->source);
    }

    /**
     * Teste resolveLookup avec une reponse JSON dans un bloc markdown.
     */
    public function testResolveLookupHandlesMarkdownJsonBlock(): void
    {
        $jsonResponse = "```json\n{\"title\": \"Naruto\", \"authors\": \"Masashi Kishimoto\"}\n```";

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

        $provider = $this->createProvider(geminiClient: $geminiClient);

        $state = ['cacheKey' => 'test_key', 'prompt' => 'Test prompt'];
        $result = $provider->resolveLookup($state);

        self::assertNotNull($result);
        self::assertSame('Naruto', $result->title);
        self::assertSame('Masashi Kishimoto', $result->authors);
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
                            ['text' => 'This is not JSON at all'],
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

        $provider = $this->createProvider(geminiClient: $geminiClient);

        $state = ['cacheKey' => 'test_key', 'prompt' => 'Test prompt'];
        $result = $provider->resolveLookup($state);

        self::assertNull($result);

        $apiMessage = $provider->getLastApiMessage();
        self::assertSame('error', $apiMessage['status']);
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

        $provider = $this->createProvider(geminiClient: $geminiClient);

        $state = ['cacheKey' => 'test_key', 'prompt' => 'Test prompt'];
        $result = $provider->resolveLookup($state);

        self::assertNull($result);

        $apiMessage = $provider->getLastApiMessage();
        self::assertSame('not_found', $apiMessage['status']);
    }

    /**
     * Teste resolveLookup en cas d'ErrorException avec code 429.
     */
    public function testResolveLookupErrorException429(): void
    {
        $exception = new ErrorException([
            'code' => 429,
            'message' => 'Resource exhausted',
            'status' => 'RESOURCE_EXHAUSTED',
        ]);

        $geminiClient = new ClientFake([$exception]);

        $realCache = new ArrayAdapter();
        $this->cache->method('getItem')->willReturn($realCache->getItem('test_key'));

        $this->logger->expects(self::once())->method('error');

        $provider = $this->createProvider(geminiClient: $geminiClient);

        $state = ['cacheKey' => 'test_key', 'prompt' => 'Test prompt'];
        $result = $provider->resolveLookup($state);

        self::assertNull($result);

        $apiMessage = $provider->getLastApiMessage();
        self::assertSame('rate_limited', $apiMessage['status']);
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

        $geminiClient = new ClientFake([$exception]);

        $realCache = new ArrayAdapter();
        $this->cache->method('getItem')->willReturn($realCache->getItem('test_key'));

        $this->logger->expects(self::once())->method('error');

        $provider = $this->createProvider(geminiClient: $geminiClient);

        $state = ['cacheKey' => 'test_key', 'prompt' => 'Test prompt'];
        $result = $provider->resolveLookup($state);

        self::assertNull($result);

        $apiMessage = $provider->getLastApiMessage();
        self::assertSame('error', $apiMessage['status']);
    }

    /**
     * Teste resolveLookup en cas de Throwable generique.
     */
    public function testResolveLookupGenericThrowable(): void
    {
        $geminiClient = $this->createMock(GeminiClient::class);
        $model = $this->createMock(GenerativeModelContract::class);

        $geminiClient->method('generativeModel')->willReturn($model);
        $model->method('withTool')->willReturn($model);
        $model->method('generateContent')->willThrowException(new \RuntimeException('Connection lost'));

        $realCache = new ArrayAdapter();
        $this->cache->method('getItem')->willReturn($realCache->getItem('test_key'));

        $this->logger->expects(self::once())->method('error');

        $provider = $this->createProvider(geminiClient: $geminiClient);

        $state = ['cacheKey' => 'test_key', 'prompt' => 'Test prompt'];
        $result = $provider->resolveLookup($state);

        self::assertNull($result);

        $apiMessage = $provider->getLastApiMessage();
        self::assertSame('error', $apiMessage['status']);
        self::assertSame('Erreur de connexion', $apiMessage['message']);
    }

    /**
     * Teste que prepareEnrich retourne null quand le titre partiel est vide.
     */
    public function testPrepareEnrichReturnsNullForEmptyTitle(): void
    {
        $partial = new LookupResult(title: '', source: 'test');
        $provider = $this->createProvider();

        $state = $provider->prepareEnrich($partial, ComicType::MANGA);

        self::assertNull($state);
    }

    /**
     * Teste que prepareEnrich retourne null quand le titre partiel est null.
     */
    public function testPrepareEnrichReturnsNullForNullTitle(): void
    {
        $partial = new LookupResult(source: 'test');
        $provider = $this->createProvider();

        $state = $provider->prepareEnrich($partial, ComicType::MANGA);

        self::assertNull($state);
    }

    /**
     * Teste que prepareEnrich retourne un LookupResult depuis le cache sans appeler l'API.
     */
    public function testPrepareEnrichReturnsCachedResult(): void
    {
        $cachedResult = new LookupResult(
            description: 'Cached description',
            source: 'gemini',
            title: 'One Piece',
        );

        $cacheItem = $this->createCacheItem('test_key', $cachedResult, true);
        $this->cache->method('getItem')->willReturn($cacheItem);

        $partial = new LookupResult(title: 'One Piece', source: 'other');
        $provider = $this->createProvider();
        $state = $provider->prepareEnrich($partial, ComicType::MANGA);

        self::assertInstanceOf(LookupResult::class, $state);
        self::assertSame('One Piece', $state->title);
        self::assertSame('Cached description', $state->description);

        $apiMessage = $provider->getLastApiMessage();
        self::assertSame('success', $apiMessage['status']);
        self::assertStringContainsString('cache', $apiMessage['message']);
    }

    /**
     * Teste que prepareEnrich construit un prompt d'enrichissement.
     */
    public function testPrepareEnrichBuildsPreparedState(): void
    {
        $partial = new LookupResult(title: 'One Piece', authors: 'Oda', source: 'test');

        $cacheItem = $this->createCacheItem('test_key', null, false);
        $this->cache->method('getItem')->willReturn($cacheItem);

        $provider = $this->createProvider();
        $state = $provider->prepareEnrich($partial, ComicType::MANGA);

        self::assertIsArray($state);
        self::assertArrayHasKey('prompt', $state);
        self::assertStringContainsString('One Piece', $state['prompt']);
        self::assertStringContainsString('manga', $state['prompt']);
    }

    /**
     * Teste que resolveEnrich delegue a resolveLookup.
     */
    public function testResolveEnrichDelegatesToResolveLookup(): void
    {
        $provider = $this->createProvider();

        $result = $provider->resolveEnrich(null);

        self::assertNull($result);
    }

    /**
     * Teste que resolveEnrich retourne un LookupResult passe directement.
     */
    public function testResolveEnrichReturnsCachedResult(): void
    {
        $cachedResult = new LookupResult(title: 'Test', source: 'gemini');
        $provider = $this->createProvider();

        $result = $provider->resolveEnrich($cachedResult);

        self::assertSame($cachedResult, $result);
    }

    /**
     * Teste le prompt en mode title.
     */
    public function testPrepareLookupTitleModePromptContainsTitle(): void
    {
        $cacheItem = $this->createCacheItem('test_key', null, false);
        $this->cache->method('getItem')->willReturn($cacheItem);

        $provider = $this->createProvider();
        $state = $provider->prepareLookup('Dragon Ball', ComicType::MANGA, 'title');

        self::assertIsArray($state);
        self::assertStringContainsString('Dragon Ball', $state['prompt']);
        self::assertStringContainsString('titre', $state['prompt']);
    }

    /**
     * Teste callGemini avec isOneShot non-bool dans la reponse → null.
     */
    public function testResolveLookupNonBoolIsOneShotBecomesNull(): void
    {
        $jsonResponse = \json_encode([
            'authors' => 'Test Author',
            'isOneShot' => 'yes',
            'title' => 'Test',
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

        $provider = $this->createProvider(geminiClient: $geminiClient);

        $state = ['cacheKey' => 'test_key', 'prompt' => 'Test prompt'];
        $result = $provider->resolveLookup($state);

        self::assertNotNull($result);
        self::assertNull($result->isOneShot);
    }

    /**
     * Teste callGemini avec latestPublishedIssue non-int dans la reponse → null.
     */
    public function testResolveLookupNonIntLatestPublishedIssueBecomesNull(): void
    {
        $jsonResponse = \json_encode([
            'authors' => 'Test Author',
            'latestPublishedIssue' => '42',
            'title' => 'Test',
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

        $provider = $this->createProvider(geminiClient: $geminiClient);

        $state = ['cacheKey' => 'test_key', 'prompt' => 'Test prompt'];
        $result = $provider->resolveLookup($state);

        self::assertNotNull($result);
        self::assertNull($result->latestPublishedIssue);
    }

    /**
     * Teste prepareWithCache avec une entree cache corrompue (non-LookupResult) → passe au rate limit.
     */
    public function testPrepareLookupCorruptedCacheEntryFallsThrough(): void
    {
        $cacheItem = $this->createCacheItem('test_key', 'corrupted_string_value', true);
        $this->cache->method('getItem')->willReturn($cacheItem);

        $provider = $this->createProvider();
        $state = $provider->prepareLookup('Test', ComicType::MANGA, 'title');

        // La valeur corrompue est ignoree, on passe au rate limit et on obtient un prompt
        self::assertIsArray($state);
        self::assertArrayHasKey('prompt', $state);
    }

    /**
     * Teste parseJsonFromText retourne null quand json_decode donne un scalaire.
     */
    public function testResolveLookupJsonScalarResponseReturnsNull(): void
    {
        $fakeResponse = GenerateContentResponse::fake([
            'candidates' => [
                [
                    'content' => [
                        'parts' => [
                            ['text' => '"just a string"'],
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

        $provider = $this->createProvider(geminiClient: $geminiClient);

        $state = ['cacheKey' => 'test_key', 'prompt' => 'Test prompt'];
        $result = $provider->resolveLookup($state);

        self::assertNull($result);

        $apiMessage = $provider->getLastApiMessage();
        self::assertSame('error', $apiMessage['status']);
    }

    /**
     * Teste consumeRateLimit rejete → rate_limited, prepareLookup retourne null.
     */
    public function testPrepareLookupRateLimitRejectedReturnsNull(): void
    {
        $cacheItem = $this->createCacheItem('test_key', null, false);
        $this->cache->method('getItem')->willReturn($cacheItem);

        $storage = new InMemoryStorage();
        $limiterFactory = new RateLimiterFactory(
            ['id' => 'test', 'policy' => 'fixed_window', 'interval' => '1 minute', 'limit' => 1],
            $storage,
        );

        // Consommer le seul token disponible
        $limiterFactory->create('gemini_global')->consume();

        $provider = $this->createProvider(limiterFactory: $limiterFactory);
        $state = $provider->prepareLookup('Test', ComicType::MANGA, 'title');

        self::assertNull($state);

        $apiMessage = $provider->getLastApiMessage();
        self::assertSame('rate_limited', $apiMessage['status']);
    }

    /**
     * Cree un CacheItem avec les valeurs souhaitees via reflexion.
     */
    private function createCacheItem(string $key, mixed $value, bool $isHit): CacheItem
    {
        $realCache = new ArrayAdapter();

        if ($isHit && null !== $value) {
            $realCache->get($key, static fn () => $value);
        }

        return $realCache->getItem($key);
    }

    /**
     * Cree une instance de GeminiLookup avec des dependances configurables.
     */
    private function createProvider(
        ?GeminiClient $geminiClient = null,
        ?RateLimiterFactory $limiterFactory = null,
    ): GeminiLookup {
        $geminiClient ??= $this->createMock(GeminiClient::class);

        $limiterFactory ??= new RateLimiterFactory(
            ['id' => 'test', 'policy' => 'fixed_window', 'interval' => '1 minute', 'limit' => 100],
            new InMemoryStorage(),
        );

        return new GeminiLookup(
            $this->cache,
            $geminiClient,
            $limiterFactory,
            $this->logger,
        );
    }
}

<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Merge;

use App\DTO\MergeGroup;
use App\DTO\MergeGroupEntry;
use App\Entity\ComicSeries;
use App\Service\Lookup\GeminiClientPool;
use App\Service\Merge\SeriesGroupDetector;
use Gemini\Contracts\ClientContract as GeminiClient;
use Gemini\Responses\GenerativeModel\GenerateContentResponse;
use Gemini\Testing\ClientFake;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\RateLimiter\Storage\InMemoryStorage;

/**
 * Tests unitaires pour SeriesGroupDetector.
 */
final class SeriesGroupDetectorTest extends TestCase
{
    private LoggerInterface $logger;

    protected function setUp(): void
    {
        $this->logger = $this->createStub(LoggerInterface::class);
    }

    /**
     * Teste la detection correcte de groupes de series.
     */
    public function testDetectGroupsSeriesCorrectly(): void
    {
        $series = [
            $this->createSeries(1, 'Astérix - Astérix le Gaulois'),
            $this->createSeries(2, 'Astérix - Astérix chez les Bretons'),
            $this->createSeries(3, 'Astérix - Le Tour de Gaule'),
            $this->createSeries(4, 'Tintin - Les Bijoux de la Castafiore'),
            $this->createSeries(5, 'Tintin - On a marché sur la Lune'),
        ];

        $responseA = \json_encode([
            [
                'entries' => [
                    ['id' => 1, 'tomeNumber' => 1],
                    ['id' => 2, 'tomeNumber' => 8],
                    ['id' => 3, 'tomeNumber' => 5],
                ],
                'title' => 'Astérix',
            ],
        ]);

        $responseT = \json_encode([
            [
                'entries' => [
                    ['id' => 4, 'tomeNumber' => 21],
                    ['id' => 5, 'tomeNumber' => 17],
                ],
                'title' => 'Tintin',
            ],
        ]);

        $fakeResponseA = GenerateContentResponse::fake([
            'candidates' => [
                [
                    'content' => [
                        'parts' => [
                            ['text' => $responseA],
                        ],
                        'role' => 'model',
                    ],
                    'finishReason' => 'STOP',
                ],
            ],
        ]);

        $fakeResponseT = GenerateContentResponse::fake([
            'candidates' => [
                [
                    'content' => [
                        'parts' => [
                            ['text' => $responseT],
                        ],
                        'role' => 'model',
                    ],
                    'finishReason' => 'STOP',
                ],
            ],
        ]);

        $geminiClient = new ClientFake([$fakeResponseA, $fakeResponseT]);
        $detector = $this->createDetector(geminiClient: $geminiClient);

        $groups = $detector->detect($series);

        self::assertCount(2, $groups);

        // Premier groupe : Astérix
        self::assertInstanceOf(MergeGroup::class, $groups[0]);
        self::assertSame('Astérix', $groups[0]->suggestedTitle);
        self::assertCount(3, $groups[0]->entries);

        self::assertInstanceOf(MergeGroupEntry::class, $groups[0]->entries[0]);
        self::assertSame(1, $groups[0]->entries[0]->seriesId);
        self::assertSame('Astérix - Astérix le Gaulois', $groups[0]->entries[0]->originalTitle);
        self::assertSame(1, $groups[0]->entries[0]->suggestedTomeNumber);

        // Second groupe : Tintin
        self::assertSame('Tintin', $groups[1]->suggestedTitle);
        self::assertCount(2, $groups[1]->entries);
        self::assertSame(4, $groups[1]->entries[0]->seriesId);
        self::assertSame(21, $groups[1]->entries[0]->suggestedTomeNumber);
    }

    /**
     * Teste que les groupes avec une seule entree sont filtres.
     */
    public function testDetectFiltersSingleEntryGroups(): void
    {
        $series = [
            $this->createSeries(1, 'Astérix - Astérix le Gaulois'),
            $this->createSeries(2, 'Astérix - Astérix chez les Bretons'),
            $this->createSeries(3, 'Blacksad'),
        ];

        $jsonResponse = \json_encode([
            [
                'entries' => [
                    ['id' => 1, 'tomeNumber' => 1],
                    ['id' => 2, 'tomeNumber' => 8],
                ],
                'title' => 'Astérix',
            ],
            [
                'entries' => [
                    ['id' => 3, 'tomeNumber' => null],
                ],
                'title' => 'Blacksad',
            ],
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
        $detector = $this->createDetector(geminiClient: $geminiClient);

        $groups = $detector->detect($series);

        self::assertCount(1, $groups);
        self::assertSame('Astérix', $groups[0]->suggestedTitle);
    }

    /**
     * Teste que detect retourne un tableau vide si Gemini retourne du JSON invalide.
     */
    public function testDetectReturnsEmptyOnInvalidJson(): void
    {
        $series = [
            $this->createSeries(1, 'Astérix'),
            $this->createSeries(2, 'Tintin'),
        ];

        $fakeResponse = GenerateContentResponse::fake([
            'candidates' => [
                [
                    'content' => [
                        'parts' => [
                            ['text' => 'Ceci nest pas du JSON valide'],
                        ],
                        'role' => 'model',
                    ],
                    'finishReason' => 'STOP',
                ],
            ],
        ]);

        $geminiClient = new ClientFake([$fakeResponse]);
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('warning');

        $detector = $this->createDetector(geminiClient: $geminiClient, logger: $logger);

        $groups = $detector->detect($series);

        self::assertSame([], $groups);
    }

    /**
     * Teste que detect lève une exception si le rate limiter refuse.
     */
    public function testDetectThrowsOnRateLimit(): void
    {
        $series = [
            $this->createSeries(1, 'Astérix'),
            $this->createSeries(2, 'Tintin'),
        ];

        $storage = new InMemoryStorage();
        $limiterFactory = new RateLimiterFactory(
            ['id' => 'test', 'policy' => 'fixed_window', 'interval' => '1 minute', 'limit' => 1],
            $storage,
        );
        // Consommer le seul jeton disponible
        $limiterFactory->create('gemini_global')->consume();

        $detector = $this->createDetector(limiterFactory: $limiterFactory);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Rate limit atteint');

        $detector->detect($series);
    }

    /**
     * Teste que detect regroupe alphabetiquement et appelle Gemini par batch.
     */
    public function testDetectBatchesAlphabetically(): void
    {
        $series = [
            $this->createSeries(1, 'Astérix - Astérix le Gaulois'),
            $this->createSeries(2, 'Astérix - Astérix chez les Bretons'),
            $this->createSeries(3, 'Akira'),
            $this->createSeries(4, 'Blacksad - Quelque part entre les ombres'),
            $this->createSeries(5, 'Blacksad - Arctic Nation'),
        ];

        $responseA = \json_encode([
            [
                'entries' => [
                    ['id' => 1, 'tomeNumber' => 1],
                    ['id' => 2, 'tomeNumber' => 8],
                ],
                'title' => 'Astérix',
            ],
        ]);

        $responseB = \json_encode([
            [
                'entries' => [
                    ['id' => 4, 'tomeNumber' => 1],
                    ['id' => 5, 'tomeNumber' => 2],
                ],
                'title' => 'Blacksad',
            ],
        ]);

        $fakeResponseA = GenerateContentResponse::fake([
            'candidates' => [
                [
                    'content' => [
                        'parts' => [
                            ['text' => $responseA],
                        ],
                        'role' => 'model',
                    ],
                    'finishReason' => 'STOP',
                ],
            ],
        ]);

        $fakeResponseB = GenerateContentResponse::fake([
            'candidates' => [
                [
                    'content' => [
                        'parts' => [
                            ['text' => $responseB],
                        ],
                        'role' => 'model',
                    ],
                    'finishReason' => 'STOP',
                ],
            ],
        ]);

        $geminiClient = new ClientFake([$fakeResponseA, $fakeResponseB]);
        $detector = $this->createDetector(geminiClient: $geminiClient);

        $groups = $detector->detect($series);

        self::assertCount(2, $groups);
        self::assertSame('Astérix', $groups[0]->suggestedTitle);
        self::assertSame('Blacksad', $groups[1]->suggestedTitle);
    }

    /**
     * Cree un stub de ComicSeries avec un ID et un titre.
     */
    private function createSeries(int $id, string $title): ComicSeries
    {
        $series = $this->createStub(ComicSeries::class);
        $series->method('getId')->willReturn($id);
        $series->method('getTitle')->willReturn($title);

        return $series;
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
     * Cree une instance de SeriesGroupDetector avec des dependances configurables.
     */
    private function createDetector(
        ?GeminiClientPool $pool = null,
        ?GeminiClient $geminiClient = null,
        ?LoggerInterface $logger = null,
        ?RateLimiterFactory $limiterFactory = null,
    ): SeriesGroupDetector {
        $pool ??= ($geminiClient instanceof GeminiClient ? $this->createPoolFromClient($geminiClient) : $this->createStub(GeminiClientPool::class));
        $logger ??= $this->logger;

        $limiterFactory ??= new RateLimiterFactory(
            ['id' => 'test', 'policy' => 'fixed_window', 'interval' => '1 minute', 'limit' => 100],
            new InMemoryStorage(),
        );

        return new SeriesGroupDetector(
            $pool,
            $limiterFactory,
            $logger,
        );
    }
}

<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Merge;

use App\DTO\MergeGroup;
use App\DTO\MergeGroupEntry;
use App\Entity\Author;
use App\Entity\ComicSeries;
use App\Entity\Tome;
use App\Enum\ComicType;
use App\Service\Lookup\Gemini\GeminiClientPool;
use App\Service\Merge\MergePreviewBuilder;
use Gemini\Contracts\ClientContract as GeminiClient;
use Gemini\Responses\GenerativeModel\GenerateContentResponse;
use Gemini\Testing\ClientFake;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\RateLimiter\Storage\InMemoryStorage;

class MergePreviewBuilderTest extends TestCase
{
    /**
     * 3 one-shots fusionnés : chacun doit devenir un tome avec le numéro suggéré.
     */
    public function testBuildFromGroupWithOneShots(): void
    {
        $series1 = $this->createSeries(1, 'Astérix chez les Bretons', ComicType::BD, isOneShot: true);
        $this->addTome($series1, 1, bought: true, read: true);

        $series2 = $this->createSeries(2, 'Astérix le Gaulois', ComicType::BD, isOneShot: true);
        $this->addTome($series2, 1, bought: true);

        $series3 = $this->createSeries(3, 'Astérix et Cléopâtre', ComicType::BD, isOneShot: true);
        $this->addTome($series3, 1, onNas: true);

        $group = new MergeGroup(
            entries: [
                new MergeGroupEntry(originalTitle: 'Astérix chez les Bretons', seriesId: 1, suggestedTomeNumber: 8),
                new MergeGroupEntry(originalTitle: 'Astérix le Gaulois', seriesId: 2, suggestedTomeNumber: 1),
                new MergeGroupEntry(originalTitle: 'Astérix et Cléopâtre', seriesId: 3, suggestedTomeNumber: 6),
            ],
            suggestedTitle: 'Astérix',
        );

        $seriesMap = [1 => $series1, 2 => $series2, 3 => $series3];

        $builder = $this->createBuilder();
        $preview = $builder->buildFromGroup($group, $seriesMap);

        self::assertSame('Astérix', $preview->title);
        self::assertCount(3, $preview->tomes);

        // Tomes triés par numéro
        self::assertSame(1, $preview->tomes[0]->number);
        self::assertSame('Astérix le Gaulois', $preview->tomes[0]->title);
        self::assertTrue($preview->tomes[0]->bought);

        self::assertSame(6, $preview->tomes[1]->number);
        self::assertSame('Astérix et Cléopâtre', $preview->tomes[1]->title);
        self::assertTrue($preview->tomes[1]->onNas);

        self::assertSame(8, $preview->tomes[2]->number);
        self::assertSame('Astérix chez les Bretons', $preview->tomes[2]->title);
        self::assertTrue($preview->tomes[2]->bought);
        self::assertTrue($preview->tomes[2]->read);
    }

    /**
     * Mix : une série multi-tomes + un one-shot.
     */
    public function testBuildFromGroupWithMultiTomeSeries(): void
    {
        $series1 = $this->createSeries(1, 'Naruto', ComicType::MANGA);
        $this->addTome($series1, 1, bought: true);
        $this->addTome($series1, 2, bought: true, read: true);
        $this->addTome($series1, 3);

        $series2 = $this->createSeries(2, 'Naruto - Gaiden', ComicType::MANGA, isOneShot: true);
        $this->addTome($series2, 1, bought: true, onNas: true);

        $group = new MergeGroup(
            entries: [
                new MergeGroupEntry(originalTitle: 'Naruto', seriesId: 1, suggestedTomeNumber: null),
                new MergeGroupEntry(originalTitle: 'Naruto - Gaiden', seriesId: 2, suggestedTomeNumber: 4),
            ],
            suggestedTitle: 'Naruto',
        );

        $seriesMap = [1 => $series1, 2 => $series2];

        $builder = $this->createBuilder();
        $preview = $builder->buildFromGroup($group, $seriesMap);

        self::assertCount(4, $preview->tomes);

        // Tomes 1-3 de la série multi-tomes
        self::assertSame(1, $preview->tomes[0]->number);
        self::assertNull($preview->tomes[0]->title);
        self::assertTrue($preview->tomes[0]->bought);

        self::assertSame(2, $preview->tomes[1]->number);
        self::assertTrue($preview->tomes[1]->read);

        self::assertSame(3, $preview->tomes[2]->number);

        // One-shot → tome 4 avec titre original
        self::assertSame(4, $preview->tomes[3]->number);
        self::assertSame('Naruto - Gaiden', $preview->tomes[3]->title);
        self::assertTrue($preview->tomes[3]->bought);
        self::assertTrue($preview->tomes[3]->onNas);
    }

    /**
     * Réconciliation des métadonnées : description la plus longue, premier coverUrl, union des auteurs, etc.
     */
    public function testBuildFromGroupReconciliesMetadata(): void
    {
        $author1 = $this->createAuthor('Goscinny');
        $author2 = $this->createAuthor('Uderzo');
        $author3 = $this->createAuthor('goscinny'); // doublon casse différente

        $series1 = $this->createSeries(1, 'Série A', ComicType::BD);
        $series1->setDescription('Court');
        $series1->setCoverUrl(null);
        $series1->setPublisher(null);
        $series1->setLatestPublishedIssue(5);
        $series1->setLatestPublishedIssueComplete(false);
        $series1->addAuthor($author1);
        $this->addTome($series1, 1);

        $series2 = $this->createSeries(2, 'Série B', ComicType::BD);
        $series2->setDescription('Une description beaucoup plus longue que la première');
        $series2->setCoverUrl('https://example.com/cover.jpg');
        $series2->setPublisher('Dargaud');
        $series2->setLatestPublishedIssue(10);
        $series2->setLatestPublishedIssueComplete(true);
        $series2->addAuthor($author2);
        $series2->addAuthor($author3);
        $this->addTome($series2, 1);

        $group = new MergeGroup(
            entries: [
                new MergeGroupEntry(originalTitle: 'Série A', seriesId: 1, suggestedTomeNumber: 1),
                new MergeGroupEntry(originalTitle: 'Série B', seriesId: 2, suggestedTomeNumber: 2),
            ],
            suggestedTitle: 'Série Fusionnée',
        );

        $seriesMap = [1 => $series1, 2 => $series2];

        $builder = $this->createBuilder();
        $preview = $builder->buildFromGroup($group, $seriesMap);

        // Description la plus longue
        self::assertSame('Une description beaucoup plus longue que la première', $preview->description);

        // Premier coverUrl non null
        self::assertSame('https://example.com/cover.jpg', $preview->coverUrl);

        // Premier publisher non null
        self::assertSame('Dargaud', $preview->publisher);

        // Auteurs dédupliqués (case-insensitive)
        self::assertCount(2, $preview->authors);
        self::assertContains('Goscinny', $preview->authors);
        self::assertContains('Uderzo', $preview->authors);

        // isOneShot toujours false
        self::assertFalse($preview->isOneShot);

        // Max latestPublishedIssue
        self::assertSame(10, $preview->latestPublishedIssue);

        // Complete si au moins une source est complete
        self::assertTrue($preview->latestPublishedIssueComplete);

        // Type de la première série
        self::assertSame('bd', $preview->type);

        // IDs source
        self::assertSame([1, 2], $preview->sourceSeriesIds);
    }

    /**
     * Sélection manuelle : retourne un fallback immédiat (titre 1ère série, numéros séquentiels).
     */
    public function testBuildFromManualSelectionReturnsFallback(): void
    {
        $series1 = $this->createSeries(10, 'Lucky Luke - Daisy Town', ComicType::BD, isOneShot: true);
        $this->addTome($series1, 1, bought: true);

        $series2 = $this->createSeries(20, 'Lucky Luke - Le pied-tendre', ComicType::BD, isOneShot: true);
        $this->addTome($series2, 1, bought: true);

        $builder = $this->createBuilder();
        $preview = $builder->buildFromManualSelection([$series1, $series2]);

        // Fallback : titre de la première série
        self::assertSame('Lucky Luke - Daisy Town', $preview->title);
        self::assertCount(2, $preview->tomes);

        // Numéros séquentiels
        self::assertSame(1, $preview->tomes[0]->number);
        self::assertSame(2, $preview->tomes[1]->number);
    }

    /**
     * suggestFromGemini retourne les suggestions de Gemini.
     */
    public function testSuggestFromGeminiReturnsGeminiResult(): void
    {
        $series1 = $this->createSeries(10, 'Lucky Luke - Daisy Town', ComicType::BD, isOneShot: true);
        $this->addTome($series1, 1, bought: true);

        $series2 = $this->createSeries(20, 'Lucky Luke - Le pied-tendre', ComicType::BD, isOneShot: true);
        $this->addTome($series2, 1, bought: true);

        $geminiResponse = \json_encode([
            'title' => 'Lucky Luke',
            'entries' => [
                ['id' => 10, 'tomeNumber' => 51],
                ['id' => 20, 'tomeNumber' => 33],
            ],
        ], \JSON_THROW_ON_ERROR);

        $fakeResponse = GenerateContentResponse::fake([
            'candidates' => [
                [
                    'content' => [
                        'parts' => [['text' => $geminiResponse]],
                        'role' => 'model',
                    ],
                    'finishReason' => 'STOP',
                ],
            ],
        ]);

        $geminiClient = new ClientFake([$fakeResponse]);

        $builder = $this->createBuilder($geminiClient);
        $result = $builder->suggestFromGemini([$series1, $series2]);

        self::assertNotNull($result);
        self::assertSame('Lucky Luke', $result['title']);
        self::assertCount(2, $result['entries']);
        self::assertSame(10, $result['entries'][0]['id']);
        self::assertSame(51, $result['entries'][0]['tomeNumber']);
        self::assertSame(20, $result['entries'][1]['id']);
        self::assertSame(33, $result['entries'][1]['tomeNumber']);
    }

    /**
     * suggestFromGemini retourne null quand Gemini échoue.
     */
    public function testSuggestFromGeminiReturnsNullOnError(): void
    {
        $series1 = $this->createSeries(1, 'Tintin au Tibet', ComicType::BD, isOneShot: true);
        $this->addTome($series1, 1, bought: true);

        $series2 = $this->createSeries(2, 'Tintin au Congo', ComicType::BD, isOneShot: true);
        $this->addTome($series2, 1);

        $fakeResponse = GenerateContentResponse::fake([
            'candidates' => [
                [
                    'content' => [
                        'parts' => [['text' => 'Ceci n\'est pas du JSON']],
                        'role' => 'model',
                    ],
                    'finishReason' => 'STOP',
                ],
            ],
        ]);

        $geminiClient = new ClientFake([$fakeResponse]);

        $builder = $this->createBuilder($geminiClient);
        $result = $builder->suggestFromGemini([$series1, $series2]);

        self::assertNull($result);
    }

    private function createPoolFromClient(GeminiClient $client): GeminiClientPool
    {
        $pool = $this->createMock(GeminiClientPool::class);
        $pool->method('executeWithRetry')->willReturnCallback(
            static fn (callable $callback) => $callback($client, 'gemini-2.5-flash'),
        );

        return $pool;
    }

    private function createBuilder(?ClientFake $geminiClient = null): MergePreviewBuilder
    {
        $pool = $geminiClient instanceof ClientFake
            ? $this->createPoolFromClient($geminiClient)
            : $this->createStub(GeminiClientPool::class);

        $limiterFactory = new RateLimiterFactory(
            ['id' => 'test', 'policy' => 'fixed_window', 'interval' => '1 minute', 'limit' => 100],
            new InMemoryStorage(),
        );

        return new MergePreviewBuilder(
            geminiClientPool: $pool,
            limiterFactory: $limiterFactory,
            logger: new NullLogger(),
        );
    }

    private function createSeries(int $id, string $title, ComicType $type, bool $isOneShot = false): ComicSeries
    {
        $series = new ComicSeries();
        $series->setTitle($title);
        $series->setType($type);
        $series->setIsOneShot($isOneShot);

        $ref = new \ReflectionProperty(ComicSeries::class, 'id');
        $ref->setValue($series, $id);

        return $series;
    }

    private function createAuthor(string $name): Author
    {
        $author = new Author();
        $author->setName($name);

        return $author;
    }

    private function addTome(
        ComicSeries $series,
        int $number,
        bool $bought = false,
        bool $onNas = false,
        bool $read = false,
        ?string $isbn = null,
        ?string $title = null,
        ?int $tomeEnd = null,
    ): Tome {
        $tome = new Tome();
        $tome->setBought($bought);
        $tome->setIsbn($isbn);
        $tome->setNumber($number);
        $tome->setOnNas($onNas);
        $tome->setRead($read);
        $tome->setTitle($title);
        $tome->setTomeEnd($tomeEnd);

        $series->addTome($tome);

        return $tome;
    }
}

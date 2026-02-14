<?php

declare(strict_types=1);

namespace App\Tests\Service\Lookup;

use App\Enum\ComicType;
use App\Service\Lookup\EnrichableLookupProviderInterface;
use App\Service\Lookup\LookupOrchestrator;
use App\Service\Lookup\LookupProviderInterface;
use App\Service\Lookup\LookupResult;
use PHPUnit\Framework\TestCase;

class LookupOrchestratorTest extends TestCase
{
    public function testLookupByIsbnCallsAllSupportingProviders(): void
    {
        $google = $this->createProvider('google_books', ['isbn'], new LookupResult(
            source: 'google_books',
            title: 'Google Title',
        ));
        $openLibrary = $this->createProvider('open_library', ['isbn'], new LookupResult(
            publisher: 'OL Publisher',
            source: 'open_library',
        ));

        $orchestrator = new LookupOrchestrator([$google, $openLibrary]);
        $result = $orchestrator->lookup('9781234567890');

        self::assertNotNull($result);
        self::assertSame('Google Title', $result->title);
        self::assertSame('OL Publisher', $result->publisher);
    }

    public function testLookupByIsbnMergesResults(): void
    {
        $google = $this->createProvider('google_books', ['isbn'], new LookupResult(
            authors: 'Author Google',
            description: 'Description Google',
            source: 'google_books',
            title: 'Google Title',
        ));
        $openLibrary = $this->createProvider('open_library', ['isbn'], new LookupResult(
            authors: 'Author OL',
            publisher: 'OL Publisher',
            source: 'open_library',
            thumbnail: 'https://ol.jpg',
        ));

        $orchestrator = new LookupOrchestrator([$google, $openLibrary]);
        $result = $orchestrator->lookup('9781234567890');

        self::assertNotNull($result);
        // Google prioritaire
        self::assertSame('Author Google', $result->authors);
        self::assertSame('Description Google', $result->description);
        self::assertSame('Google Title', $result->title);
        // OL complète
        self::assertSame('OL Publisher', $result->publisher);
        self::assertSame('https://ol.jpg', $result->thumbnail);
    }

    public function testLookupByIsbnReturnNullWhenNoResults(): void
    {
        $google = $this->createProvider('google_books', ['isbn'], null);
        $openLibrary = $this->createProvider('open_library', ['isbn'], null);

        $orchestrator = new LookupOrchestrator([$google, $openLibrary]);
        $result = $orchestrator->lookup('0000000000');

        self::assertNull($result);
    }

    public function testLookupByIsbnWithEmptyQueryReturnsNull(): void
    {
        $orchestrator = new LookupOrchestrator([]);

        self::assertNull($orchestrator->lookup(''));
        self::assertNull($orchestrator->lookup('   '));
    }

    public function testLookupByIsbnNormalizesQuery(): void
    {
        $queryCaptured = null;
        $google = $this->createCapturingProvider('google_books', ['isbn'], new LookupResult(
            source: 'google_books',
            title: 'Test',
        ), $queryCaptured);

        $orchestrator = new LookupOrchestrator([$google]);
        $orchestrator->lookup('978-2-505-00123-4');

        self::assertSame('9782505001234', $queryCaptured);
    }

    public function testLookupByIsbnAddsIsbnToResult(): void
    {
        $google = $this->createProvider('google_books', ['isbn'], new LookupResult(
            source: 'google_books',
            title: 'Test',
        ));

        $orchestrator = new LookupOrchestrator([$google]);
        $result = $orchestrator->lookup('9781234567890');

        self::assertSame('9781234567890', $result->isbn);
    }

    public function testLookupByTitleCallsMangaProviders(): void
    {
        $google = $this->createProvider('google_books', ['title'], new LookupResult(
            source: 'google_books',
            title: 'Google Title',
        ));
        $anilist = $this->createProvider('anilist', ['title'], new LookupResult(
            authors: 'Mangaka',
            source: 'anilist',
            thumbnail: 'https://anilist.jpg',
        ), ComicType::MANGA);

        $orchestrator = new LookupOrchestrator([$google, $anilist]);
        $result = $orchestrator->lookupByTitle('Test Manga', ComicType::MANGA);

        self::assertNotNull($result);
        self::assertSame('Google Title', $result->title);
        self::assertSame('Mangaka', $result->authors);
    }

    public function testLookupByTitleAniListOverridesThumbnailForManga(): void
    {
        $google = $this->createProvider('google_books', ['title'], new LookupResult(
            source: 'google_books',
            thumbnail: 'https://google.jpg',
            title: 'Manga',
        ));
        $anilist = $this->createProvider('anilist', ['title'], new LookupResult(
            source: 'anilist',
            thumbnail: 'https://anilist.jpg',
        ), ComicType::MANGA);

        $orchestrator = new LookupOrchestrator([$google, $anilist]);
        $result = $orchestrator->lookupByTitle('Manga', ComicType::MANGA);

        self::assertSame('https://anilist.jpg', $result->thumbnail);
    }

    public function testLookupByTitleAniListOverridesIsOneShotForManga(): void
    {
        $google = $this->createProvider('google_books', ['title'], new LookupResult(
            isOneShot: true,
            source: 'google_books',
            title: 'Manga',
        ));
        $anilist = $this->createProvider('anilist', ['title'], new LookupResult(
            isOneShot: false,
            source: 'anilist',
        ), ComicType::MANGA);

        $orchestrator = new LookupOrchestrator([$google, $anilist]);
        $result = $orchestrator->lookupByTitle('Manga', ComicType::MANGA);

        self::assertFalse($result->isOneShot);
    }

    public function testLookupByTitleAniListDoesNotOverwriteExistingFields(): void
    {
        $google = $this->createProvider('google_books', ['title'], new LookupResult(
            authors: 'Google Author',
            description: 'Google Desc',
            source: 'google_books',
            title: 'Manga',
        ));
        $anilist = $this->createProvider('anilist', ['title'], new LookupResult(
            authors: 'AniList Author',
            description: 'AniList Desc',
            source: 'anilist',
        ), ComicType::MANGA);

        $orchestrator = new LookupOrchestrator([$google, $anilist]);
        $result = $orchestrator->lookupByTitle('Manga', ComicType::MANGA);

        self::assertSame('Google Author', $result->authors);
        self::assertSame('Google Desc', $result->description);
    }

    public function testLookupSkipsProvidersThatDontSupport(): void
    {
        $google = $this->createProvider('google_books', ['title'], new LookupResult(
            source: 'google_books',
            title: 'Title',
        ));
        // Open Library ne supporte que ISBN
        $openLibrary = $this->createProvider('open_library', ['isbn'], new LookupResult(
            publisher: 'Should Not Appear',
            source: 'open_library',
        ));

        $orchestrator = new LookupOrchestrator([$google, $openLibrary]);
        $result = $orchestrator->lookupByTitle('Test');

        self::assertNull($result->publisher);
    }

    public function testLookupCollectsApiMessages(): void
    {
        $google = $this->createProvider('google_books', ['isbn'], new LookupResult(
            source: 'google_books',
            title: 'Test',
        ), null, ['message' => 'Données trouvées', 'status' => 'success']);
        $openLibrary = $this->createProvider('open_library', ['isbn'], null, null, ['message' => 'Aucun résultat', 'status' => 'not_found']);

        $orchestrator = new LookupOrchestrator([$google, $openLibrary]);
        $orchestrator->lookup('1234567890');

        $messages = $orchestrator->getLastApiMessages();
        self::assertArrayHasKey('google_books', $messages);
        self::assertArrayHasKey('open_library', $messages);
        self::assertSame('success', $messages['google_books']['status']);
        self::assertSame('not_found', $messages['open_library']['status']);
    }

    public function testApiMessagesResetOnEachCall(): void
    {
        $google = $this->createProvider('google_books', ['isbn'], new LookupResult(
            source: 'google_books',
            title: 'Test',
        ), null, ['message' => 'Données trouvées', 'status' => 'success']);

        $orchestrator = new LookupOrchestrator([$google]);
        $orchestrator->lookup('1111111111');
        self::assertNotEmpty($orchestrator->getLastApiMessages());

        $orchestrator->lookup('2222222222');
        $messages = $orchestrator->getLastApiMessages();
        self::assertArrayHasKey('google_books', $messages);
    }

    public function testLookupCallsEnrichWhenIncomplete(): void
    {
        $google = $this->createProvider('google_books', ['isbn'], new LookupResult(
            source: 'google_books',
            title: 'Incomplete Book',
        ));
        $enrichable = $this->createEnrichableProvider('gemini', ['isbn'], null, new LookupResult(
            authors: 'AI Author',
            description: 'AI Desc',
            source: 'gemini',
        ));

        $orchestrator = new LookupOrchestrator([$google, $enrichable]);
        $result = $orchestrator->lookup('1234567890');

        self::assertNotNull($result);
        self::assertSame('Incomplete Book', $result->title);
        self::assertSame('AI Author', $result->authors);
        self::assertSame('AI Desc', $result->description);
    }

    public function testLookupDoesNotEnrichWhenComplete(): void
    {
        $google = $this->createProvider('google_books', ['isbn'], new LookupResult(
            authors: 'Author',
            description: 'Desc',
            publishedDate: '2020',
            publisher: 'Pub',
            source: 'google_books',
            thumbnail: 'https://img.jpg',
            title: 'Complete Book',
        ));

        $enrichCalled = false;
        $enrichable = $this->createEnrichableProvider('gemini', ['isbn'], null, null, $enrichCalled);

        $orchestrator = new LookupOrchestrator([$google, $enrichable]);
        $result = $orchestrator->lookup('1234567890');

        self::assertNotNull($result);
        self::assertFalse($enrichCalled);
    }

    public function testLookupHandlesProviderErrors(): void
    {
        $google = $this->createProvider('google_books', ['isbn'], null);
        $openLibrary = $this->createProvider('open_library', ['isbn'], new LookupResult(
            source: 'open_library',
            title: 'OL Book',
        ));

        $orchestrator = new LookupOrchestrator([$google, $openLibrary]);
        $result = $orchestrator->lookup('1234567890');

        self::assertNotNull($result);
        self::assertSame('OL Book', $result->title);
    }

    public function testLookupAddsSources(): void
    {
        $google = $this->createProvider('google_books', ['isbn'], new LookupResult(
            source: 'google_books',
            title: 'Test',
        ));
        $openLibrary = $this->createProvider('open_library', ['isbn'], new LookupResult(
            publisher: 'OL Pub',
            source: 'open_library',
        ));

        $orchestrator = new LookupOrchestrator([$google, $openLibrary]);
        $result = $orchestrator->lookup('1234567890');

        $sources = $orchestrator->getLastSources();
        self::assertContains('google_books', $sources);
        self::assertContains('open_library', $sources);
    }

    public function testLookupByTitleWithEmptyQueryReturnsNull(): void
    {
        $orchestrator = new LookupOrchestrator([]);

        self::assertNull($orchestrator->lookupByTitle(''));
        self::assertNull($orchestrator->lookupByTitle('   '));
    }

    // --- Gemini integration tests ---

    public function testLookupCallsGeminiAsLastProvider(): void
    {
        $callOrder = [];
        $google = $this->createOrderTrackingProvider('google_books', ['isbn'], new LookupResult(
            source: 'google_books',
            title: 'Google Title',
        ), $callOrder);
        $openLibrary = $this->createOrderTrackingProvider('open_library', ['isbn'], new LookupResult(
            publisher: 'OL Pub',
            source: 'open_library',
        ), $callOrder);
        $gemini = $this->createOrderTrackingProvider('gemini', ['isbn'], new LookupResult(
            description: 'Gemini Desc',
            source: 'gemini',
        ), $callOrder);

        $orchestrator = new LookupOrchestrator([$google, $openLibrary, $gemini]);
        $orchestrator->lookup('9781234567890');

        self::assertSame(['google_books', 'open_library', 'gemini'], $callOrder);
    }

    public function testLookupGeminiEnrichesIncompleteResult(): void
    {
        $google = $this->createProvider('google_books', ['isbn'], new LookupResult(
            source: 'google_books',
            title: 'Incomplete Book',
        ));
        $gemini = $this->createEnrichableProvider('gemini', ['isbn'], null, new LookupResult(
            authors: 'Gemini Author',
            description: 'Gemini Desc',
            publisher: 'Gemini Pub',
            source: 'gemini',
        ));

        $orchestrator = new LookupOrchestrator([$google, $gemini]);
        $result = $orchestrator->lookup('1234567890');

        self::assertNotNull($result);
        self::assertSame('Incomplete Book', $result->title);
        self::assertSame('Gemini Author', $result->authors);
        self::assertSame('Gemini Desc', $result->description);
        self::assertSame('Gemini Pub', $result->publisher);
    }

    public function testLookupGeminiNotCalledForEnrichWhenComplete(): void
    {
        $google = $this->createProvider('google_books', ['isbn'], new LookupResult(
            authors: 'Author',
            description: 'Desc',
            publishedDate: '2020',
            publisher: 'Pub',
            source: 'google_books',
            thumbnail: 'https://img.jpg',
            title: 'Complete Book',
        ));

        $enrichCalled = false;
        $gemini = $this->createEnrichableProvider('gemini', ['isbn'], null, null, $enrichCalled);

        $orchestrator = new LookupOrchestrator([$google, $gemini]);
        $orchestrator->lookup('1234567890');

        self::assertFalse($enrichCalled);
    }

    public function testLookupGeminiFallbackWhenAllOthersReturnNull(): void
    {
        $google = $this->createProvider('google_books', ['isbn'], null);
        $openLibrary = $this->createProvider('open_library', ['isbn'], null);
        $gemini = $this->createEnrichableProvider('gemini', ['isbn'], new LookupResult(
            authors: 'Gemini Author',
            source: 'gemini',
            title: 'Found by Gemini',
        ), null);

        $orchestrator = new LookupOrchestrator([$google, $openLibrary, $gemini]);
        $result = $orchestrator->lookup('1234567890');

        self::assertNotNull($result);
        self::assertSame('Found by Gemini', $result->title);
        self::assertSame('Gemini Author', $result->authors);
    }

    public function testLookupGeminiErrorDoesNotBreakResult(): void
    {
        $google = $this->createProvider('google_books', ['isbn'], new LookupResult(
            source: 'google_books',
            title: 'Google Book',
        ));
        // Gemini renvoie null en lookup (erreur) et ne casse pas le résultat
        $gemini = $this->createEnrichableProvider('gemini', ['isbn'], null, null);

        $orchestrator = new LookupOrchestrator([$google, $gemini]);
        $result = $orchestrator->lookup('1234567890');

        self::assertNotNull($result);
        self::assertSame('Google Book', $result->title);
    }

    // --- Helper methods ---

    /**
     * @param list<string>                              $supportedModes
     * @param array{status: string, message: string}|null $apiMessage
     */
    private function createProvider(
        string $name,
        array $supportedModes,
        ?LookupResult $result,
        ?ComicType $requiredType = null,
        ?array $apiMessage = null,
    ): LookupProviderInterface {
        return new class($name, $supportedModes, $result, $requiredType, $apiMessage) implements LookupProviderInterface {
            public function __construct(
                private readonly string $name,
                private readonly array $supportedModes,
                private readonly ?LookupResult $result,
                private readonly ?ComicType $requiredType,
                private readonly ?array $apiMessage,
            ) {
            }

            public function getLastApiMessage(): ?array
            {
                return $this->apiMessage;
            }

            public function getName(): string
            {
                return $this->name;
            }

            public function lookup(string $query, ?ComicType $type, string $mode = 'title'): ?LookupResult
            {
                return $this->result;
            }

            public function supports(string $mode, ?ComicType $type): bool
            {
                if (null !== $this->requiredType && $type !== $this->requiredType) {
                    return false;
                }

                return \in_array($mode, $this->supportedModes, true);
            }
        };
    }

    /**
     * @param list<string>  $supportedModes
     * @param list<string> &$callOrder
     */
    private function createOrderTrackingProvider(
        string $name,
        array $supportedModes,
        ?LookupResult $result,
        array &$callOrder,
    ): LookupProviderInterface {
        return new class($name, $supportedModes, $result, $callOrder) implements LookupProviderInterface {
            public function __construct(
                private readonly string $name,
                private readonly array $supportedModes,
                private readonly ?LookupResult $result,
                private array &$callOrder,
            ) {
            }

            public function getLastApiMessage(): ?array
            {
                return null;
            }

            public function getName(): string
            {
                return $this->name;
            }

            public function lookup(string $query, ?ComicType $type, string $mode = 'title'): ?LookupResult
            {
                $this->callOrder[] = $this->name;

                return $this->result;
            }

            public function supports(string $mode, ?ComicType $type): bool
            {
                return \in_array($mode, $this->supportedModes, true);
            }
        };
    }

    /**
     * @param list<string> $supportedModes
     */
    private function createCapturingProvider(
        string $name,
        array $supportedModes,
        ?LookupResult $result,
        ?string &$capturedQuery,
    ): LookupProviderInterface {
        return new class($name, $supportedModes, $result, $capturedQuery) implements LookupProviderInterface {
            public function __construct(
                private readonly string $name,
                private readonly array $supportedModes,
                private readonly ?LookupResult $result,
                private ?string &$capturedQuery,
            ) {
            }

            public function getLastApiMessage(): ?array
            {
                return null;
            }

            public function getName(): string
            {
                return $this->name;
            }

            public function lookup(string $query, ?ComicType $type, string $mode = 'title'): ?LookupResult
            {
                $this->capturedQuery = $query;

                return $this->result;
            }

            public function supports(string $mode, ?ComicType $type): bool
            {
                return \in_array($mode, $this->supportedModes, true);
            }
        };
    }

    /**
     * @param list<string> $supportedModes
     */
    private function createEnrichableProvider(
        string $name,
        array $supportedModes,
        ?LookupResult $lookupResult,
        ?LookupResult $enrichResult,
        bool &$enrichCalled = false,
    ): EnrichableLookupProviderInterface {
        return new class($name, $supportedModes, $lookupResult, $enrichResult, $enrichCalled) implements EnrichableLookupProviderInterface {
            public function __construct(
                private readonly string $name,
                private readonly array $supportedModes,
                private readonly ?LookupResult $lookupResult,
                private readonly ?LookupResult $enrichResult,
                private bool &$enrichCalled,
            ) {
            }

            public function enrich(LookupResult $partial, ?ComicType $type): ?LookupResult
            {
                $this->enrichCalled = true;

                return $this->enrichResult;
            }

            public function getLastApiMessage(): ?array
            {
                return null;
            }

            public function getName(): string
            {
                return $this->name;
            }

            public function lookup(string $query, ?ComicType $type, string $mode = 'title'): ?LookupResult
            {
                return $this->lookupResult;
            }

            public function supports(string $mode, ?ComicType $type): bool
            {
                return \in_array($mode, $this->supportedModes, true);
            }
        };
    }
}

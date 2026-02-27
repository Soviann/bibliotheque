<?php

declare(strict_types=1);

namespace App\Tests\Service\Lookup;

use App\Enum\ComicType;
use App\Service\Lookup\EnrichableLookupProviderInterface;
use App\Service\Lookup\LookupOrchestrator;
use App\Service\Lookup\LookupProviderInterface;
use App\Service\Lookup\LookupResult;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

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

        $orchestrator = $this->createOrchestrator([$google, $openLibrary]);
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

        $orchestrator = $this->createOrchestrator([$google, $openLibrary]);
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

        $orchestrator = $this->createOrchestrator([$google, $openLibrary]);
        $result = $orchestrator->lookup('0000000000');

        self::assertNull($result);
    }

    public function testLookupByIsbnWithEmptyQueryReturnsNull(): void
    {
        $orchestrator = $this->createOrchestrator([]);

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

        $orchestrator = $this->createOrchestrator([$google]);
        $orchestrator->lookup('978-2-505-00123-4');

        self::assertSame('9782505001234', $queryCaptured);
    }

    public function testLookupByIsbnAddsIsbnToResult(): void
    {
        $google = $this->createProvider('google_books', ['isbn'], new LookupResult(
            source: 'google_books',
            title: 'Test',
        ));

        $orchestrator = $this->createOrchestrator([$google]);
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

        $orchestrator = $this->createOrchestrator([$google, $anilist]);
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
        ), defaultPriority: 100);
        $anilist = $this->createProvider('anilist', ['title'], new LookupResult(
            source: 'anilist',
            thumbnail: 'https://anilist.jpg',
        ), requiredType: ComicType::MANGA, defaultPriority: 60, fieldPriorities: ['isOneShot' => 200, 'thumbnail' => 200]);

        $orchestrator = $this->createOrchestrator([$google, $anilist]);
        $result = $orchestrator->lookupByTitle('Manga', ComicType::MANGA);

        self::assertSame('https://anilist.jpg', $result->thumbnail);
    }

    public function testLookupByTitleAniListOverridesIsOneShotForManga(): void
    {
        $google = $this->createProvider('google_books', ['title'], new LookupResult(
            isOneShot: true,
            source: 'google_books',
            title: 'Manga',
        ), defaultPriority: 100);
        $anilist = $this->createProvider('anilist', ['title'], new LookupResult(
            isOneShot: false,
            source: 'anilist',
        ), requiredType: ComicType::MANGA, defaultPriority: 60, fieldPriorities: ['isOneShot' => 200, 'thumbnail' => 200]);

        $orchestrator = $this->createOrchestrator([$google, $anilist]);
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
        ), defaultPriority: 100);
        $anilist = $this->createProvider('anilist', ['title'], new LookupResult(
            authors: 'AniList Author',
            description: 'AniList Desc',
            source: 'anilist',
        ), requiredType: ComicType::MANGA, defaultPriority: 60, fieldPriorities: ['isOneShot' => 200, 'thumbnail' => 200]);

        $orchestrator = $this->createOrchestrator([$google, $anilist]);
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

        $orchestrator = $this->createOrchestrator([$google, $openLibrary]);
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

        $orchestrator = $this->createOrchestrator([$google, $openLibrary]);
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

        $orchestrator = $this->createOrchestrator([$google]);
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

        $orchestrator = $this->createOrchestrator([$google, $enrichable]);
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

        $orchestrator = $this->createOrchestrator([$google, $enrichable]);
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

        $orchestrator = $this->createOrchestrator([$google, $openLibrary]);
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

        $orchestrator = $this->createOrchestrator([$google, $openLibrary]);
        $result = $orchestrator->lookup('1234567890');

        $sources = $orchestrator->getLastSources();
        self::assertContains('google_books', $sources);
        self::assertContains('open_library', $sources);
    }

    public function testLookupByTitleWithEmptyQueryReturnsNull(): void
    {
        $orchestrator = $this->createOrchestrator([]);

        self::assertNull($orchestrator->lookupByTitle(''));
        self::assertNull($orchestrator->lookupByTitle('   '));
    }

    // --- Per-field priority tests ---

    public function testMergeUsesPerFieldPriority(): void
    {
        // Provider A : haute priorité globale (100), mais basse pour description (10)
        $providerA = $this->createProvider('provider_a', ['isbn'], new LookupResult(
            description: 'Description A',
            source: 'provider_a',
            title: 'Title A',
        ), defaultPriority: 100, fieldPriorities: ['description' => 10]);

        // Provider B : basse priorité globale (50), donc description à 50 par défaut
        $providerB = $this->createProvider('provider_b', ['isbn'], new LookupResult(
            description: 'Description B',
            source: 'provider_b',
            title: 'Title B',
        ), defaultPriority: 50);

        $orchestrator = $this->createOrchestrator([$providerA, $providerB]);
        $result = $orchestrator->lookup('9781234567890');

        self::assertNotNull($result);
        // Title : provider A gagne (100 > 50)
        self::assertSame('Title A', $result->title);
        // Description : provider B gagne (50 > 10)
        self::assertSame('Description B', $result->description);
    }

    public function testWikipediaDescriptionLosesToOtherProviders(): void
    {
        // Wikipedia : priorité 120 globale, mais description à 10
        $wikipedia = $this->createProvider('wikipedia', ['isbn'], new LookupResult(
            authors: 'Wiki Author',
            description: 'Synopsis Wikipedia générique',
            source: 'wikipedia',
            title: 'Wiki Title',
        ), defaultPriority: 120, fieldPriorities: ['description' => 10]);

        // Google Books : priorité 100 globale
        $google = $this->createProvider('google_books', ['isbn'], new LookupResult(
            description: 'Synopsis Google Books détaillé',
            source: 'google_books',
        ), defaultPriority: 100);

        $orchestrator = $this->createOrchestrator([$wikipedia, $google]);
        $result = $orchestrator->lookup('9781234567890');

        self::assertNotNull($result);
        // Wikipedia gagne pour les champs normaux
        self::assertSame('Wiki Author', $result->authors);
        self::assertSame('Wiki Title', $result->title);
        // Google Books gagne pour la description (100 > 10)
        self::assertSame('Synopsis Google Books détaillé', $result->description);
    }

    public function testWikipediaDescriptionUsedWhenOnlySource(): void
    {
        $wikipedia = $this->createProvider('wikipedia', ['isbn'], new LookupResult(
            description: 'Synopsis Wikipedia',
            source: 'wikipedia',
            title: 'Wiki Title',
        ), defaultPriority: 120, fieldPriorities: ['description' => 10]);

        // Google Books sans description
        $google = $this->createProvider('google_books', ['isbn'], new LookupResult(
            authors: 'Google Author',
            source: 'google_books',
        ), defaultPriority: 100);

        $orchestrator = $this->createOrchestrator([$wikipedia, $google]);
        $result = $orchestrator->lookup('9781234567890');

        self::assertNotNull($result);
        // Wikipedia description utilisée en dernier recours (seule source)
        self::assertSame('Synopsis Wikipedia', $result->description);
    }

    public function testEnrichmentRespectsFieldPriority(): void
    {
        // Résultat initial incomplet (pas de description)
        $google = $this->createProvider('google_books', ['isbn'], new LookupResult(
            source: 'google_books',
            title: 'Book Title',
        ), defaultPriority: 100);

        // Wikipedia enrichit avec description (priorité 10)
        $wikipedia = $this->createEnrichableProvider(
            'wikipedia',
            ['isbn'],
            null,
            new LookupResult(description: 'Synopsis Wikipedia', source: 'wikipedia'),
            defaultPriority: 120,
            fieldPriorities: ['description' => 10],
        );

        // Gemini enrichit avec description (priorité 40)
        $gemini = $this->createEnrichableProvider(
            'gemini',
            ['isbn'],
            null,
            new LookupResult(description: 'Synopsis Gemini détaillé', source: 'gemini'),
            defaultPriority: 40,
        );

        $orchestrator = $this->createOrchestrator([$google, $wikipedia, $gemini]);
        $result = $orchestrator->lookup('1234567890');

        self::assertNotNull($result);
        self::assertSame('Book Title', $result->title);
        // Gemini gagne pour la description enrichie (40 > 10)
        self::assertSame('Synopsis Gemini détaillé', $result->description);
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

        $orchestrator = $this->createOrchestrator([$google, $openLibrary, $gemini]);
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

        $orchestrator = $this->createOrchestrator([$google, $gemini]);
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

        $orchestrator = $this->createOrchestrator([$google, $gemini]);
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

        $orchestrator = $this->createOrchestrator([$google, $openLibrary, $gemini]);
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

        $orchestrator = $this->createOrchestrator([$google, $gemini]);
        $result = $orchestrator->lookup('1234567890');

        self::assertNotNull($result);
        self::assertSame('Google Book', $result->title);
    }

    // --- Error handling tests (two-phase) ---

    public function testPrepareLookupExceptionSkipsProviderAndContinues(): void
    {
        $failing = $this->createThrowingProvider('failing_provider', ['isbn'], throwOnPrepare: true);
        $working = $this->createProvider('working_provider', ['isbn'], new LookupResult(
            source: 'working_provider',
            title: 'Working Title',
        ));

        $orchestrator = $this->createOrchestrator([$failing, $working]);
        $result = $orchestrator->lookup('1234567890');

        self::assertNotNull($result);
        self::assertSame('Working Title', $result->title);

        // Le provider en erreur doit avoir un message API ERROR
        $messages = $orchestrator->getLastApiMessages();
        self::assertArrayHasKey('failing_provider', $messages);
        self::assertSame('error', $messages['failing_provider']['status']);
    }

    public function testResolveLookupExceptionSkipsProviderAndContinues(): void
    {
        $failing = $this->createThrowingProvider('failing_provider', ['isbn'], throwOnResolve: true);
        $working = $this->createProvider('working_provider', ['isbn'], new LookupResult(
            source: 'working_provider',
            title: 'Working Title',
        ));

        $orchestrator = $this->createOrchestrator([$failing, $working]);
        $result = $orchestrator->lookup('1234567890');

        self::assertNotNull($result);
        self::assertSame('Working Title', $result->title);

        $messages = $orchestrator->getLastApiMessages();
        self::assertArrayHasKey('failing_provider', $messages);
        self::assertSame('error', $messages['failing_provider']['status']);
    }

    public function testGlobalTimeoutSkipsRemainingProviders(): void
    {
        // Le premier provider consomme le budget de timeout
        $slow = $this->createSlowProvider('slow_provider', ['isbn'], new LookupResult(
            source: 'slow_provider',
            title: 'Slow Title',
        ), resolveDelay: 0.2);
        // Le second provider n'aura plus le temps d'être résolu
        $late = $this->createProvider('late_provider', ['isbn'], new LookupResult(
            source: 'late_provider',
            title: 'Late Title',
        ));

        // Timeout de 0.1s → slow_provider résout (check passe avant resolve),
        // puis late_provider est skippé car le budget est épuisé
        $orchestrator = $this->createOrchestrator([$slow, $late], globalTimeout: 0.1);
        $result = $orchestrator->lookup('1234567890');

        self::assertNotNull($result);
        self::assertSame('Slow Title', $result->title);

        $messages = $orchestrator->getLastApiMessages();
        self::assertArrayHasKey('late_provider', $messages);
        self::assertSame('timeout', $messages['late_provider']['status']);
    }

    // --- Helper methods ---

    /**
     * @param list<LookupProviderInterface> $providers
     */
    private function createOrchestrator(array $providers, float $globalTimeout = 15.0): LookupOrchestrator
    {
        return new LookupOrchestrator(
            globalTimeout: $globalTimeout,
            logger: new NullLogger(),
            providers: $providers,
        );
    }

    /**
     * @param list<string>                                $supportedModes
     * @param array{status: string, message: string}|null $apiMessage
     * @param array<string, int>                          $fieldPriorities
     */
    private function createProvider(
        string $name,
        array $supportedModes,
        ?LookupResult $result,
        ?ComicType $requiredType = null,
        ?array $apiMessage = null,
        int $defaultPriority = 0,
        array $fieldPriorities = [],
    ): LookupProviderInterface {
        return new class($name, $supportedModes, $result, $requiredType, $apiMessage, $defaultPriority, $fieldPriorities) implements LookupProviderInterface {
            public function __construct(
                private readonly string $name,
                private readonly array $supportedModes,
                private readonly ?LookupResult $result,
                private readonly ?ComicType $requiredType,
                private readonly ?array $apiMessage,
                private readonly int $defaultPriority,
                private readonly array $fieldPriorities,
            ) {
            }

            public function getFieldPriority(string $field, ?ComicType $type = null): int
            {
                return $this->fieldPriorities[$field] ?? $this->defaultPriority;
            }

            public function getLastApiMessage(): ?array
            {
                return $this->apiMessage;
            }

            public function getName(): string
            {
                return $this->name;
            }

            public function prepareLookup(string $query, ?ComicType $type, string $mode = 'title'): mixed
            {
                return ['result' => $this->result];
            }

            public function resolveLookup(mixed $state): ?LookupResult
            {
                return $state['result'] ?? null;
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
     * @param list<string> $supportedModes
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

            public function getFieldPriority(string $field, ?ComicType $type = null): int
            {
                return 0;
            }

            public function getLastApiMessage(): ?array
            {
                return null;
            }

            public function getName(): string
            {
                return $this->name;
            }

            public function prepareLookup(string $query, ?ComicType $type, string $mode = 'title'): mixed
            {
                $this->callOrder[] = $this->name;

                return ['result' => $this->result];
            }

            public function resolveLookup(mixed $state): ?LookupResult
            {
                return $state['result'] ?? null;
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

            public function getFieldPriority(string $field, ?ComicType $type = null): int
            {
                return 0;
            }

            public function getLastApiMessage(): ?array
            {
                return null;
            }

            public function getName(): string
            {
                return $this->name;
            }

            public function prepareLookup(string $query, ?ComicType $type, string $mode = 'title'): mixed
            {
                $this->capturedQuery = $query;

                return ['result' => $this->result];
            }

            public function resolveLookup(mixed $state): ?LookupResult
            {
                return $state['result'] ?? null;
            }

            public function supports(string $mode, ?ComicType $type): bool
            {
                return \in_array($mode, $this->supportedModes, true);
            }
        };
    }

    /**
     * @param list<string>       $supportedModes
     * @param array<string, int> $fieldPriorities
     */
    private function createEnrichableProvider(
        string $name,
        array $supportedModes,
        ?LookupResult $lookupResult,
        ?LookupResult $enrichResult,
        bool &$enrichCalled = false,
        int $defaultPriority = 0,
        array $fieldPriorities = [],
    ): EnrichableLookupProviderInterface {
        return new class($name, $supportedModes, $lookupResult, $enrichResult, $enrichCalled, $defaultPriority, $fieldPriorities) implements EnrichableLookupProviderInterface {
            public function __construct(
                private readonly string $name,
                private readonly array $supportedModes,
                private readonly ?LookupResult $lookupResult,
                private readonly ?LookupResult $enrichResult,
                private bool &$enrichCalled,
                private readonly int $defaultPriority,
                private readonly array $fieldPriorities,
            ) {
            }

            public function getFieldPriority(string $field, ?ComicType $type = null): int
            {
                return $this->fieldPriorities[$field] ?? $this->defaultPriority;
            }

            public function getLastApiMessage(): ?array
            {
                return null;
            }

            public function getName(): string
            {
                return $this->name;
            }

            public function prepareEnrich(LookupResult $partial, ?ComicType $type): mixed
            {
                $this->enrichCalled = true;

                return ['result' => $this->enrichResult];
            }

            public function prepareLookup(string $query, ?ComicType $type, string $mode = 'title'): mixed
            {
                return ['result' => $this->lookupResult];
            }

            public function resolveEnrich(mixed $state): ?LookupResult
            {
                return $state['result'] ?? null;
            }

            public function resolveLookup(mixed $state): ?LookupResult
            {
                return $state['result'] ?? null;
            }

            public function supports(string $mode, ?ComicType $type): bool
            {
                return \in_array($mode, $this->supportedModes, true);
            }
        };
    }

    /**
     * Crée un provider qui lance une exception dans prepareLookup ou resolveLookup.
     *
     * @param list<string> $supportedModes
     */
    private function createThrowingProvider(
        string $name,
        array $supportedModes,
        bool $throwOnPrepare = false,
        bool $throwOnResolve = false,
    ): LookupProviderInterface {
        return new class($name, $supportedModes, $throwOnPrepare, $throwOnResolve) implements LookupProviderInterface {
            public function __construct(
                private readonly string $name,
                private readonly array $supportedModes,
                private readonly bool $throwOnPrepare,
                private readonly bool $throwOnResolve,
            ) {
            }

            public function getFieldPriority(string $field, ?ComicType $type = null): int
            {
                return 0;
            }

            public function getLastApiMessage(): ?array
            {
                return null;
            }

            public function getName(): string
            {
                return $this->name;
            }

            public function prepareLookup(string $query, ?ComicType $type, string $mode = 'title'): mixed
            {
                if ($this->throwOnPrepare) {
                    throw new \RuntimeException('prepareLookup failed');
                }

                return ['result' => null];
            }

            public function resolveLookup(mixed $state): ?LookupResult
            {
                if ($this->throwOnResolve) {
                    throw new \RuntimeException('resolveLookup failed');
                }

                return null;
            }

            public function supports(string $mode, ?ComicType $type): bool
            {
                return \in_array($mode, $this->supportedModes, true);
            }
        };
    }

    /**
     * Crée un provider dont le resolveLookup est lent (pour tester le timeout global).
     *
     * @param list<string> $supportedModes
     */
    private function createSlowProvider(
        string $name,
        array $supportedModes,
        ?LookupResult $result,
        float $resolveDelay,
    ): LookupProviderInterface {
        return new class($name, $supportedModes, $result, $resolveDelay) implements LookupProviderInterface {
            public function __construct(
                private readonly string $name,
                private readonly array $supportedModes,
                private readonly ?LookupResult $result,
                private readonly float $resolveDelay,
            ) {
            }

            public function getFieldPriority(string $field, ?ComicType $type = null): int
            {
                return 0;
            }

            public function getLastApiMessage(): ?array
            {
                return null;
            }

            public function getName(): string
            {
                return $this->name;
            }

            public function prepareLookup(string $query, ?ComicType $type, string $mode = 'title'): mixed
            {
                return ['result' => $this->result];
            }

            public function resolveLookup(mixed $state): ?LookupResult
            {
                \usleep((int) ($this->resolveDelay * 1_000_000));

                return $state['result'] ?? null;
            }

            public function supports(string $mode, ?ComicType $type): bool
            {
                return \in_array($mode, $this->supportedModes, true);
            }
        };
    }
}

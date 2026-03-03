<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Lookup;

use App\Enum\ApiLookupStatus;
use App\Enum\ComicType;
use App\Service\Lookup\AbstractLookupProvider;
use App\Service\Lookup\EnrichableLookupProviderInterface;
use App\Service\Lookup\LookupOrchestrator;
use App\Service\Lookup\LookupProviderInterface;
use App\Service\Lookup\LookupResult;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Tests unitaires pour LookupOrchestrator.
 */
final class LookupOrchestratorTest extends TestCase
{
    /**
     * Teste lookup avec un seul provider retournant un resultat.
     */
    public function testLookupWithSingleProviderReturningResult(): void
    {
        $result = new LookupResult(
            authors: 'Oda',
            source: 'test_provider',
            title: 'One Piece',
        );

        $provider = $this->createStubProvider(
            fieldPriority: 100,
            name: 'test_provider',
            result: $result,
            supports: true,
        );

        $orchestrator = new LookupOrchestrator(30.0, new NullLogger(), [$provider]);

        $lookupResult = $orchestrator->lookup('978-2-7234-8900-3');

        self::assertNotNull($lookupResult);
        self::assertSame('One Piece', $lookupResult->title);
        self::assertSame('Oda', $lookupResult->authors);
        self::assertSame('9782723489003', $lookupResult->isbn);
        self::assertContains('test_provider', $orchestrator->getLastSources());
    }

    /**
     * Teste lookup avec ISBN contenant des espaces et tirets (normalisation).
     */
    public function testLookupNormalizesIsbn(): void
    {
        $result = new LookupResult(title: 'Test', source: 'provider');

        $provider = $this->createStubProvider(
            fieldPriority: 100,
            name: 'provider',
            result: $result,
            supports: true,
        );

        $orchestrator = new LookupOrchestrator(30.0, new NullLogger(), [$provider]);

        $lookupResult = $orchestrator->lookup('978 2-7234 8900-3');

        self::assertNotNull($lookupResult);
        self::assertSame('9782723489003', $lookupResult->isbn);
    }

    /**
     * Teste lookup avec un ISBN vide retourne null.
     */
    public function testLookupWithEmptyIsbnReturnsNull(): void
    {
        $orchestrator = new LookupOrchestrator(30.0, new NullLogger(), []);

        self::assertNull($orchestrator->lookup(''));
        self::assertNull($orchestrator->lookup('   '));
        self::assertNull($orchestrator->lookup('- -'));
    }

    /**
     * Teste lookupByTitle avec un titre vide retourne null.
     */
    public function testLookupByTitleWithEmptyTitleReturnsNull(): void
    {
        $orchestrator = new LookupOrchestrator(30.0, new NullLogger(), []);

        self::assertNull($orchestrator->lookupByTitle(''));
        self::assertNull($orchestrator->lookupByTitle('   '));
    }

    /**
     * Teste lookupByTitle avec un titre valide.
     */
    public function testLookupByTitleReturnsResult(): void
    {
        $result = new LookupResult(
            authors: 'Oda',
            source: 'provider',
            title: 'One Piece',
        );

        $provider = $this->createStubProvider(
            fieldPriority: 100,
            name: 'provider',
            result: $result,
            supports: true,
        );

        $orchestrator = new LookupOrchestrator(30.0, new NullLogger(), [$provider]);

        $lookupResult = $orchestrator->lookupByTitle('One Piece');

        self::assertNotNull($lookupResult);
        self::assertSame('One Piece', $lookupResult->title);
        self::assertNull($lookupResult->isbn);
    }

    /**
     * Teste la fusion par priorite de champ avec plusieurs providers.
     */
    public function testLookupMergesByFieldPriority(): void
    {
        $lowPriorityResult = new LookupResult(
            authors: 'Auteur Faible Prio',
            description: 'Description de faible priorite',
            source: 'low_prio',
            title: 'Titre Faible Prio',
        );

        $highPriorityResult = new LookupResult(
            authors: 'Auteur Haute Prio',
            source: 'high_prio',
            title: 'Titre Haute Prio',
        );

        $lowProvider = $this->createStubProvider(
            fieldPriority: 50,
            name: 'low_prio',
            result: $lowPriorityResult,
            supports: true,
        );

        $highProvider = $this->createStubProvider(
            fieldPriority: 100,
            name: 'high_prio',
            result: $highPriorityResult,
            supports: true,
        );

        $orchestrator = new LookupOrchestrator(30.0, new NullLogger(), [$lowProvider, $highProvider]);

        $result = $orchestrator->lookup('1234567890');

        self::assertNotNull($result);
        // Haute priorite l'emporte pour les champs qu'il fournit
        self::assertSame('Auteur Haute Prio', $result->authors);
        self::assertSame('Titre Haute Prio', $result->title);
        // Faible priorite comble les champs manquants
        self::assertSame('Description de faible priorite', $result->description);
    }

    /**
     * Teste que les providers ne supportant pas le mode sont ignores.
     */
    public function testProviderNotSupportingModeIsSkipped(): void
    {
        $unsupported = $this->createStubProvider(
            fieldPriority: 200,
            name: 'unsupported',
            result: new LookupResult(title: 'Should Not Appear', source: 'unsupported'),
            supports: false,
        );

        $supported = $this->createStubProvider(
            fieldPriority: 50,
            name: 'supported',
            result: new LookupResult(title: 'Correct', source: 'supported'),
            supports: true,
        );

        $orchestrator = new LookupOrchestrator(30.0, new NullLogger(), [$unsupported, $supported]);

        $result = $orchestrator->lookup('1234567890');

        self::assertNotNull($result);
        self::assertSame('Correct', $result->title);
        self::assertNotContains('unsupported', $orchestrator->getLastSources());
    }

    /**
     * Teste que tous les providers retournent null → resultat null.
     */
    public function testAllProvidersReturnNullReturnsNull(): void
    {
        $provider1 = $this->createStubProvider(
            fieldPriority: 100,
            name: 'provider1',
            result: null,
            supports: true,
        );

        $provider2 = $this->createStubProvider(
            fieldPriority: 80,
            name: 'provider2',
            result: null,
            supports: true,
        );

        $orchestrator = new LookupOrchestrator(30.0, new NullLogger(), [$provider1, $provider2]);

        self::assertNull($orchestrator->lookup('1234567890'));
    }

    /**
     * Teste qu'une exception dans prepareLookup est geree (enregistree + continue).
     */
    public function testPrepareLookupExceptionIsLoggedAndContinues(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('error');

        $failingProvider = $this->createStubProvider(
            fieldPriority: 100,
            name: 'failing',
            result: null,
            supports: true,
            throwOnPrepare: new \RuntimeException('Network failure'),
        );

        $workingResult = new LookupResult(title: 'Working', source: 'working');
        $workingProvider = $this->createStubProvider(
            fieldPriority: 80,
            name: 'working',
            result: $workingResult,
            supports: true,
        );

        $orchestrator = new LookupOrchestrator(30.0, $logger, [$failingProvider, $workingProvider]);

        $result = $orchestrator->lookup('1234567890');

        self::assertNotNull($result);
        self::assertSame('Working', $result->title);

        $messages = $orchestrator->getLastApiMessages();
        self::assertArrayHasKey('failing', $messages);
        self::assertSame('error', $messages['failing']['status']);
    }

    /**
     * Teste qu'une exception dans resolveLookup est geree (enregistree + continue).
     */
    public function testResolveLookupExceptionIsLoggedAndContinues(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('error');

        $failingProvider = $this->createStubProvider(
            fieldPriority: 100,
            name: 'failing',
            result: null,
            supports: true,
            throwOnResolve: new \RuntimeException('Parse error'),
        );

        $workingResult = new LookupResult(title: 'Working', source: 'working');
        $workingProvider = $this->createStubProvider(
            fieldPriority: 80,
            name: 'working',
            result: $workingResult,
            supports: true,
        );

        $orchestrator = new LookupOrchestrator(30.0, $logger, [$failingProvider, $workingProvider]);

        $result = $orchestrator->lookup('1234567890');

        self::assertNotNull($result);
        self::assertSame('Working', $result->title);

        $messages = $orchestrator->getLastApiMessages();
        self::assertArrayHasKey('failing', $messages);
        self::assertSame('error', $messages['failing']['status']);
    }

    /**
     * Teste le timeout global : les providers restants recoivent un message TIMEOUT.
     */
    public function testGlobalTimeoutExceededRecordsTimeoutMessage(): void
    {
        // Provider lent qui consomme tout le timeout
        $slowProvider = new class extends AbstractLookupProvider {
            public function getFieldPriority(string $field, ?ComicType $type = null): int
            {
                return 100;
            }

            public function getName(): string
            {
                return 'slow_provider';
            }

            public function prepareLookup(string $query, ?ComicType $type, string $mode = 'title'): mixed
            {
                return 'prepared';
            }

            public function resolveLookup(mixed $state): ?LookupResult
            {
                // Simuler un delai en consommant du temps
                \usleep(100000); // 100ms

                $this->recordApiMessage(ApiLookupStatus::SUCCESS, 'OK');

                return new LookupResult(title: 'Slow Result', source: 'slow_provider');
            }

            public function supports(string $mode, ?ComicType $type): bool
            {
                return true;
            }
        };

        $fastProvider = $this->createStubProvider(
            fieldPriority: 80,
            name: 'fast_provider',
            result: new LookupResult(title: 'Fast Result', source: 'fast_provider'),
            supports: true,
        );

        // Timeout de 0 secondes : tout depasse immediatement apres le premier resolve
        $orchestrator = new LookupOrchestrator(0.0, new NullLogger(), [$slowProvider, $fastProvider]);

        $result = $orchestrator->lookup('1234567890');

        // Le slow provider doit avoir eu le temps de s'executer (premier dans la liste)
        // Le fast provider devrait etre en timeout
        $messages = $orchestrator->getLastApiMessages();

        // Au moins un provider doit avoir le statut timeout
        $hasTimeout = false;
        foreach ($messages as $message) {
            if ('timeout' === $message['status']) {
                $hasTimeout = true;

                break;
            }
        }

        self::assertTrue($hasTimeout, 'Au moins un provider devrait avoir le statut timeout');
    }

    /**
     * Teste getLastApiMessages retourne les messages de tous les providers.
     */
    public function testGetLastApiMessagesReturnsAllMessages(): void
    {
        $provider1 = $this->createStubProvider(
            fieldPriority: 100,
            name: 'provider1',
            result: new LookupResult(title: 'T1', source: 'p1'),
            supports: true,
            apiMessage: ['message' => 'OK', 'status' => 'success'],
        );

        $provider2 = $this->createStubProvider(
            fieldPriority: 80,
            name: 'provider2',
            result: null,
            supports: true,
            apiMessage: ['message' => 'Aucun resultat', 'status' => 'not_found'],
        );

        $orchestrator = new LookupOrchestrator(30.0, new NullLogger(), [$provider1, $provider2]);

        $orchestrator->lookup('1234567890');

        $messages = $orchestrator->getLastApiMessages();

        self::assertArrayHasKey('provider1', $messages);
        self::assertArrayHasKey('provider2', $messages);
        self::assertSame('success', $messages['provider1']['status']);
        self::assertSame('not_found', $messages['provider2']['status']);
    }

    /**
     * Teste getLastSources retourne les noms des providers ayant fourni des resultats.
     */
    public function testGetLastSourcesReturnsContributingProviders(): void
    {
        $provider1 = $this->createStubProvider(
            fieldPriority: 100,
            name: 'provider1',
            result: new LookupResult(title: 'T1', source: 'p1'),
            supports: true,
        );

        $provider2 = $this->createStubProvider(
            fieldPriority: 80,
            name: 'provider2',
            result: null,
            supports: true,
        );

        $provider3 = $this->createStubProvider(
            fieldPriority: 60,
            name: 'provider3',
            result: new LookupResult(description: 'Desc', source: 'p3'),
            supports: true,
        );

        $orchestrator = new LookupOrchestrator(30.0, new NullLogger(), [$provider1, $provider2, $provider3]);

        $orchestrator->lookup('1234567890');

        $sources = $orchestrator->getLastSources();

        self::assertContains('provider1', $sources);
        self::assertNotContains('provider2', $sources);
        self::assertContains('provider3', $sources);
    }

    /**
     * Teste l'enrichissement quand le resultat initial est incomplet.
     */
    public function testEnrichmentHappyPath(): void
    {
        // Provider de lookup retournant un resultat incomplet (sans description ni thumbnail)
        $lookupResult = new LookupResult(
            authors: 'Oda',
            source: 'lookup_provider',
            title: 'One Piece',
        );

        $lookupProvider = $this->createStubProvider(
            fieldPriority: 100,
            name: 'lookup_provider',
            result: $lookupResult,
            supports: true,
        );

        // Provider enrichable qui ajoute les champs manquants
        $enrichResult = new LookupResult(
            description: 'Un manga de pirates',
            source: 'enrich_provider',
            thumbnail: 'https://example.com/cover.jpg',
        );

        $enrichProvider = $this->createStubEnrichableProvider(
            enrichResult: $enrichResult,
            fieldPriority: 50,
            name: 'enrich_provider',
            result: null,
            supports: false,
        );

        $orchestrator = new LookupOrchestrator(30.0, new NullLogger(), [$lookupProvider, $enrichProvider]);

        $result = $orchestrator->lookup('1234567890');

        self::assertNotNull($result);
        // Champs du lookup initial (priorite superieure)
        self::assertSame('One Piece', $result->title);
        self::assertSame('Oda', $result->authors);
        // Champs de l'enrichissement
        self::assertSame('Un manga de pirates', $result->description);
        self::assertSame('https://example.com/cover.jpg', $result->thumbnail);
        self::assertContains('enrich_provider', $orchestrator->getLastSources());
    }

    /**
     * Teste qu'une exception dans prepareEnrich est geree et enregistree.
     */
    public function testPrepareEnrichExceptionIsLoggedAndContinues(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('error');

        // Provider de lookup retournant un resultat incomplet
        $lookupResult = new LookupResult(
            authors: 'Oda',
            source: 'lookup_provider',
            title: 'One Piece',
        );

        $lookupProvider = $this->createStubProvider(
            fieldPriority: 100,
            name: 'lookup_provider',
            result: $lookupResult,
            supports: true,
        );

        // Provider enrichable qui leve une exception dans prepareEnrich
        $enrichProvider = $this->createStubEnrichableProvider(
            enrichResult: null,
            fieldPriority: 50,
            name: 'failing_enrich',
            result: null,
            supports: false,
            throwOnPrepareEnrich: new \RuntimeException('Enrich prepare failed'),
        );

        $orchestrator = new LookupOrchestrator(30.0, $logger, [$lookupProvider, $enrichProvider]);

        $result = $orchestrator->lookup('1234567890');

        self::assertNotNull($result);
        self::assertSame('One Piece', $result->title);

        $messages = $orchestrator->getLastApiMessages();
        self::assertArrayHasKey('failing_enrich.enrich', $messages);
        self::assertSame('error', $messages['failing_enrich.enrich']['status']);
    }

    /**
     * Teste qu'une exception dans resolveEnrich est geree et enregistree.
     */
    public function testResolveEnrichExceptionIsLoggedAndContinues(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('error');

        // Provider de lookup retournant un resultat incomplet
        $lookupResult = new LookupResult(
            authors: 'Oda',
            source: 'lookup_provider',
            title: 'One Piece',
        );

        $lookupProvider = $this->createStubProvider(
            fieldPriority: 100,
            name: 'lookup_provider',
            result: $lookupResult,
            supports: true,
        );

        // Provider enrichable qui leve une exception dans resolveEnrich
        $enrichProvider = $this->createStubEnrichableProvider(
            enrichResult: null,
            fieldPriority: 50,
            name: 'failing_enrich',
            result: null,
            supports: false,
            throwOnResolveEnrich: new \RuntimeException('Enrich resolve failed'),
        );

        $orchestrator = new LookupOrchestrator(30.0, $logger, [$lookupProvider, $enrichProvider]);

        $result = $orchestrator->lookup('1234567890');

        self::assertNotNull($result);
        self::assertSame('One Piece', $result->title);

        $messages = $orchestrator->getLastApiMessages();
        self::assertArrayHasKey('failing_enrich.enrich', $messages);
        self::assertSame('error', $messages['failing_enrich.enrich']['status']);
    }

    /**
     * Teste que lookupByTitle propage le ComicType aux appels supports() et prepareLookup().
     */
    public function testLookupByTitlePropagatesComicType(): void
    {
        $calledWithType = null;
        $calledSupportsType = null;

        $provider = new class($calledWithType, $calledSupportsType) implements LookupProviderInterface {
            public function __construct(
                private mixed &$calledWithType,
                private mixed &$calledSupportsType,
            ) {
            }

            public function getFieldPriority(string $field, ?ComicType $type = null): int
            {
                return 100;
            }

            public function getLastApiMessage(): ?array
            {
                return null;
            }

            public function getName(): string
            {
                return 'type_tracker';
            }

            public function prepareLookup(string $query, ?ComicType $type, string $mode = 'title'): mixed
            {
                $this->calledWithType = $type;

                return 'state';
            }

            public function resolveLookup(mixed $state): ?LookupResult
            {
                return new LookupResult(title: 'Test', source: 'type_tracker');
            }

            public function supports(string $mode, ?ComicType $type): bool
            {
                $this->calledSupportsType = $type;

                return true;
            }
        };

        $orchestrator = new LookupOrchestrator(30.0, new NullLogger(), [$provider]);

        $orchestrator->lookupByTitle('One Piece', ComicType::MANGA);

        self::assertSame(ComicType::MANGA, $calledWithType);
        self::assertSame(ComicType::MANGA, $calledSupportsType);
    }

    /**
     * Teste que lookupByIsbn retourne null quand tous les providers retournent null.
     */
    public function testLookupByIsbnAllProvidersReturnNullReturnsNull(): void
    {
        $provider = $this->createStubProvider(
            fieldPriority: 100,
            name: 'empty_provider',
            result: null,
            supports: true,
        );

        $orchestrator = new LookupOrchestrator(30.0, new NullLogger(), [$provider]);

        $result = $orchestrator->lookup('9782723489003');

        self::assertNull($result);
        self::assertSame([], $orchestrator->getLastSources());
    }

    /**
     * Teste que le merge par priorite fonctionne champ par champ.
     */
    public function testMergeByFieldPriorityPerField(): void
    {
        // Provider A : haute priorite pour tous les champs, mais ne fournit que title et authors
        $providerA = $this->createStubProvider(
            fieldPriority: 200,
            name: 'provider_a',
            result: new LookupResult(
                authors: 'A Authors',
                source: 'provider_a',
                title: 'A Title',
            ),
            supports: true,
        );

        // Provider B : priorite moyenne, fournit title, description, publisher
        $providerB = $this->createStubProvider(
            fieldPriority: 100,
            name: 'provider_b',
            result: new LookupResult(
                description: 'B Description',
                publisher: 'B Publisher',
                source: 'provider_b',
                title: 'B Title',
            ),
            supports: true,
        );

        // Provider C : faible priorite, fournit thumbnail
        $providerC = $this->createStubProvider(
            fieldPriority: 50,
            name: 'provider_c',
            result: new LookupResult(
                source: 'provider_c',
                thumbnail: 'C Thumbnail',
            ),
            supports: true,
        );

        $orchestrator = new LookupOrchestrator(30.0, new NullLogger(), [$providerA, $providerB, $providerC]);

        $result = $orchestrator->lookup('1234567890');

        self::assertNotNull($result);
        // Provider A gagne pour title et authors (priorite 200)
        self::assertSame('A Title', $result->title);
        self::assertSame('A Authors', $result->authors);
        // Provider B gagne pour description et publisher (seul a les fournir, priorite 100)
        self::assertSame('B Description', $result->description);
        self::assertSame('B Publisher', $result->publisher);
        // Provider C gagne pour thumbnail (seul a le fournir, priorite 50)
        self::assertSame('C Thumbnail', $result->thumbnail);
    }

    /**
     * Teste que l'enrichissement est ignore quand le resultat initial est complet.
     */
    public function testNoEnrichmentWhenResultIsComplete(): void
    {
        $completeResult = new LookupResult(
            authors: 'Oda',
            description: 'Un manga de pirates',
            publishedDate: '1997',
            publisher: 'Glenat',
            source: 'lookup_provider',
            thumbnail: 'https://example.com/cover.jpg',
            title: 'One Piece',
        );

        $lookupProvider = $this->createStubProvider(
            fieldPriority: 100,
            name: 'lookup_provider',
            result: $completeResult,
            supports: true,
        );

        // Le provider enrichable ne devrait PAS etre appele
        $enrichProvider = $this->createStubEnrichableProvider(
            enrichResult: new LookupResult(description: 'Should not appear', source: 'enrich'),
            fieldPriority: 50,
            name: 'enrich_provider',
            result: null,
            supports: false,
        );

        $orchestrator = new LookupOrchestrator(30.0, new NullLogger(), [$lookupProvider, $enrichProvider]);

        $result = $orchestrator->lookup('1234567890');

        self::assertNotNull($result);
        self::assertSame('Un manga de pirates', $result->description);
        self::assertNotContains('enrich_provider', $orchestrator->getLastSources());
    }

    /**
     * Teste que getLastApiMessage retournant null ne produit pas de message enregistre.
     */
    public function testGetLastApiMessageNullDoesNotRecordMessage(): void
    {
        $provider = $this->createStubProvider(
            fieldPriority: 100,
            name: 'silent_provider',
            result: new LookupResult(title: 'Test', source: 'silent'),
            supports: true,
            apiMessage: null,
        );

        $orchestrator = new LookupOrchestrator(30.0, new NullLogger(), [$provider]);

        $orchestrator->lookup('1234567890');

        $messages = $orchestrator->getLastApiMessages();
        self::assertArrayNotHasKey('silent_provider', $messages);
    }

    /**
     * Teste que le resultat du lookup recoit l'ISBN via withIsbn.
     */
    public function testLookupResultGetsIsbnInjected(): void
    {
        $result = new LookupResult(
            source: 'provider',
            title: 'Test',
        );

        $provider = $this->createStubProvider(
            fieldPriority: 100,
            name: 'provider',
            result: $result,
            supports: true,
        );

        $orchestrator = new LookupOrchestrator(30.0, new NullLogger(), [$provider]);

        $lookupResult = $orchestrator->lookup('978-2-7234-8900-3');

        self::assertNotNull($lookupResult);
        self::assertSame('9782723489003', $lookupResult->isbn);
        // Le resultat original n'avait pas d'ISBN
        self::assertNull($result->isbn);
    }

    /**
     * Cree un stub enrichable pour les tests d'enrichissement.
     */
    private function createStubEnrichableProvider(
        ?LookupResult $enrichResult,
        int $fieldPriority,
        string $name,
        ?LookupResult $result,
        bool $supports,
        ?\Throwable $throwOnPrepareEnrich = null,
        ?\Throwable $throwOnResolveEnrich = null,
    ): EnrichableLookupProviderInterface {
        return new class($enrichResult, $fieldPriority, $name, $result, $supports, $throwOnPrepareEnrich, $throwOnResolveEnrich) implements EnrichableLookupProviderInterface {
            public function __construct(
                private readonly ?LookupResult $enrichResult,
                private readonly int $fieldPriority,
                private readonly string $name,
                private readonly ?LookupResult $result,
                private readonly bool $supports,
                private readonly ?\Throwable $throwOnPrepareEnrich,
                private readonly ?\Throwable $throwOnResolveEnrich,
            ) {
            }

            public function getFieldPriority(string $field, ?ComicType $type = null): int
            {
                return $this->fieldPriority;
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
                if (null !== $this->throwOnPrepareEnrich) {
                    throw $this->throwOnPrepareEnrich;
                }

                return 'enrich_state';
            }

            public function prepareLookup(string $query, ?ComicType $type, string $mode = 'title'): mixed
            {
                return 'prepared';
            }

            public function resolveEnrich(mixed $state): ?LookupResult
            {
                if (null !== $this->throwOnResolveEnrich) {
                    throw $this->throwOnResolveEnrich;
                }

                return $this->enrichResult;
            }

            public function resolveLookup(mixed $state): ?LookupResult
            {
                return $this->result;
            }

            public function supports(string $mode, ?ComicType $type): bool
            {
                return $this->supports;
            }
        };
    }

    /**
     * Cree un stub de LookupProviderInterface pour les tests.
     *
     * @param array{status: string, message: string}|null $apiMessage
     */
    private function createStubProvider(
        int $fieldPriority,
        string $name,
        ?LookupResult $result,
        bool $supports,
        ?\Throwable $throwOnPrepare = null,
        ?\Throwable $throwOnResolve = null,
        ?array $apiMessage = null,
    ): LookupProviderInterface {
        return new class($apiMessage, $fieldPriority, $name, $result, $supports, $throwOnPrepare, $throwOnResolve) implements LookupProviderInterface {
            public function __construct(
                private readonly ?array $apiMessage,
                private readonly int $fieldPriority,
                private readonly string $name,
                private readonly ?LookupResult $result,
                private readonly bool $supports,
                private readonly ?\Throwable $throwOnPrepare,
                private readonly ?\Throwable $throwOnResolve,
            ) {
            }

            public function getFieldPriority(string $field, ?ComicType $type = null): int
            {
                return $this->fieldPriority;
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
                if (null !== $this->throwOnPrepare) {
                    throw $this->throwOnPrepare;
                }

                return 'prepared_state';
            }

            public function resolveLookup(mixed $state): ?LookupResult
            {
                if (null !== $this->throwOnResolve) {
                    throw $this->throwOnResolve;
                }

                return $this->result;
            }

            public function supports(string $mode, ?ComicType $type): bool
            {
                return $this->supports;
            }
        };
    }
}

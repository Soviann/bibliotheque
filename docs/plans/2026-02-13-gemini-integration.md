# Intégration Gemini + Refactoring Lookup — Plan d'implémentation

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Refactorer `IsbnLookupService` en architecture provider-based et ajouter Gemini comme source d'enrichissement IA.

**Architecture:** Chaque API (Google Books, Open Library, AniList, Gemini) a son propre service implémentant `LookupProviderInterface`. Un `LookupOrchestrator` coordonne les appels et fusionne les résultats. Gemini utilise Google Search grounding + structured output pour enrichir ou rechercher de manière autonome.

**Tech Stack:** PHP 8.3, Symfony 7.4, `google-gemini-php/symfony` v2.0, Symfony Cache (filesystem), Symfony Rate Limiter

---

## Task 1 : Setup — Package et configuration

**Files:**
- Modify: `composer.json`
- Modify: `config/bundles.php`
- Modify: `.env`
- Create: `config/packages/gemini.yaml`

**Step 1: Installer le package**

```bash
ddev exec composer require google-gemini-php/symfony
```

**Step 2: Vérifier que le bundle est enregistré dans `config/bundles.php`**

Symfony Flex devrait l'ajouter automatiquement. Sinon, ajouter :
```php
Gemini\Symfony\GeminiBundle::class => ['all' => true],
```

**Step 3: Ajouter la variable d'environnement**

Dans `.env`, ajouter le placeholder :
```dotenv
###> google-gemini-php/symfony ###
GEMINI_API_KEY=
###< google-gemini-php/symfony ###
```

Dans `.env.local` (non commité) :
```dotenv
GEMINI_API_KEY=AIzaSyA6venCufM3Tz95aWTQSybae0ajOXoj3hY
```

**Step 4: Configurer le cache pool dédié**

Dans `config/packages/cache.yaml`, ajouter un pool `gemini` :
```yaml
framework:
    cache:
        pools:
            gemini.cache:
                adapter: cache.adapter.filesystem
                default_lifetime: 2592000  # 30 jours
```

**Step 5: Configurer le rate limiter**

Créer `config/packages/rate_limiter.yaml` (s'il n'existe pas) ou ajouter dans `framework.yaml` :
```yaml
framework:
    rate_limiter:
        gemini_api:
            policy: sliding_window
            limit: 10
            interval: '1 minute'
```

**Step 6: Vérifier que `make build` fonctionne**

```bash
make build
```

**Step 7: Commit**

```bash
git add -A && git commit -m "chore: installer google-gemini-php/symfony et configurer cache/rate limiter"
```

---

## Task 2 : DTO `LookupResult` + Interfaces

**Files:**
- Create: `src/Service/Lookup/LookupResult.php`
- Create: `src/Service/Lookup/LookupProviderInterface.php`
- Create: `src/Service/Lookup/EnrichableLookupProviderInterface.php`
- Create: `tests/Service/Lookup/LookupResultTest.php`

**Step 1: Écrire le test du DTO**

```php
<?php

declare(strict_types=1);

namespace App\Tests\Service\Lookup;

use App\Service\Lookup\LookupResult;
use PHPUnit\Framework\TestCase;

class LookupResultTest extends TestCase
{
    public function testCreateWithAllFields(): void
    {
        $result = new LookupResult(
            authors: 'John Doe',
            description: 'A great book',
            isbn: '9781234567890',
            isOneShot: false,
            publishedDate: '2020-01-01',
            publisher: 'Great Publisher',
            source: 'google_books',
            thumbnail: 'https://example.com/cover.jpg',
            title: 'Test Book',
        );

        self::assertSame('John Doe', $result->authors);
        self::assertSame('A great book', $result->description);
        self::assertSame('9781234567890', $result->isbn);
        self::assertFalse($result->isOneShot);
        self::assertSame('2020-01-01', $result->publishedDate);
        self::assertSame('Great Publisher', $result->publisher);
        self::assertSame('google_books', $result->source);
        self::assertSame('https://example.com/cover.jpg', $result->thumbnail);
        self::assertSame('Test Book', $result->title);
    }

    public function testCreateWithMinimalFields(): void
    {
        $result = new LookupResult(source: 'open_library', title: 'Minimal Book');

        self::assertNull($result->authors);
        self::assertNull($result->description);
        self::assertNull($result->isbn);
        self::assertNull($result->isOneShot);
        self::assertNull($result->publishedDate);
        self::assertNull($result->publisher);
        self::assertSame('open_library', $result->source);
        self::assertNull($result->thumbnail);
        self::assertSame('Minimal Book', $result->title);
    }

    public function testIsComplete(): void
    {
        $complete = new LookupResult(
            authors: 'Author',
            description: 'Desc',
            publishedDate: '2020',
            publisher: 'Pub',
            source: 'test',
            thumbnail: 'https://img.jpg',
            title: 'Title',
        );
        self::assertTrue($complete->isComplete());

        $incomplete = new LookupResult(source: 'test', title: 'Title');
        self::assertFalse($incomplete->isComplete());
    }
}
```

**Step 2: Lancer le test — doit échouer**

```bash
ddev exec bin/phpunit tests/Service/Lookup/LookupResultTest.php
```

**Step 3: Créer le DTO**

`src/Service/Lookup/LookupResult.php` :
```php
<?php

declare(strict_types=1);

namespace App\Service\Lookup;

/**
 * Résultat d'un lookup depuis un provider.
 */
class LookupResult
{
    public function __construct(
        public readonly ?string $authors = null,
        public readonly ?string $description = null,
        public readonly ?string $isbn = null,
        public readonly ?bool $isOneShot = null,
        public readonly ?string $publishedDate = null,
        public readonly ?string $publisher = null,
        public readonly string $source = '',
        public readonly ?string $thumbnail = null,
        public readonly ?string $title = null,
    ) {
    }

    /**
     * Vérifie si les données principales sont complètes.
     */
    public function isComplete(): bool
    {
        return null !== $this->authors
            && null !== $this->description
            && null !== $this->publishedDate
            && null !== $this->publisher
            && null !== $this->thumbnail
            && null !== $this->title;
    }
}
```

**Step 4: Créer les interfaces**

`src/Service/Lookup/LookupProviderInterface.php` :
```php
<?php

declare(strict_types=1);

namespace App\Service\Lookup;

use App\Enum\ComicType;

/**
 * Interface pour les providers de recherche de données bibliographiques.
 */
interface LookupProviderInterface
{
    /**
     * Nom unique du provider (ex: 'google_books', 'anilist', 'gemini').
     */
    public function getName(): string;

    /**
     * Indique si le provider supporte le mode donné.
     *
     * @param string         $mode 'isbn' ou 'title'
     * @param ComicType|null $type Le type de série (certains providers sont spécifiques à un type)
     */
    public function supports(string $mode, ?ComicType $type): bool;

    /**
     * Recherche des informations sur une série.
     *
     * @param string         $query ISBN ou titre selon le mode
     * @param ComicType|null $type  Le type de série
     */
    public function lookup(string $query, ?ComicType $type): ?LookupResult;

    /**
     * Retourne le message de statut du dernier appel.
     *
     * @return array{status: string, message: string}|null
     */
    public function getLastApiMessage(): ?array;
}
```

`src/Service/Lookup/EnrichableLookupProviderInterface.php` :
```php
<?php

declare(strict_types=1);

namespace App\Service\Lookup;

use App\Enum\ComicType;

/**
 * Interface pour les providers capables d'enrichir des données existantes.
 */
interface EnrichableLookupProviderInterface extends LookupProviderInterface
{
    /**
     * Enrichit des données partielles avec des informations complémentaires.
     *
     * @param array<string, mixed> $partialData Les données partielles à enrichir
     * @param ComicType|null       $type        Le type de série
     */
    public function enrich(array $partialData, ?ComicType $type): ?LookupResult;
}
```

**Step 5: Lancer le test — doit passer**

```bash
ddev exec bin/phpunit tests/Service/Lookup/LookupResultTest.php
```

**Step 6: Commit**

```bash
git add src/Service/Lookup/ tests/Service/Lookup/
git commit -m "feat(lookup): créer DTO LookupResult et interfaces provider"
```

---

## Task 3 : Extraire `GoogleBooksLookup`

**Files:**
- Create: `src/Service/Lookup/GoogleBooksLookup.php`
- Create: `tests/Service/Lookup/GoogleBooksLookupTest.php`

**Step 1: Écrire les tests**

Migrer les tests pertinents de `IsbnLookupServiceTest` vers `GoogleBooksLookupTest`. Les tests ciblent le provider isolé (pas l'orchestrateur). Tests à écrire :

- `testLookupByIsbnReturnsData` — ISBN valide, retourne LookupResult avec titre/auteur/etc.
- `testLookupByIsbnMergesMultipleResults` — Plusieurs items Google Books fusionnés
- `testLookupByIsbnSelectsBestThumbnail` — thumbnail > smallThumbnail
- `testLookupByIsbnFallsBackToSmallThumbnail` — Fallback sur smallThumbnail
- `testLookupByIsbnExtractsIsbn13` — Préfère ISBN-13 à ISBN-10
- `testLookupByIsbnDetectsOneShot` — Détection via absence de seriesInfo
- `testLookupByTitleReturnsData` — Recherche par titre
- `testLookupReturnsNullWhenNoResults` — items vide → null
- `testLookupHandlesNetworkErrors` — TransportExceptionInterface → null + message error
- `testLookupHandlesRateLimiting` — HTTP 429 → null + message rate_limited
- `testLookupHandlesServerErrors` — HTTP 500 → null + message error
- `testLookupHandlesInvalidJson` — JSON invalide → null + message error
- `testSupportsIsbnAndTitle` — supports('isbn') = true, supports('title') = true
- `testGetName` — retourne 'google_books'

Chaque test instancie `GoogleBooksLookup` avec un `MockHttpClient` et `NullLogger`, appelle `lookup()` et vérifie le `LookupResult` retourné + `getLastApiMessage()`.

**Step 2: Lancer les tests — doivent échouer**

```bash
ddev exec bin/phpunit tests/Service/Lookup/GoogleBooksLookupTest.php
```

**Step 3: Implémenter `GoogleBooksLookup`**

Extraire de `IsbnLookupService` :
- Méthode `lookup()` : détermine si ISBN (cherche avec `isbn:` prefix) ou titre
- Méthodes privées : `requestGoogleBooks()`, `processResponse()`, `mergeItems()`, `isComplete()`, `extractIsbnFromIdentifiers()`
- Tracking du message API via `recordApiMessage()` / `getLastApiMessage()`

Structure :
```php
class GoogleBooksLookup implements LookupProviderInterface
{
    private const string API_URL = 'https://www.googleapis.com/books/v1/volumes';
    private ?array $lastApiMessage = null;

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly LoggerInterface $logger,
    ) {}

    public function getName(): string { return 'google_books'; }

    public function supports(string $mode, ?ComicType $type): bool
    {
        return \in_array($mode, ['isbn', 'title'], true);
    }

    public function lookup(string $query, ?ComicType $type): ?LookupResult
    {
        $this->lastApiMessage = null;
        // Si mode isbn : q=isbn:{query}, sinon q={query}
        // Appel API, traitement réponse, retour LookupResult
    }

    public function getLastApiMessage(): ?array { return $this->lastApiMessage; }

    // Méthodes privées extraites de IsbnLookupService...
}
```

**Important** : le provider ne sait pas si `$query` est un ISBN ou un titre. C'est l'orchestrateur qui appelle `supports('isbn', ...)` ou `supports('title', ...)`. Le provider doit distinguer le mode en interne. **Solution** : ajouter un paramètre `$mode` optionnel ou déduire du format. **Meilleure solution** : passer le mode dans `lookup()`.

**Ajustement de l'interface** : Ajouter `string $mode` à `lookup()` :
```php
public function lookup(string $query, ?ComicType $type, string $mode = 'title'): ?LookupResult;
```

GoogleBooksLookup utilise `$mode` pour construire la query (`isbn:{query}` vs `{query}`).

**Step 4: Lancer les tests — doivent passer**

```bash
ddev exec bin/phpunit tests/Service/Lookup/GoogleBooksLookupTest.php
```

**Step 5: Commit**

```bash
git add src/Service/Lookup/GoogleBooksLookup.php tests/Service/Lookup/GoogleBooksLookupTest.php
git commit -m "refactor(lookup): extraire GoogleBooksLookup du service monolithique"
```

---

## Task 4 : Extraire `OpenLibraryLookup`

**Files:**
- Create: `src/Service/Lookup/OpenLibraryLookup.php`
- Create: `tests/Service/Lookup/OpenLibraryLookupTest.php`

**Step 1: Écrire les tests**

Tests à écrire :
- `testLookupByIsbnReturnsData` — ISBN valide, retourne LookupResult avec titre/éditeur/date/thumbnail
- `testLookupByIsbnFetchesAuthorsInParallel` — Récupère les auteurs via sous-requêtes parallèles
- `testLookupReturnsNullWhenNotFound` — HTTP 404 → null
- `testLookupReturnsNullWhenTitleMissing` — Réponse sans titre → null
- `testLookupHandlesNetworkErrors` — Erreur réseau → null + message error
- `testLookupHandlesRateLimiting` — HTTP 429 → null + message rate_limited
- `testSupportsIsbnOnly` — supports('isbn') = true, supports('title') = false
- `testGetName` — retourne 'open_library'

**Step 2: Lancer — doit échouer**

**Step 3: Implémenter**

Extraire de `IsbnLookupService` :
- `lookup()` : appel `https://openlibrary.org/isbn/{isbn}.json`
- `fetchAuthorsParallel()` : requêtes parallèles pour les auteurs
- Le provider ne supporte **que** le mode `isbn`. `supports('title', ...)` retourne `false`.

```php
class OpenLibraryLookup implements LookupProviderInterface
{
    private const string API_URL = 'https://openlibrary.org/isbn/';
    // ...
    public function supports(string $mode, ?ComicType $type): bool
    {
        return 'isbn' === $mode;
    }
}
```

**Step 4: Lancer — doit passer**

**Step 5: Commit**

```bash
git commit -m "refactor(lookup): extraire OpenLibraryLookup du service monolithique"
```

---

## Task 5 : Extraire `AniListLookup`

**Files:**
- Create: `src/Service/Lookup/AniListLookup.php`
- Create: `tests/Service/Lookup/AniListLookupTest.php`

**Step 1: Écrire les tests**

Tests à écrire :
- `testLookupByTitleReturnsData` — Titre manga, retourne LookupResult avec titre/auteurs/description/thumbnail
- `testLookupExtractsMultipleAuthors` — Filtre rôles Story/Art/Story & Art
- `testLookupFiltersNonAuthorRoles` — Exclut Editor, Director, etc.
- `testLookupFormatsFullDate` — YYYY-MM-DD
- `testLookupFormatsPartialDate` — YYYY-MM ou YYYY
- `testLookupDetectsOneShotByFormat` — format='ONE_SHOT' → isOneShot=true
- `testLookupDetectsOneShotByVolumesAndStatus` — volumes=1 + status=FINISHED → isOneShot=true
- `testLookupCleansTitleSuffixes` — Supprime "Tome 2", "Vol. 3", etc.
- `testLookupReturnsNullWhenNoResults` — Media null → null
- `testLookupHandlesNetworkErrors`
- `testLookupHandlesRateLimiting`
- `testSupportsTitleForMangaOnly` — supports('title', MANGA) = true, supports('title', BD) = false, supports('isbn', ...) = false
- `testGetName` — retourne 'anilist'

**Step 2: Lancer — doit échouer**

**Step 3: Implémenter**

Extraire de `IsbnLookupService` :
- `lookup()` : appel GraphQL AniList
- `cleanTitleForAniList()`, `extractAuthors()`, `formatDate()` → méthodes privées
- Supporte uniquement `title` + `ComicType::MANGA`

```php
class AniListLookup implements LookupProviderInterface
{
    public function supports(string $mode, ?ComicType $type): bool
    {
        return 'title' === $mode && ComicType::MANGA === $type;
    }
}
```

**Step 4: Lancer — doit passer**

**Step 5: Commit**

```bash
git commit -m "refactor(lookup): extraire AniListLookup du service monolithique"
```

---

## Task 6 : Créer `LookupOrchestrator`

**Files:**
- Create: `src/Service/Lookup/LookupOrchestrator.php`
- Create: `tests/Service/Lookup/LookupOrchestratorTest.php`

**Step 1: Écrire les tests**

L'orchestrateur est testé avec des **mock providers** (pas des mock HTTP). Utiliser des implémentations anonymes de `LookupProviderInterface` qui retournent des `LookupResult` prédéfinis.

Tests à écrire :

- `testLookupByIsbnCallsAllSupportingProviders` — Appelle Google Books + Open Library
- `testLookupByIsbnMergesResults` — Fusionne : Google Books prioritaire, Open Library complète
- `testLookupByIsbnReturnNullWhenNoResults` — Tous les providers retournent null → null
- `testLookupByIsbnWithEmptyQueryReturnsNull` — Query vide → null
- `testLookupByIsbnNormalizesQuery` — Supprime tirets et espaces
- `testLookupByTitleCallsMangaProviders` — Pour MANGA : appelle Google Books + AniList
- `testLookupByTitleAniListOverridesThumbnailForManga` — AniList remplace thumbnail
- `testLookupByTitleAniListOverridesIsOneShotForManga` — AniList remplace isOneShot
- `testLookupByTitleAniListDoesNotOverwriteExistingFields` — AniList complète mais ne remplace pas
- `testLookupSkipsProvidersThatDontSupport` — Open Library non appelé pour title mode
- `testLookupCollectsApiMessages` — apiMessages de tous les providers collectés
- `testApiMessagesResetOnEachCall` — Réinitialisés entre les appels
- `testLookupCallsEnrichWhenIncomplete` — Provider enrichable appelé si résultat incomplet
- `testLookupDoesNotEnrichWhenComplete` — Provider enrichable non appelé si résultat complet
- `testLookupHandlesProviderErrors` — Provider qui retourne null → skip gracieux
- `testLookupAddsSources` — Le champ `sources` contient les noms de tous les providers qui ont répondu
- `testLookupAddsIsbnToResult` — En mode isbn, l'ISBN recherché est ajouté au résultat

**Step 2: Lancer — doivent échouer**

**Step 3: Implémenter l'orchestrateur**

```php
<?php

declare(strict_types=1);

namespace App\Service\Lookup;

use App\Enum\ComicType;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

class LookupOrchestrator
{
    /** @var array<string, array{status: string, message: string}> */
    private array $apiMessages = [];

    /**
     * @param iterable<LookupProviderInterface> $providers
     */
    public function __construct(
        #[AutowireIterator('app.lookup_provider')]
        private readonly iterable $providers,
    ) {
    }

    public function getLastApiMessages(): array
    {
        return $this->apiMessages;
    }

    public function lookup(string $isbn, ?ComicType $type = null): ?array
    {
        $isbn = \preg_replace('/[\s-]/', '', $isbn) ?? '';

        if ('' === $isbn) {
            return null;
        }

        $result = $this->doLookup($isbn, $type, 'isbn');

        if (null !== $result) {
            $result['isbn'] = $isbn;
        }

        return $result;
    }

    public function lookupByTitle(string $title, ?ComicType $type = null): ?array
    {
        $title = \trim($title);

        if ('' === $title) {
            return null;
        }

        return $this->doLookup($title, $type, 'title');
    }

    private function doLookup(string $query, ?ComicType $type, string $mode): ?array
    {
        $this->apiMessages = [];
        $results = [];

        foreach ($this->providers as $provider) {
            if (!$provider->supports($mode, $type)) {
                continue;
            }

            $result = $provider->lookup($query, $type, $mode);
            $apiMessage = $provider->getLastApiMessage();

            if (null !== $apiMessage) {
                $this->apiMessages[$provider->getName()] = $apiMessage;
            }

            if (null !== $result) {
                $results[] = $result;
            }
        }

        if (0 === \count($results)) {
            return null;
        }

        $merged = $this->mergeResults($results, $type);

        // Enrichissement si incomplet
        if (!$this->isMergedComplete($merged)) {
            $merged = $this->tryEnrich($merged, $type);
        }

        return $merged;
    }

    /**
     * Fusionne les LookupResult avec règles de priorité.
     * L'ordre des providers dans $results détermine la priorité.
     * AniList a des règles spéciales : remplace thumbnail et isOneShot pour les mangas.
     *
     * @param LookupResult[] $results
     * @return array<string, mixed>
     */
    private function mergeResults(array $results, ?ComicType $type): array
    {
        $merged = [
            'authors' => null,
            'description' => null,
            'isOneShot' => null,
            'publishedDate' => null,
            'publisher' => null,
            'sources' => [],
            'thumbnail' => null,
            'title' => null,
        ];

        foreach ($results as $result) {
            $merged['sources'][] = $result->source;

            // Champs standard : premier non-null gagne
            foreach (['authors', 'description', 'isbn', 'publishedDate', 'publisher', 'thumbnail', 'title'] as $field) {
                if (null === ($merged[$field] ?? null) && null !== $result->$field) {
                    $merged[$field] = $result->$field;
                }
            }

            if (null === $merged['isOneShot'] && null !== $result->isOneShot) {
                $merged['isOneShot'] = $result->isOneShot;
            }

            // AniList override pour les mangas
            if ('anilist' === $result->source && ComicType::MANGA === $type) {
                if (null !== $result->thumbnail) {
                    $merged['thumbnail'] = $result->thumbnail;
                }
                if (null !== $result->isOneShot) {
                    $merged['isOneShot'] = $result->isOneShot;
                }
            }
        }

        return $merged;
    }

    private function isMergedComplete(array $merged): bool
    {
        return null !== $merged['authors']
            && null !== $merged['description']
            && null !== $merged['publishedDate']
            && null !== $merged['publisher']
            && null !== $merged['thumbnail']
            && null !== $merged['title'];
    }

    /**
     * Tente d'enrichir les données via un provider enrichable.
     */
    private function tryEnrich(array $merged, ?ComicType $type): array
    {
        foreach ($this->providers as $provider) {
            if (!$provider instanceof EnrichableLookupProviderInterface) {
                continue;
            }

            $enriched = $provider->enrich($merged, $type);
            $apiMessage = $provider->getLastApiMessage();

            if (null !== $apiMessage) {
                $this->apiMessages[$provider->getName().'.enrich'] = $apiMessage;
            }

            if (null !== $enriched) {
                $merged['sources'][] = $enriched->source;

                // Complète les champs manquants
                foreach (['authors', 'description', 'isbn', 'publishedDate', 'publisher', 'thumbnail', 'title'] as $field) {
                    if (null === ($merged[$field] ?? null) && null !== $enriched->$field) {
                        $merged[$field] = $enriched->$field;
                    }
                }

                if (null === $merged['isOneShot'] && null !== $enriched->isOneShot) {
                    $merged['isOneShot'] = $enriched->isOneShot;
                }
            }
        }

        return $merged;
    }
}
```

**Step 4: Configurer le tag Symfony**

Dans `config/services.yaml` (ou via `#[AutoconfigureTag]` sur l'interface) :
```yaml
services:
    _instanceof:
        App\Service\Lookup\LookupProviderInterface:
            tags: ['app.lookup_provider']
```

L'ordre des providers est déterminé par la priorité du tag. Ajouter sur chaque provider :
```php
#[AutoconfigureTag('app.lookup_provider', priority: 100)]  // Google Books
#[AutoconfigureTag('app.lookup_provider', priority: 80)]   // Open Library
#[AutoconfigureTag('app.lookup_provider', priority: 60)]   // AniList
#[AutoconfigureTag('app.lookup_provider', priority: 40)]   // Gemini
```

**Step 5: Lancer — doivent passer**

```bash
ddev exec bin/phpunit tests/Service/Lookup/LookupOrchestratorTest.php
```

**Step 6: Commit**

```bash
git commit -m "feat(lookup): créer LookupOrchestrator avec fusion et enrichissement"
```

---

## Task 7 : Brancher l'orchestrateur + supprimer l'ancien service

**Files:**
- Modify: `src/Controller/ApiController.php`
- Delete: `src/Service/IsbnLookupService.php`
- Delete: `tests/Service/IsbnLookupServiceTest.php`

**Step 1: Modifier ApiController pour injecter LookupOrchestrator**

Remplacer `IsbnLookupService` par `LookupOrchestrator` dans les deux endpoints :

```php
use App\Service\Lookup\LookupOrchestrator;

// isbnLookup()
public function isbnLookup(Request $request, LookupOrchestrator $lookupOrchestrator): JsonResponse
{
    // ... même logique, juste le type injecté change
    $result = $lookupOrchestrator->lookup($isbn, $type);
    $apiMessages = $lookupOrchestrator->getLastApiMessages();
    // ...
}

// titleLookup()
public function titleLookup(Request $request, LookupOrchestrator $lookupOrchestrator): JsonResponse
{
    // ...
    $result = $lookupOrchestrator->lookupByTitle($title, $type);
    $apiMessages = $lookupOrchestrator->getLastApiMessages();
    // ...
}
```

**Step 2: Lancer tous les tests**

```bash
make test-php
```

Vérifier que tous les tests passent (les tests du contrôleur API, les tests des providers individuels, les tests de l'orchestrateur).

**Step 3: Supprimer l'ancien service et ses tests**

```bash
rm src/Service/IsbnLookupService.php
rm tests/Service/IsbnLookupServiceTest.php
```

**Step 4: Lancer tous les tests**

```bash
make test-php
```

Vérifier qu'il n'y a pas de référence orpheline à `IsbnLookupService`.

**Step 5: Commit**

```bash
git commit -m "refactor(lookup): migrer ApiController vers LookupOrchestrator et supprimer IsbnLookupService"
```

---

## Task 8 : Créer `GeminiLookup`

**Files:**
- Create: `src/Service/Lookup/GeminiLookup.php`
- Create: `tests/Service/Lookup/GeminiLookupTest.php`

**Step 1: Écrire les tests**

Utiliser le **fake client** de `google-gemini-php/symfony` pour mocker les réponses Gemini.

Tests à écrire :

- `testLookupByIsbnReturnsData` — ISBN → Gemini retourne des données structurées → LookupResult
- `testLookupByTitleReturnsData` — Titre → Gemini retourne des données structurées → LookupResult
- `testEnrichCompletesPartialData` — Données partielles enrichies → LookupResult avec champs manquants complétés
- `testEnrichReturnsNullWhenNoTitle` — Données sans titre → null (pas assez de contexte)
- `testLookupReturnsNullOnApiError` — Erreur API → null + message error
- `testLookupReturnsNullOnRateLimit` — Rate limit atteint → null + message rate_limited
- `testLookupUsesCacheForSameQuery` — Deuxième appel avec même query → résultat depuis le cache, pas d'appel API
- `testLookupReturnsNullWhenGeminiReturnsEmptyData` — Gemini retourne un JSON vide/incomplet → null
- `testSupportsIsbnAndTitle` — supports('isbn') = true, supports('title') = true
- `testGetName` — retourne 'gemini'

**Step 2: Lancer — doivent échouer**

**Step 3: Implémenter `GeminiLookup`**

```php
<?php

declare(strict_types=1);

namespace App\Service\Lookup;

use App\Enum\ApiLookupStatus;
use App\Enum\ComicType;
use Gemini\Client as GeminiClient;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

#[AutoconfigureTag('app.lookup_provider', priority: 40)]
class GeminiLookup implements EnrichableLookupProviderInterface
{
    private ?array $lastApiMessage = null;

    public function __construct(
        private readonly CacheInterface $geminiCache,
        private readonly GeminiClient $geminiClient,
        private readonly LoggerInterface $logger,
        private readonly RateLimiterFactory $geminiApiLimiter,
    ) {
    }

    public function getName(): string
    {
        return 'gemini';
    }

    public function supports(string $mode, ?ComicType $type): bool
    {
        return \in_array($mode, ['isbn', 'title'], true);
    }

    public function lookup(string $query, ?ComicType $type, string $mode = 'title'): ?LookupResult
    {
        $this->lastApiMessage = null;

        $cacheKey = 'gemini_'.\md5($query.$mode.($type?->value ?? ''));

        return $this->geminiCache->get($cacheKey, function (ItemInterface $item) use ($query, $type, $mode): ?LookupResult {
            $item->expiresAfter(2592000); // 30 jours

            // Rate limiting
            $limiter = $this->geminiApiLimiter->create('gemini_global');
            if (!$limiter->consume()->isAccepted()) {
                $this->recordApiMessage(ApiLookupStatus::RATE_LIMITED, 'Quota dépassé');
                return null;
            }

            $prompt = $this->buildLookupPrompt($query, $type, $mode);

            return $this->callGemini($prompt);
        });
    }

    public function enrich(array $partialData, ?ComicType $type): ?LookupResult
    {
        $this->lastApiMessage = null;

        $title = $partialData['title'] ?? null;
        if (!\is_string($title) || '' === $title) {
            return null;
        }

        $cacheKey = 'gemini_enrich_'.\md5(\json_encode($partialData).($type?->value ?? ''));

        return $this->geminiCache->get($cacheKey, function (ItemInterface $item) use ($partialData, $type): ?LookupResult {
            $item->expiresAfter(2592000);

            $limiter = $this->geminiApiLimiter->create('gemini_global');
            if (!$limiter->consume()->isAccepted()) {
                $this->recordApiMessage(ApiLookupStatus::RATE_LIMITED, 'Quota dépassé');
                return null;
            }

            $prompt = $this->buildEnrichPrompt($partialData, $type);

            return $this->callGemini($prompt);
        });
    }

    public function getLastApiMessage(): ?array
    {
        return $this->lastApiMessage;
    }

    private function buildLookupPrompt(string $query, ?ComicType $type, string $mode): string
    {
        $typeLabel = $type?->value ?? 'bande dessinée/comics/manga';
        $searchBy = 'isbn' === $mode ? "l'ISBN {$query}" : "le titre \"{$query}\"";

        return <<<PROMPT
            Tu es un assistant spécialisé en bandes dessinées, comics et mangas.
            Recherche les informations sur la série identifiée par {$searchBy} (type: {$typeLabel}).

            Utilise Google Search pour trouver les informations les plus précises et à jour.

            Retourne UNIQUEMENT les informations que tu trouves avec certitude.
            Si tu n'es pas sûr d'une information, laisse le champ à null.
            Pour le titre, retourne le titre de la SÉRIE (pas du tome individuel).
            Pour isOneShot, retourne true si c'est un tome unique (one-shot, intégrale), false si c'est une série multi-tomes.
            PROMPT;
    }

    private function buildEnrichPrompt(array $partialData, ?ComicType $type): string
    {
        $typeLabel = $type?->value ?? 'bande dessinée/comics/manga';
        $existingData = \json_encode(\array_filter($partialData, static fn ($v) => null !== $v && [] !== $v));

        return <<<PROMPT
            Tu es un assistant spécialisé en bandes dessinées, comics et mangas.
            J'ai les informations partielles suivantes sur une série ({$typeLabel}) :
            {$existingData}

            Complète les champs manquants en utilisant Google Search.
            Retourne UNIQUEMENT les informations que tu trouves avec certitude.
            Si tu n'es pas sûr d'une information, laisse le champ à null.
            PROMPT;
    }

    private function callGemini(string $prompt): ?LookupResult
    {
        try {
            $schema = $this->buildJsonSchema();

            $response = $this->geminiClient
                ->geminiFlash()
                ->withGenerationConfig([
                    'responseMimeType' => 'application/json',
                    'responseSchema' => $schema,
                ])
                ->generateContent($prompt);

            $text = $response->text();
            $data = \json_decode($text, true);

            if (!\is_array($data)) {
                $this->recordApiMessage(ApiLookupStatus::ERROR, 'Réponse JSON invalide');
                return null;
            }

            // Vérifie qu'au moins un champ est non-null
            $hasData = false;
            foreach (['authors', 'description', 'publishedDate', 'publisher', 'thumbnail', 'title'] as $field) {
                if (!empty($data[$field])) {
                    $hasData = true;
                    break;
                }
            }

            if (!$hasData) {
                $this->recordApiMessage(ApiLookupStatus::NOT_FOUND, 'Aucun résultat');
                return null;
            }

            $this->recordApiMessage(ApiLookupStatus::SUCCESS, 'Données trouvées via IA');

            return new LookupResult(
                authors: \is_string($data['authors'] ?? null) ? $data['authors'] : null,
                description: \is_string($data['description'] ?? null) ? $data['description'] : null,
                isOneShot: \is_bool($data['isOneShot'] ?? null) ? $data['isOneShot'] : null,
                publishedDate: \is_string($data['publishedDate'] ?? null) ? $data['publishedDate'] : null,
                publisher: \is_string($data['publisher'] ?? null) ? $data['publisher'] : null,
                source: 'gemini',
                thumbnail: \is_string($data['thumbnail'] ?? null) ? $data['thumbnail'] : null,
                title: \is_string($data['title'] ?? null) ? $data['title'] : null,
            );
        } catch (\Throwable $e) {
            $this->logger->error('Erreur Gemini : {error}', ['error' => $e->getMessage()]);
            $this->recordApiMessage(ApiLookupStatus::ERROR, 'Erreur de connexion');

            return null;
        }
    }

    private function buildJsonSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'authors' => ['type' => 'string', 'description' => 'Auteur(s) séparés par des virgules', 'nullable' => true],
                'description' => ['type' => 'string', 'description' => 'Synopsis de la série', 'nullable' => true],
                'isOneShot' => ['type' => 'boolean', 'description' => 'true = tome unique', 'nullable' => true],
                'publishedDate' => ['type' => 'string', 'description' => 'Date au format YYYY-MM-DD ou YYYY', 'nullable' => true],
                'publisher' => ['type' => 'string', 'description' => 'Éditeur français', 'nullable' => true],
                'thumbnail' => ['type' => 'string', 'description' => 'URL image de couverture', 'nullable' => true],
                'title' => ['type' => 'string', 'description' => 'Titre de la série', 'nullable' => true],
            ],
        ];
    }

    private function recordApiMessage(ApiLookupStatus $status, string $message): void
    {
        $this->lastApiMessage = ['message' => $message, 'status' => $status->value];
    }
}
```

**Note** : L'API `google-gemini-php/client` v2.0 peut ne pas supporter Google Search grounding (`tools: [googleSearch]`) nativement. **Vérifier pendant l'implémentation**. Si ce n'est pas supporté, deux options :
1. Appel REST direct via Symfony HttpClient (bypass le client PHP)
2. Utiliser le client sans grounding (le modèle utilise ses connaissances internes)

Option 1 est préférable pour les données à jour. Le plan prévoit cette vérification.

**Step 4: Lancer — doivent passer**

```bash
ddev exec bin/phpunit tests/Service/Lookup/GeminiLookupTest.php
```

**Step 5: Commit**

```bash
git commit -m "feat(lookup): créer GeminiLookup avec cache, rate limiting et structured output"
```

---

## Task 9 : Intégrer Gemini dans l'orchestrateur

**Files:**
- Modify: `tests/Service/Lookup/LookupOrchestratorTest.php`

**Step 1: Ajouter les tests d'intégration Gemini dans l'orchestrateur**

Tests à ajouter :
- `testLookupCallsGeminiAsLastProvider` — Gemini est appelé après les autres sources
- `testLookupGeminiEnrichesIncompleteResult` — Résultat incomplet → Gemini enrichit
- `testLookupGeminiNotCalledWhenResultComplete` — Résultat complet → Gemini non appelé
- `testLookupGeminiFallbackWhenAllOthersReturnNull` — Aucun résultat des APIs classiques → Gemini fait une recherche autonome
- `testLookupGeminiErrorDoesNotBreakResult` — Erreur Gemini → le résultat partiel est quand même retourné

**Step 2: Lancer — doivent échouer**

**Step 3: Ajuster l'orchestrateur si nécessaire**

Les tests devraient passer sans modification si l'orchestrateur est bien implémenté (Task 6). Le tag priority garantit l'ordre.

**Step 4: Lancer — doivent passer**

```bash
ddev exec bin/phpunit tests/Service/Lookup/LookupOrchestratorTest.php
```

**Step 5: Lancer la suite complète**

```bash
make test
```

**Step 6: Commit**

```bash
git commit -m "test(lookup): ajouter tests d'intégration Gemini dans l'orchestrateur"
```

---

## Task 10 : Cleanup — CHANGELOG, CLAUDE.md, documentation

**Files:**
- Modify: `CHANGELOG.md`
- Modify: `CLAUDE.md`

**Step 1: Mettre à jour CHANGELOG.md**

Ajouter dans `## [Unreleased]` / `### Added` :
```markdown
- **Enrichissement Gemini IA** : Intégration de l'API Google Gemini pour enrichir les données des séries
  - Recherche par ISBN ou titre via Gemini 2.0 Flash avec Google Search grounding
  - Enrichissement automatique des champs manquants après lookup classique
  - Structured output JSON pour des réponses fiables et typées
  - Cache filesystem (30 jours) pour économiser les quotas
  - Rate limiting (10 requêtes/minute) pour respecter le plan gratuit
```

Ajouter dans `### Changed` :
```markdown
- **Refactoring architecture lookup** : Extraction du service monolithique `IsbnLookupService` en architecture provider-based
  - Interface `LookupProviderInterface` avec méthode `supports()` pour filtrer les providers par mode (ISBN/titre) et type
  - Providers individuels : `GoogleBooksLookup`, `OpenLibraryLookup`, `AniListLookup`, `GeminiLookup`
  - `LookupOrchestrator` coordonne les appels et fusionne les résultats
  - Interface `EnrichableLookupProviderInterface` pour les providers capables d'enrichir des données existantes
  - Tests migrés vers les providers individuels + tests de l'orchestrateur
```

**Step 2: Mettre à jour CLAUDE.md**

Section Architecture > Services : remplacer `IsbnLookupService` par la nouvelle architecture :
```markdown
### Lookup Providers (src/Service/Lookup/)

**LookupProviderInterface**: `getName()`, `supports(mode, type)`, `lookup(query, type, mode)`, `getLastApiMessage()`

**EnrichableLookupProviderInterface** extends LookupProviderInterface: `enrich(partialData, type)`

**GoogleBooksLookup**: ISBN + titre → Google Books API
**OpenLibraryLookup**: ISBN → Open Library API
**AniListLookup**: titre (manga uniquement) → AniList GraphQL
**GeminiLookup**: ISBN + titre + enrichissement → Google Gemini AI (cache 30j, rate limited)

**LookupOrchestrator**: coordonne les providers, fusionne les résultats, enrichit si incomplet. Remplace `IsbnLookupService`.
```

**Step 3: Supprimer le plan design**

```bash
rm docs/plans/2026-02-13-gemini-integration-design.md
rm docs/plans/2026-02-13-gemini-integration.md
```

**Step 4: Commit**

```bash
git commit -m "docs: mettre à jour CHANGELOG et CLAUDE.md pour le refactoring lookup + Gemini"
```

---

## Notes techniques

### Ordre d'exécution des providers (via tag priority)
1. Google Books (100) — source principale, ISBN + titre
2. Open Library (80) — complément ISBN
3. AniList (60) — manga uniquement, override thumbnail/isOneShot
4. Gemini (40) — enrichissement/fallback

### Paramètre `$mode` dans `lookup()`
L'interface `LookupProviderInterface::lookup()` reçoit un 3e paramètre `string $mode = 'title'`. Cela permet aux providers qui supportent les deux modes (comme GoogleBooksLookup) de construire la requête correctement.

### Google Search grounding dans Gemini
Le client PHP `google-gemini-php/client` v2.0 peut ne pas supporter le parameter `tools: [googleSearch]`. Si c'est le cas pendant l'implémentation :
- **Option A** : Appel REST direct via Symfony HttpClient au lieu du client PHP
- **Option B** : Utiliser le client sans grounding (connaissances internes du modèle)

Vérifier en premier si `withTool()` ou équivalent existe dans le client v2.0.

### Cache
- Pool dédié `gemini.cache` (filesystem)
- Clé : `gemini_` + md5(query + mode + type)
- TTL : 30 jours (2 592 000 secondes)

### Rate Limiting
- Symfony Rate Limiter : `sliding_window`, 10/minute
- Si limite atteinte → retourne `null` + apiMessage `rate_limited`

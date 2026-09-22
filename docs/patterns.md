# Codebase Patterns — Bibliotheque

Reference for implementing features without exploring the codebase.

## Entities (`backend/src/Entity/`)

| Entity | Key fields | Relations | API |
|--------|-----------|-----------|-----|
| `ComicSeries` | title, status:`ComicStatus`, type:`ComicType`, latestPublishedIssue?:int, latestPublishedIssueComplete:bool, isOneShot:bool, defaultTome{Bought,OnNas,Read}:bool, amazonUrl?, description?, publisher?, coverFile?:File, coverImage?, coverUrl?, deletedAt?, lookupCompletedAt?, mergeCheckedAt?, newReleasesCheckedAt? | `authors:M2M→Author`, `tomes:O2M→Tome(cascade,orphanRemoval)` | GetCollection, Get, Post, Patch, Delete(soft), Put(/restore), Delete(/trash/permanent) |
| `Tome` | number:int, tomeEnd?:int, isHorsSerie:bool, bought, onNas, read, isbn?, title? | `comicSeries:M2O→ComicSeries` | Sub: `/comic_series/{id}/tomes` (GetCollection, Post), standalone: Get, Patch, Put, Delete |
| `Author` | name:string(unique), followedForNewSeries:bool(default false) | `comicSeries:M2M(mappedBy)` | GetCollection(search by name), Get, Patch, Post |
| `SeriesSuggestion` | title, type:`ComicType`, authors:JSON, reason:string, status:`SuggestionStatus`(default PENDING) | `sourceSeries:M2O→ComicSeries(SET NULL)` | GetCollection(filter status), Patch(status) |
| `User` | email:string(unique), googleId?, roles, tokenVersion:int(default=1) | — | — |
| `EnrichmentProposal` | field:`EnrichableField`, confidence:`EnrichmentConfidence`, currentValue:JSON?, proposedValue:JSON, source:string, triggeredBy?:string(100), status:`ProposalStatus`(default PENDING), reviewedAt? | `comicSeries:M2O→ComicSeries(CASCADE)` | GetCollection(filter status/comicSeries), Get, Patch(/accept), Patch(/reject) |
| `Notification` | title, message, type:`NotificationType`, read:bool, relatedEntityType?:`NotificationEntityType`, relatedEntityId?:int, metadata?:JSON | `user:M2O→User(CASCADE)` | GetCollection(filter read/type, paginated), Get, Patch(read), Delete |
| `NotificationPreference` | type:`NotificationType`, channel:`NotificationChannel`(default IN_APP) | `user:M2O→User(CASCADE)` | GetCollection(provider: initializer), Patch |
| `PushSubscription` | endpoint:string(unique), publicKey, authToken, expirationTime? | `user:M2O→User(CASCADE)` | GetCollection, Post, Delete |

## Enums (`backend/src/Enum/`)

| Enum | Cases |
|------|-------|
| `ComicStatus` | `BUYING`, `DOWNLOADING`, `FINISHED`, `STOPPED`, `WISHLIST` — `getLabel()` |
| `ComicType` | `BD`, `COMICS`, `LIVRE`, `MANGA` — `getLabel()` |
| `ApiLookupStatus` | `ERROR`, `NOT_FOUND`, `RATE_LIMITED`, `SUCCESS`, `TIMEOUT` |
| `BatchLookupStatus` | `FAILED`, `SKIPPED`, `UPDATED` — `getLabel()` |
| `EnrichableField` | `AMAZON_URL`, `AUTHORS`, `COVER`, `DESCRIPTION`, `ISBN`, `IS_ONE_SHOT`, `LATEST_PUBLISHED_ISSUE`, `PUBLISHER` |
| `EnrichmentConfidence` | `HIGH`, `LOW`, `MEDIUM` — `fromScore(float)` |
| `LookupMode` | `ISBN`, `TITLE` |
| `NotificationChannel` | `BOTH`, `IN_APP`, `OFF`, `PUSH` — `getLabel()` |
| `NotificationEntityType` | `AUTHOR`, `COMIC_SERIES`, `ENRICHMENT_PROPOSAL` |
| `NotificationType` | `AUTHOR_NEW_SERIES`, `ENRICHMENT_APPLIED`, `ENRICHMENT_REVIEW`, `MISSING_TOME`, `NEW_RELEASE` — `getLabel()` |
| `ProposalStatus` | `ACCEPTED`, `PENDING`, `PRE_ACCEPTED`, `REJECTED`, `SKIPPED` |
| `SuggestionStatus` | `ADDED`, `DISMISSED`, `PENDING` — `getLabel()` |

## DTOs (`backend/src/DTO/`)

| DTO | Purpose |
|-----|---------|
| `AuthorReleaseResult` | Result of followed author new series detection (authorName, newSeriesTitle, type) |
| `ComicSeriesFilter` | Query filters for `findWithFilters()` |
| `ComicSeriesListItem` | Cached API list item (JsonSerializable, `fromEntity()`, `__unserialize()` for cache compat) |
| `CoverSearchResult` | Cover image search result (JsonSerializable) |
| `ImportResult` | Global import result: typeDetails, totals (JsonSerializable) |
| `MergeGroup` / `MergeGroupEntry` | Detected merge group + entries (JsonSerializable) |
| `MergePreview` / `MergePreviewTome` | Full merge preview + tomes (JsonSerializable) |
| `MissingTomeResult` | Missing tomes result (missingNumbers, seriesId, seriesTitle) |
| `NasSeriesData` | Series extracted from NAS (title, lastOnNas, readUpTo, readComplete, isComplete) |
| `NewReleaseProgress` | New release check progress (JsonSerializable) |
| `ParsedIntegerValue` | Parsed Excel integer/fini/stop/ranges/CSV (hsCount, isComplete, isStopped, specificValues, value) |
| `PurgeableSeries` | Series eligible for purge (JsonSerializable) |
| `RowImportResult` | Per-row import result (isUpdate, metadataApplied, series, tomesCount) |
| `Service/Lookup/Contract/ApiMessage` | Lookup provider API status (JsonSerializable) |
| `Share/ShareResolution` | Result of shared link resolution (matched, seriesId, lookupResult) |
| `Share/ShareUrlInfo` | Extracted data from shared URL (isbn, titleHint, type) |

## Domain Events (`backend/src/Event/`)

- `ComicSeriesCreatedEvent` — postPersist, holds entity
- `ComicSeriesUpdatedEvent` — postUpdate (non-soft-delete), holds entity
- `ComicSeriesDeletedEvent` — soft/hard/permanent-delete, holds `int $id` + `string $title`

## Event Listeners (`backend/src/EventListener/`)

| Listener | Purpose |
|----------|---------|
| `ComicSeriesCacheInvalidator` | postPersist/Update/Remove: invalidates cache & collection version for ComicSeries, Tome, Author |
| `ComicSeriesEventListener` | postPersist/Update/Remove: dispatches domain events |
| `CoverUrlChangeListener` | preUpdate: dispatches `DownloadCoverMessage` (async) when `coverUrl` changes on ComicSeries |
| `EnrichOnCreateListener` | ComicSeriesCreatedEvent → dispatches `EnrichSeriesMessage` (async). `disable()`/`enable()` for batch imports |
| `HttpCacheListener` | kernel.request (early 304 on versioned ETag) & kernel.response (ETag + no-cache) on GET `/api/comic_series` |
| `JwtTokenVersionListener` | JWT create: adds tokenVersion. JWT decode: validates version match |
| `PlaceholderSecretChecker` | kernel.request (priority 255): blocks prod if placeholder secrets |
| `ReEnrichOnUpdateListener` | ComicSeriesUpdatedEvent → re-dispatches `EnrichSeriesMessage` if cover/description/publisher still null (cooldown 24h) |
| `TomeLatestIssueListener` | prePersist/Update on Tome: updates `latestPublishedIssue` on parent ComicSeries when tome number is higher |

## Controllers (`backend/src/Controller/`)

| Controller | Routes |
|------------|--------|
| `ApiController` | `GET /api/lookup/{isbn,title}?...&type=...` (JWT, 30/min) |
| `BatchLookupController` | `GET /api/tools/batch-lookup/preview`, `POST .../run` (async Messenger queue) |
| `BatchTomeController` | `POST /api/comic_series/{id}/tomes/batch` (batch create tomes with validation) |
| `DevLoginController` | `POST /api/login/dev` (dev-only, bypasses OAuth for automated testing/MCP) |
| `GoogleLoginController` | `POST /api/login/google` (public) |
| `MergeSeriesController` | `POST /api/merge-series/{detect,preview,execute,suggest}` |
| `NotificationController` | `GET /api/notifications/unread-count`, `PATCH /api/notifications/read-all` |
| `PurgeController` | `GET /api/tools/purge/preview?days=30`, `POST .../execute` |
| `ShareController` | `POST /api/share` (Web Share Target resolution: URL/title → DB match or lookup candidate) |

## State Processors & Providers (`backend/src/State/`)

| File | Purpose |
|------|---------|
| `AuthorCreateProcessor` | POST `/api/authors`: find-or-create by name without triggering UniqueEntity violation |
| `ComicSeriesCollectionProvider` | GET `/api/comic_series` (join-fetch eager-loading authors & tomes, prevents N+1) |
| `ComicSeriesDeleteProcessor` | Soft delete |
| `ComicSeriesPermanentDeleteProcessor` | Permanent delete |
| `ComicSeriesRestoreProcessor` | Restore from trash |
| `EnrichmentProposalAcceptProcessor` | Accept proposal → apply value + log. Checks stale (409 Conflict) |
| `EnrichmentProposalRejectProcessor` | Reject proposal → log |
| `NotificationPreferenceInitializer` | AP4 provider: creates default prefs (IN_APP) on first GET |
| `SoftDeletedComicSeriesProvider` | Disables soft-delete filter for trashed access |
| `TrashCollectionProvider` | GET `/api/trash` |

## Messages & Handlers (`backend/src/Message/`, `backend/src/MessageHandler/`)

| Message | Handler | Purpose |
|---------|---------|---------|
| `DownloadCoverMessage(seriesId, coverUrl)` | `DownloadCoverHandler` | Downloads and stores cover in WebP format (async via Messenger) |
| `EnrichSeriesMessage(seriesId)` | `EnrichSeriesHandler` | Automated metadata enrichment via lookup providers (async via Messenger) |
| `WarmThumbnailsMessage(coverImage)` | `WarmThumbnailsHandler` | Pre-warms LiipImagine thumbnail cache for cover (async via Messenger) |

## Deploy Tasks (`backend/src/DeployTask/`)

| File | Purpose |
|------|---------|
| `DeployTaskInterface` / `AbstractDeployTask` | One-off post-deployment database migration and data-fix runner (`app:deploy:run-tasks`) |

## Services (`backend/src/Service/`)

### ComicSeries (`Service/ComicSeries/`)
| Service | Key API |
|---------|---------|
| `ComicSeriesService` | `softDelete()`, `moveToLibrary()`, `restore()`, `permanentDelete()` |
| `PurgeService` | `findPurgeable(days)`, `executePurge(seriesIds)` |

### Cover (`Service/Cover/`)
| Service | Key API |
|---------|---------|
| `CoverDownloader` | `downloadAndStore(series, url): bool` — HTTP GET → validation ratio/résolution/placeholders → resize 600×900 → WebP → VichUploader |
| `CoverRemoverInterface` / `VichCoverRemover` | Cover removal + LiipImagine cache invalidation |
| `CoverSearchService` | `search(query, ?type): CoverSearchResult[]` — Google Books + Serper |
| `ThumbnailGenerator` | `generate(coverImage): void` — pre-warms LiipImagine `cover_thumbnail` |
| `Upload/UploadHandlerInterface` / `Upload/VichUploadHandlerAdapter` | VichUploader abstraction |

### Notification (`Service/Notification/`)
| Service | Key API |
|---------|---------|
| `NotificationService` | `create(user, type, title, message, ?entityType, ?entityId, ?metadata): ?Notification` — checks prefs, sends push |
| `NotifierInterface` | Contract for notification dispatch |
| `WebPushService` | `sendToUser(user, title, body, ?url)` — VAPID Web Push via `minishlink/web-push` |

### Recommendation (`Service/Recommendation/`)
| Service | Key API |
|---------|---------|
| `AuthorReleaseCheckerService` | `check(dryRun): Generator<AuthorReleaseResult>` — checks new series from followed authors via Gemini |
| `MissingTomeDetectorService` | `detect(dryRun): Generator<MissingTomeResult>` — detects missing tomes, dispatches notifications |
| `NewReleaseCheckerService` | `run(dryRun, ?limit): Generator<NewReleaseProgress>` — checks new releases for BUYING series |
| `SimilarSeriesService` | `generateSuggestions(): Generator<SeriesSuggestion>` — AI suggestions via Gemini |

### Other modules
| Service | Key API |
|---------|---------|
| `Enrichment/ConfidenceScorer` | `score(query, type, mode, result, sources): EnrichmentConfidence` |
| `Enrichment/EnrichmentService` | `enrich(series, result, mode, sources): EnrichmentConfidence` — routes HIGH→apply, MEDIUM→propose, LOW→skip |
| `Import/ImportService` | `import(filePath, dryRun): ImportResult` — tracking + metadata Excel import |
| `Merge/MergePreviewBuilder` | `buildFromGroup()`, `buildFromManualSelection()`, `suggestFromGemini()` |
| `Merge/MergePreviewHydrator` | `hydrate(array): MergePreview` — JSON→DTO hydration |
| `Merge/SeriesGroupDetector` | `detect(): list<MergeGroup>` — Gemini AI grouping (batch size 50) |
| `Merge/SeriesMerger` | `execute(MergePreview): ComicSeries` — merge + cleanup |
| `Nas/NasDirectoryParser` | Parse NAS directory listings → `NasSeriesData[]` (unread, read, in-progress) |
| `Share/ShareResolver` | `resolve(info, ?titleFallback): ShareResolution` — lookup + fuzzy DB match |
| `Share/ShareUrlParser` | `parse(url): ShareUrlInfo` — extracts ISBN or title hint from shared URL |

### Lookup (`Service/Lookup/`)

**Root (public API):**
| Class | Purpose |
|-------|---------|
| `BatchLookupService` | `countSeriesToProcess()`, `queue(): int` (dispatches `EnrichSeriesMessage` to worker) |
| `LookupApplier` | Applies result to series (null fields only), creates missing tomes |
| `LookupOrchestrator` | Coordinates providers, merges by field priority |

**Contract/ (interfaces + DTOs):**
| Class | Purpose |
|-------|---------|
| `ApiMessage` | Lookup provider API status (JsonSerializable) |
| `EnrichableLookupProviderInterface` | Extends: `prepareEnrich`/`resolveEnrich` |
| `LookupProviderInterface` | `getFieldPriority(field, ?type)`, `supports(mode, type)`, `prepareLookup()`/`resolveLookup()` |
| `LookupResult` | Immutable DTO (JsonSerializable): amazonUrl, authors, description, isbn, isOneShot, latestPublishedIssue, publishedDate, publisher, seriesTitle, source, thumbnail, title, tomeEnd, tomeNumber, tomeTitle |
| `MultiResultLookupProviderInterface` | Extends: `prepareMultipleLookup`/`resolveMultipleLookup` |

**Gemini/ (Gemini infrastructure):**
| Class | Purpose |
|-------|---------|
| `AbstractGeminiLookupProvider` | Extends AbstractLookupProvider: `callGemini()`, `consumeRateLimit()`, `prepareWithCache()`, CACHE_TTL=30d |
| `GeminiClientPool` | Key × model rotation on 429, `executeWithRetry(callable): T` |
| `GeminiJsonParser` | Static `parseJsonFromText(string): ?array` — parses Gemini JSON (with/without markdown code blocks) |
| `GeminiQueryService` | `queryJsonArray(prompt): list<array>` — DRY helper for Gemini JSON queries |

**Provider/ (concrete providers):**
| Provider | Mode | Default Priority |
|----------|------|-----------------|
| `AniListLookup` | Title (manga only) | 60 |
| `BedethequeLookup` | ISBN + title (Gemini grounding) | BD:150, other:110, thumb:50 |
| `BnfLookup` | ISBN + title | 90 |
| `ComicVineLookup` | Title | publisher:100 (BD/Comics), other:55 |
| `GeminiLookup` | ISBN + title + enrichment | 40 |
| `GoogleBooksLookup` | ISBN + title | 100 |
| `JikanLookup` | Title (manga only) | description/latestIssue:65, other:50 |
| `KitsuLookup` | Title (manga only) | thumb:55, other:45 |
| `MangaDexLookup` | Title (manga only) | authors:55, other:40 |
| `OpenLibraryLookup` | ISBN | 80 |
| `WikipediaLookup` | ISBN + title (cache 7d) | 120 (description: 10) |
| `AbstractLookupProvider` | Base: shared `lastApiMessage`, `recordApiMessage()` |

**Util/ (stateless helpers):**
| Class | Purpose |
|-------|---------|
| `GoogleBooksUrlHelper` | Static `optimizeThumbnailUrl(string): ?string` — HTTPS, zoom=0, remove edge=curl, null on placeholder |
| `LookupTitleCleaner` | Static `clean(title): string` — removes tome/volume suffixes |
| `TitleMatcher` | Static `matches(query, resultTitle, threshold=0.85): bool`, `similarity(query, resultTitle): float` (Levenshtein > 85%) |

## Repositories (`backend/src/Repository/`)

| Repo | Custom methods |
|------|----------------|
| `AuthorRepository` | `findOrCreate()`, `findOrCreateMultiple()` |
| `ComicSeriesRepository` | `findWithFilters()`, `findAllForApi()`, `findBuyingForReleaseCheck()`, `findWithMissingLookupData()`, `findForAutoEnrich()`, `findForMergeDetection()`, `findPurgeable(days)`, `findTrashed()`, `findWithLocalCover()` |
| `EnrichmentProposalRepository` | `findPendingBySeries()`, `findPendingBySeriesAndField()`, `countPending()` |
| `NotificationPreferenceRepository` | `findByUser()`, `findByUserAndType()` |
| `NotificationRepository` | `countUnread()`, `existsUnreadByTypeAndEntity()`, `markAllRead()`, `purgeOlderThan()` |
| `PushSubscriptionRepository` | `findByUser()`, `findByEndpoint()` |
| `SeriesSuggestionRepository` | `existsPendingByTitleAndType()`, `findDismissedTitles()`, `findPending()` |
| `TomeRepository` | Standard |
| `UserRepository` | Standard |

## Commands (`backend/src/Command/`)

| Command | Signature |
|---------|-----------|
| `AutoEnrichCommand` | `app:auto-enrich [--delay=2] [--dry-run] [--force] [--limit=0] [--type=...]` — automated metadata enrichment with confidence scoring |
| `CheckAuthorReleasesCommand` | `app:check-author-releases [--dry-run]` — checks new series from followed authors |
| `CheckNewReleasesCommand` | `app:check-new-releases [--dry-run] [--limit=0]` — checks new issues for BUYING series |
| `DetectMissingTomesCommand` | `app:detect-missing-tomes [--dry-run]` — scans for gaps in tome numbers and creates alerts |
| `DownloadCoversCommand` | `app:download-covers [--delay=1] [--dry-run] [--limit=0]` — downloads pending covers |
| `ImportCommand` | `app:import <file> [--dry-run]` — Excel catalog import |
| `InvalidateTokensCommand` | `app:invalidate-tokens [--email=...]` — increments JWT token version |
| `PurgeDeletedCommand` | `app:purge-deleted [--days=30] [--dry-run]` — permanently deletes old trashed series |
| `PurgeNotificationsCommand` | `app:purge-notifications [--days=90]` — purges old read notifications |
| `RunDeployTasksCommand` | `app:deploy:run-tasks` — executes pending post-deploy tasks (`deploy-tasks/Task*.php`) |
| `ScanNasCommand` | `app:scan-nas [-o var/nas-import.xlsx]` — SSH scan NAS directory structure → Excel |
| `WarmThumbnailsCommand` | `app:warm-thumbnails [--async] [--dry-run]` — pre-warms LiipImagine cover thumbnails |

## Other Backend

- **Fixtures**: `UserFixtures` — test user `test@example.com` / googleId `test-google-id`
- **Filter**: `SoftDeleteFilter` — SQL filter excluding soft-deleted entities (enabled by default)
- **Scheduler**: `backend/src/Schedule.php` (`#[AsSchedule('default')]`) — centralizes scheduled cron jobs (auto-enrich Tue-Sat, release checks, cover downloads, missing tomes Sun, author releases Mon, monthly purges, daily failed Messenger retry). Consumed by `messenger:consume scheduler_default` in supervisord.

### Config highlights (`backend/config/packages/`)

| File | Key settings |
|------|-------------|
| `rate_limiter.yaml` | `gemini_api` 20/min, `dev_login` 5/min, `google_login` 10/min (sliding window) |
| `cache.yaml` | `gemini.cache` 30d, `wikipedia.cache` 7d |
| `lexik_jwt_authentication.yaml` | TTL 365d, token versioning via `JwtTokenVersionListener` |
| `liip_imagine.yaml` | `cover_thumbnail` 300x450 webp, `cover_medium` 600x900 webp |
| `security.yaml` | JWT firewall `/api/` (stateless). Public: `POST /api/login/google` |
| `vich_uploader.yaml` | `comic_covers` → `public/uploads/covers` |
| `messenger.yaml` | Doctrine transport (`doctrine://default`), `DownloadCoverMessage` + `EnrichSeriesMessage` → async, failed transport, retry ×3. Test: `in-memory://` |
| `secrets/prod/` | Vault: `APP_SECRET` + `JWT_PASSPHRASE`. Decrypt key gitignored. VAPID keys (`VAPID_PUBLIC_KEY`, `VAPID_PRIVATE_KEY`, `VAPID_SUBJECT`) are configured via `.env` / `.env.local`. |

### Backend Tests (`backend/tests/`)

Three-tier: **Unit** (no kernel) → **Integration** (kernel + DB) → **Functional** (HTTP). PHPUnit 12, DAMA DoctrineTestBundle.

| Directory | Coverage |
|-----------|----------|
| `Unit/Entity/` | Author, ComicSeries, Tome, User (4 files) |
| `Unit/Enum/` | ApiLookupStatus, BatchLookupStatus, ComicStatus, ComicType, EnrichmentConfidence (5 files) |
| `Unit/Event/` | ComicSeriesCreatedEvent, ComicSeriesDeletedEvent, ComicSeriesUpdatedEvent (3 files) |
| `Unit/EventListener/` | CacheInvalidator, EventListener, CoverUrlChange, EnrichOnCreate, HttpCache, JwtTokenVersion, PlaceholderSecretChecker, ReEnrichOnUpdate, TomeLatestIssue (9 files) |
| `Unit/Command/` | AutoEnrichCommand, DownloadCoversCommand (2 files) |
| `Unit/DeployTask/` | AbstractDeployTask (1 file) |
| `Unit/DTO/` | CoverSearchResult, NewReleaseProgress (2 files) |
| `Unit/Message/` & `Unit/MessageHandler/` | DownloadCover, EnrichSeries, WarmThumbnails (4 files) |
| `Unit/Service/ComicSeries/` | ComicSeriesService, PurgeService |
| `Unit/Service/Cover/` | CoverDownloader, CoverSearchService, ThumbnailGenerator, VichUploadHandlerAdapter, VichCoverRemover |
| `Unit/Service/Enrichment/` | ConfidenceScorer, EnrichmentService |
| `Unit/Service/Import/` | ImportService |
| `Unit/Service/Lookup/` | BatchLookupService, LookupApplier, LookupOrchestrator |
| `Unit/Service/Lookup/Contract/` | LookupResult |
| `Unit/Service/Lookup/Gemini/` | GeminiCircuitBreaker, GeminiClientPool, GeminiJsonParser, GeminiQueryService |
| `Unit/Service/Lookup/Provider/` | All 11 providers + AbstractLookupProvider (12 files) |
| `Unit/Service/Lookup/Util/` | GoogleBooksUrlHelper, LookupTitleCleaner, TitleMatcher |
| `Unit/Service/Merge/` | MergePreviewBuilder, MergePreviewHydrator, SeriesGroupDetector, SeriesMerger |
| `Unit/Service/Nas/` | NasDirectoryParser |
| `Unit/Service/Notification/` | NotificationService |
| `Unit/Service/Recommendation/` | AuthorReleaseCheckerService, NewReleaseCheckerService |
| `Unit/Service/Share/` | ShareResolver, ShareUrlParser |
| `Unit/State/` | ComicSeriesDelete, ComicSeriesPermanentDelete, ComicSeriesRestore, EnrichmentProposalAccept, EnrichmentProposalReject, SoftDeletedComicSeriesProvider, TrashCollectionProvider (7 files) |
| `Integration/Command/` | CheckNewReleases, DownloadCovers, InvalidateTokens, PurgeDeleted, WarmThumbnails (5 files) |
| `Integration/Doctrine/` | SoftDeleteFilter |
| `Integration/Repository/` | AuthorRepository, ComicSeriesRepository, TomeRepository, UserRepository (4 files) |
| `Integration/Service/Merge/` | SeriesMerger (full DB) |
| `Functional/Api/` | Author, ComicSeries, HttpCache, Lookup, MergeSeries, Tome, Trash (7 files) |
| `Functional/Auth/` | GoogleLogin, JwtAuth (2 files) |
| `Functional/Controller/` | BatchLookup, Purge, Share (3 files) |
| `Functional/Security/` | Authentication, RateLimit (2 files) |
| `Factory/` | `EntityFactory` — `createAuthor()`, `createComicSeries()`, `createTome()`, `createUser()` |
| `Trait/` | `AuthenticatedTestTrait` — JWT auth helper |

## Frontend — Pages (`frontend/src/pages/`)

| Page | Route | Purpose |
|------|-------|---------|
| `ComicDetail` | `/comic/:id` | Detail view: cover, unified metrics & acquisition tracking (buy/NAS toggles with ✕ unmonitored axes), foldable `VolumeMatrixAccordion`, metadata, edit/delete |
| `ComicForm` | `/comic/new`, `/comic/:id/edit` | Create/edit series: barcode scan, title lookup, tomes table, author autocomplete |
| `EnrichmentReview` | `/tools/enrichment-review` | Review pending metadata enrichment proposals (accept/reject) |
| `HelpPage` | `/tools/help` | Integrated user manual and feature guide |
| `Home` | `/` | Main library grid/shelf view with URL-synced filters (`useSearchParams`) |
| `Login` | `/login` | Google OAuth authentication |
| `LookupTool` | `/tools/lookup` | Trigger batch metadata lookup for incomplete series (dispatches to worker) |
| `MergeSeries` | `/tools/merge-series` | Series deduplication tool (Gemini auto-detect and manual select tabs) |
| `NotFound` | `*` | 404 fallback |
| `Notifications` | `/notifications` | Notification center: list alerts, mark as read, delete |
| `NotificationSettings` | `/settings/notifications` | Configure notification delivery channels per alert type |
| `PurgeTool` | `/tools/purge` | Preview and execute permanent deletion of old trashed series |
| `QuickAdd` | `/quick-add` | Rapid collection addition via barcode scanner with reticle, 1-tap confirmation card, batch mode, and session ingestion counter |
| `Search` | `/search` | Multicriteria series and volume search |
| `ShareHandler` | `/share` | Web Share Target handler (opens detail if existing, pre-fills form if new) |
| `Suggestions` | `/tools/suggestions` | Review AI-generated series recommendations (add to library or dismiss) |
| `ToBuy` | `/to-buy` | Missing tomes to purchase grouped by series (`bought = false`) |
| `ToDownload` | `/to-download` | Missing tomes to download on NAS grouped by series (`onNas = false`) |
| `Tools` | `/tools` | Administrative utilities launcher hub |
| `Trash` | `/trash` | Trashed series management: restore or permanently delete |

## Frontend — Components (`frontend/src/components/`)

| Component | Purpose |
|-----------|---------|
| `AcquisitionList` | Grouped list of missing tomes for ToBuy / ToDownload with toggles |
| `AcquisitionTabs` | Tab navigation between ToBuy (`/to-buy`) and ToDownload (`/to-download`) |
| `AddedStack` | Animated floating counter stack of recently added items (QuickAdd) |
| `AuthGuard` | Route wrapper redirecting unauthenticated users to `/login` |
| `AuthorAutocomplete` | Headless UI combobox for searching and creating authors |
| `BarcodeScanner` | Continuous camera barcode scanner via `html5-qrcode` |
| `BottomNav` | 4-pillar navigation bar (Collection, Recherche, Scanner, Outils) |
| `Breadcrumb` | Hierarchical breadcrumb navigation with accessibility attributes |
| `CardActionBar` | Mobile fixed action overlay for series card (Edit/Delete) |
| `CollapsibleSection` | Expandable accordion container with animated toggle |
| `CollectionMap` | Interactive visual grid of numbered tome squares opening TomeDrawer |
| `ComicCard` | Mini-dashboard card: unobstructed cover, missing tomes micro-badge, 3-metric tracking row with ✕ indicator on unmonitored axes, operational tobuy/todownload strips |
| `ComicCardSkeleton` / `SkeletonBox` | Shimmering loading placeholders |
| `ComponentErrorBoundary` | Contextual error boundary with retry support |
| `ConfirmModal` | Headless UI modal dialog for confirming destructive operations |
| `ContinueReading` | Horizontal carousel of in-progress series with unread tomes |
| `CoverImage` | Image component with skeleton loading, placeholder fallback, and aspect ratio constraint |
| `CoverLightbox` | Fullscreen modal viewer for high-resolution cover images |
| `CoverSearchModal` | Modal search interface to query and pick online cover thumbnails |
| `DatePartialSelect` | Granular date picker supporting partial dates (year/month/day) |
| `EmptyState` | Informative empty screen graphic with optional action button |
| `ErrorFallback` | Application-level full-page crash fallback UI |
| `FileDropZone` | Drag-and-drop file upload zone for spreadsheet imports |
| `FilterChips` | Horizontal scrollable filter pills for quick status and type toggling |
| `Filters` | Dropdown filter controls (type, status, sort order) |
| `Layout` | Application shell (Header, BottomNav, NotificationBell, OfflineBanner, Toast container) |
| `LookupCandidateCard` | Card previewing metadata candidate match with diff and confidence score |
| `LookupSection` | ISBN and title search input with candidate selection in ComicForm |
| `MergeGroupCard` | Card representing a detected merge group with candidate entries |
| `MergeMetadataForm` | Metadata conflict resolution form in merge preview |
| `MergePreviewModal` | Modal dialog containing merge preview form and tome combination table |
| `MergeSeriesConfirmModal` | Confirmation prompt before executing series merge |
| `MergeTomeTable` | Interactive table for resolving overlapping tome numbers during merge |
| `NotificationBell` | Bell icon in header displaying unread notification count badge |
| `OfflineBanner` | Sticky alert banner indicating offline mode and queued changes |
| `ProgressBar` | Accessible progress indicator supporting compact and full variants |
| `ProposalCard` | Card displaying metadata proposal with before/after diff and accept/reject controls |
| `QuickAddScan` | Barcode scanner view with camera targeting reticle, laser effect, and 1-tap confirmation card |
| `QuickAddSearch` | Instant title lookup search view for QuickAdd |
| `SearchInput` | Text input with debounced callback and clear button |
| `SelectListbox` | Accessible custom dropdown select built on Headless UI Listbox |
| `SeriesEnrichmentProposals` | Tabbed section on ComicDetail displaying active proposals and historical logs |
| `SeriesMultiSelect` | Multi-item selection combobox with badges and search filter |
| `ShelfRow` | Horizontal wooden shelf visualization showing book spines |
| `ShelfView` | Alternate library layout rendering series on virtual bookshelves |
| `StickySearchBar` | Sticky header containing search input and filter toggles |
| `SyncErrorBanner` / `SyncPendingIndicator` | Offline synchronization state and conflict alerts |
| `SyncFailureSection` | Accordion view detailing failed offline mutations with retry triggers |
| `TomeDrawer` | Mobile bottom drawer with 3 large toggles (Acheté, Sur NAS, Lu) and instant auto-save |
| `TomeTable` | Responsive tome list (desktop table / mobile cards) with batch tome creation and per-tome ISBN/title search |
| `VirtualGrid` | High-performance virtualized grid powered by `react-virtuoso` |
| `VolumeMatrixAccordion` | Foldable accordion containing volume squares (`CollectionMap`) or table view, sorted columns, bulk toggle, folded by default for long series (> 12 tomes) |

## Frontend — Hooks (`frontend/src/hooks/`)

| Hook | Purpose |
|------|---------|
| `useAuth` | Google login mutation, session check, logout handler |
| `useAuthorManagement` | Author autocomplete and addition logic for ComicForm |
| `useAuthors` | Author query with name filtering |
| `useBatchLookup` | Preview count query + trigger queue mutation (`POST /api/tools/batch-lookup/run`) |
| `useBuyTome` | Optimistic PATCH mutation to mark tome as bought |
| `useColumnCount` | Computes responsive column counts based on viewport and container width |
| `useComic` / `useComics` | Single series query / filtered library collection query |
| `useComicForm` | Orchestrator hook managing state across ComicForm sub-hooks |
| `useCoverSearch` | Queries Google Books and Serper thumbnail images |
| `useCreateComic` / `useUpdateComic` / `useDeleteComic` | Series CRUD mutations |
| `useCreateTome` / `useUpdateTome` / `useDeleteTome` | Tome CRUD mutations with offline queue and optimistic updates |
| `useDarkMode` | Dark mode toggler synced with `<html>` class and `localStorage` |
| `useDebounce` | Debounces high-frequency input values |
| `useDominantColor` | Extracts vibrant background color from cover image |
| `useEnrichment` | Queries pending proposals and performs accept/reject mutations |
| `useFollowedAuthors` | Manages followed authors list and follow/unfollow toggle |
| `useGoBack` | Smart navigation navigating to in-app parent or falling back to root |
| `useLookup` | Hooks for single ISBN or title metadata lookups |
| `useLookupFeature` | Manages search query, results, and autofill application inside ComicForm |
| `useMediaQuery` | Subscribes to CSS media query changes |
| `useMergePreviewForm` | Reducer hook managing form state for merge preview |
| `useMergeSeries` | Hooks for merge group detection, preview generation, and execution |
| `useNotificationPreferences` | Queries and updates user notification channel settings |
| `useNotifications` | Fetches notification list, unread count badge, and mark-read actions |
| `useOfflineMutation` | Wraps mutations to transparently enqueue to IndexedDB when offline |
| `useOnlineStatus` | Tracks browser network connectivity via `navigator.onLine` |
| `usePendingQueueCount` | Polls count of pending offline mutations in IndexedDB |
| `usePullToRefresh` | Touch gesture hook enabling pull-to-refresh on mobile |
| `usePurge` | Queries purgeable series preview and executes bulk deletion |
| `useQuickAdd` | State machine and mutations for rapid continuous tome additions |
| `useScrollRestoration` | Restores window scroll position across client-side page transitions |
| `useScrollReveal` | IntersectionObserver hook applying fade-in effects on scroll |
| `useServiceWorker` | Registers service worker, detects updates, and communicates auth tokens |
| `useSuggestions` | Manages AI recommendation queries, additions, and dismissals |
| `useSyncFailures` / `useSyncStatus` | Subscribes to sync failures and background synchronization events |
| `useTomeManagement` | Manages tome state, batch addition, per-tome ISBN/title lookup, and barcode scanning in ComicForm |
| `useTrash` | Queries soft-deleted series and performs restore or permanent purge |

## Frontend — Services & Utils

| File | Exports |
|------|---------|
| `endpoints.ts` | `endpoints` — Centralized catalogue of API endpoint paths (without `/api` prefix) |
| `queryKeys.ts` | `queryKeys` — Hierarchical query key factory for TanStack Query |
| `services/api.ts` | `apiFetch<T>()`, `loginWithGoogle()`, `getToken/setToken/removeToken()`, `isAuthenticated()`, error parsers |
| `services/offlineQueue.ts` | IndexedDB persistent queue: enqueue, dequeue, status tracking, pending count |
| `services/syncHandler.ts` | `processSyncQueue()` — Sequential FIFO replay of queued mutations with retry logic |
| `styles/formStyles.ts` | Shared Tailwind CSS design tokens for form inputs and labels |
| `utils/coverUtils.ts` | `getCoverSrc()` — Resolves local upload image path or falls back to remote URL |
| `utils/enrichmentUtils.ts` | `formatEnrichmentValue()` — Serializes proposal values for diff presentation |
| `utils/lookupCandidate.ts` | `scoreCandidate()`, `sortCandidates()` — Match scoring and sorting for lookup candidates |
| `utils/releaseUtils.ts` | `hasNewRelease()` — Identifies ongoing series with newly added volumes |
| `utils/searchComics.ts` | `searchComics()` — Fuzzy multi-field search engine powered by Fuse.js |
| `utils/sortComics.ts` | `SortOption`, `sortComics()` — Client-side locale-aware sorting |
| `utils/syncLabels.ts` | Human-readable labels and formatters for sync operations and fields |
| `utils/toBuyUtils.ts` | `getSeriesToBuy()` — Aggregates and groups unbought volumes across series |
| `utils/tomeUtils.ts` | `parseRange()`, `findMissingNumbers()` — Range expansion (`1-5`) and hole detection |

## Frontend — Types (`frontend/src/types/`)

- `api.ts`: `HydraCollection<T>`, `Author`, `Tome`, `ComicSeries`, `PurgeableSeries`, `MergeGroup`, `MergeGroupEntry`, `MergePreview`, `MergePreviewTome`, `CreateComicPayload`, `UpdateComicPayload`, `TomePayload`, `CreateTomePayload`, `ShareLookupResult`, `ShareResponse`, `LookupCandidatesResponse`, `LookupCandidate`, `EnrichmentProposal`, `LookupResult`
- `enums.ts`: `ComicStatus`, `ComicType`, `EnrichmentConfidence`, `NotificationChannel`, `NotificationEntityType`, `NotificationType`, `ProposalStatus`, `SuggestionStatus` (+ label and badge color helpers)
- `notifications.ts`: `AppNotification`, `NotificationPreference`
- `sync.d.ts`: `SyncManager`, `SyncEvent` ambient service worker types

### Frontend Tests (`frontend/src/__tests__/`)

Three-tier: Unit + Integration. Vitest + jsdom + Testing Library + MSW.

| Directory | Coverage |
|-----------|----------|
| `helpers/` | `renderWithProviders()`, MSW server and mock handlers, test factories |
| `unit/` | Unit tests for components (`AddedStack`, `SearchInput`, `ShelfRow`, `ShelfView`, `VirtualGrid`), hooks (`useColumnCount`, `useDebounce`, `useQuickAdd`), `queryClient`, `queryKeys`, `endpoints`, services (`api`, `offlineQueue`, `syncHandler`), `styles`, `src/sw-custom.ts`, `types/enums`, and all 7 utility files (17 test files) |
| `integration/components/` | All 29 individual component integration suites |
| `integration/hooks/` | All 23 hook integration test suites |
| `integration/pages/` | All 15 page test suites (+ `App.test.tsx`) |

### Frontend Config

| File | Purpose |
|------|---------|
| `vite.config.ts` | React + Tailwind + VitePWA (`injectManifest`, `src/sw-custom.ts`) + API/uploads proxy + vendor chunk splitting |
| `src/sw-custom.ts` | Precache, NetworkFirst API caching (5s timeout), CacheFirst covers (30d), Background Sync, Push notifications |
| `src/theme.ts` | `THEME_COLOR_LIGHT` / `THEME_COLOR_DARK` — Canonical theme colors for PWA status bar |
| `src/queryClient.ts` | TanStack Query client configuration (staleTime 5min, retry 1) |
| `src/App.tsx` | `createBrowserRouter` route tree + providers + code-split lazy loading + View Transitions |
| `src/index.css` | Tailwind CSS configuration, dark mode tokens, `--bottom-nav-h: 4.5rem` |
| `lighthouserc.json` | Lighthouse CI budgets (performance ≥ 80, a11y ≥ 90, PWA ≥ 80, SEO ≥ 90) |

## Implementation Patterns

### New API Resource
1. `#[ApiResource]` on entity with operations + serialization groups
2. State processors/providers if needed (`backend/src/State/`)
3. Migration: `make db-diff && make db-migrate`
4. Tests: `Unit/State/` + `Functional/Api/`
5. **Update patterns.md + AGENTS.md**

### New React Page
1. Hook in `hooks/` with `useQuery`/`useMutation` + `apiFetch`
2. Page in `pages/`
3. Lazy route in `App.tsx`
4. Tests: `__tests__/integration/pages/`
5. **Update patterns.md + AGENTS.md**

### New React Component
1. Component in `components/`, props interface at top
2. Tests: `__tests__/integration/components/`
3. **Update patterns.md**

### New Lookup Provider
1. Extend `AbstractLookupProvider` or `AbstractGeminiLookupProvider`
2. Gemini: implement `buildResult()`, `getUsefulDataFields()`, `getLogName()`, `getSuccessMessage()`, `getNotFoundMessage()`
3. `getFieldPriority(field, ?type)` — orchestrator merges by highest per field
4. Two-phase async: `prepareLookup`/`resolveLookup` for HttpClient multiplexing
5. Tests: `Unit/Service/Lookup/`
6. **Update patterns.md**

## Gotchas

- **Permanent delete**: DBAL (not `$em->remove()`) — FK order: `comic_series_author` → `tome` → `comic_series`
- **Vite proxy**: `/api` + `/uploads` proxied to DDEV backend in dev
- **Author creation**: Negative IDs = new authors (created API-side). Offline: `_pendingAuthors` in queue
- **PATCH**: `method: "PATCH"` + `Content-Type: application/merge-patch+json` in `apiFetch`
- **Enum values**: lowercase (`'buying'` not `'BUYING'`)
- **Readonly + cache**: New props on cached DTOs → add `__unserialize()` with defaults
- **View Transitions**: Data router required. All Links + `navigate()` use `viewTransition`. `navigate(-1)` doesn't support options
- **Sticky action bars**: `sticky bottom-[var(--bottom-nav-h)]` (not `fixed`). CSS var centralizes BottomNav height
- **Home filters**: URL params via `useSearchParams` with `replace: true`. BottomNav Wishlist → `/?status=wishlist`
- **Cover placeholders**: `/placeholder-{bd,comics,livre,manga}.jpg` in `frontend/public/`. Map: `ComicTypePlaceholder[comic.type]`

## Docker Production

2 containers: `app` (FrankenPHP) + `db` (MariaDB). FrankenPHP runs as `www-data` (via `gosu`); supervisord manages web server + worker + scheduler. Files:

| File | Purpose |
|------|---------|
| `backend/Dockerfile` | Multi-stage build: Node.js 22 builds frontend (Vite) → `dunglas/frankenphp:1-php8.4` + composer:2 + extensions (gd/intl/opcache/pdo_mysql/zip). Build context = monorepo root |
| `backend/docker/frankenphp/Caddyfile` | Caddy server (port 8080): security headers, `/api` + `/media` (LiipImagine fallback) → PHP, SPA React (`index.html` fallback), 12MB upload limit |
| `backend/docker/frankenphp/supervisord.conf` | PID 1 supervisor: `frankenphp run` + `messenger:consume async` + `messenger:consume scheduler_default` (all as `www-data`) |
| `backend/docker/frankenphp/docker-entrypoint.sh` | Sets volume ownership (root) → `cache:clear` and `cache:warmup` (as `www-data`) → executes supervisord |
| `backend/docker-compose.yml` | 2 services (`app`, `db`) + 5 volumes (`app_var`, `db_data`, `jwt_keys`, `media`, `uploads`) |
| `.dockerignore` (root) | Root build context: excludes local var, vendor, node_modules, tests, and dev envs (preserves `config/secrets/prod/` vault) |

Single image: `ghcr.io/soviann/bibliotheque` (CI `docker-publish.yml`). App paths under `/app`.

# Codebase Patterns — Bibliotheque

Reference for implementing features without exploring the codebase.

## Entities (`backend/src/Entity/`)

| Entity | Key fields | Relations | API |
|--------|-----------|-----------|-----|
| `ComicSeries` | title, status:`ComicStatus`, type:`ComicType`, latestPublishedIssue?:int, latestPublishedIssueComplete:bool, isOneShot:bool, defaultTome{Bought,Downloaded,Read}:bool, amazonUrl?, description?, publisher?, coverFile?:File, coverImage?, coverUrl?, deletedAt?, lookupCompletedAt?, mergeCheckedAt?, newReleasesCheckedAt? | `authors:M2M→Author`, `tomes:O2M→Tome(cascade,orphanRemoval)` | GetCollection, Get, Post, Patch, Delete(soft), Put(/restore), Delete(/trash/permanent) |
| `Tome` | number:int, tomeEnd?:int, bought, downloaded, onNas, read, isbn?, title? | `comicSeries:M2O→ComicSeries` | Sub: `/comic_series/{id}/tomes` (GetCollection, Post), standalone: Get, Patch, Put, Delete |
| `Author` | name:string(unique), followedForNewSeries:bool(default false) | `comicSeries:M2M(mappedBy)` | GetCollection(search by name), Get, Patch, Post |
| `SeriesSuggestion` | title, type:`ComicType`, authors:JSON, reason:string, status:`SuggestionStatus`(default PENDING) | `sourceSeries:M2O→ComicSeries(SET NULL)` | GetCollection(filter status), Patch(status) |
| `User` | email:string(unique), googleId?, roles, tokenVersion:int(default=1) | — | — |
| `EnrichmentProposal` | field:`EnrichableField`, confidence:`EnrichmentConfidence`, currentValue:JSON?, proposedValue:JSON, source:string, status:`ProposalStatus`(default PENDING), reviewedAt? | `comicSeries:M2O→ComicSeries(CASCADE)` | GetCollection(filter status/comicSeries), Get, Patch(/accept), Patch(/reject) |
| `Notification` | title, message, type:`NotificationType`, read:bool, relatedEntityType?:`NotificationEntityType`, relatedEntityId?:int, metadata?:JSON | `user:M2O→User(CASCADE)` | GetCollection(filter read/type, paginated), Get, Patch(read), Delete |
| `NotificationPreference` | type:`NotificationType`, channel:`NotificationChannel`(default IN_APP) | `user:M2O→User(CASCADE)` | GetCollection(provider: initializer), Patch |
| `PushSubscription` | endpoint:string(unique), publicKey, authToken, expirationTime? | `user:M2O→User(CASCADE)` | GetCollection, Post, Delete |

## Enums (`backend/src/Enum/`)

| Enum | Cases |
|------|-------|
| `ComicStatus` | `BUYING`, `FINISHED`, `STOPPED`, `WISHLIST` — `getLabel()` |
| `ComicType` | `BD`, `COMICS`, `LIVRE`, `MANGA` — `getLabel()` |
| `ApiLookupStatus` | `ERROR`, `NOT_FOUND`, `RATE_LIMITED`, `SUCCESS`, `TIMEOUT` |
| `BatchLookupStatus` | `FAILED`, `SKIPPED`, `UPDATED` — `getLabel()` |
| `EnrichableField` | `AMAZON_URL`, `AUTHORS`, `COVER`, `DESCRIPTION`, `ISBN`, `IS_ONE_SHOT`, `LATEST_PUBLISHED_ISSUE`, `PUBLISHER` |
| `EnrichmentConfidence` | `HIGH`, `LOW`, `MEDIUM` — `fromScore(float)` |
| `NotificationChannel` | `BOTH`, `IN_APP`, `OFF`, `PUSH` — `getLabel()` |
| `NotificationEntityType` | `AUTHOR`, `COMIC_SERIES`, `ENRICHMENT_PROPOSAL` |
| `NotificationType` | `AUTHOR_NEW_SERIES`, `ENRICHMENT_APPLIED`, `ENRICHMENT_REVIEW`, `MISSING_TOME`, `NEW_RELEASE` — `getLabel()` |
| `ProposalStatus` | `ACCEPTED`, `PENDING`, `PRE_ACCEPTED`, `REJECTED`, `SKIPPED` |
| `SuggestionStatus` | `ADDED`, `DISMISSED`, `PENDING` — `getLabel()` |

## DTOs (`backend/src/DTO/`)

| DTO | Purpose |
|-----|---------|
| `BatchLookupProgress` | Single lookup progress (JsonSerializable) |
| `BatchLookupSummary` | Batch summary: failed/processed/skipped/updated (JsonSerializable) |
| `BookGroup` / `BookRow` | Import grouping (ImportBooksCommand) |
| `ComicSeriesFilter` | Query filters for `findWithFilters()` |
| `ComicSeriesListItem` | Cached API list item (JsonSerializable, `fromEntity()`, `__unserialize()` for cache compat) |
| `CoverSearchResult` | Cover image search result (JsonSerializable) |
| `ImportBooksResult` / `ImportExcelResult` / `ImportResult` | Import results (JsonSerializable) |
| `NasSeriesData` | Series extracted from NAS (title, lastDownloaded, readUpTo, readComplete, isComplete) |
| `ParsedIntegerValue` | Parsed Excel integer/fini/fini N |
| `MergeGroup` / `MergeGroupEntry` | Detected merge group + entries (JsonSerializable) |
| `NewReleaseProgress` | New release check progress (JsonSerializable) |
| `MergePreview` / `MergePreviewTome` | Full merge preview + tomes (JsonSerializable) |
| `PurgeableSeries` | Series eligible for purge (JsonSerializable) |
| `SeriesInfo` | Extracted series name + tome number |
| `Service/Lookup/Contract/ApiMessage` | Lookup provider API status (JsonSerializable) |

## Domain Events (`backend/src/Event/`)

- `ComicSeriesCreatedEvent` — postPersist, holds entity
- `ComicSeriesUpdatedEvent` — postUpdate (non-soft-delete), holds entity
- `ComicSeriesDeletedEvent` — soft/hard/permanent-delete, holds `int $id` + `string $title`

## Event Listeners (`backend/src/EventListener/`)

| Listener | Purpose |
|----------|---------|
| `ComicSeriesCacheInvalidator` | postPersist/Update/Remove: invalidates `comic_series_api.cache` for ComicSeries, Tome, Author |
| `ComicSeriesEventListener` | postPersist/Update/Remove: dispatches domain events |
| `HttpCacheListener` | kernel.response: ETag (content hash) + 304 Not Modified sur GET `/api/comic_series` |
| `JwtTokenVersionListener` | JWT create: adds tokenVersion. JWT decode: validates version match |
| `EnrichOnCreateListener` | ComicSeriesCreatedEvent → dispatches `EnrichSeriesMessage` (async). `disable()`/`enable()` for batch imports |
| `ReEnrichOnUpdateListener` | ComicSeriesUpdatedEvent → re-dispatches `EnrichSeriesMessage` if cover/description/publisher still null (cooldown 24h) |
| `CoverUrlChangeListener` | preUpdate: dispatches `DownloadCoverMessage` (async) when `coverUrl` changes on ComicSeries |
| `PlaceholderSecretChecker` | kernel.request (priority 255): blocks prod if placeholder secrets |

## Controllers (`backend/src/Controller/`)

| Controller | Routes |
|------------|--------|
| `ApiController` | `GET /api/lookup/{isbn,title}?...&type=...` (JWT, 30/min) |
| `BatchLookupController` | `GET /api/tools/batch-lookup/preview`, `POST .../run` (SSE) |
| `GoogleLoginController` | `POST /api/login/google` (public) |
| `ImportController` | `POST /api/tools/import/{books,excel}` (multipart + dryRun) |
| `MergeSeriesController` | `POST /api/merge-series/{detect,preview,execute}` |
| `NotificationController` | `GET /api/notifications/unread-count`, `PATCH /api/notifications/read-all` |
| `PurgeController` | `GET /api/tools/purge/preview?days=30`, `POST .../execute` |

## State Processors & Providers (`backend/src/State/`)

| File | Purpose |
|------|---------|
| `ComicSeriesDeleteProcessor` | Soft delete |
| `ComicSeriesRestoreProcessor` | Restore from trash |
| `ComicSeriesPermanentDeleteProcessor` | Permanent delete |
| `SoftDeletedComicSeriesProvider` | Disables soft-delete filter for trashed access |
| `EnrichmentProposalAcceptProcessor` | Accept proposal → apply value + log. Checks stale (409 Conflict) |
| `EnrichmentProposalRejectProcessor` | Reject proposal → log |
| `NotificationPreferenceInitializer` | AP4 provider: creates default prefs (IN_APP) on first GET |
| `TrashCollectionProvider` | GET `/api/trash` |

## Messages & Handlers (`backend/src/Message/`, `backend/src/MessageHandler/`)

| Message | Handler | Purpose |
|---------|---------|---------|
| `DownloadCoverMessage(seriesId, coverUrl)` | `DownloadCoverHandler` | Télécharge et stocke la couverture en WebP (async via Messenger) |
| `EnrichSeriesMessage(seriesId)` | `EnrichSeriesHandler` | Enrichissement automatique via lookup providers (async via Messenger) |

## Services (`backend/src/Service/`)

### ComicSeries (`Service/ComicSeries/`)
| Service | Key API |
|---------|---------|
| `ComicSeriesService` | `softDelete()`, `moveToLibrary()`, `restore()`, `permanentDelete()` |
| `PurgeService` | `findPurgeable(days)`, `executePurge(seriesIds)` |

### Cover (`Service/Cover/`)
| Service | Key API |
|---------|---------|
| `CoverDownloader` | `downloadAndStore(series, url): bool` — HTTP GET → resize 600×900 → WebP → VichUploader |
| `CoverSearchService` | `search(query, ?type): CoverSearchResult[]` — Google Books + Serper |
| `CoverRemoverInterface` / `VichCoverRemover` | Cover removal + LiipImagine cache invalidation |
| `Upload/UploadHandlerInterface` / `Upload/VichUploadHandlerAdapter` | VichUploader abstraction |

### Notification (`Service/Notification/`)
| Service | Key API |
|---------|---------|
| `NotifierInterface` | Contract for notification dispatch |
| `NotificationService` | `create(user, type, title, message, ?entityType, ?entityId, ?metadata): ?Notification` — checks prefs, sends push |
| `WebPushService` | `sendToUser(user, title, body, ?url)` — VAPID Web Push via `minishlink/web-push` |

### Recommendation (`Service/Recommendation/`)
| Service | Key API |
|---------|---------|
| `AuthorReleaseCheckerService` | `check(dryRun): Generator<AuthorReleaseResult>` — vérifie nouvelles séries d'auteurs suivis via GeminiQueryService |
| `MissingTomeDetectorService` | `detect(dryRun): Generator<MissingTomeResult>` — détecte tomes manquants, crée notifications via NotifierInterface |
| `NewReleaseCheckerService` | `run(dryRun, ?limit): Generator<NewReleaseProgress>` — checks new releases for BUYING series |
| `SimilarSeriesService` | `generateSuggestions(): Generator<SeriesSuggestion>` — suggestions IA via GeminiQueryService |

### Other modules
| Service | Key API |
|---------|---------|
| `Enrichment/ConfidenceScorer` | `score(query, type, mode, result, sources): EnrichmentConfidence` |
| `Enrichment/EnrichmentService` | `enrich(series, result, mode, sources): EnrichmentConfidence` — routes HIGH→apply, MEDIUM→propose, LOW→skip |
| `Import/ImportBooksService` | `import(filePath, dryRun): ImportBooksResult` |
| `Import/ImportExcelService` | `import(filePath, dryRun): ImportExcelResult` — col H « Parution terminée », format « fini N » |
| `Merge/SeriesGroupDetector` | `detect(): list<MergeGroup>` — Gemini AI grouping (batch size 50) |
| `Merge/MergePreviewBuilder` | `buildFromGroup()`, `buildFromManualSelection()` — via GeminiClientPool |
| `Merge/MergePreviewHydrator` | `hydrate(array): MergePreview` — JSON→DTO hydration |
| `Merge/SeriesMerger` | `execute(MergePreview): ComicSeries` — merge + cleanup |
| `Nas/NasDirectoryParser` | Parse NAS directory listings → `NasSeriesData[]` (unread, read, in-progress) |

### Lookup (`Service/Lookup/`)

**Root (public API):**
| Class | Purpose |
|-------|---------|
| `LookupOrchestrator` | Coordinates providers, merges by field priority |
| `LookupApplier` | Applies result to series (null fields only), creates missing tomes |
| `BatchLookupService` | `countSeriesToProcess()`, `run(): Generator<BatchLookupProgress>` |

**Contract/ (interfaces + DTOs):**
| Class | Purpose |
|-------|---------|
| `LookupResult` | Immutable DTO (JsonSerializable): amazonUrl, authors, description, isbn, isOneShot, latestPublishedIssue, publishedDate, publisher, source, thumbnail, title, tomeEnd, tomeNumber |
| `LookupProviderInterface` | `getFieldPriority(field, ?type)`, `supports(mode, type)`, `prepareLookup()`/`resolveLookup()` |
| `EnrichableLookupProviderInterface` | Extends: `prepareEnrich`/`resolveEnrich` |
| `MultiResultLookupProviderInterface` | Extends: `prepareMultipleLookup`/`resolveMultipleLookup` |
| `ApiMessage` | Lookup provider API status (JsonSerializable) |

**Gemini/ (Gemini infrastructure):**
| Class | Purpose |
|-------|---------|
| `GeminiClientPool` | Key × model rotation on 429, `executeWithRetry(callable): T` |
| `GeminiJsonParser` | Static `parseJsonFromText(string): ?array` — parses Gemini JSON (with/without markdown code blocks) |
| `GeminiQueryService` | `queryJsonArray(prompt): list<array>` — DRY helper for Gemini JSON queries |
| `AbstractGeminiLookupProvider` | Extends AbstractLookupProvider: `callGemini()`, `consumeRateLimit()`, `prepareWithCache()`, CACHE_TTL=30d |

**Provider/ (concrete providers):**
| Provider | Mode | Default Priority |
|----------|------|-----------------|
| `GeminiLookup` | ISBN + title + enrichment | 40 |
| `AniListLookup` | Title (manga only) | 60 |
| `OpenLibraryLookup` | ISBN | 80 |
| `BnfLookup` | ISBN + title | 90 |
| `GoogleBooksLookup` | ISBN + title | 100 |
| `BedethequeLookup` | ISBN + title (Gemini grounding) | BD:150, other:110, thumb:50 |
| `WikipediaLookup` | ISBN + title (cache 7d) | 120 (description: 10) |
| `AbstractLookupProvider` | Base: shared `lastApiMessage`, `recordApiMessage()` |

**Util/ (stateless helpers):**
| Class | Purpose |
|-------|---------|
| `TitleMatcher` | Static `matches(query, resultTitle): bool` |
| `LookupTitleCleaner` | Static `clean(title): string` — removes tome/volume suffixes |
| `GoogleBooksUrlHelper` | Static `optimizeThumbnailUrl(string): string` — HTTPS, zoom=0, remove edge=curl |

## Repositories (`backend/src/Repository/`)

| Repo | Custom methods |
|------|----------------|
| `ComicSeriesRepository` | `findWithFilters()`, `findAllForApi()`, `findBuyingForReleaseCheck()`, `findWithMissingLookupData()`, `findForAutoEnrich()`, `findForMergeDetection()`, `findPurgeable(days)`, `findTrashed()` |
| `AuthorRepository` | `findOrCreate()`, `findOrCreateMultiple()` |
| `EnrichmentProposalRepository` | `findPendingBySeries()`, `findPendingBySeriesAndField()`, `countPending()` |
| `NotificationRepository` | `countUnread()`, `existsUnreadByTypeAndEntity()`, `markAllRead()`, `purgeOlderThan()` |
| `NotificationPreferenceRepository` | `findByUser()`, `findByUserAndType()` |
| `PushSubscriptionRepository` | `findByUser()`, `findByEndpoint()` |
| `TomeRepository` | Standard |
| `UserRepository` | Standard |

## Commands (`backend/src/Command/`)

| Command | Signature |
|---------|-----------|
| `CheckNewReleasesCommand` | `app:check-new-releases [--dry-run] [--limit=0]` |
| `DownloadCoversCommand` | `app:download-covers [--delay=1] [--dry-run] [--limit=0]` |
| `ImportBooksCommand` | `app:import-books <file> [--dry-run]` |
| `ImportExcelCommand` | `app:import-excel <file> [--dry-run]` |
| `InvalidateTokensCommand` | `app:invalidate-tokens [--email=...]` |
| `AutoEnrichCommand` | `app:auto-enrich [--delay=2] [--dry-run] [--force] [--limit=0] [--type=...]` — replaces lookup-missing with confidence scoring |
| `PurgeDeletedCommand` | `app:purge-deleted [--days=30] [--dry-run]` |
| `PurgeNotificationsCommand` | `app:purge-notifications [--days=90]` |
| `CheckAuthorReleasesCommand` | `app:check-author-releases [--dry-run]` — vérifie les nouvelles séries des auteurs suivis |
| `DetectMissingTomesCommand` | `app:detect-missing-tomes [--dry-run]` — détecte les tomes manquants |
| `ScanNasCommand` | `app:scan-nas [-o var/nas-import.xlsx]` — SSH scan NAS → Excel |

## Traits (`backend/src/Controller/Trait/`)

| Trait | Purpose |
|-------|---------|
| `RateLimitTrait` | `checkRateLimit(Request, RateLimiterFactory): ?JsonResponse` — shared rate limiting with `Retry-After` header |

## Other Backend

- **Fixtures**: `UserFixtures` — test user `test@example.com` / googleId `test-google-id`
- **Filter**: `SoftDeleteFilter` — SQL filter excluding soft-deleted (enabled by default)

### Config highlights (`backend/config/packages/`)

| File | Key settings |
|------|-------------|
| `rate_limiter.yaml` | `api_lookup` 30/min, `batch_lookup` 2/min, `cover_search` 20/min, `gemini_api` 20/min, `google_login` 10/min, `import` 5/min, `merge_series` 5/min, `purge` 5/min (sliding window) |
| `cache.yaml` | `gemini.cache` 30d, `wikipedia.cache` 7d |
| `lexik_jwt_authentication.yaml` | TTL 365d, token versioning via `JwtTokenVersionListener` |
| `liip_imagine.yaml` | `cover_thumbnail` 300x450 webp, `cover_medium` 600x900 webp |
| `security.yaml` | JWT firewall `/api/` (stateless). Public: `POST /api/login/google` |
| `vich_uploader.yaml` | `comic_covers` → `public/uploads/covers` |
| `messenger.yaml` | Doctrine transport (`doctrine://default`), `DownloadCoverMessage` + `EnrichSeriesMessage` → async, failed transport, retry ×3. Test: `in-memory://` |
| `secrets/prod/` | Vault: `APP_SECRET` + `JWT_PASSPHRASE` + `VAPID_PUBLIC_KEY` + `VAPID_PRIVATE_KEY`. Decrypt key gitignored. |

### Backend Tests (`backend/tests/`)

Three-tier: **Unit** (no kernel) → **Integration** (kernel + DB) → **Functional** (HTTP). PHPUnit 12, DAMA DoctrineTestBundle.

| Directory | Coverage |
|-----------|----------|
| `Unit/Entity/` | ComicSeries, Tome, Author, User |
| `Unit/Enum/` | All 4 enums |
| `Unit/Event/` | All 3 domain events |
| `Unit/EventListener/` | CacheInvalidator, EventListener, HttpCache, JwtTokenVersion, PlaceholderSecretChecker |
| `Unit/Service/ComicSeries/` | ComicSeriesService, Purge |
| `Unit/Service/Cover/` | CoverDownloader, CoverSearchService, VichCoverRemover, Upload/VichUploadHandlerAdapter |
| `Unit/Service/Notification/` | NotificationService |
| `Unit/Service/Recommendation/` | NewReleaseChecker |
| `Unit/Service/Merge/` | SeriesGroupDetector, MergePreviewBuilder, MergePreviewHydrator, SeriesMerger |
| `Unit/State/` | All 5 processors/providers |
| `Unit/Service/Lookup/` | Orchestrator, LookupApplier, BatchLookupService |
| `Unit/Service/Lookup/Contract/` | LookupResult |
| `Unit/Service/Lookup/Gemini/` | GeminiClientPool, GeminiJsonParser, GeminiQueryService |
| `Unit/Service/Lookup/Provider/` | All 12 providers + AbstractProvider |
| `Unit/Service/Lookup/Util/` | TitleMatcher, LookupTitleCleaner, GoogleBooksUrlHelper |
| `Integration/Repository/` | All 4 repositories |
| `Integration/Command/` | CheckNewReleases, ImportBooks, ImportExcel, InvalidateTokens, LookupMissing, PurgeDeleted |
| `Integration/Doctrine/` | SoftDeleteFilter |
| `Integration/Service/Merge/` | SeriesMerger (full DB) |
| `Functional/Api/` | ComicSeries, HttpCache, Tome, Author, Trash, Lookup, MergeSeries |
| `Functional/Controller/` | BatchLookup, Import, Purge |
| `Functional/Auth/` | GoogleLogin, JwtAuth |
| `Functional/Security/` | Authentication, RateLimit |
| `Factory/` | `EntityFactory` — `createAuthor()`, `createComicSeries()`, `createTome()`, `createUser()` |
| `Trait/` | `AuthenticatedTestTrait` — JWT auth helper |

## Frontend — Pages (`frontend/src/pages/`)

| Page | Route | Purpose |
|------|-------|---------|
| `Home` | `/` | Library grid, URL-synced filters (useSearchParams) |
| `ComicDetail` | `/comic/:id` | Detail: cover, metadata, tomes, edit/delete |
| `ComicForm` | `/comic/new`, `/comic/:id/edit` | Create/edit: lookup, barcode, tomes, author autocomplete |
| `Trash` | `/trash` | Restore / permanent delete |
| `Login` | `/login` | Google OAuth |
| `LookupTool` | `/tools/lookup` | Batch lookup with SSE progress |
| `MergeSeries` | `/tools/merge-series` | Auto-detect (Gemini) + manual-select tabs |
| `Tools` | `/tools` | Hub for admin tools |
| `ImportTool` | `/tools/import` | Excel import: tracking + books tabs, dry run |
| `PurgeTool` | `/tools/purge` | Purge soft-deleted: preview, confirm, bulk delete |
| `EnrichmentReview` | `/tools/enrichment-review` | Review enrichment proposals (accept/reject) |
| `Notifications` | `/notifications` | Notification list, mark read, delete |
| `NotificationSettings` | `/settings/notifications` | Per-type channel preferences |
| `Suggestions` | `/tools/suggestions` | AI-powered similar series suggestions (add/dismiss) |
| `NotFound` | `*` | 404 |

## Frontend — Components (`frontend/src/components/`)

| Component | Purpose |
|-----------|---------|
| `AuthGuard` | Redirect to `/login` if unauthenticated |
| `BarcodeScanner` | html5-qrcode ISBN scanner |
| `BottomNav` | Mobile nav (Home, Wishlist→`/?status=wishlist`, Add, Trash) |
| `CardActionBar` | Mobile fixed bottom overlay: Edit/Delete |
| `CollectionMap` | Visual grid of numbered tome squares (bought/downloaded/read/missing) with series color |
| `ComicCard` | Card: cover, title, type, tomes, progress, menu |
| `ContinueReading` | Horizontal slider of series with unread tomes (readCount < max(boughtCount, downloadedCount)) |
| `ComponentErrorBoundary` | Contextual error boundary (label + retry, onReset, resetKeys) — wraps TomeTable, VirtualGrid, LookupSection |
| `ConfirmModal` | Headless UI destructive confirmation |
| `CoverLightbox` | Fullscreen cover image overlay (Headless UI Dialog) |
| `CoverSearchModal` | Image search with debounced input, thumbnail grid |
| `ErrorFallback` | App-level error boundary UI (full-page) |
| `FileDropZone` | Drag-drop upload (.xlsx) |
| `FilterChips` | Quick filter chips (type + status) scrollable, toggle on/off |
| `AuthorAutocomplete` | Author search/create combobox (extracted from ComicForm) |
| `Filters` | Type + status + sort dropdowns |
| `LookupSection` | ISBN/title lookup section (extracted from ComicForm) |
| `SeriesEnrichmentProposals` | Enrichment proposals (actionable + history) on ComicDetail |
| `Layout` | Header + BottomNav + NotificationBell + Outlet + Sonner + OfflineBanner |
| `NotificationBell` | Bell icon + unread count badge in header |
| `OfflineBanner` | "Mode hors ligne" banner |
| `ProgressBar` | Reusable bar with aria, color prop, compact mode |
| `ProgressLog` | Batch lookup progress list with status icons |
| `SkeletonBox` / `ComicCardSkeleton` | Loading placeholders |
| `Breadcrumb` | Breadcrumb nav with parent links and aria-current on last item |
| `EmptyState` | Icon + title + optional description/CTA |
| `MergeGroupCard` | Merge group: entries, suggested title, action buttons |
| `MergeMetadataForm` | Merge preview metadata fields (title, type, status, publisher, etc.) — used by MergePreviewModal |
| `MergePreviewModal` | Thin modal wrapper: uses useMergePreviewForm + MergeMetadataForm + MergeTomeTable |
| `MergeTomeTable` | Merge-specific tome table with edit/remove/add via dispatch |
| `SelectListbox` | Reusable Headless UI listbox with optional label/placeholder |
| `SeriesMultiSelect` | Multi-select with search, chips, checkboxes |
| `SyncErrorBanner` / `SyncPendingIndicator` | Sync failure details / pending indicator |
| `SyncFailureSection` | Offline sync failure warning with payload details (extracted from ComicForm) |
| `TomeTable` | Tome table: mobile cards + desktop table + batch add. Props: `{ form, tomeManager: TomeManager }` |

## Frontend — Hooks (`frontend/src/hooks/`)

| Hook | Purpose |
|------|---------|
| `useAuth` | Google login mutation, logout |
| `useBuyTome` | PATCH tome as bought with optimistic update on comics list (ToBuy page) |
| `useAuthors` | GET `/api/authors?name=...` (autocomplete) |
| `useBatchLookup` | Preview query + SSE streaming (start/cancel/progress/summary) |
| `useComic` / `useComics` | GET single / GET collection with filters |
| `useAuthorManagement` | Author autocomplete, add/remove — sub-hook of useComicForm |
| `useComicForm` | Orchestrates sub-hooks (useLookupFeature, useTomeManagement, useAuthorManagement) for ComicForm |
| `useCoverSearch` | GET `/api/lookup/covers?query=...&type=...` (staleTime 5min) |
| `useCreateComic` / `useUpdateComic` / `useDeleteComic` | CRUD mutations |
| `useEnrichment` | Enrichment proposals (list, accept, reject) + logs queries |
| `useGoBack` | Smart back navigation: `navigate(-1)` if in-app history, fallback to `/` otherwise |
| `useCreateTome` / `useUpdateTome` / `useDeleteTome` | Tome CRUD (offline-capable, optimistic) |
| `useDarkMode` | Toggle `.dark` on `<html>`, localStorage |
| `useImport` | `useImportExcel()`, `useImportBooks()` — file upload |
| `useLookup` | `useLookupIsbn()`, `useLookupTitle()` |
| `useNotifications` | `useUnreadCount()` (refetchInterval 60s), `useNotifications()`, `useMarkAsRead()`, `useMarkAllRead()`, `useDeleteNotification()` |
| `useNotificationPreferences` | `useNotificationPreferences()`, `useUpdatePreference()` |
| `useLookupFeature` | Lookup state + apply logic — sub-hook of useComicForm |
| `useMergePreviewForm` | useReducer for MergePreviewModal state (18 fields → single reducer) |
| `useMergeSeries` | `useDetectMergeGroups`, `useMergePreview`, `useExecuteMerge` |
| `useOfflineMutation` | Enqueues to IndexedDB offline, pass-through online |
| `useOnlineStatus` | `useSyncExternalStore` for `navigator.onLine` |
| `usePendingQueueCount` | Polls `getPendingCount()` every 2s |
| `usePullToRefresh` | Touch gesture pull-to-refresh with threshold, returns `isRefreshing` + `pullDistance` |
| `usePurge` | Preview query + execute mutation |
| `useServiceWorker` | SW registration, update toast, token messaging |
| `useSyncStatus` / `useSyncFailures` | SW sync events / IndexedDB failure store |
| `useTomeManagement` | Tome CRUD + batch add + ISBN lookup — sub-hook of useComicForm, exports `TomeManager` interface |
| `useTrash` | GET soft-deleted, restore, permanent delete |

## Frontend — Services & Utils

| File | Exports |
|------|---------|
| `services/api.ts` | `apiFetch<T>()`, `fetchSSE()`, `loginWithGoogle()`, `getToken/setToken/removeToken()`, `isAuthenticated()`, `getErrorMessage(err, fallback?)`, `handleUnauthorized()` |
| `services/offlineQueue.ts` | IndexedDB queue: enqueue/dequeue/getAll/removeById/updateStatus/clearQueue/getPendingCount |
| `services/syncHandler.ts` | `processSyncQueue()` — FIFO, `_pendingAuthors`, 4xx skip, 5xx retry |
| `utils/releaseUtils.ts` | `hasNewRelease()` — detects series with recent new tomes (7d, BUYING) |
| `utils/searchComics.ts` | `searchComics()` — Fuse.js fuzzy multi-field search |
| `utils/coverUtils.ts` | `getCoverSrc()` — local cover path or URL fallback |
| `utils/sortComics.ts` | `SortOption`, `sortComics()` — client-side sort (French locale) |
| `utils/enrichmentUtils.ts` | `formatEnrichmentValue()` — shared between EnrichmentReview + ProposalCard |
| `utils/syncLabels.ts` | `operationLabels`, `resourceLabels`, `fieldLabels`, `formatSyncValue()` |

## Frontend — Types (`frontend/src/types/`)

- `api.ts`: `HydraCollection<T>`, `Author`, `Tome`, `ComicSeries`, `PurgeableSeries`, `MergeGroup`, `MergeGroupEntry`, `MergePreview`, `MergePreviewTome`, `CreateComicPayload`, `UpdateComicPayload`, `TomePayload`, `CreateTomePayload`, `ImportExcelResult`, `ImportBooksResult`, `BatchLookupProgress`, `BatchLookupSummary`, `LookupResult`
- `enums.ts`: `ComicStatus`, `ComicType`, `EnrichmentConfidence`, `NotificationChannel`, `NotificationEntityType`, `NotificationType`, `ProposalStatus` (+ labels, colors)
- `notifications.ts`: `AppNotification`, `NotificationPreference`
- `sync.d.ts`: `SyncManager`, `SyncEvent` type declarations

### Frontend Tests (`frontend/src/__tests__/`)

Three-tier: Unit + Integration. Vitest 4 + jsdom + RTL + MSW.

| Directory | Coverage |
|-----------|----------|
| `helpers/` | `renderWithProviders()`, mock factories, MSW handlers |
| `unit/` | sw-custom (service worker sync handler + routes) |
| `unit/services/` | api, offlineQueue, syncHandler |
| `unit/types/` | enums (typeOptions, statusOptions) |
| `unit/utils/` | coverUtils, releaseUtils, searchComics, sortComics, syncLabels |
| `integration/hooks/` | All 22 hooks |
| `integration/components/` | All 22 components (incl. CollectionMap, ComponentErrorBoundary, ContinueReading, MergeGroupCard, SelectListbox, SeriesMultiSelect) |
| `integration/pages/` | All 11 pages + ComicDetailToggle (incl. Tools) |

### Frontend Config

| File | Purpose |
|------|---------|
| `vite.config.ts` | React + Tailwind + VitePWA (injectManifest, `sw-custom.ts`) + API/uploads proxy + vendor chunk splitting |
| `sw-custom.ts` | Precache, NetworkFirst API (5s), CacheFirst covers (30d), Background Sync, Push notifications |
| `src/theme.ts` | `THEME_COLOR_LIGHT` / `THEME_COLOR_DARK` — canonical source for PWA theme colors |
| `src/queryClient.ts` | staleTime 5min, retry 1 |
| `src/App.tsx` | `createBrowserRouter` + providers + lazy loading + View Transitions |
| `src/index.css` | Tailwind, `@theme` colors, dark mode, `--bottom-nav-h: 3.5rem` |
| `lighthouserc.json` | Lighthouse CI config — score budgets (perf ≥ 80, a11y ≥ 90, PWA ≥ 80, SEO ≥ 90) |

## Implementation Patterns

### New API Resource
1. `#[ApiResource]` on entity with operations + serialization groups
2. State processors/providers if needed (`backend/src/State/`)
3. Migration: `make db-diff && make db-migrate`
4. Tests: `Unit/State/` + `Functional/Api/`
5. **Update patterns.md + CLAUDE.md**

### New React Page
1. Hook in `hooks/` with `useQuery`/`useMutation` + `apiFetch`
2. Page in `pages/`
3. Lazy route in `App.tsx`
4. Tests: `__tests__/integration/pages/`
5. **Update patterns.md + CLAUDE.md**

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

3 containers: nginx + php-fpm + MariaDB. All non-root. Files:

| File | Purpose |
|------|---------|
| `backend/Dockerfile` | php:8.3-fpm + gosu (drops to www-data) + composer:2 + cache warmup |
| `backend/docker/nginx/Dockerfile` | Multi-stage: Node.js 22 builds frontend → nginxinc/nginx-unprivileged:alpine (port 8080) |
| `backend/docker/nginx/default.conf` | SPA fallback, `/api` → php:9000, cache headers, gzip, security headers |
| `backend/docker/nginx/security-headers.conf` | CSP, HSTS, X-Content-Type-Options, etc. |
| `backend/docker/php/healthcheck.conf` | php-fpm ping endpoint for Docker healthcheck |
| `backend/docker/php/docker-entrypoint.sh` | chown volumes (root) → gosu www-data for composer dump-env + php-fpm |
| `backend/docker-compose.yml` | 3 services + 5 volumes (app_var, db_data, jwt_keys, media, uploads) |
| `backend/.dockerignore` | Excludes tests, var, vendor, dev configs from build context |

Build context for nginx: `..` (monorepo root) to access `frontend/`.

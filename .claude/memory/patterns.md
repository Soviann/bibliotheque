# Patterns

## Backend Structure

```
src/
  Command/           Console commands (app:*)
  Controller/        Non-API Platform controllers
  DataFixtures/      UserFixtures
  Doctrine/Filter/   SoftDeleteFilter
  DTO/               Value objects for services
  Entity/            Doctrine entities (10)
  Enum/              PHP backed enums (13)
  Event/             Domain events
  EventListener/     Doctrine & domain event listeners
  Message/           Messenger messages
  MessageHandler/    Messenger handlers
  Repository/        Doctrine repositories
  Schedule.php       Scheduler config
  Service/           Business logic (subdirectories below)
  State/             API Platform processors & providers
tests/
  Factory/           EntityFactory
  Functional/        API, Auth, Controller, Security
  Integration/       Command, Doctrine, Repository, Service
  Trait/             AuthenticatedTestTrait
  Unit/              Mirrors src/ structure
```

## Frontend Structure

```
src/
  components/     UI components (40+)
  hooks/          One hook per feature/concern (40+)
  pages/          Route-level pages (16), lazy-loaded
  services/       api.ts, offlineQueue.ts, syncHandler.ts
  styles/         formStyles.ts (shared Tailwind classes)
  types/          api.ts, enums.ts, notifications.ts, sync.d.ts
  utils/          Pure utilities (cover, search, sort, tome…)
  endpoints.ts    Centralized API endpoint paths
  queryKeys.ts    Centralized TanStack Query cache keys
  queryClient.ts  Query client config
  App.tsx         Routes + React.lazy()
  __tests__/
    helpers/      factories.ts, handlers.ts (MSW), server.ts, test-utils.tsx
    integration/  Component, hook, page tests (RTL + MSW)
    unit/         Pure function/utility tests
```

## Entities

| Entity | Purpose |
|---|---|
| Author | Comic/manga author |
| ComicSeries | Central entity, soft-deletable |
| EnrichmentProposal | Enrichment proposals (PENDING/PRE_ACCEPTED/ACCEPTED/REJECTED/SKIPPED) — serves as history |
| Notification | User notifications |
| NotificationPreference | Per-user notification channel preferences |
| PushSubscription | Web Push endpoints per user |
| SeriesSuggestion | Suggested new series from recommendations |
| Tome | Volume within a series |
| User | Single user (Google OAuth, JWT token versioning) |

## Enums

ApiLookupStatus, BatchLookupStatus, ComicStatus (buying/downloading/finished/stopped/wishlist), ComicType (BD/comic/manga), EnrichableField, EnrichmentConfidence (high/medium/low), LookupMode (isbn/title), NotificationChannel (web/push), NotificationEntityType, NotificationType, ProposalStatus (pending/pre_accepted/accepted/rejected/skipped), SuggestionStatus.

## Services (`src/Service/`)

| Directory | Services |
|---|---|
| ComicSeries/ | ComicSeriesService, PurgeService |
| Cover/ | CoverDownloader, CoverSearchService, ThumbnailGenerator, VichCoverRemover |
| Cover/Upload/ | UploadHandlerInterface, VichUploadHandlerAdapter |
| Enrichment/ | ConfidenceScorer, EnrichmentService |
| Import/ | ImportService |
| Lookup/Contract/ | ApiMessage, LookupProviderInterface, LookupResult… |
| Lookup/Gemini/ | AbstractGeminiLookupProvider, GeminiClientPool, GeminiJsonParser, GeminiQueryService |
| Lookup/Provider/ | 12 providers: AniList, Bedetheque, BNF, ComicVine, Gemini, GoogleBooks, Jikan, Kitsu, MangaDex, OpenLibrary, Wikipedia + AbstractLookupProvider |
| Lookup/Util/ | GoogleBooksUrlHelper, LookupTitleCleaner, TitleMatcher |
| Lookup/ | BatchLookupService, LookupApplier, LookupOrchestrator |
| Merge/ | MergePreviewBuilder, MergePreviewHydrator, SeriesGroupDetector, SeriesMerger |
| Nas/ | NasDirectoryParser |
| Share/ | ShareUrlParser (parse URL → ShareUrlInfo), ShareResolver (lookup + match + enrich dispatch → ShareResolution) |
| Notification/ | NotificationService, NotifierInterface, WebPushService |
| Recommendation/ | AuthorReleaseCheckerService, MissingTomeDetectorService, NewReleaseCheckerService, SimilarSeriesService |

## API Platform

**State Processors**: AuthorCreateProcessor, ComicSeriesDeleteProcessor (soft), ComicSeriesPermanentDeleteProcessor, ComicSeriesRestoreProcessor, EnrichmentProposalAcceptProcessor, EnrichmentProposalRejectProcessor.

**State Providers**: SoftDeletedComicSeriesProvider, TrashCollectionProvider, NotificationPreferenceInitializer.

**Controllers**: ApiController (lookup: title/ISBN/covers), BatchLookupController (SSE streaming), GoogleLoginController, ImportController (books/Excel), MergeSeriesController (detect/preview/execute/suggest), NotificationController (read-all/unread-count), PurgeController (preview/execute purge), ShareController (`POST /api/share` — Web Share Target : parsing URL + lookup + match/enrichissement).

**Resources**: ComicSeries, Tome, Author, EnrichmentProposal, Notification, NotificationPreference, SeriesSuggestion. Format: JSON-LD.

## Messenger & Scheduler

**Messenger**: transport `doctrine://default` (test: `in-memory://`). Message: EnrichSeriesMessage → EnrichSeriesHandler. Dispatched by: EnrichOnCreateListener, ReEnrichOnUpdateListener.

**Scheduler** (Schedule.php):
- Daily 3-8h Tue-Sat: `app:auto-enrich`
- Daily 4h: `app:check-new-releases`
- Daily 5h: `app:download-covers`
- Weekly Sun 3-8h: `app:detect-missing-tomes`
- Weekly Mon 3-8h: `app:check-author-releases`
- Monthly 1st 1h: `app:purge-deleted`
- Monthly 1st 2h: `app:purge-notifications`

**Other commands**: `app:import`, `app:invalidate-tokens`, `app:scan-nas`, `app:warm-thumbnails`.

## Events

**Domain**: ComicSeriesCreatedEvent, ComicSeriesUpdatedEvent, ComicSeriesDeletedEvent.

**Listeners**: ComicSeriesCacheInvalidator, ComicSeriesEventListener, CoverUrlChangeListener, EnrichOnCreateListener, HttpCacheListener, JwtTokenVersionListener, PlaceholderSecretChecker, ReEnrichOnUpdateListener, TomeLatestIssueListener.

## Doctrine Filters

SoftDeleteFilter — excludes soft-deleted ComicSeries. Disabled in trash-related providers.

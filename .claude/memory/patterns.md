# Patterns

## Backend Structure

```
src/
  Command/           Console commands (app:*)
  Controller/        Non-API Platform controllers
  DataFixtures/      UserFixtures
  DeployTask/        DeployTaskInterface + AbstractDeployTask (one-shot deploy tasks)
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
deploy-tasks/        One-shot deploy task files (Task*.php, namespace DeployTask\, tracked in var/deploy-tasks-executed.json)
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

## Frontend Stack & Conventions

**Stack**: React 19, TS, Vite, TanStack Query v5, React Router v7, Tailwind 4, Headless UI, Lucide, Sonner, `@react-oauth/google`. Tests: Vitest + jsdom + RTL.

- `apiFetch<T>()` handles JWT, Content-Type, 401 redirects.
- Mutations invalidate relevant query keys on success.
- Pages lazy-loaded via `React.lazy()` in `App.tsx`.
- JWT in `localStorage`, 365-day TTL, token versioning. `AuthGuard` → `/login`. Google OAuth.
- Dark mode: `useDarkMode` (`.dark` on `<html>`, localStorage).
- Offline: `useOnlineStatus` + `OfflineBanner`, SW updates via `useServiceWorker` + Sonner toast.

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

**Format & auth**: JSON-LD (`application/ld+json`). Login: `POST /api/login/google` `{credential}` → `{token}`. Single email gate via `OAUTH_ALLOWED_EMAIL`.

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

**Other commands**: `app:deploy:run-tasks` (exécute les tasks `deploy-tasks/Task*.php` one-shot), `app:import`, `app:invalidate-tokens`, `app:scan-nas`, `app:warm-thumbnails`.

## Events

**Domain**: ComicSeriesCreatedEvent, ComicSeriesUpdatedEvent, ComicSeriesDeletedEvent.

**Listeners**: ComicSeriesCacheInvalidator, ComicSeriesEventListener, CoverUrlChangeListener, EnrichOnCreateListener, HttpCacheListener, JwtTokenVersionListener, PlaceholderSecretChecker, ReEnrichOnUpdateListener, TomeLatestIssueListener.

## Doctrine Filters

SoftDeleteFilter — excludes soft-deleted ComicSeries. Disabled in trash-related providers.

## Deployment

**Docker (Synology NAS)**: 3 containers — nginx (static + reverse proxy), php (php-fpm 8.3), db (MariaDB 10.11). Frontend built in nginx multi-stage Dockerfile. Images on `ghcr.io/soviann/bibliotheque-{php,nginx}` (CI). Guides: `docs/guide-deploiement-nas.md` (human), `docs/guide-deploiement-nas-claude.md` (Claude/SSH).

```bash
cd backend && docker compose up --build -d                  # dev local (build)
TAG=2.9.0 docker compose pull && docker compose up -d       # NAS prod (pull)
```

**Symfony Secrets (prod vault)**: `APP_SECRET`, `JWT_PASSPHRASE`, `VAPID_PUBLIC_KEY`, `VAPID_PRIVATE_KEY` in encrypted vault (`config/secrets/prod/`). Public key committed; decrypt key gitignored. `PlaceholderSecretChecker` blocks prod startup if placeholders remain. Deploy unlocks via `SYMFONY_DECRYPTION_SECRET` env or copying `prod.decrypt.private.php`.

```bash
ddev exec "cd backend && bin/console secrets:set NAME --env=prod"
ddev exec "cd backend && bin/console secrets:list --env=prod"
```

**VAPID (Web Push)** — generate once:
```bash
ddev exec php -r "use Minishlink\WebPush\VAPID; \$k = VAPID::createVapidKeys(); echo 'Public: '.\$k['publicKey'].PHP_EOL.'Private: '.\$k['privateKey'].PHP_EOL;"
```
Set `VAPID_PUBLIC_KEY` / `VAPID_PRIVATE_KEY` / `VAPID_SUBJECT=mailto:…` in `backend/.env.local` (dev) or secrets vault (prod). Frontend needs `VITE_VAPID_PUBLIC_KEY` for subscription.

**Symfony Messenger**: transport `doctrine://default` (table `messenger_messages`). Test: `in-memory://`. Config: `backend/config/packages/messenger.yaml`. `EnrichSeriesMessage` routed async.

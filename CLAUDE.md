# CLAUDE.md

**Mandatory** rules for Claude Code on this project.

## Project

**Comic/Manga Library** — Symfony 7.4, PHP 8.3, MariaDB 10.11, DDEV, Doctrine ORM, Symfony UX, PWA.

**Context**: Claude = sole developer → maximum rigor, keep this file and tests up to date.

## Approach

- Edit when asked to edit. No issues/plans unless requested.
- Prefer acting over asking.
- **No codebase exploration.** CLAUDE.md, MEMORY.md, and `memory/patterns.md` contain all needed context (file paths, patterns, conventions). Jump straight to implementation. Only read files you are about to edit — never glob/grep to "understand the codebase" first.
- **Keep docs up to date.** When adding new entities, enums, services, routes, or commands, update the Architecture section in this file and `memory/patterns.md` in the same session.

## Plans

- Location: `docs/plans/` (never global). Delete after PR merged.

## Workflow

- **Complex tasks**: Plan mode → approval → implementation
- **Splitting**: divide large changes into verifiable chunks

## Principle: Don't Reinvent the Wheel

**Before any implementation**, search in order:
1. Native Symfony/Doctrine component
2. Official bundle
3. Third-party library (maintained, popular, PHP 8.3/Symfony 7.x compatible, MIT/Apache/BSD license)
4. Custom implementation (last resort)

**Search**: Packagist, symfony.com/bundles, npm, https://ux.symfony.com

## Commands

**Makefile**: `make help` for the full list. Main shortcuts:

```bash
make build          # ddev start + composer + npm + migrations + asset-map:compile
make test           # PHP + JS tests
make test-php       # PHPUnit only
make test-js        # Vitest only
make lint           # PHP-CS-Fixer (dry-run) + PHPStan
make cc             # cache:clear
make migration      # doctrine:migrations:diff
make migrate        # doctrine:migrations:migrate
make deploy         # docker-compose prod
```

**Direct DDEV commands** (when the Makefile isn't enough):

```bash
ddev exec bin/phpunit tests/PathToTest.php          # Specific test
ddev exec npm run test:watch                        # JS Tests (watch mode)
ddev exec bin/console doctrine:migrations:diff -n   # Generate migration
```

**PHP-CS-Fixer and PHPStan**: automated via PostToolUse hooks.

## PHP Rules

1. `declare(strict_types=1);` at the top of each file
2. Prefix native functions: `\array_map()`, `\sprintf()`, `\count()`
3. Method order: `__construct()` → `public` → `protected` → `private`
4. Arguments on one line (except constructors with promotion: one per line)
5. Alphabetical sorting: constructor assignments, array keys, YAML keys
6. Documentation in French
7. Symfony standards

**Entity validation**: `$this->validator->validate($entity)` before persist.

## Mandatory TDD

1. **Test first**: write/modify test → must fail
2. **Implement**: minimum to make the test pass
3. **Refactor**: green tests

**Convention**: `src/X/Foo.php` → `tests/X/FooTest.php`

**Test environment**: `db_test`, `https://test.bibliotheque.ddev.site`, `.env.test`

**Test helpers**: `AuthenticatedWebTestCase` (base class for controller tests with login).

**TDD exceptions**: Twig templates, YAML config, migrations, assets.

## JavaScript Tests (Vitest)

**Framework**: Vitest + jsdom (native ESM, AssetMapper compatible).

**Convention**: `assets/X/foo.js` → `tests/js/X/foo.test.js`

**Helpers**:
- `tests/js/setup.js` — global mocks (fetch, localStorage, Cache API, crypto)
- `tests/js/helpers/stimulus-helper.js` — `startStimulusController()` / `stopStimulusController()`

**Fake timers**: always enable `vi.useFakeTimers()` **AFTER** `startStimulusController()` (otherwise Stimulus timeout).

## Rector (occasional use)

```bash
ddev exec vendor/bin/rector process --dry-run      # Always check first
ddev exec vendor/bin/rector process src/File.php
```

Run PHP-CS-Fixer and tests afterwards.

## Frontend

Priority: Symfony UX → npm → custom Stimulus.
UX Packages: `ux-autocomplete`, `ux-dropzone`, `ux-turbo`.

## Git

### Commits

Format: `<type>(scope): description in French` — Types: `feat`, `fix`, `chore`, `refactor`, `docs`
**Always** reference the issue: append `#N` in the message body or use `fixes #N` to auto-close.

### Branches (GitHub Flow)

- `main` = always stable and deployable
- Working branches: `<type>/<N>-<short-description>` (e.g., `feat/23-api-cache`, `fix/25-camera-permission`)
- Non-trivial work → branch + PR + squash merge
- Direct commits on `main` allowed for: typos, CLAUDE.md, minor config
- Link PRs to issues: `fixes #N` or `closes #N`
- Remote branch auto-deleted after merge (GitHub setting)
- Update CHANGELOG after every merged PR.

### Tags and releases (SemVer)

- Format: `vMAJOR.MINOR.PATCH`
- MAJOR = breaking change, MINOR = new feature, PATCH = bugfix/perf
- Tags on `main` only, no need to tag every merge
- Release process:
  1. CHANGELOG.md: `[Unreleased]` → `[vX.Y.Z] - YYYY-MM-DD`
  2. Commit: `chore(release): vX.Y.Z`
  3. Tag: `git tag -a vX.Y.Z -m "vX.Y.Z"` + push
  4. GitHub Release: `gh release create vX.Y.Z`

## Issue Workflow

- "Next issue" → pick highest-priority Todo from board, start immediately (don't list/ask).
- Full cycle: implement → test → PR → review fixes → squash merge → close → update board.

## GitHub Issues & Project

**Repo**: `Soviann/bibliotheque` — **Project**: `Bibliotheque - Roadmap` (number: 1, owner: Soviann)

**Board columns** (Status field): `Backlog` → `Todo` → `In Progress` → `Done`

### Rules

1. **All work starts from an issue.** If the user provides an issue number, use it. Otherwise create one directly — **never list/search issues first** (sole developer knows what exists).
2. **No read-only GitHub queries.** Never call `list_issues`, `list_pull_requests`, `search_issues`, `search_pull_requests`, or `list_branches` to "check" state. The user will provide context. Only query GitHub when a specific ID or data point is needed and unknown.
3. **Board automation**: GitHub Project workflows automatically move issues to `Done` on close. Don't move manually. Only `In Progress` at start needs manual move.
4. **New feature ideas** without immediate implementation go to `Backlog`.
5. **Close issues** via `fixes #N` in PR/commit message — no manual closing.
6. **Labels**: use existing repo labels (`enhancement`, `bug`, etc.). Don't create new labels without asking.

### Token optimization

- **Prefer `gh` CLI** over MCP tools for simple queries (less verbose output, use `--json field1,field2` to filter)
- **Max `perPage: 5`** unless more results are explicitly needed
- **No exploratory chains**: one targeted call, not list → read → read

### Quick reference

```bash
# Issues
gh issue create --repo Soviann/bibliotheque --title "..." --body "..." --label "..."

# Project board — move item (only needed for In Progress, Done is automated)
# 1. Get item ID:  gh project item-list 1 --owner Soviann --format json
# 2. Edit status:  gh project item-edit --project-id PVT_kwHOANG8LM4BObgL --id <ITEM_ID> \
#      --field-id PVTSSF_lAHOANG8LM4BObgLzg9IoUA --single-select-option-id <OPTION_ID>
# Column option IDs: Backlog=d55ad18f  Todo=31c84745  InProgress=7c2874a8  Done=automated
```

## Changelog

Add in `## [Unreleased]`: `### Added|Changed|Fixed|Removed`
Format: `- **Name**: Description`

## Structure

```
src/{Command,Controller,DataFixtures,Doctrine/Filter,Dto,Entity,Enum,Form,Repository,Service,Twig}/
templates/                    assets/{controllers,utils}/
tests/{Behat,Command,Controller,Doctrine/Filter,Dto,Entity,Enum,Form,js,Panther,playwright,Repository,Security,Service,Twig}/
features/                     # Behat .feature files
```

## Architecture

### Entities

**ComicSeries**: `title`, `status:ComicStatus`, `type:ComicType`, `latestPublishedIssue?:int`, `latestPublishedIssueComplete:bool`, `isOneShot:bool`, `description?`, `publishedDate?`, `publisher?`, `coverFile?:File(Vich)`, `coverImage?`, `coverUrl?`, `deletedAt?:datetime(SoftDeletable)`, `createdAt`, `updatedAt`
- Implements: `SoftDeletableInterface` (trait `SoftDeletableTrait` from `knplabs/doctrine-behaviors`)
- Relations: `authors:M2M→Author`, `tomes:O2M→Tome(cascade,orphanRemoval)`
- Methods: `isWishlist()`, `getCurrentIssue()`, `getLastBought()`, `getLastDownloaded()`, `getMissingTomesNumbers()`, `getOwnedTomesNumbers()`, `getAuthorsAsString()`, `isCurrentIssueComplete()`, `isLastBoughtComplete()`, `isLastDownloadedComplete()`, `delete()`, `restore()`, `isDeleted()`

**Tome**: `number:int`, `bought:bool`, `downloaded:bool`, `onNas:bool`, `isbn?`, `title?`, `createdAt`, `updatedAt` — Relation: `comicSeries:M2O→ComicSeries`

**Author**: `name:string(unique)` — Relation: `comicSeries:M2M→ComicSeries`

**User**: `email:string(unique)`, `password`, `roles:array`

### Enums

**ApiLookupStatus**: `ERROR`, `NOT_FOUND`, `RATE_LIMITED`, `SUCCESS`
**ComicStatus**: `BUYING`, `FINISHED`, `STOPPED`, `WISHLIST`
**ComicType**: `BD`, `COMICS`, `LIVRE`, `MANGA`

### Services

**ComicSeriesMapper**: `mapToEntity(ComicSeriesInput, ?ComicSeries): ComicSeries`, `mapToInput(ComicSeries): ComicSeriesInput`

**CoverRemoverInterface**: `remove(ComicSeries): void` — Impl: `VichCoverRemover` (also invalidates LiipImagine cache)

**UploadHandlerInterface**: `remove(object, string): void` — Impl: `VichUploadHandlerAdapter` (adapter for VichUploader's final `UploadHandler`)

**IsbnLookupService**: `lookup(isbn, ?type): ?array`, `lookupByTitle(title, ?type): ?array`, `getLastApiMessages(): array`
- APIs: Google Books, Open Library, AniList (mangas)
- Returns: `[title, authors, description, publishedDate, publisher, isbn, thumbnail, isOneShot, sources]`
- `getLastApiMessages()`: status of each queried API (`{api_name: {status, message}}`), uses `ApiLookupStatus`

### DTOs

**ComicSeriesInput**: `title`, `status`, `type`, `latestPublishedIssue?`, `latestPublishedIssueComplete`, `isOneShot`, `description?`, `publishedDate?`, `publisher?`, `coverUrl?`, `coverImage?`, `deleteCover`, `coverFile?`, `tomes:list<TomeInput>`, `authors:list<AuthorInput>`

**TomeInput**: `number`, `bought`, `downloaded`, `onNas`, `isbn?`, `title?`

**AuthorInput**: `name`

**ComicFilters**: `nas?`, `q?`, `sort`, `status?`, `type?` — Used with `#[MapQueryString]`

### Routes

| Route | Controller |
|-------|------------|
| `/` | HomeController::index |
| `/comic/{id}` | ComicController::show |
| `/comic/new` | ComicController::new |
| `/comic/{id}/edit` | ComicController::edit |
| `/comic/{id}/delete` | ComicController::delete |
| `/comic/{id}/to-library` | ComicController::toLibrary |
| `/wishlist` | WishlistController::index |
| `/search` | SearchController::index |
| `/trash` | TrashController::index |
| `/trash/{id}/restore` | TrashController::restore |
| `/trash/{id}/permanent-delete` | TrashController::permanentDelete |
| `/login`, `/logout` | SecurityController |
| `/offline` | OfflineController |
| `/api/comics` | ApiController::comics |
| `/api/isbn-lookup` | ApiController::isbnLookup |
| `/api/title-lookup` | ApiController::titleLookup |

### Repositories

**AuthorRepository**: `findOrCreate()`, `findOrCreateMultiple()`
**ComicSeriesRepository**: `findWithFilters()`, `findByStatus()`, `search()`, `findAllForApi()`
**TomeRepository**: (no custom methods)
**UserRepository**: (no custom methods)

### Form Types

**ComicSeriesType**, **TomeType**, **AuthorAutocompleteType**
**DataTransformer**: `AuthorToInputTransformer`

### Twig Extensions

**CoverImageExtension**: `cover_image_url(comic, filter='cover_thumbnail')` — optimized cover URL (LiipImagine for uploads, direct URL for external, empty string if no cover)

**SafeRefererExtension**: `safeReferer(fallback)` — returns filtered HTTP referer

### Console Commands

- `app:create-user <email> <password>`
- `app:import-excel <file> [--dry-run]`
- `app:purge-deleted [--days=30] [--dry-run]`

### Integrations

VichUploaderBundle (covers), LiipImagineBundle (resize + WebP), knplabs/doctrine-behaviors (soft delete), PWA (`/offline`, `/api/comics`), APIs (Google Books, Open Library, AniList)

### Behat

**Features**: `features/*.feature` — acceptance tests (authentication, CRUD comics, tomes, filtering, search, wishlist, one-shot)
**Contexts**: `tests/Behat/Context/` — `AuthenticationContext`, `ComicSeriesContext`, `DatabaseContext`, `FeatureContext`, `FormContext`, `NavigationContext`, `PantherContext`

### Playwright

`tests/playwright/offline.spec.js` — offline mode E2E test

## Deployment

```bash
docker compose -f docker-compose.prod.yml up --build -d
```

## Gotchas

- **Google Books**: may return partial data, verify `title`
- **VichUploader**: removing `coverImage` doesn't delete the physical file
- **Tomes**: `orphanRemoval=true`, be careful with direct manipulations
- **Twig cache**: `cache:clear` sometimes needed in dev
- **Panther tests must extend `TestCase`**, not `KernelTestCase` — DAMA wraps data in uncommitted transactions invisible to Selenium. Use `PantherTestHelper` trait (driver, login, `runSql()` via `Process`)
- **Enum values are lowercase in DB**: `'buying'` not `'BUYING'` (PHP backed enum stores the value, not the case name)
- **`LAST_INSERT_ID()`** doesn't work across separate `bin/console` calls (each opens a new connection). Use `SELECT ... WHERE title = '...'` instead
- **Turbo + Selenium**: use `executeScript` to fill forms + `form.submit()` (avoids `StaleElementReferenceException` from DOM replacement)
- **AssetMapper + Panther**: after modifying JS, run `ddev exec bin/console asset-map:compile` — Selenium loads compiled assets from `public/assets/`, not source files
- **Soft delete filter**: `SoftDeleteFilter` is enabled by default in `doctrine.yaml`. To access soft-deleted series, disable with `$em->getFilters()->disable('soft_delete')` then re-enable after
- **Permanent deletion**: `$em->remove()` is always intercepted by `SoftDeletableEventSubscriber` — use direct DBAL (`$connection->delete()`) to actually delete, respecting FK order: `comic_series_author` → `tome` → `comic_series`

## Maintenance

Explore code only for: internal implementation, Twig templates, Stimulus controllers.

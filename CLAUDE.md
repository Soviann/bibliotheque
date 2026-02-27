# CLAUDE.md

**Mandatory** rules for Claude Code on this project.

## Project

**Comic/Manga Library** — Monorepo: `backend/` (Symfony 7.4, PHP 8.3, API Platform 4, JWT) + `frontend/` (React 19, TypeScript, Vite, TanStack Query). MariaDB 10.11, DDEV, PWA.

**Context**: Claude = sole developer → maximum rigor, keep this file and tests up to date.

## Approach

- Edit when asked to edit. No issues/plans unless requested.
- Prefer acting over asking.
- **No codebase exploration.** CLAUDE.md, MEMORY.md, and `memory/patterns.md` contain all needed context (file paths, patterns, conventions). Jump straight to implementation. Only read files you are about to edit — never glob/grep to "understand the codebase" first.
- **Keep docs up to date.** When adding new entities, enums, services, routes, or commands, update the Architecture section in this file and `memory/patterns.md` in the same session.

## Plans

- Location: `docs/plans/` (never global, gitignored). Delete after PR merged.
- **Concise plans only**: describe what to do (files, logic, order), not how (no code blocks). Code is written at implementation time.

## Workflow

- **Complex tasks**: Plan mode → approval → implementation
- **Splitting**: divide large changes into verifiable chunks

## Principle: Don't Reinvent the Wheel

**Before any implementation**, search in order:
1. Native Symfony/Doctrine/React component
2. Official bundle or npm package
3. Third-party library (maintained, popular, compatible, MIT/Apache/BSD license)
4. Custom implementation (last resort)

**Search**: Packagist, symfony.com/bundles, npm

## Commands

**Root Makefile** delegates to `backend/` and `frontend/`. Main shortcuts:

```bash
make dev            # install + jwt + migrate
make prod           # install --no-dev + dump-env + build + migrate + cache
make ci             # lint + test
make install        # install-back + install-front
make test           # test-back + test-front
make test-back      # PHPUnit
make test-front     # Vitest
make lint           # lint-back + lint-front
make lint-back      # PHPStan + CS Fixer dry-run
make lint-front     # tsc --noEmit
make build          # Vite production build
make serve-prod     # build + vite preview (port 4173)
make verify-build   # build + check no devtools in bundle
make cc             # cache:clear
make sf CMD=...     # Run any Symfony console command
make jwt            # Generate JWT keypair
make dump-env       # Compile .env for Symfony
make db-diff        # doctrine:migrations:diff
make db-migrate     # doctrine:migrations:migrate
make db-reset       # drop + create + migrate
make db-seed        # Load fixtures
make rector         # Apply Rector refactorings
make rector-dry     # Preview Rector refactorings
make deploy         # docker-compose prod
```

**Direct DDEV commands** (when the Makefile isn't enough):

```bash
ddev exec bin/phpunit tests/PathToTest.php          # Specific test
ddev exec "cd frontend && npx vitest run"           # Frontend tests
ddev exec bin/console doctrine:migrations:diff -n   # Generate migration
```

**PHP-CS-Fixer and PHPStan**: run before committing, only on modified files.

## PHP Rules

1. `declare(strict_types=1);` at the top of each file
2. Prefix native functions: `\array_map()`, `\sprintf()`, `\count()`
3. Method order: `__construct()` → `public` → `protected` → `private`
4. Arguments on one line (except constructors with promotion: one per line)
5. Alphabetical sorting: constructor assignments, array keys, YAML keys
6. Documentation in French
7. Symfony standards

**Entity validation**: `$this->validator->validate($entity)` before persist.

**DB queries**: All database queries MUST live in dedicated entity repositories (`src/Repository/`). Use QueryBuilder exclusively (no raw DQL strings). Services and controllers inject repositories, never `EntityManagerInterface` for queries.

## Mandatory TDD

1. **Test first**: write/modify test → must fail
2. **Implement**: minimum to make the test pass
3. **Refactor**: green tests

**Backend convention**: `backend/src/X/Foo.php` → `backend/tests/X/FooTest.php`

**Frontend convention**: `frontend/src/X/Foo.tsx` → `frontend/src/__tests__/X/Foo.test.tsx`

**Test environment**: `db_test`, `https://test.bibliotheque.ddev.site`, `.env.test`

**TDD exceptions**: YAML config, migrations, assets, CSS.

## Frontend (React + TypeScript)

**Stack**: React 19, TypeScript, Vite, TanStack Query v5, React Router v7, Tailwind CSS 4, Headless UI, Lucide React, Sonner.

**Tests**: Vitest + jsdom + React Testing Library.

**Patterns**:
- Hooks for all API interactions (`useComics`, `useComic`, `useCreateComic`, etc.)
- `apiFetch<T>()` in `services/api.ts` handles JWT token, Content-Type, 401 redirects
- Mutations invalidate relevant query keys on success
- Pages are lazy-loaded via `React.lazy()` in `App.tsx`

**JWT Auth**: Token stored in `localStorage`, 30-day TTL for PWA offline use. `AuthGuard` component redirects to `/login` if not authenticated.

## API (API Platform 4)

**Format**: JSON-LD (`application/ld+json`).

**Resources**:
- `ComicSeries` — CRUD + custom restore/permanent-delete operations
- `Author` — GetCollection (search by name), Get, Post
- `Tome` — Sub-resource of ComicSeries via `uriTemplate`

**Processors**: `ComicSeriesDeleteProcessor` (soft delete), `ComicSeriesRestoreProcessor`, `ComicSeriesPermanentDeleteProcessor`.

**Lookup**: `/api/lookup/isbn?isbn=...&type=...` and `/api/lookup/title?title=...&type=...` (JWT protected).

**Login**: `POST /api/login` with `{email, password}` → `{token}`.

## Rector (occasional use)

```bash
ddev exec vendor/bin/rector process --dry-run      # Always check first
ddev exec vendor/bin/rector process backend/src/File.php
```

Run PHP-CS-Fixer and tests afterwards.

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

- "Next issue" → pick highest-priority Todo from board (use Priority field: Urgent > High > Medium > Low), start immediately (don't list/ask).
- Full cycle: implement → test → PR → review fixes → squash merge → close → update board.

## GitHub Issues & Project

**Repo**: `Soviann/bibliotheque` — **Project**: `Bibliotheque - Roadmap` (number: 1, owner: Soviann)

**Board columns** (Status field): `Backlog` → `Todo` → `In Progress` → `Done`
**Priority field**: `Urgent` → `High` → `Medium` → `Low`

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

# Priority field
#   gh project item-edit --project-id PVT_kwHOANG8LM4BObgL --id <ITEM_ID> \
#      --field-id PVTSSF_lAHOANG8LM4BObgLzg-FnaM --single-select-option-id <OPTION_ID>
# Priority IDs: Urgent=76f5d51a  High=e40c620b  Medium=df6c7ff1  Low=8d76e9b3
```

## Changelog

Add in `## [Unreleased]`: `### Added|Changed|Fixed|Removed`
Format: `- **Name**: Description`

## Structure

```
backend/
  src/{Command,Controller,Doctrine/Filter,Dto,Entity,Enum,Repository,Service,State}/
  tests/{Command,Entity,Enum,Repository,Service,State}/
  config/  migrations/

frontend/
  src/
    components/   # AuthGuard, BarcodeScanner, ComicCard, ConfirmModal, ErrorFallback, Filters, Layout
    hooks/        # useAuth, useAuthors, useComic, useComics, useCreateComic, useDeleteComic,
                  # useLookup, useTrash, useUpdateComic
    pages/        # ComicDetail, ComicForm, Home, Login, NotFound, Search, Trash, Wishlist
    services/     # api.ts (apiFetch, JWT token management)
    types/        # api.ts (interfaces), enums.ts (ComicStatus, ComicType)
    __tests__/    # test-utils.tsx
```

## Deployment

```bash
docker compose -f docker-compose.prod.yml up --build -d
```

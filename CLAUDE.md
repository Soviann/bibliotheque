# CLAUDE.md

**Mandatory** rules for Claude Code on this project.

## Project

**Comic/Manga Library** — Monorepo: `backend/` (Symfony 7.4, PHP 8.3, API Platform 4, JWT) + `frontend/` (React 19, TypeScript, Vite, TanStack Query). MariaDB 10.11, DDEV, PWA.

**Context**: Claude = sole developer → maximum rigor, keep this file and tests up to date.

## Approach

- Edit when asked to edit. No issues/plans unless requested.
- Prefer acting over asking.
- **No codebase exploration.** CLAUDE.md, MEMORY.md, and `memory/patterns.md` contain all needed context. Jump straight to implementation. Only read files you are about to edit.
- **Keep docs up to date.** When adding new entities, enums, services, routes, or commands, update this file and `memory/patterns.md` in the same session.

## Plans

Location: `docs/plans/` (gitignored). Concise only: what to do (files, logic, order), not how (no code blocks). Delete after PR merged.

## Workflow

- **GitHub issues → `/implement` skill**: ALL work on a GitHub issue (bug, feature, chore) MUST go through the `/implement` skill. No exceptions.
- **Complex tasks**: Plan mode → approval → implementation
- **Splitting**: divide large changes into verifiable chunks

## Don't Reinvent the Wheel

**Before implementing**, search: 1) Native Symfony/Doctrine/React 2) Official bundle/npm package 3) Third-party lib (maintained, MIT/Apache/BSD) 4) Custom (last resort). Search: Packagist, symfony.com/bundles, npm.

## DDEV — Mandatory

**All commands via DDEV** (`ddev exec ...`). NEVER run `npm`, `npx`, `composer`, `php`, `bin/console`, `bin/phpunit`, `make` on host. Exceptions: `git`, `gh`, host-only tools (`docker`, `ssh`, `curl`).

## Commands

**Root Makefile** delegates to `backend/` and `frontend/`:

| Command | Purpose |
|---------|---------|
| `make dev` | install + jwt + migrate |
| `make prod` | install --no-dev + dump-env + build + migrate + cache |
| `make ci` | lint + test |
| `make install` | install-back + install-front |
| `make test` / `test-back` / `test-front` | PHPUnit / Vitest |
| `make lint` / `lint-back` / `lint-front` | PHPStan+CS Fixer / tsc --noEmit |
| `make build` | Vite production build |
| `make verify-build` | build + check no devtools in bundle |
| `make cc` | cache:clear |
| `make sf CMD=...` | Any Symfony console command |
| `make jwt` | Generate JWT keypair |
| `make dump-env` | Compile .env for Symfony |
| `make db-diff` / `db-migrate` / `db-reset` / `db-seed` | Migration diff / migrate / drop+create+migrate / fixtures |
| `make rector` / `rector-dry` | Apply / preview Rector refactorings |
| `make deploy` | docker-compose prod |

**Direct DDEV** (when Makefile isn't enough):
```bash
ddev exec bin/phpunit tests/PathToTest.php
ddev exec "cd frontend && npx vitest run"
ddev exec bin/console doctrine:migrations:diff -n
ddev exec bin/console app:invalidate-tokens [--email=X]
```

PHP-CS-Fixer and PHPStan: run before committing, only on modified files.

## PHP Rules

1. `declare(strict_types=1);` at top of each file
2. Prefix native functions: `\array_map()`, `\sprintf()`, `\count()`
3. Method order: `__construct()` → `public` → `protected` → `private`
4. Arguments on one line (constructors with promotion: one per line)
5. Alphabetical sorting: constructor assignments, array keys, YAML keys
6. Documentation in French
7. Symfony standards
8. **Prefer DTOs over arrays**: `readonly` DTO classes in `src/DTO/` (domain) or same namespace. `JsonSerializable` only for API/cache.

**Entity validation**: `$this->validator->validate($entity)` before persist.

**DB queries**: All in repositories (`src/Repository/`). QueryBuilder only (no raw DQL). Services/controllers inject repositories, never `EntityManagerInterface` for queries.

## Mandatory TDD

1. **Test first**: write/modify test → must fail
2. **Implement**: minimum to pass
3. **Refactor**: green tests

**Backend**: `backend/src/X/Foo.php` → `backend/tests/{Unit,Integration,Functional}/X/FooTest.php`
**Frontend**: `frontend/src/X/Foo.tsx` → `frontend/src/__tests__/{unit,integration}/X/Foo.test.tsx`
**Test env**: `db_test`, `https://test.bibliotheque.ddev.site`, `.env.test`
**Exceptions**: YAML config, migrations, assets, CSS.

## Frontend (React + TypeScript)

**Stack**: React 19, TypeScript, Vite, TanStack Query v5, React Router v7, Tailwind CSS 4, Headless UI, Lucide React, Sonner, `@react-oauth/google`. **Tests**: Vitest + jsdom + RTL.

**Patterns**:
- `apiFetch<T>()` handles JWT, Content-Type, 401 redirects
- Mutations invalidate relevant query keys on success
- Pages lazy-loaded via `React.lazy()` in `App.tsx`
- JWT in `localStorage`, 365-day TTL, token versioning. `AuthGuard` → `/login`. Google OAuth
- Dark mode: `useDarkMode` (`.dark` on `<html>`, localStorage)
- Offline: `useOnlineStatus` + `OfflineBanner`, SW updates via `useServiceWorker` + Sonner toast

## API (API Platform 4)

**Format**: JSON-LD (`application/ld+json`). **Login**: `POST /api/login/google` with `{credential}` → `{token}`. Single email via `OAUTH_ALLOWED_EMAIL`.

Resources, processors, providers, lookup endpoints → see `memory/patterns.md`.

## Rector

```bash
ddev exec vendor/bin/rector process --dry-run      # Always check first
ddev exec vendor/bin/rector process backend/src/File.php
```
Run CS-Fixer and tests afterwards.

## Git

### Commits

**Always** reference the issue: `#N` in body or `fixes #N` to auto-close.

### Branches (GitHub Flow)

- `main` = stable, deployable
- Working: `<type>/<N>-<short-description>` (e.g., `feat/23-api-cache`)
- Non-trivial → branch + PR + squash merge
- Direct on `main`: typos, CLAUDE.md, minor config
- `fixes #N` / `closes #N` in PRs. Remote branch auto-deleted after merge.
- Update CHANGELOG after every merged PR.

### Tags/Releases (SemVer)

Format: `vMAJOR.MINOR.PATCH`. Tags on `main` only. **Pushing a tag triggers production deployment** (NAS pulls latest tag nightly via `nas-update.sh`).
1. CHANGELOG: `[Unreleased]` → `[vX.Y.Z] - YYYY-MM-DD`
2. Commit: `chore(release): vX.Y.Z`
3. `git tag -a vX.Y.Z -m "vX.Y.Z"` + push tag + push commit

## Issue Workflow

- "Next issue" → pick highest-priority Todo (Urgent > High > Medium > Low), start immediately.
- Full cycle: implement → test → PR → review fixes → squash merge → close → update board.

## GitHub Issues & Project

**Repo**: `Soviann/bibliotheque` — **Project**: `Bibliotheque - Roadmap` (number: 1, owner: Soviann)

**Board**: `Backlog` → `Todo` → `In Progress` → `Done` | **Priority**: `Urgent` → `High` → `Medium` → `Low`

### Rules

1. **All work starts from an issue.** User provides number or create directly — never list/search first.
2. **No read-only GitHub queries.** Never `list_issues`, `list_pull_requests`, `search_issues`, etc. to "check" state.
3. **Board automation**: `Done` is automatic on close. Only `In Progress` needs manual move.
4. New feature ideas without immediate implementation → `Backlog`.
5. Close issues via `fixes #N` in PR/commit — no manual closing.
6. Use existing labels (`enhancement`, `bug`, etc.).

### Token optimization

- Prefer `gh` CLI over MCP tools (`--json field1,field2` to filter)
- Max `perPage: 5` unless more needed
- No exploratory chains: one targeted call

### Quick reference

```bash
gh issue create --repo Soviann/bibliotheque --title "..." --body "..." --label "..."

# Project board — move item (In Progress only, Done is automated)
# 1. gh project item-list 1 --owner Soviann --format json --limit 200
# 2. gh project item-edit --project-id PVT_kwHOANG8LM4BObgL --id <ITEM_ID> \
#      --field-id PVTSSF_lAHOANG8LM4BObgLzg9IoUA --single-select-option-id <OPTION_ID>
# Status IDs: Backlog=d55ad18f  Todo=31c84745  InProgress=7c2874a8
# Priority field-id: PVTSSF_lAHOANG8LM4BObgLzg-FnaM
# Priority IDs: Urgent=76f5d51a  High=e40c620b  Medium=df6c7ff1  Low=8d76e9b3
```

## Changelog

Add in `## [Unreleased]`: `### Added|Changed|Fixed|Removed`. Format: `- **Name**: Description`

## Structure

Full file map → `memory/patterns.md`

```
backend/src/{Command,Controller,DataFixtures,Doctrine/Filter,DTO,Entity,Enum,Event,EventListener,Repository,Service,State}/
backend/tests/{Unit,Integration,Functional,Factory,Trait}/
frontend/src/{components,hooks,pages,services,types,__tests__}/
```

## Deployment

### Docker (Synology NAS)

3 containers: **nginx** (static + reverse proxy) + **php** (php-fpm 8.3) + **db** (MariaDB 10.11). Frontend built in multi-stage nginx Dockerfile.

```bash
cd backend && docker compose up --build -d
```

Guides: `docs/guide-deploiement-nas.md` (human), `docs/guide-deploiement-nas-claude.md` (Claude via SSH), `docs/guide-deploiement-ovh.md` (OVH bare metal)

### Symfony Secrets (vault prod)

`APP_SECRET` + `JWT_PASSPHRASE` in encrypted vault (`config/secrets/prod/`). Public key committed, decrypt key gitignored.

```bash
ddev exec "cd backend && bin/console secrets:set SECRET_NAME --env=prod"
ddev exec "cd backend && bin/console secrets:list --env=prod"
```

Deploy: `SYMFONY_DECRYPTION_SECRET` env var or copy `prod.decrypt.private.php`. `PlaceholderSecretChecker` blocks prod startup if placeholders remain.

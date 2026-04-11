# CLAUDE.md — Mandatory rules

<!-- TEMPLATE:START — managed by sync-template-config, do not edit manually.
     Add here: conventions shared by ALL Siqual projects (coding standards, git rules,
     language rules, approach, token optimization, recommended plugins, translations).
     If unsure whether a rule belongs here or in PROJECT, ask the user before adding. -->

## Approach
- Act over ask; read only files you'll edit.
- Key context: CLAUDE.md, MEMORY.md, `docs/patterns.md` — update `docs/patterns.md` when adding entities/controllers/forms/commands/routes.
- CLAUDE.md and `docs/patterns.md` must be optimized for LLM use and token efficiency, without loss of information/instruction.
- Complex tasks: plan → approval → implement. Large changes: verifiable chunks.
- Plans: `docs/plans/YYYY-MM-DD-<feature-name>.md` — temporary, delete after merge. Never commit.
- Act on user instructions directly — no exploratory glob/grep when user names the target.
- Don't verify existence of items already known from plan or memory.

## Quality
- Linters/formatters: before committing only, ONLY on modified files.
- DRY: extract at 3+ occurrences (or 2 if complex). Exception: abstraction obscures intent or coupling > duplication cost.
- Prefer native/library solutions over custom code.

## Token Cost Optimization
- **Parallelize** independent tool calls in one turn. Serialize only when B depends on A's output.
- **Combine Bash** with `&&` when output-independent. Don't combine when you need A's output to decide B, or when failure diagnosis/timeouts differ.
- **No exploratory search when target is known.** Named file/symbol → Read/Grep directly. Skip verifying items known from plan/memory.
- **Direct tools > subagents.** Subagent = full Opus conversation billed on top. Use Grep/Glob/Read for known targets, single-file reads, <3 queries. Use subagents only for open-ended multi-file research or to protect main context.
- **Cheap models on mechanical subagents.** File search, simple refactor, mechanical lookup → `model: "haiku"` or `"sonnet"`.
- **`gh --json field1,field2`** over MCP GitHub tools for simple queries. `minimal_output: true` on MCP list/search calls.

## Coding Standards
- `@Symfony` CS Fixer ruleset + [Symfony standards](https://symfony.com/doc/current/contributing/code/standards.html)
- Backslash-prefix native functions: `\array_map()`, `\sprintf()`, `\count()`
- Prefer `u()` (String component) over native string functions.
- Yoda conditions: `null === $var` not `$var === null`.
- Method order: `__construct` → public → protected → private (`setUp`/`tearDown` first in tests).
- One-line args (except promoted constructors: one per line, trailing comma).
- Existing files: fix only your changes.
- PHPStan level 9 — never ignore/lower.
- Alphabetical: constructor assignments, array keys, YAML keys.
- No magic strings: use constants or enums for domain values reused across files.
- DB queries: repositories only (`src/Repository/`), QueryBuilder only (no DQL). Never inject `EntityManagerInterface` for queries.
- Doctrine migrations: always set `getDescription()` (French, concise).
- **DTOs over arrays**: `readonly` DTO classes in `src/DTO/` or same namespace. `JsonSerializable` only for API/cache.

## Git
- Format: `<type>(scope|branch-name): description` — types: `feat|fix|chore|refactor|docs`
- French descriptions: 3rd-person imperative (`ajoute`, `corrige`, `supprime` — not infinitive).
- Commit title = visible impact, not implementation detail. Technical details in body.
  - `fix`: problem solved. BAD: `utilise PATCH au lieu de PUT` GOOD: `corrige la perte des tomes`
  - `feat`: capability added. BAD: `ajoute CoverSearchService` GOOD: `ajoute la recherche de couvertures`
  - `refactor`/`chore`: improvement. BAD: `extrait getFieldPriority` GOOD: `simplifie la résolution de priorité`
- Never commit `docs/plans/` — always `git reset docs/plans/` before committing.
- Skip `git diff` when you made the edits — diff only to discover changes you didn't make.
- Merges: `--no-ff`

## Translations
- No hardcoded user-facing text — always use translation keys.
- `translations/messages.fr.yaml`: UI labels, titles, buttons, menus.
- `translations/validators.fr.yaml`: `Assert\*` constraint `message:` params.
- Key pattern: `app.<entity>.<context>.<purpose>` (e.g. `app.example.admin.fields.name.label`). Vendor keys: `siqual.*` — never duplicate in app.
- Twig: `{{ 'key'|trans }}`, with params: `{{ 'key'|trans({'%name%': val}) }}`

## Language
Commits + docs/comments: French. Code identifiers: English. CLAUDE.md: English.

## Recommended Plugins
`php-lsp`, `context7`, `superpowers`, `pr-review-toolkit`, `commit-commands`, `hookify`, `code-simplifier`.

<!-- TEMPLATE:END -->

<!-- PROJECT:START — project-specific content, edit freely.
     Add here: project description, tech stack, architecture, commands, project-specific
     deviations from the template rules.
     If unsure whether a rule belongs here or in TEMPLATE, ask the user before adding. -->

## Project

**Comic/Manga Library** — Monorepo: `backend/` (Symfony 7.4, PHP 8.3, API Platform 4, JWT) + `frontend/` (React 19, TypeScript, Vite, TanStack Query). MariaDB 10.11, DDEV, PWA.

**Context**: Claude = sole developer → maximum rigor, keep this file and tests up to date.

## Approach (overrides)

- No issues/plans unless requested.
- **Patterns file**: `.claude/memory/patterns.md` (not `docs/patterns.md`). Update when adding entities, enums, services, routes, or commands.
- **No codebase exploration.** CLAUDE.md, MEMORY.md, and `.claude/memory/patterns.md` contain all needed context. Jump straight to implementation. Only read files you are about to edit.
- **Keep docs up to date.** When adding new entities, enums, services, routes, or commands, update this file and `.claude/memory/patterns.md` in the same session.

## Translations (override)

This project uses a React frontend — user-facing text lives in React components, not Twig templates. The template `## Translations` rules do not apply to the frontend. Backend validation messages (`Assert\*`) still use `validators.fr.yaml`.

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
| `make coverage` | PHPUnit HTML coverage report (pcov) |
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
3. Prefer `u()` (String component) over native string functions
4. Yoda conditions: `if (null === $var)` not `if ($var === null)`
5. Method order: `__construct()` → `public` → `protected` → `private`
6. Arguments on one line (constructors with promotion: one per line)
7. Alphabetical sorting: constructor assignments, array keys, YAML keys
8. No magic strings: use constants or enums for domain values reused across files
9. PHPStan level 9 — never ignore/lower
10. Documentation in French
11. `@Symfony` CS Fixer ruleset + Symfony standards
12. **Prefer DTOs over arrays**: `readonly` DTO classes in `src/DTO/` (domain) or same namespace. `JsonSerializable` only for API/cache.

**Entity validation**: `$this->validator->validate($entity)` before persist.

**DB queries**: All in repositories (`src/Repository/`). QueryBuilder only (no raw DQL). Services/controllers inject repositories, never `EntityManagerInterface` for queries.

## Testing

**Rule: nothing ships without tests.** Timing is flexible — what matters is coverage, not ceremony.

| Situation | Approach |
|-----------|----------|
| Bug fix | **Test-first**: write a test that reproduces the bug → fix → green. Proves the bug existed. |
| Complex business logic (multiple edge cases) | **Test-first**: enumerate cases upfront, then implement. |
| New service / simple logic | **Write code + tests together**, run once. Skip the ceremonial "red" step when failure is obvious (class doesn't exist yet). |
| API endpoint (functional test) | **Code first, test after**: too many moving parts (routing, serialization, DB) for a useful "red" step. |
| React component | **Code first, test after**: component tests need DOM structure to exist. |
| Refactoring | **Tests already exist**: green before, green after. |

**Why not strict TDD everywhere**: the "verify it fails" step costs ~10s + tokens per run via DDEV. For an AI dev, it adds no information when the class/method doesn't exist yet. Reserve strict red-green-refactor for bug fixes and complex logic where it genuinely catches mistakes.

**Paths**: `backend/src/X/Foo.php` → `backend/tests/{Unit,Integration,Functional}/X/FooTest.php` | `frontend/src/X/Foo.tsx` → `frontend/src/__tests__/{unit,integration}/X/Foo.test.tsx`
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

Resources, processors, providers, lookup endpoints → see `.claude/memory/patterns.md`.

## Rector

```bash
ddev exec vendor/bin/rector process --dry-run      # Always check first
ddev exec vendor/bin/rector process backend/src/File.php
```
Run CS-Fixer and tests afterwards.

## Git (project additions)

- **Always** reference the issue: `#N` in body or `fixes #N` to auto-close.

### Branches (GitHub Flow)

- `main` = stable, deployable
- Working: `<type>/<N>-<short-description>` (e.g., `feat/23-api-cache`)
- Non-trivial → branch + PR + squash merge
- Direct on `main`: typos, CLAUDE.md, minor config
- `fixes #N` / `closes #N` in PRs. Remote branch auto-deleted after merge.
- Update CHANGELOG after every merged PR.

### Tags/Releases (SemVer)

Format: `vMAJOR.MINOR.PATCH`. Tags on `main` only. **Pushing a tag triggers production deployment** (CI builds images on ghcr.io, then SSH deploys on NAS via `nas-update.sh`).
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

Full file map → `.claude/memory/patterns.md`

```
backend/src/{Command,Controller,DataFixtures,Doctrine/Filter,DTO,Entity,Enum,Event,EventListener,Message,MessageHandler,Repository,State}/
backend/src/Service/{ComicSeries,Cover/Upload,Enrichment,Import,Lookup/{Contract,Gemini,Provider,Util},Merge,Nas,Notification,Recommendation}/
backend/tests/{Unit,Integration,Functional,Factory,Trait}/
frontend/src/{components,hooks,pages,services,types,__tests__}/
```

## Deployment

### Docker (Synology NAS)

3 containers: **nginx** (static + reverse proxy) + **php** (php-fpm 8.3) + **db** (MariaDB 10.11). Frontend built in multi-stage nginx Dockerfile. Images pre-built on CI and pushed to `ghcr.io/soviann/bibliotheque-{php,nginx}`.

```bash
# Dev local (build)
cd backend && docker compose up --build -d
# NAS production (pull pre-built images)
TAG=2.9.0 docker compose pull && docker compose up -d
```

Guides: `docs/guide-deploiement-nas.md` (human), `docs/guide-deploiement-nas-claude.md` (Claude via SSH)

### Symfony Secrets (vault prod)

`APP_SECRET` + `JWT_PASSPHRASE` + `VAPID_PUBLIC_KEY` + `VAPID_PRIVATE_KEY` in encrypted vault (`config/secrets/prod/`). Public key committed, decrypt key gitignored.

```bash
ddev exec "cd backend && bin/console secrets:set SECRET_NAME --env=prod"
ddev exec "cd backend && bin/console secrets:list --env=prod"
```

Deploy: `SYMFONY_DECRYPTION_SECRET` env var or copy `prod.decrypt.private.php`. `PlaceholderSecretChecker` blocks prod startup if placeholders remain.

### VAPID Keys (Web Push)

Required for push notifications. Generate once:
```bash
# Generate key pair
ddev exec php -r "use Minishlink\WebPush\VAPID; \$keys = VAPID::createVapidKeys(); echo 'Public: '.\$keys['publicKey'].PHP_EOL.'Private: '.\$keys['privateKey'].PHP_EOL;"
```

Set in `backend/.env.local` (dev) or Symfony secrets vault (prod):
```
VAPID_PUBLIC_KEY=<base64url>
VAPID_PRIVATE_KEY=<base64url>
VAPID_SUBJECT=mailto:user@example.com
```

Frontend needs the public key via `VITE_VAPID_PUBLIC_KEY` env var for push subscription.

### Symfony Messenger

Transport: `doctrine://default` (messages stored in `messenger_messages` table). Test env: `in-memory://`.

Config: `backend/config/packages/messenger.yaml`. Currently routes `EnrichSeriesMessage` → async.

<!-- PROJECT:END -->

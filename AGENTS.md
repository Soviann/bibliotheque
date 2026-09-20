# AGENTS.md — Bibliotheque Project Rules

> Project-specific rules and overrides for Bibliotheque. Shared global rules (Approach, Quality, Token Optimization, Git, Language) are injected globally from `~/.gemini/config/AGENTS.md`.

## Project Overview

**Comic/Manga Library** — Monorepo:
- `backend/`: Symfony 7.4, PHP 8.4, API Platform 4, Doctrine ORM, JWT authentication.
- `frontend/`: React 19, TypeScript, Vite, TanStack Query, Tailwind CSS, PWA.
- Infrastructure: MariaDB 10.11, DDEV local environment, Docker Compose for production.

## Codebase Map & Documentation Upkeep

- **No codebase exploration:** `docs/patterns.md` + `MEMORY.md` is the full map. Read `docs/patterns.md` before searching. Consult `docs/CONTEXT.md` for ubiquitous domain vocabulary.
- **Docs upkeep:** When adding or modifying entities, enums, DTOs, services, routes, or commands, update `AGENTS.md` + `docs/patterns.md` within the same commit/session.
- **Plans:** Path `<docs_dir>/plans/` (gitignored). Actionable steps only — no code blocks. Archive to `docs/plans/done/` after PR/merge per global rules.

## Translations

- **React frontend:** User-facing text lives directly in components (no Twig).
- **Backend validation:** `Assert\*` messages use `backend/translations/validators.fr.yaml`.

## Workflow & Library Search Order

- **Search order before custom code:** Native (Symfony / Doctrine / React) → official bundle/npm → maintained 3rd-party (MIT/Apache/BSD) → custom code. Search Packagist, symfony.com/bundles, npm.

## DDEV — Mandatory Execution & Safeguards

All `npm`, `npx`, `composer`, `php`, `bin/console`, `bin/phpunit`, `make` commands MUST run via `ddev exec` (or direct DDEV container). Host-only: `git`, `gh`, `docker`, `ssh`, `curl`.

### Critical Safeguards
1. **`ddev poweroff` is FORBIDDEN:** Never execute without explicit user confirmation. It stops ALL local DDEV projects, not just this one.
2. **`doctrine:schema:update` is FORBIDDEN:** Always use migrations (`ddev exec bin/console doctrine:migrations:diff -n`). Direct schema updates are strictly prohibited.
3. **Debug functions are FORBIDDEN in committed code:** `dd()`, `dump()`, `var_dump()`, `print_r()` must never be committed. Use `$this->logger->debug()` or test assertions instead.
4. **Rector is restricted:** Never run Rector on `vendor/`, migrations, or fixtures. Application code (`backend/src/`, `backend/tests/`) only. Always dry-run first (`ddev exec vendor/bin/rector process --dry-run`).

### Make Targets

| Target | Purpose |
|---|---|
| `dev` | install + jwt + migrate |
| `prod` | install --no-dev + dump-env + build + migrate + cache |
| `ci` | lint + test |
| `install` / `install-back` / `install-front` | Composer + npm |
| `test` / `test-back` / `test-front` | PHPUnit / Vitest |
| `lint` / `lint-back` / `lint-front` | PHPStan + CS Fixer / `tsc --noEmit` |
| `build` / `verify-build` | Vite build (verify: no devtools in bundle) |
| `cc` | cache:clear |
| `sf CMD=…` | Any Symfony console command |
| `jwt` / `dump-env` | JWT keypair / compile .env |
| `db-diff` / `db-migrate` / `db-reset` / `db-seed` | Migration diff / migrate / drop+create+migrate / fixtures |
| `coverage` | PHPUnit HTML (pcov) |
| `rector` / `rector-dry` | Apply / preview |
| `deploy` | docker-compose prod |

Direct DDEV when Make does not fit:
```bash
ddev exec bin/phpunit tests/Foo/BarTest.php
ddev exec "cd frontend && npx vitest run"
ddev exec bin/console doctrine:migrations:diff -n
ddev exec bin/console app:invalidate-tokens [--email=X]
```

## PHP Standards

1. `declare(strict_types=1);` at top of every PHP file.
2. Backslash-prefix native PHP functions: `\array_map()`, `\sprintf()`, `\count()`.
3. Prefer `u()` (Symfony String component) over native string functions.
4. Yoda conditions: `null === $var`.
5. Method order: `__construct` → public → protected → private (`setUp`/`tearDown` first in tests).
6. Arguments on one line — except promoted constructors (one per line, trailing comma).
7. Alphabetical ordering: constructor property assignments, array keys, YAML keys.
8. No magic strings: use constants or enums for cross-file domain values.
9. PHPStan level 9 — never lower.
10. French docblocks for business domain logic/annotations.
11. `@Symfony` CS Fixer ruleset + Symfony standards.
12. **DTOs over arrays:** `readonly` in `src/DTO/` or same namespace. `JsonSerializable` only for API/cache.
13. **Entity validation:** `$this->validator->validate($entity)` before persist.
14. **Database queries:** Repositories only (`src/Repository/`), QueryBuilder only (no DQL). Inject repositories — never `EntityManagerInterface` for queries.
15. **Doctrine migrations:** Always implement `getDescription()` with a concise French explanation.

## Testing Standards

Nothing ships without tests.
- **Bug fix:** Failing regression test first (TDD: reproduce → fix → green).
- **Complex business logic:** Test-first (enumerate cases upfront).
- **New service / simple logic:** Code and tests together.
- **API endpoint (functional):** Code first, test after.
- **React component:** Code first, test after.
- **Refactoring:** Existing tests stay green.
- **Skip strict red-green-refactor** when failure is obvious (class does not exist yet) — DDEV round-trip overhead saves no tokens.
- **Paths:**
  - Backend: `backend/src/X/Foo.php` → `backend/tests/{Unit,Integration,Functional}/X/FooTest.php`
  - Frontend: `frontend/src/X/Foo.tsx` → `frontend/src/__tests__/{unit,integration}/X/Foo.test.tsx`
- **Test environment:** `db_test`, `https://test.bibliotheque.ddev.site`, `.env.test`.
- **No tests for:** YAML config, migrations, assets, CSS.

## Frontend & API

Stack, conventions, API Platform 4 format, authentication, endpoints → see `docs/patterns.md`.

## Git & Versioning

- **Issue references:** `#N` in body, or `fixes #N` to auto-close.
- **Branches (GitHub Flow):** `main` = stable / deployable. Feature: `<type>/<N>-<short-description>` (e.g. `feat/23-api-cache`). Non-trivial → PR + squash merge (overrides shared `--no-ff`). Trivial (typos, AGENTS.md, minor config) → direct on `main`.
- **Releases & Tags (SemVer):** `vMAJOR.MINOR.PATCH` on `main` only. **Pushing a tag triggers prod deploy** (CI builds ghcr.io images → SSH to NAS via `nas-update.sh`). Use the `release` skill — handles CHANGELOG promotion + commit + tag + push.
- **CHANGELOG:** Update after each merged PR. Add to `## [Unreleased]` under `### Added|Changed|Fixed|Removed`. Format: `- **Name**: description`.

## Issues & Project Board

- **Repo:** `Soviann/bibliotheque` · **Project:** `Bibliotheque - Roadmap` (number 1, owner `Soviann`).
- **Board:** Backlog → Todo → In Progress → Done · **Priority:** Urgent > High > Medium > Low.
- All work starts from an issue (user provides number, or create directly — never list/search first).
- Close issues via `fixes #N` in PR/commit. `Done` is auto on close. Only `In Progress` needs manual move.
- Prefer `gh --json field1,field2`. Max `perPage: 5`. One targeted call.

```bash
gh issue create --repo Soviann/bibliotheque --title "..." --body "..." --label "..."

# Move to In Progress
# 1. gh project item-list 1 --owner Soviann --format json --limit 200
# 2. gh project item-edit --project-id PVT_kwHOANG8LM4BObgL --id <ITEM_ID> \
#      --field-id PVTSSF_lAHOANG8LM4BObgLzg9IoUA --single-select-option-id <OPTION_ID>
# Status:   Backlog=d55ad18f  Todo=31c84745  InProgress=7c2874a8
# Priority field-id: PVTSSF_lAHOANG8LM4BObgLzg-FnaM
# Priority: Urgent=76f5d51a  High=e40c620b  Medium=df6c7ff1  Low=8d76e9b3
```

## Structure & Deployment

Full file map, services, Docker/NAS deploy, Symfony Secrets vault, VAPID, Messenger → `docs/patterns.md`.

# CLAUDE.md — Mandatory rules

> Shared rules (Approach, Quality, Token Optimization, Git, Language, Recommended Plugins) injected by `soviann-conventions@soviann-tools` via SessionStart (`rules.md`). This file holds bibliotheque-specific rules and overrides only.

## Project

**Comic/Manga Library** — Monorepo: `backend/` (Symfony 7.4, PHP 8.3, API Platform 4, JWT) + `frontend/` (React 19, TS, Vite, TanStack Query). MariaDB 10.11, DDEV, PWA. Sole developer (Claude) → maximum rigor; keep this file + tests current.

## Approach (overrides)

- No issues/plans unless requested.
- **Patterns file**: `.claude/memory/patterns.md` (NOT `docs/patterns.md`).
- **Docs upkeep**: when adding entities, enums, services, routes, or commands, update CLAUDE.md + patterns.md in the same session.
- **No codebase exploration.** CLAUDE.md + MEMORY.md + patterns.md is the full map.

## Translations (override)

React frontend → user-facing text in components, not Twig. Backend `Assert\*` messages still use `translations/validators.fr.yaml`.

## Plans

`docs/plans/` (gitignored). Actionable steps only — no code blocks. Delete after PR merged.

## Workflow

- **GitHub issue work → `/implement` skill.** No exceptions.
- **Search order before custom**: native (Symfony/Doctrine/React) → official bundle/npm → maintained 3rd-party (MIT/Apache/BSD) → custom. Search Packagist, symfony.com/bundles, npm.

## DDEV — Mandatory

All `npm`, `npx`, `composer`, `php`, `bin/console`, `bin/phpunit`, `make` commands run via `ddev exec`. Host-only: `git`, `gh`, `docker`, `ssh`, `curl`.

## Make targets (root delegates to backend/frontend)

| Target | Purpose |
|---|---|
| `dev` | install + jwt + migrate |
| `prod` | install --no-dev + dump-env + build + migrate + cache |
| `ci` | lint + test |
| `install` / `install-back` / `install-front` | Composer + npm |
| `test` / `test-back` / `test-front` | PHPUnit / Vitest |
| `lint` / `lint-back` / `lint-front` | PHPStan+CS Fixer / `tsc --noEmit` |
| `build` / `verify-build` | Vite build (verify: no devtools in bundle) |
| `cc` | cache:clear |
| `sf CMD=…` | Any Symfony console command |
| `jwt` / `dump-env` | JWT keypair / compile .env |
| `db-diff` / `db-migrate` / `db-reset` / `db-seed` | Migration diff / migrate / drop+create+migrate / fixtures |
| `coverage` | PHPUnit HTML (pcov) |
| `rector` / `rector-dry` | Apply / preview |
| `deploy` | docker-compose prod |

Direct DDEV when Make doesn't fit:
```bash
ddev exec bin/phpunit tests/Foo/BarTest.php
ddev exec "cd frontend && npx vitest run"
ddev exec bin/console doctrine:migrations:diff -n
ddev exec bin/console app:invalidate-tokens [--email=X]
```

## PHP Rules

1. `declare(strict_types=1);` at top of each file
2. Backslash-prefix natives: `\array_map()`, `\sprintf()`, `\count()`
3. Prefer `u()` (String component) over native string functions
4. Yoda: `null === $var`
5. Method order: `__construct` → public → protected → private (`setUp`/`tearDown` first in tests)
6. Args on one line — except promoted constructors (one per line, trailing comma)
7. Alphabetical: constructor assignments, array keys, YAML keys
8. No magic strings — constants/enums for cross-file domain values
9. PHPStan level 9 — never lower
10. French docblocks
11. `@Symfony` CS Fixer ruleset + Symfony standards
12. **DTOs over arrays**: `readonly` in `src/DTO/` or same namespace. `JsonSerializable` only for API/cache.
13. **Entity validation**: `$this->validator->validate($entity)` before persist.
14. **DB queries**: repositories only (`src/Repository/`), QueryBuilder only (no DQL). Inject repositories — never `EntityManagerInterface` for queries.
15. **Doctrine migrations**: always set `getDescription()` (French, concise).

## Testing

Nothing ships without tests. Approach by situation:

- **Bug fix** → test-first (reproduce → fix → green)
- **Complex business logic** → test-first (enumerate cases upfront)
- **New service / simple logic** → code + tests together
- **API endpoint (functional)** → code first, test after
- **React component** → code first, test after
- **Refactoring** → existing tests stay green

Skip strict red-green-refactor when failure is obvious (class doesn't exist yet) — the DDEV round-trip costs ~10s + tokens for no information.

**Paths**: `backend/src/X/Foo.php` → `backend/tests/{Unit,Integration,Functional}/X/FooTest.php` · `frontend/src/X/Foo.tsx` → `frontend/src/__tests__/{unit,integration}/X/Foo.test.tsx`
**Test env**: `db_test`, `https://test.bibliotheque.ddev.site`, `.env.test`
**No tests for**: YAML config, migrations, assets, CSS.

## Frontend & API

Stack, conventions, API Platform 4 format/auth/endpoints → `.claude/memory/patterns.md`.

## Rector

```bash
ddev exec vendor/bin/rector process --dry-run             # always check first
ddev exec vendor/bin/rector process backend/src/File.php
```
Run CS-Fixer + tests after.

## Git (project)

- Reference issue: `#N` in body, or `fixes #N` to auto-close.
- **Branches (GitHub Flow)**: `main` = stable / deployable. Feature: `<type>/<N>-<short-description>` (e.g. `feat/23-api-cache`). Non-trivial → PR + squash merge (overrides shared `--no-ff`). Trivial (typos, CLAUDE.md, minor config) → direct on `main`. Update CHANGELOG after each merged PR.
- **Tags / Releases (SemVer)**: `vMAJOR.MINOR.PATCH` on `main` only. **Pushing a tag triggers prod deploy** (CI builds ghcr.io images → SSH to NAS via `nas-update.sh`). Use the `release` skill — handles CHANGELOG promotion + commit + tag + push.

## Issues & project board

- **Repo**: `Soviann/bibliotheque` · **Project**: `Bibliotheque - Roadmap` (number 1, owner `Soviann`).
- **Board**: Backlog → Todo → In Progress → Done · **Priority**: Urgent > High > Medium > Low.
- All work starts from an issue (user provides number, or create directly — never list/search first).
- **Next issue**: pick highest-priority Todo.
- New feature ideas without immediate implementation → `Backlog`.
- No read-only GH queries (`list_*`, `search_*`) just to "check" state.
- Close issues via `fixes #N` in PR/commit. `Done` is auto on close. Only `In Progress` needs manual move.
- Use existing labels (`enhancement`, `bug`, …).
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

## CHANGELOG

Add to `## [Unreleased]` under `### Added|Changed|Fixed|Removed`. Format: `- **Name**: description`.

## Structure & Deployment

Full file map, services, Docker/NAS deploy, Symfony Secrets vault, VAPID, Messenger → `.claude/memory/patterns.md`.

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

- Location: `docs/plans/` (never global, gitignored). Delete after PR merged.
- **Concise plans only**: describe what to do (files, logic, order), not how (no code blocks). Code is written at implementation time.

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
src/{Command,Controller,DataFixtures,Doctrine/Filter,Dto,Entity,Enum,Form,Repository,Service,Twig}/
templates/                    assets/{controllers,utils}/
tests/{Behat,Command,Controller,Doctrine/Filter,Dto,Entity,Enum,Form,js,Panther,playwright,Repository,Security,Service,Twig}/
features/                     # Behat .feature files
```

## Architecture

See `memory/patterns.md` for the complete file map, implementation patterns, and conventions.

## Deployment

```bash
docker compose -f docker-compose.prod.yml up --build -d
```

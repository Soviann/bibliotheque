# CLAUDE.md

**Mandatory** rules for Claude Code on this project.

## Project

**Comic/Manga Library** — Symfony 7.4, PHP 8.3, MariaDB 10.11, DDEV, Doctrine ORM, Symfony UX, PWA.

**Context**: Claude = sole developer → maximum rigor, keep this file and tests up to date.

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

## DDEV Commands

```bash
ddev start && ddev composer install && ddev exec bin/console doctrine:migrations:migrate -n
ddev exec bin/console doctrine:migrations:diff -n   # Generate migration
ddev exec bin/console cache:clear
ddev exec bin/phpunit tests/PathToTest.php          # Tests
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

**TDD exceptions**: Twig templates, YAML config, migrations, assets.

## Rector (occasional use)

```bash
ddev exec vendor/bin/rector process --dry-run      # Always check first
ddev exec vendor/bin/rector process src/File.php
```

Never on `vendor/`, migrations, fixtures. Run PHP-CS-Fixer and tests afterwards.

## Frontend

Priority: Symfony UX → npm → custom Stimulus.
UX Packages: `ux-autocomplete`, `ux-live-component`, `ux-chartjs`, `ux-dropzone`.

## Git

Format: `<type>(scope): description` — Types: `feat`, `fix`, `chore`, `refactor`, `docs`
No `Co-Authored-By`.

## Changelog

Add in `## [Unreleased]`: `### Added|Changed|Fixed|Removed`
Format: `- **Name**: Description`

## Structure

```
src/{Command,Controller,DataFixtures,Entity,Enum,Form,Repository,Service,Twig}/
templates/                    assets/controllers/
tests/                        features/
```

## Architecture

### Entities

**ComicSeries**: `title`, `status:ComicStatus`, `type:ComicType`, `latestPublishedIssue?:int`, `latestPublishedIssueComplete:bool`, `isOneShot:bool`, `description?`, `publishedDate?`, `publisher?`, `coverImage?`, `coverUrl?`
- Relations: `authors:M2M→Author`, `tomes:O2M→Tome(cascade,orphanRemoval)`
- Methods: `isWishlist()`, `getCurrentIssue()`, `getLastBought()`, `getLastDownloaded()`, `getMissingTomesNumbers()`, `isCurrentIssueComplete()`

**Tome**: `number:int`, `bought:bool`, `downloaded:bool`, `onNas:bool`, `isbn?`, `title?` — Relation: `comicSeries:M2O→ComicSeries`

**Author**: `name:string(unique)` — Relation: `comicSeries:M2M→ComicSeries`

**User**: `email:string(unique)`, `password`, `roles:array`

### Enums

**ComicStatus**: `BUYING`, `FINISHED`, `STOPPED`, `WISHLIST`
**ComicType**: `BD`, `COMICS`, `LIVRE`, `MANGA`

### Services

**ComicSeriesMapper**: `mapToEntity(ComicSeriesInput, ?ComicSeries): ComicSeries`, `mapToInput(ComicSeries): ComicSeriesInput`

**CoverRemoverInterface**: `remove(ComicSeries): void` — Impl: `VichCoverRemover`

**IsbnLookupService**: `lookup(isbn, ?type): ?array`, `lookupByTitle(title, ?type): ?array`
- APIs: Google Books, Open Library, AniList (mangas)
- Returns: `[title, authors, description, publishedDate, publisher, isbn, thumbnail, isOneShot, sources]`

### DTOs

**ComicSeriesInput**: `title`, `status`, `type`, `latestPublishedIssue?`, `latestPublishedIssueComplete`, `isOneShot`, `isWishlist`, `description?`, `publishedDate?`, `publisher?`, `coverUrl?`, `coverImage?`, `deleteCover`, `coverFile?`, `tomes:list<TomeInput>`, `authors:list<AuthorInput>`

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
| `/login`, `/logout` | SecurityController |
| `/offline` | OfflineController |
| `/api/comics` | ApiController::comics |
| `/api/isbn-lookup` | ApiController::isbnLookup |
| `/api/title-lookup` | ApiController::titleLookup |

### Repositories

**ComicSeriesRepository**: `findWithFilters()`, `search()`, `findAllForApi()`
**AuthorRepository**: `findOrCreate()`, `findOrCreateMultiple()`

### Console Commands

- `app:create-user <email> <password>`
- `app:import-excel <file> [--dry-run]`

### Integrations

VichUploaderBundle (covers), PWA (`/offline`, `/api/comics`), APIs (Google Books, Open Library, AniList)

## Deployment

```bash
docker compose -f docker-compose.prod.yml up --build -d
```

## Gotchas

- **Google Books**: may return partial data, verify `title`
- **VichUploader**: removing `coverImage` doesn't delete the physical file
- **Tomes**: `orphanRemoval=true`, be careful with direct manipulations
- **Twig cache**: `cache:clear` sometimes needed in dev

## Maintenance

Update "Architecture" after: new entity/enum/service/route/command.
Explore code only for: internal implementation, Twig templates, Stimulus controllers.

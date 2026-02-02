# CLAUDE.md

Règles **obligatoires** pour Claude Code sur ce projet.

## Projet

**Bibliothèque BD/Comics/Mangas** — Symfony 7.4, PHP 8.3, MariaDB 10.11, DDEV, Doctrine ORM, Symfony UX, PWA.

**Contexte** : Claude = seul développeur → rigueur maximale, maintenir ce fichier et les tests à jour.

## Workflow

- **Tâches complexes** : mode Plan → approbation → implémentation
- **Découpage** : diviser les gros changements en morceaux vérifiables

## Principe : ne pas réinventer la roue

**Avant toute implémentation**, chercher dans l'ordre :
1. Composant natif Symfony/Doctrine
2. Bundle officiel
3. Librairie tierce (maintenue, populaire, compatible PHP 8.3/Symfony 7.x, licence MIT/Apache/BSD)
4. Implémentation custom (dernier recours)

**Recherche** : Packagist, symfony.com/bundles, npm, https://ux.symfony.com

## Commandes DDEV

```bash
ddev start && ddev composer install && ddev exec bin/console doctrine:migrations:migrate -n
ddev exec bin/console doctrine:migrations:diff -n   # Générer migration
ddev exec bin/console cache:clear
ddev exec bin/phpunit tests/CheminVersTest.php      # Tests
```

**PHP-CS-Fixer et PHPStan** : automatisés via PostToolUse hooks.

## Règles PHP

1. `declare(strict_types=1);` en haut de chaque fichier
2. Préfixer fonctions natives : `\array_map()`, `\sprintf()`, `\count()`
3. Ordre méthodes : `__construct()` → `public` → `protected` → `private`
4. Arguments sur une ligne (sauf constructeurs avec promotion : un par ligne)
5. Tri alphabétique : assignations constructeur, clés tableaux, clés YAML
6. Documentation en français
7. Standards Symfony

**Validation entités** : `$this->validator->validate($entity)` avant persist.

## TDD obligatoire

1. **Test d'abord** : écrire/modifier le test → doit échouer
2. **Implémenter** : minimum pour faire passer le test
3. **Refactoriser** : tests verts

**Convention** : `src/X/Foo.php` → `tests/X/FooTest.php`

**Environnement test** : `db_test`, `https://test.bibliotheque.ddev.site`, `.env.test`

**Exceptions TDD** : templates Twig, config YAML, migrations, assets.

## Rector (usage ponctuel)

```bash
ddev exec vendor/bin/rector process --dry-run      # Toujours vérifier d'abord
ddev exec vendor/bin/rector process src/Fichier.php
```

Jamais sur `vendor/`, migrations, fixtures. Exécuter PHP-CS-Fixer et tests après.

## Frontend

Priorité : Symfony UX → npm → Stimulus custom.
Packages UX : `ux-autocomplete`, `ux-live-component`, `ux-chartjs`, `ux-dropzone`.

## Git

Format : `<type>(scope): description` — Types : `feat`, `fix`, `chore`, `refactor`, `docs`
Pas de `Co-Authored-By`.

## Changelog

Ajouter dans `## [Unreleased]` : `### Added|Changed|Fixed|Removed`
Format : `- **Nom** : Description`

## Structure

```
src/{Command,Controller,DataFixtures,Entity,Enum,Form,Repository,Service,Twig}/
templates/                    assets/controllers/
tests/                        features/
```

## Architecture

### Entités

**ComicSeries** : `title`, `status:ComicStatus`, `type:ComicType`, `latestPublishedIssue?:int`, `latestPublishedIssueComplete:bool`, `isOneShot:bool`, `description?`, `publishedDate?`, `publisher?`, `coverImage?`, `coverUrl?`
- Relations : `authors:M2M→Author`, `tomes:O2M→Tome(cascade,orphanRemoval)`
- Méthodes : `isWishlist()`, `getCurrentIssue()`, `getLastBought()`, `getLastDownloaded()`, `getMissingTomesNumbers()`, `isCurrentIssueComplete()`

**Tome** : `number:int`, `bought:bool`, `downloaded:bool`, `onNas:bool`, `isbn?`, `title?` — Relation : `comicSeries:M2O→ComicSeries`

**Author** : `name:string(unique)` — Relation : `comicSeries:M2M→ComicSeries`

**User** : `email:string(unique)`, `password`, `roles:array`

### Enums

**ComicStatus** : `BUYING`, `FINISHED`, `STOPPED`, `WISHLIST`
**ComicType** : `BD`, `COMICS`, `LIVRE`, `MANGA`

### Services

**ComicSeriesMapper** : `mapToEntity(ComicSeriesInput, ?ComicSeries): ComicSeries`, `mapToInput(ComicSeries): ComicSeriesInput`

**CoverRemoverInterface** : `remove(ComicSeries): void` — Impl : `VichCoverRemover`

**IsbnLookupService** : `lookup(isbn, ?type): ?array`, `lookupByTitle(title, ?type): ?array`
- APIs : Google Books, Open Library, AniList (mangas)
- Retour : `[title, authors, description, publishedDate, publisher, isbn, thumbnail, isOneShot, sources]`

### DTOs

**ComicSeriesInput** : `title`, `status`, `type`, `latestPublishedIssue?`, `latestPublishedIssueComplete`, `isOneShot`, `isWishlist`, `description?`, `publishedDate?`, `publisher?`, `coverUrl?`, `coverImage?`, `deleteCover`, `coverFile?`, `tomes:list<TomeInput>`, `authors:list<AuthorInput>`

**TomeInput** : `number`, `bought`, `downloaded`, `onNas`, `isbn?`, `title?`

**AuthorInput** : `name`

**ComicFilters** : `nas?`, `q?`, `sort`, `status?`, `type?` — Utilisé avec `#[MapQueryString]`

### Routes

| Route | Contrôleur |
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

**ComicSeriesRepository** : `findWithFilters()`, `search()`, `findAllForApi()`
**AuthorRepository** : `findOrCreate()`, `findOrCreateMultiple()`

### Commandes console

- `app:create-user <email> <password>`
- `app:import-excel <file> [--dry-run]`

### Intégrations

VichUploaderBundle (covers), PWA (`/offline`, `/api/comics`), APIs (Google Books, Open Library, AniList)

## Déploiement

```bash
docker compose -f docker-compose.prod.yml up --build -d
```

## Gotchas

- **Google Books** : peut retourner données partielles, vérifier `title`
- **VichUploader** : supprimer `coverImage` ne supprime pas le fichier physique
- **Tomes** : `orphanRemoval=true`, attention aux manipulations directes
- **Cache Twig** : `cache:clear` parfois nécessaire en dev

## Maintenance

Mettre à jour "Architecture" après : nouvelle entité/enum/service/route/commande.
Explorer le code uniquement pour : implémentation interne, templates Twig, contrôleurs Stimulus.

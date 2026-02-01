# CLAUDE.md

Instructions pour Claude Code. Ces règles sont **obligatoires** et doivent être suivies à chaque intervention.

## Projet

Application Symfony de gestion de bibliothèque BD/Comics/Mangas avec mode PWA.

**Stack technique** : Symfony 7.4, PHP 8.3, MariaDB 10.11, DDEV, Doctrine ORM, Symfony UX.

## Maintenance de ce fichier

**Obligatoire** : ce fichier doit refléter l'état actuel du code. Mettre à jour la section "Architecture détaillée" après chaque modification structurelle :

| Type de modification | Action requise |
|---------------------|----------------|
| Nouvelle entité | Ajouter dans "Entités Doctrine" avec propriétés et relations |
| Modification entité | Mettre à jour propriétés/relations/méthodes concernées |
| Suppression entité | Retirer de la documentation |
| Nouvel enum | Ajouter dans "Enums" avec toutes les valeurs |
| Nouveau service | Ajouter dans "Services" avec rôle et méthodes publiques |
| Nouveau contrôleur/route | Ajouter dans "Contrôleurs et routes" |
| Nouvelle commande console | Ajouter dans "Commandes console" |
| Nouvelle intégration externe | Ajouter dans "Intégrations externes" |

**Ne pas documenter** : modifications mineures (renommage variable, refactoring interne, corrections de bugs sans changement d'API).

## Utilisation de ce fichier vs exploration du code

**Ce fichier est la source principale d'information.** La section "Architecture détaillée" documente les entités, services, routes et méthodes publiques. Utiliser ces informations directement sans explorer le codebase.

**Explorer le code uniquement si :**

| Besoin | Action |
|--------|--------|
| Signature d'une méthode documentée | Utiliser ce fichier |
| Implémentation interne d'une méthode | Lire le fichier source |
| Structure d'un template Twig | Lire le fichier template |
| Logique d'un contrôleur Stimulus | Lire `assets/controllers/` |
| Fichier créé récemment non documenté | Explorer puis mettre à jour ce fichier |

**Ne pas utiliser l'agent Explore** pour des informations déjà présentes ici.

## Commandes

**Toutes les commandes s'exécutent via DDEV** :

### Démarrage du projet

```bash
ddev start                                            # Démarrer les containers
ddev composer install                                 # Dépendances PHP
ddev exec bin/console doctrine:migrations:migrate -n  # Appliquer migrations
ddev launch                                           # Ouvrir dans le navigateur
```

### Commandes courantes

```bash
ddev composer install                                 # Dépendances
ddev exec bin/console doctrine:migrations:diff -n    # Générer migration
ddev exec bin/console doctrine:migrations:migrate -n # Appliquer migrations
ddev exec bin/console cache:clear                    # Vider cache
```

## Règles de code PHP

Ces règles sont **obligatoires** pour tout code PHP écrit ou modifié :

1. **`declare(strict_types=1);`** en haut de chaque fichier PHP
2. **Préfixer les fonctions natives** avec `\` : `\array_map()`, `\sprintf()`, `\count()`, `\trim()`, etc.
3. **Ordre des méthodes** : `__construct()` en premier, puis `public` → `protected` → `private`
4. **Arguments sur une ligne**, sauf pour les constructeurs avec promotion de propriétés (un paramètre par ligne)
5. **Tri alphabétique** :
   - Assignations dans le corps du constructeur
   - Clés des tableaux associatifs
   - Clés YAML à chaque niveau
6. **Documentation en français** : PHPDoc, commentaires inline
7. **Standards Symfony** : https://symfony.com/doc/current/contributing/code/standards.html

## Méthodologie TDD (Test-Driven Development)

**Obligatoire** : tout développement de fonctionnalité ou correction de bug suit le cycle TDD.

### Cycle Red-Green-Refactor

Pour chaque nouvelle fonctionnalité ou bug à corriger :

1. **RED** — Écrire le test en premier
   - Créer le fichier de test avant le code de production
   - Le test décrit le comportement attendu
   - Exécuter le test : il **doit échouer** (rouge)

2. **GREEN** — Écrire le minimum de code pour faire passer le test
   - Implémenter uniquement ce qui est nécessaire
   - Ne pas anticiper les besoins futurs
   - Exécuter le test : il **doit passer** (vert)

3. **REFACTOR** — Améliorer le code sans changer le comportement
   - Nettoyer, simplifier, éliminer les duplications
   - Les tests doivent rester verts après refactoring

### Application pratique

```bash
# 1. Créer/modifier le test
# tests/Service/MonServiceTest.php

# 2. Exécuter le test (doit échouer)
ddev exec bin/phpunit tests/Service/MonServiceTest.php

# 3. Implémenter le code
# src/Service/MonService.php

# 4. Exécuter le test (doit passer)
ddev exec bin/phpunit tests/Service/MonServiceTest.php

# 5. Refactoriser si nécessaire, relancer les tests
```

### Types de tests

- **Tests unitaires** (`tests/`) : services, entités, logique métier
- **Tests fonctionnels** (`tests/Controller/`) : contrôleurs, requêtes HTTP
- **Tests Behat** (`features/`) : scénarios utilisateur end-to-end
- **Tests Playwright** (`tests/playwright/`) : tests E2E navigateur

### Environnement de test (OBLIGATOIRE)

**Tous les tests doivent utiliser l'environnement de test dédié**, jamais l'environnement de développement :

| Type de test | Base de données | Hostname |
|--------------|-----------------|----------|
| PHPUnit / Panther | `db_test` (suffixe automatique Doctrine) | `https://test.bibliotheque.ddev.site` |
| Playwright | `db_test` | `https://test.bibliotheque.ddev.site` |

**Configuration obligatoire dans les tests :**

1. **Tests PHPUnit/Panther** : utiliser l'environnement `test` ou `panther` (configuré dans `.env.test`)
2. **Tests Playwright** : configurer `baseURL: 'https://test.bibliotheque.ddev.site'` dans `playwright.config.js`
3. **Tests avec client HTTP** : toujours cibler le hostname de test, jamais `bibliotheque.ddev.site`

**Pourquoi ?** La base `db_test` est isolée et peut être vidée/recréée sans affecter les données de développement. Le hostname `test.bibliotheque.ddev.site` pointe vers l'environnement Symfony `test`.

### Exceptions au TDD

Le TDD n'est **pas requis** pour :
- Modifications de templates Twig uniquement
- Fichiers de configuration (YAML, .env)
- Migrations Doctrine (générées automatiquement)

## Outils de qualité

**Après chaque modification de code PHP**, exécuter sur les fichiers modifiés :

```bash
# Corriger le style (obligatoire)
ddev exec vendor/bin/php-cs-fixer fix src/MonFichier.php

# Vérifier les types (obligatoire)
ddev exec vendor/bin/phpstan analyse src/MonFichier.php

# Lancer les tests si concernés
ddev exec bin/phpunit tests/MonTest.php
```

**Ne pas exécuter ces outils sur tout le projet**, uniquement sur les fichiers modifiés.

## Frontend & JavaScript

**Symfony UX est obligatoire.** Avant d'écrire du JavaScript custom :

1. Vérifier si un package Symfony UX existe : https://ux.symfony.com/packages
2. Installer le package UX si disponible : `ddev composer require symfony/ux-xxx`
3. Créer un contrôleur Stimulus custom **uniquement si** aucun package UX ne couvre le besoin

**Packages UX courants** :
- Autocomplétion/tags : `symfony/ux-autocomplete`
- Composants dynamiques : `symfony/ux-live-component`
- Charts : `symfony/ux-chartjs`
- Upload : `symfony/ux-dropzone`

## Git

**Format des commits** (Conventional Commits) :

```
<type>(scope): description

Corps optionnel
```

**Types** : `feat`, `fix`, `chore`, `refactor`, `docs`

**Exemple** : `feat(isbn): add ISBN lookup via Google Books API`

**Ne pas inclure** de trailer `Co-Authored-By`.

## Changelog

**Mettre à jour `CHANGELOG.md` après chaque modification** :

1. Ajouter l'entrée dans `## [Unreleased]` sous la bonne catégorie :
   - `### Added` : nouvelles fonctionnalités
   - `### Changed` : modifications
   - `### Fixed` : corrections de bugs
   - `### Removed` : suppressions

2. Format : `- **Nom court** : Description`

## Structure du projet

```
src/
├── Command/          # Commandes console
├── Controller/       # Contrôleurs HTTP
├── Entity/           # Entités Doctrine
├── Enum/             # Enums PHP
├── Form/             # Types de formulaire
├── Repository/       # Repositories Doctrine
└── Service/          # Services métier
templates/            # Templates Twig
assets/controllers/   # Contrôleurs Stimulus
tests/                # Tests PHPUnit
features/             # Tests Behat (Gherkin)
```

## Architecture détaillée

Cette section documente le code existant pour éviter les explorations répétitives.

### Entités Doctrine

#### ComicSeries (`src/Entity/ComicSeries.php`)
Entité principale représentant une série BD/Comics/Manga/Livre.

| Propriété | Type | Description |
|-----------|------|-------------|
| `title` | string(255) | Titre de la série |
| `status` | ComicStatus | Statut (BUYING, FINISHED, STOPPED, WISHLIST) |
| `type` | ComicType | Type (BD, COMICS, LIVRE, MANGA) |
| `latestPublishedIssue` | int\|null | Dernier numéro paru |
| `latestPublishedIssueComplete` | bool | Série terminée par l'éditeur |
| `isOneShot` | bool | One-shot (volume unique) |
| `isWishlist` | bool | Dans la liste de souhaits |
| `description` | text\|null | Description |
| `publishedDate` | string\|null | Date de publication |
| `publisher` | string\|null | Éditeur |
| `coverImage` | string\|null | Fichier image uploadé (VichUploader) |
| `coverUrl` | string\|null | URL de couverture externe |

**Relations :**
- `authors` : ManyToMany → Author
- `tomes` : OneToMany → Tome (cascade persist/remove, orphanRemoval)

**Méthodes utiles :**
- `getCurrentIssue()` : numéro max possédé
- `getLastBought()` : dernier tome acheté
- `getLastDownloaded()` : dernier tome téléchargé
- `getMissingTomesNumbers()` : tomes manquants
- `isCurrentIssueComplete()` : série complète ?

#### Tome (`src/Entity/Tome.php`)
Volume individuel d'une série.

| Propriété | Type | Description |
|-----------|------|-------------|
| `number` | int | Numéro du tome (≥ 0) |
| `bought` | bool | Acheté |
| `downloaded` | bool | Téléchargé |
| `onNas` | bool | Sur le NAS |
| `isbn` | string\|null | ISBN |
| `title` | string\|null | Titre spécifique du tome |

**Relation :** `comicSeries` : ManyToOne → ComicSeries

#### Author (`src/Entity/Author.php`)
Auteur (scénariste, dessinateur, mangaka).

| Propriété | Type | Description |
|-----------|------|-------------|
| `name` | string(255) | Nom (unique) |

**Relation :** `comicSeries` : ManyToMany → ComicSeries

#### User (`src/Entity/User.php`)
Utilisateur pour l'authentification.

| Propriété | Type | Description |
|-----------|------|-------------|
| `email` | string(180) | Email (unique, identifiant) |
| `password` | string | Mot de passe hashé |
| `roles` | array | Rôles (ROLE_USER inclus par défaut) |

### Enums

#### ComicStatus (`src/Enum/ComicStatus.php`)
```php
BUYING = 'buying'      // "En cours d'achat"
FINISHED = 'finished'  // "Terminée"
STOPPED = 'stopped'    // "Arrêtée"
WISHLIST = 'wishlist'  // "Liste de souhaits"
```

#### ComicType (`src/Enum/ComicType.php`)
```php
BD = 'bd'
COMICS = 'comics'
LIVRE = 'livre'
MANGA = 'manga'
```

### Services

#### IsbnLookupService (`src/Service/IsbnLookupService.php`)
Recherche d'informations via APIs externes.

**APIs utilisées :**
- Google Books (ISBN + titre)
- Open Library (ISBN, enrichissement auteur/éditeur)
- AniList (GraphQL, mangas uniquement, détection one-shot)

**Méthodes publiques :**
- `lookup(string $isbn, ?string $type): ?array` — recherche par ISBN
- `lookupByTitle(string $title, ?string $type): ?array` — recherche par titre

**Retour :** `['title', 'authors', 'description', 'publishedDate', 'publisher', 'isbn', 'thumbnail', 'isOneShot', 'sources']`

### Contrôleurs et routes

| Route | Méthode | Contrôleur | Description |
|-------|---------|------------|-------------|
| `/` | GET | HomeController::index | Liste bibliothèque (filtres: type, status, nas, q, sort) |
| `/comic/{id}` | GET | ComicController::show | Détail série |
| `/comic/new` | GET/POST | ComicController::new | Création série |
| `/comic/{id}/edit` | GET/POST | ComicController::edit | Édition série |
| `/comic/{id}/delete` | POST | ComicController::delete | Suppression (CSRF) |
| `/comic/{id}/to-library` | POST | ComicController::toLibrary | Wishlist → Bibliothèque |
| `/wishlist` | GET | WishlistController::index | Liste de souhaits |
| `/search` | GET | SearchController::index | Recherche (param: q) |
| `/login` | GET | SecurityController::login | Connexion |
| `/logout` | GET | SecurityController::logout | Déconnexion |
| `/offline` | GET | OfflineController | Page offline PWA |
| `/api/comics` | GET | ApiController::comics | JSON toutes les séries |
| `/api/isbn-lookup` | GET | ApiController::isbnLookup | Recherche ISBN (params: isbn, type) |
| `/api/title-lookup` | GET | ApiController::titleLookup | Recherche titre (params: title, type) |

### Repositories

#### ComicSeriesRepository
- `findWithFilters(array $filters)` : filtrage avancé (isWishlist, type, status, onNas, search, sort)
- `search(string $query)` : recherche titre ou ISBN tome
- `findAllForApi()` : données sérialisées pour API/PWA

#### AuthorRepository
- `findOrCreate(string $name)` : trouve ou crée un auteur
- `findOrCreateMultiple(array $names)` : batch création

### Commandes console

| Commande | Usage |
|----------|-------|
| `app:create-user` | `ddev exec bin/console app:create-user <email> <password>` |
| `app:import-excel` | `ddev exec bin/console app:import-excel <file> [--dry-run]` |

### Intégrations externes

- **VichUploaderBundle** : upload des couvertures
- **PWA** : mode offline via `/offline` et `/api/comics`
- **APIs** : Google Books, Open Library, AniList (GraphQL)

## Déploiement

```bash
docker compose -f docker-compose.prod.yml up --build -d
```

## Gotchas

- **ISBN invalide** : l'API Google Books peut retourner des données partielles, toujours vérifier le champ `title`
- **VichUploader** : supprimer `coverImage` ne supprime pas le fichier physique automatiquement
- **Tomes orphelins** : `orphanRemoval=true` sur ComicSeries, attention lors de manipulations directes
- **Cache Twig** : après modification de templates, `ddev exec bin/console cache:clear` peut être nécessaire en dev

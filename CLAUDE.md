# CLAUDE.md

Instructions pour Claude Code. Ces règles sont **obligatoires** et doivent être suivies à chaque intervention.

## Projet

Application Symfony de gestion de bibliothèque BD/Comics/Mangas avec mode PWA.

**Stack technique** : Symfony 7.4, PHP 8.3, MariaDB 10.11, DDEV, Doctrine ORM, Symfony UX.

## Principe fondamental : ne pas réinventer la roue

**Obligatoire avant toute implémentation** : vérifier si une solution existante, éprouvée et maintenue, répond au besoin.

### Ordre de recherche

1. **Composant natif du framework** — Symfony, Doctrine, ou l'écosystème utilisé
2. **Bundle/package officiel** — extensions maintenues par l'équipe du framework
3. **Librairie tierce populaire** — solutions communautaires largement adoptées
4. **Implémentation custom** — uniquement si aucune solution existante ne convient

### Critères de sélection d'une librairie tierce

| Critère | Exigence |
|---------|----------|
| Maintenance | Commits récents (< 6 mois), issues traitées |
| Popularité | Nombre d'étoiles/téléchargements significatif |
| Compatibilité | Compatible avec la stack actuelle (PHP 8.3, Symfony 7.x) |
| Documentation | Documentation claire et à jour |
| Licence | Licence compatible (MIT, Apache, BSD) |

### Ressources de recherche

| Technologie | Où chercher |
|-------------|-------------|
| PHP/Symfony | Packagist, Symfony Flex recipes, symfony.com/bundles |
| JavaScript | npm, Symfony UX (https://ux.symfony.com) |
| CSS | npm, CDN populaires |
| Général | GitHub, documentation officielle du framework |

### Application

- **Ne jamais implémenter** une fonctionnalité standard (authentification, upload, pagination, validation, etc.) sans avoir vérifié les solutions existantes
- **Documenter le choix** : si une librairie est écartée, noter pourquoi dans le commit ou le PR
- **Préférer la simplicité** : une librairie légère et ciblée vaut mieux qu'une usine à gaz

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

## TDD : Mode de développement obligatoire

**Le TDD est le mode de fonctionnement par défaut.** Chaque développement commence par un test.

### Règle absolue : Tests = Source de vérité

Les tests définissent le comportement attendu du code. Cette règle a deux implications :

1. **Nouvelle fonctionnalité** → Écrire le test EN PREMIER, puis le code
2. **Modification de code existant** → Mettre à jour les tests AVANT ou EN MÊME TEMPS que le code

**INTERDIT** : modifier du code de production sans vérifier/adapter les tests correspondants.

### Workflow obligatoire

Pour chaque tâche impliquant du code PHP :

```
1. IDENTIFIER les tests concernés
   → Si nouvelle fonctionnalité : créer le fichier de test
   → Si modification : localiser les tests existants

2. ÉCRIRE/MODIFIER le test d'abord
   → Le test décrit le comportement attendu APRÈS la modification
   → Exécuter : ddev exec bin/phpunit tests/MonTest.php
   → Le test DOIT échouer (sinon le test est inutile ou mal écrit)

3. IMPLÉMENTER le code
   → Écrire uniquement ce qui est nécessaire pour faire passer le test
   → Pas d'anticipation, pas de sur-ingénierie

4. VALIDER
   → Exécuter le test : il DOIT passer
   → Si échec : corriger le code, pas le test (sauf erreur dans le test)

5. REFACTORISER si nécessaire
   → Améliorer le code sans changer le comportement
   → Les tests doivent rester verts
```

### Synchronisation code/tests

| Action sur le code | Action sur les tests |
|--------------------|----------------------|
| Ajouter une méthode publique | Ajouter un test pour cette méthode |
| Modifier le comportement d'une méthode | Adapter le test existant |
| Supprimer une méthode | Supprimer le test correspondant |
| Corriger un bug | Ajouter un test qui reproduit le bug, puis corriger |
| Refactoring interne (même comportement) | Les tests existants doivent passer sans modification |

### Emplacement des tests

| Type de code | Fichier de test | Commande |
|--------------|-----------------|----------|
| Service (`src/Service/Foo.php`) | `tests/Service/FooTest.php` | `ddev exec bin/phpunit tests/Service/FooTest.php` |
| Entité (`src/Entity/Bar.php`) | `tests/Entity/BarTest.php` | `ddev exec bin/phpunit tests/Entity/BarTest.php` |
| Contrôleur (`src/Controller/BazController.php`) | `tests/Controller/BazControllerTest.php` | `ddev exec bin/phpunit tests/Controller/BazControllerTest.php` |
| Commande (`src/Command/QuxCommand.php`) | `tests/Command/QuxCommandTest.php` | `ddev exec bin/phpunit tests/Command/QuxCommandTest.php` |
| Scénario utilisateur complet | `features/*.feature` | `ddev exec vendor/bin/behat features/mon.feature` |
| Test E2E navigateur | `tests/playwright/*.spec.js` | `npx playwright test tests/playwright/mon.spec.js` |

### Environnement de test

**Tous les tests utilisent l'environnement isolé** :

| Paramètre | Valeur |
|-----------|--------|
| Base de données | `db_test` (suffixe automatique Doctrine) |
| Hostname | `https://test.bibliotheque.ddev.site` |
| Configuration | `.env.test` |

**Ne jamais** utiliser `bibliotheque.ddev.site` (environnement dev) dans les tests.

### Exceptions au TDD

Le TDD n'est **pas requis** pour :
- Templates Twig (pas de logique métier)
- Fichiers de configuration (YAML, .env)
- Migrations Doctrine (générées automatiquement)
- Assets statiques (CSS, images)

**Attention** : si un template Twig contient de la logique complexe, extraire cette logique dans un service et tester ce service.

## Outils de qualité

**Après chaque modification de code PHP**, exécuter dans cet ordre :

```bash
# 1. TESTS (obligatoire) — valider le comportement
ddev exec bin/phpunit tests/CheminVersTest.php

# 2. STYLE (obligatoire) — corriger le formatage
ddev exec vendor/bin/php-cs-fixer fix src/MonFichier.php

# 3. TYPES (obligatoire) — vérifier l'analyse statique
ddev exec vendor/bin/phpstan analyse src/MonFichier.php
```

**Ordre important** : les tests en premier permettent de détecter les régressions avant de formatter le code.

**Cibler uniquement les fichiers modifiés**, pas tout le projet.

## Frontend & JavaScript

Appliquer le principe "ne pas réinventer la roue" (voir section dédiée).

**Priorité pour JavaScript** :
1. Package Symfony UX : https://ux.symfony.com/packages
2. Librairie npm éprouvée
3. Contrôleur Stimulus custom (dernier recours)

**Packages UX installés ou à privilégier** :
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

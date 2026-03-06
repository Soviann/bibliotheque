# Guide développeur

## Prérequis

- **DDEV** >= 1.24 (inclut PHP, MariaDB, nginx, Node.js)
- **Git**
- Ou bien : PHP 8.3+, MariaDB 10.11+, Composer 2, Node.js 20+, nginx (voir les guides de déploiement [NAS](guide-deploiement-nas.md) / [OVH](guide-deploiement-ovh.md))

---

## Structure du projet

Le projet est un **monorepo** avec deux sous-projets :

```
bibliotheque/
├── backend/                 # Symfony 7.4 + API Platform 4
│   ├── config/              # Configuration Symfony (packages, routes, services)
│   ├── migrations/          # Migrations Doctrine
│   ├── public/              # Point d'entrée web (index.php)
│   ├── src/
│   │   ├── Command/         # Commandes console
│   │   ├── Controller/      # Contrôleurs API (lookup, import, purge, merge, batch-lookup)
│   │   ├── DataFixtures/    # Fixtures de test
│   │   ├── Doctrine/Filter/ # Filtre soft-delete
│   │   ├── DTO/             # Data Transfer Objects
│   │   ├── Entity/          # Entités Doctrine + attributs API Platform
│   │   ├── Enum/            # Enums PHP (ComicStatus, ComicType, ApiLookupStatus)
│   │   ├── Event/           # Domain events
│   │   ├── EventListener/   # Doctrine listeners, JWT listeners
│   │   ├── Repository/      # Repositories Doctrine
│   │   ├── Service/         # Services métier
│   │   │   ├── Import/      # Import Excel (suivi + livres)
│   │   │   ├── Lookup/      # Providers de recherche (ISBN, titre)
│   │   │   └── Merge/       # Fusion de séries via Gemini AI
│   │   └── State/           # Processeurs API Platform
│   ├── tests/               # Tests PHPUnit
│   ├── composer.json
│   └── phpunit.dist.xml
├── frontend/                # React 19 + TypeScript + Vite
│   ├── public/              # Assets statiques (icônes PWA)
│   ├── src/
│   │   ├── __tests__/       # Tests Vitest
│   │   ├── components/      # Composants React réutilisables
│   │   ├── hooks/           # Hooks custom (API, auth)
│   │   ├── pages/           # Pages de l'application
│   │   ├── services/        # Service API (apiFetch, JWT), offline queue, sync handler
│   │   ├── types/           # Types TypeScript et enums
│   │   └── utils/           # Utilitaires (recherche fuzzy, tri)
│   ├── index.html
│   ├── package.json
│   ├── vite.config.ts
│   └── vitest.config.ts
├── .ddev/                   # Configuration DDEV
├── docs/                    # Documentation
├── Makefile                 # Commandes raccourcies
├── CLAUDE.md                # Instructions pour Claude Code
└── CHANGELOG.md
```

---

## Installation avec DDEV

```bash
# Cloner le dépôt
git clone git@github.com:Soviann/bibliotheque.git
cd bibliotheque

# Démarrer DDEV et installer les dépendances
ddev start
ddev exec make dev
```

L'application est accessible à :
- **Frontend (dev)** : `https://bibliotheque.ddev.site:5173`
- **API docs** : `https://bibliotheque.ddev.site/api/docs`

> **Note** : pas de création d'utilisateur manuelle. Le premier login Google crée le compte automatiquement.

---

## Commandes Makefile

Toutes les commandes s'exécutent via `ddev exec make <cible>` :

| Commande | Description |
|----------|-------------|
| `make dev` | Installation complète (dépendances + JWT + migrations) |
| `make install` | Installer les dépendances backend + frontend |
| `make test` | Lancer tous les tests (PHPUnit + Vitest) |
| `make test-back` | Tests PHPUnit uniquement |
| `make test-front` | Tests Vitest uniquement |
| `make lint` | Vérifier la qualité (PHPStan + CS Fixer + TypeScript) |
| `make build` | Build de production du frontend |
| `make cc` | Vider le cache Symfony |
| `make sf CMD="..."` | Exécuter une commande Symfony console |
| `make db-diff` | Générer une migration Doctrine |
| `make db-migrate` | Exécuter les migrations |
| `make db-reset` | Recréer la base et rejouer les migrations |
| `make db-seed` | Charger les fixtures de test |
| `make jwt` | Générer les clés JWT |
| `make cs` | Corriger le style PHP |
| `make rector` | Appliquer les refactorings Rector |

---

## Architecture backend

### Stack technique

| Composant | Version | Usage |
|-----------|---------|-------|
| PHP | 8.3+ | Langage |
| Symfony | 7.4 | Framework |
| API Platform | 4 | Génération de l'API REST (JSON-LD) |
| Doctrine ORM | 3 | Persistance |
| LexikJWTAuthenticationBundle | 3 | Authentification JWT |
| MariaDB | 10.11 | Base de données |
| VichUploaderBundle | 2 | Upload de fichiers (couvertures) |
| LiipImagineBundle | 2 | Redimensionnement d'images |
| NelmioCorsBundle | 2 | Gestion des CORS |

### Entités

| Entité | Description |
|--------|-------------|
| `ComicSeries` | Série (BD, manga, comics…). Ressource API principale. |
| `Tome` | Tome d'une série. Sous-ressource de ComicSeries. |
| `Author` | Auteur, lié à ComicSeries via ManyToMany. |
| `User` | Utilisateur de l'application. |

### Enums

| Enum | Valeurs |
|------|---------|
| `ComicStatus` | `buying`, `finished`, `stopped`, `wishlist` |
| `ComicType` | `bd`, `comics`, `livre`, `manga` |

### Endpoints API

**Authentification :**

| Méthode | URL | Description |
|---------|-----|-------------|
| POST | `/api/login/google` | Login Google OAuth (retourne un token JWT) |

**Séries :**

| Méthode | URL | Description |
|---------|-----|-------------|
| GET | `/api/comic_series` | Liste des séries (filtres : status, type, title, isOneShot) |
| GET | `/api/comic_series/{id}` | Détail d'une série |
| POST | `/api/comic_series` | Créer une série |
| PUT | `/api/comic_series/{id}` | Modifier une série |
| DELETE | `/api/comic_series/{id}` | Supprimer une série (soft delete) |
| PUT | `/api/comic_series/{id}/restore` | Restaurer une série supprimée |
| DELETE | `/api/trash/{id}/permanent` | Suppression définitive |

**Tomes (sous-ressource de ComicSeries) :**

| Méthode | URL | Description |
|---------|-----|-------------|
| GET | `/api/comic_series/{id}/tomes` | Liste des tomes d'une série |
| POST | `/api/comic_series/{id}/tomes` | Ajouter un tome |
| PUT | `/api/tomes/{id}` | Modifier un tome |
| DELETE | `/api/tomes/{id}` | Supprimer un tome |

**Auteurs :**

| Méthode | URL | Description |
|---------|-----|-------------|
| GET | `/api/authors` | Liste des auteurs (filtre : name) |
| GET | `/api/authors/{id}` | Détail d'un auteur |
| POST | `/api/authors` | Créer un auteur |

**Lookup (recherche externe) :**

| Méthode | URL | Description |
|---------|-----|-------------|
| GET | `/api/lookup/isbn?isbn=...&type=...` | Recherche par ISBN |
| GET | `/api/lookup/title?title=...&type=...` | Recherche par titre |

**Outils (JWT protégés) :**

| Méthode | URL | Description |
|---------|-----|-------------|
| GET | `/api/tools/batch-lookup/preview?type=...&force=...` | Prévisualiser le batch lookup |
| POST | `/api/tools/batch-lookup/run` | Lancer le batch lookup (SSE streaming) |
| POST | `/api/tools/import/books` | Importer des livres (multipart file) |
| POST | `/api/tools/import/excel` | Importer le suivi Excel (multipart file) |
| GET | `/api/tools/purge/preview?days=30` | Prévisualiser les séries à purger |
| POST | `/api/tools/purge/execute` | Exécuter la purge (`{seriesIds}`) |
| POST | `/api/merge-series/detect` | Détecter les séries à fusionner |
| POST | `/api/merge-series/preview` | Aperçu de fusion (`{seriesIds}`) |
| POST | `/api/merge-series/execute` | Exécuter la fusion |

Les endpoints lookup sont protégés par rate limiting (30 requêtes/min par IP). En cas de dépassement, l'API renvoie un code `429 Too Many Requests`.

Tous les endpoints (sauf `/api/login/google` et `/api/docs`) nécessitent un header `Authorization: Bearer <token>`.

### Processeurs d'état (State Processors)

API Platform utilise des processeurs custom pour certaines opérations :

| Processeur | Opération |
|------------|-----------|
| `ComicSeriesDeleteProcessor` | Soft delete (met à jour `deletedAt`) |
| `ComicSeriesRestoreProcessor` | Restauration (remet `deletedAt` à null) |
| `ComicSeriesPermanentDeleteProcessor` | Suppression définitive via DBAL |

### Service de lookup

Le `LookupOrchestrator` interroge en parallèle plusieurs providers pour trouver les informations d'une série (via le multiplexage natif de Symfony HttpClient). Chaque provider a une priorité par champ :

| Provider | Sources | Spécialité |
|----------|---------|------------|
| GoogleBooksLookup | Google Books API | ISBN, couverture |
| BnfLookup | Bibliothèque nationale de France | ISBN, métadonnées françaises |
| OpenLibraryLookup | OpenLibrary.org | ISBN, couverture alternative |
| AniListLookup | AniList GraphQL | Manga, couverture, one-shot |
| WikipediaLookup | Wikipedia API | Description |
| GeminiLookup | Google Gemini AI | Informations complémentaires |
| BedethequeLookup | Bedetheque.com via Gemini | BD (référence francophone), métadonnées séries |

### Commandes console

| Commande | Description |
|----------|-------------|
| `app:import-books <fichier> [--dry-run]` | Importer des livres depuis un fichier Excel (Livres.xlsx) |
| `app:import-excel <fichier> [--dry-run]` | Importer une collection depuis un fichier Excel de suivi |
| `app:invalidate-tokens [--email=...]` | Invalider les tokens JWT (tous ou par utilisateur) |
| `app:lookup-missing [--type=...] [--limit=...] [--force]` | Rechercher les métadonnées manquantes des séries |
| `app:purge-deleted [--days=30] [--dry-run]` | Supprimer définitivement les séries dans la corbeille |

---

## Architecture frontend

### Stack technique

| Composant | Version | Usage |
|-----------|---------|-------|
| React | 19 | UI library |
| TypeScript | 5.9 | Typage statique |
| Vite | 7 | Bundler / dev server |
| TanStack Query | 5 | Gestion du state serveur (requêtes API, cache, mutations) |
| React Router | 7 | Routing SPA |
| Tailwind CSS | 4 | Styling utility-first |
| Headless UI | 2 | Composants accessibles (Dialog, Combobox) |
| Lucide React | - | Icônes |
| Sonner | 2 | Notifications toast |
| @react-oauth/google | 1 | Authentification Google OAuth |
| Fuse.js | 7 | Recherche fuzzy client-side |
| vite-plugin-pwa | 1 | Service worker et manifest PWA |
| html5-qrcode | 2 | Scanner de codes-barres |

### Patterns

**Service API (`services/api.ts`)** :
- `apiFetch<T>(path, options)` — Fonction générique qui gère les headers JWT, Content-Type, erreurs 401
- Le token JWT est stocké dans `localStorage`
- En cas de 401, le token est supprimé et l'utilisateur est redirigé vers `/login`

**Hooks** :
- Chaque interaction API a son propre hook (`useComics`, `useComic`, `useCreateComic`, etc.)
- Les hooks de lecture utilisent `useQuery` (TanStack Query)
- Les hooks de mutation utilisent `useMutation` et invalident les query keys pertinentes au succès

**Routing** :
- Routes définies dans `App.tsx`
- Pages chargées en lazy loading (`React.lazy()`) sauf Home
- `AuthGuard` protège toutes les routes sauf `/login`

**Composants** :

| Composant | Description |
|-----------|-------------|
| `Layout` | Shell de l'app (header, navigation, Outlet) |
| `AuthGuard` | Redirige vers /login si non authentifié |
| `ComicCard` | Carte d'une série (couverture, titre, auteur, badges) |
| `Filters` | Menus déroulants de filtrage (type, statut) |
| `ConfirmModal` | Modal de confirmation (Headless UI Dialog) |
| `ErrorFallback` | Fallback pour ErrorBoundary |
| `BarcodeScanner` | Scanner de codes-barres (html5-qrcode) |

---

## Tests

### Backend (PHPUnit)

```bash
ddev exec make test-back                           # Tous les tests
ddev exec "cd backend && vendor/bin/phpunit tests/Service/Lookup/GoogleBooksLookupTest.php"  # Un fichier
```

Convention : `backend/src/X/Foo.php` → `backend/tests/{Unit,Integration,Functional}/X/FooTest.php`

Le bundle `DAMA\DoctrineTestBundle` est utilisé pour isoler chaque test dans une transaction (rollback automatique).

### Frontend (Vitest)

```bash
ddev exec make test-front                          # Tous les tests
ddev exec "cd frontend && npx vitest run src/__tests__/services/api.test.ts"  # Un fichier
ddev exec "cd frontend && npx vitest"              # Mode watch
```

Convention : `frontend/src/X/Foo.tsx` → `frontend/src/__tests__/{unit,integration}/X/Foo.test.tsx`

Helpers :
- `test-utils.tsx` — `renderWithProviders()` (QueryClient + MemoryRouter)
- `test-setup.ts` — Stubs pour ResizeObserver et matchMedia

---

## Qualité de code

```bash
ddev exec make lint          # PHPStan + CS Fixer dry-run + TypeScript
ddev exec make cs            # Corriger le style PHP
ddev exec make phpstan       # Analyse statique PHP seule
ddev exec make lint-front    # TypeScript seul (tsc --noEmit)
```

### Standards PHP

- `declare(strict_types=1)` obligatoire
- Fonctions natives préfixées : `\array_map()`, `\sprintf()`
- Ordre des méthodes : `__construct()` → `public` → `protected` → `private`
- Tri alphabétique : assignments du constructeur, clés de tableau, clés YAML
- Documentation en français

### Standards TypeScript

- Strict mode activé
- Pas de `any` implicite
- Props typées avec des interfaces

---

## Workflow Git

### Branches

- `main` = toujours stable et déployable
- Branches de travail : `<type>/<N>-<description>` (ex : `feat/42-barcode-scanner`)
- Types : `feat`, `fix`, `chore`, `refactor`, `docs`

### Commits

Format : `<type>(scope): description en français`

Exemples :
- `feat(api): expose ComicSeries via API Platform`
- `fix(auth): corrige le login JWT avec email`

### Pull requests

- Branches non triviales → PR + squash merge
- Lier à une issue : `fixes #N` dans le corps de la PR
- Mettre à jour le CHANGELOG avant merge

---

## Variables d'environnement

| Variable | Description | Défaut |
|----------|-------------|--------|
| `APP_ENV` | Environnement (`dev`, `prod`, `test`) | `dev` |
| `APP_SECRET` | Secret Symfony (vault Secrets en prod) | À définir dans `.env.local` |
| `DATABASE_URL` | URL de connexion MariaDB | `mysql://db:db@db:3306/db` |
| `JWT_SECRET_KEY` | Chemin vers la clé privée JWT | `config/jwt/private.pem` |
| `JWT_PUBLIC_KEY` | Chemin vers la clé publique JWT | `config/jwt/public.pem` |
| `JWT_PASSPHRASE` | Passphrase des clés JWT (vault Secrets en prod) | À définir dans `.env.local` |
| `GEMINI_API_KEY` | Clé API Google Gemini unique (optionnel) | vide |
| `GEMINI_API_KEYS` | Clés API Gemini multiples, séparées par virgule (rotation sur 429) | vide |
| `GEMINI_MODELS` | Modèles Gemini par ordre de priorité (dégradation progressive) | `gemini-2.5-flash,...` |
| `GOOGLE_BOOKS_API_KEY` | Clé API Google Books (optionnel) | vide |
| `OAUTH_GOOGLE_ID` | ID client OAuth Google | À définir dans `.env.local` |
| `OAUTH_ALLOWED_EMAIL` | Email Gmail autorisé pour le login | À définir dans `.env.local` |
| `CORS_ALLOW_ORIGIN` | Regex des origines CORS autorisées | `localhost` |

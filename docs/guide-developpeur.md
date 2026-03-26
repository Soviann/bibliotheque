# Guide développeur

## Prérequis

- **DDEV** >= 1.24 (inclut PHP, MariaDB, nginx, Node.js)
- **Git**
- Ou bien : PHP 8.3+, MariaDB 10.11+, Composer 2, Node.js 20+, nginx (voir les guides de déploiement [NAS](guide-deploiement-nas.md))

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
│   │   ├── Controller/      # Contrôleurs API (lookup, import, purge, merge, batch-lookup, notifications)
│   │   ├── DataFixtures/    # Fixtures de test
│   │   ├── Doctrine/Filter/ # Filtre soft-delete
│   │   ├── DTO/             # Data Transfer Objects
│   │   ├── Entity/          # Entités Doctrine + attributs API Platform
│   │   ├── Enum/            # Enums PHP
│   │   ├── Event/           # Domain events
│   │   ├── EventListener/   # Doctrine listeners, JWT listeners
│   │   ├── Message/         # Messages Messenger (async)
│   │   ├── MessageHandler/  # Handlers des messages async
│   │   ├── Repository/      # Repositories Doctrine
│   │   ├── Service/         # Services métier
│   │   │   ├── ComicSeries/ # Logique métier séries
│   │   │   ├── Cover/       # Upload et téléchargement de couvertures
│   │   │   ├── Enrichment/  # Pipeline d'enrichissement automatique
│   │   │   ├── Import/      # Import Excel (suivi + livres)
│   │   │   ├── Lookup/      # Providers de recherche (ISBN, titre)
│   │   │   │   ├── Contract/  # Interfaces
│   │   │   │   ├── Gemini/    # Client et services Gemini
│   │   │   │   ├── Provider/  # Implémentations des providers
│   │   │   │   └── Util/      # Utilitaires lookup
│   │   │   ├── Merge/       # Fusion de séries via Gemini AI
│   │   │   ├── Nas/         # Scan NAS (détection fichiers)
│   │   │   ├── Notification/ # Notifications in-app et push
│   │   │   └── Recommendation/ # Suggestions IA et détection tomes/auteurs
│   │   └── State/           # Processeurs API Platform
│   ├── tests/               # Tests PHPUnit
│   ├── composer.json
│   └── phpunit.dist.xml
├── frontend/                # React 19 + TypeScript + Vite
│   ├── public/              # Assets statiques (icônes PWA, screenshots)
│   ├── src/
│   │   ├── __tests__/       # Tests Vitest
│   │   ├── components/      # Composants React réutilisables
│   │   ├── hooks/           # Hooks custom (API, auth, notifications, offline)
│   │   ├── pages/           # Pages de l'application
│   │   ├── services/        # Service API (apiFetch, JWT), offline queue, sync handler
│   │   ├── types/           # Types TypeScript et enums
│   │   └── utils/           # Utilitaires (recherche fuzzy, tri, enrichment)
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
| `make coverage` | Rapport de couverture PHPUnit (pcov) |

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
| LiipImagineBundle | 2 | Redimensionnement d'images (miniatures 300x450) |
| NelmioCorsBundle | 2 | Gestion des CORS |
| minishlink/web-push | 9 | Notifications push (VAPID) |
| intervention/image | 3 | Traitement d'images (couvertures) |

### Entités

| Entité | Description |
|--------|-------------|
| `ComicSeries` | Série (BD, manga, comics…). Ressource API principale. |
| `Tome` | Tome d'une série. Sous-ressource de ComicSeries. |
| `Author` | Auteur, lié à ComicSeries via ManyToMany. |
| `User` | Utilisateur de l'application. |
| `EnrichmentProposal` | Proposition d'enrichissement (PENDING, PRE_ACCEPTED, ACCEPTED, REJECTED, SKIPPED). Sert d'historique. |
| `Notification` | Notification in-app (tomes manquants, nouvelles parutions, etc.). |
| `NotificationPreference` | Préférence de canal par type de notification et par utilisateur. |
| `PushSubscription` | Abonnement push d'un navigateur (endpoint, clés VAPID). |
| `SeriesSuggestion` | Suggestion IA de série similaire. |

### Enums

| Enum | Valeurs |
|------|---------|
| `ComicStatus` | `buying`, `finished`, `stopped`, `wishlist` |
| `ComicType` | `bd`, `comics`, `livre`, `manga` |
| `LookupMode` | `isbn`, `title` |
| `EnrichmentConfidence` | `high`, `medium`, `low` |
| `EnrichableField` | `authors`, `coverUrl`, `description`, `editor`, `isOneShot`, `lastPublishedVolume`, `publicationDate`, `publicationFinished`, `title` |
| `ProposalStatus` | `pending`, `pre_accepted`, `accepted`, `rejected`, `skipped` |
| `NotificationType` | `missing_tomes`, `new_release`, `author_release` |
| `NotificationChannel` | `in_app`, `push` |
| `NotificationEntityType` | `comic_series`, `author` |
| `SuggestionStatus` | `pending`, `accepted`, `rejected` |
| `BatchLookupStatus` | `pending`, `skipped`, `enriched`, `error` |

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
| PATCH | `/api/comic_series/{id}` | Modifier une série |
| DELETE | `/api/comic_series/{id}` | Supprimer une série (soft delete) |
| PUT | `/api/comic_series/{id}/restore` | Restaurer une série supprimée |
| DELETE | `/api/trash/{id}/permanent` | Suppression définitive |

**Tomes (sous-ressource de ComicSeries) :**

| Méthode | URL | Description |
|---------|-----|-------------|
| GET | `/api/comic_series/{id}/tomes` | Liste des tomes d'une série |
| POST | `/api/comic_series/{id}/tomes` | Ajouter un tome |
| PATCH | `/api/tomes/{id}` | Modifier un tome |
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
| GET | `/api/lookup/covers?title=...&type=...` | Recherche de couvertures |

**Notifications :**

| Méthode | URL | Description |
|---------|-----|-------------|
| GET | `/api/notifications` | Liste des notifications (API Platform) |
| GET | `/api/notifications/unread-count` | Nombre de notifications non lues |
| PATCH | `/api/notifications/read-all` | Marquer toutes comme lues |
| PATCH | `/api/notifications/{id}` | Modifier une notification (marquer comme lue) |
| DELETE | `/api/notifications/{id}` | Supprimer une notification |

**Préférences de notifications :**

| Méthode | URL | Description |
|---------|-----|-------------|
| GET | `/api/notification_preferences` | Préférences du user connecté |
| PATCH | `/api/notification_preferences/{id}` | Modifier une préférence |

**Push subscriptions :**

| Méthode | URL | Description |
|---------|-----|-------------|
| POST | `/api/push_subscriptions` | Enregistrer un abonnement push |
| DELETE | `/api/push_subscriptions/{id}` | Supprimer un abonnement |

**Enrichissement :**

| Méthode | URL | Description |
|---------|-----|-------------|
| GET | `/api/enrichment_proposals` | Liste des propositions (filtres : status, field, confidence, source) |
| PATCH | `/api/enrichment_proposals/{id}` | Accepter/rejeter une proposition |
| GET | `/api/enrichment_logs` | Historique d'enrichissement (filtre : comicSeries) |

**Suggestions :**

| Méthode | URL | Description |
|---------|-----|-------------|
| GET | `/api/series_suggestions` | Liste des suggestions IA |
| PATCH | `/api/series_suggestions/{id}` | Accepter/rejeter une suggestion |

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
| POST | `/api/merge-series/suggest` | Suggestions de fusion via Gemini |
| POST | `/api/merge-series/preview` | Aperçu de fusion (`{seriesIds}`) |
| POST | `/api/merge-series/execute` | Exécuter la fusion |

Les endpoints sont protégés par rate limiting par IP. En cas de dépassement, l'API renvoie un code `429 Too Many Requests`.

| Endpoint | Limite |
|----------|--------|
| Lookup (ISBN/titre/covers) | 30 req/min |
| Import (books/excel) | 5 req/min |
| Purge execute | 5 req/min |
| Batch lookup run | 2 req/min |
| Merge (detect/execute) | 5 req/min |
| Google login | 10 req/min |

Les endpoints d'import acceptent uniquement les fichiers Excel `.xlsx` (MIME `application/vnd.openxmlformats-officedocument.spreadsheetml.sheet`) de 10 Mo maximum.

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

### Enrichissement automatique

Le pipeline d'enrichissement (`Service/Enrichment/`) analyse les séries et enrichit automatiquement les champs manquants :

1. **Scoring de confiance** : chaque donnée reçoit un niveau HIGH, MEDIUM ou LOW
2. **HIGH** → auto-appliqué + `EnrichmentProposal` PRE_ACCEPTED (l'utilisateur peut rejeter → revert)
3. **MEDIUM** → `EnrichmentProposal` PENDING en attente de revue manuelle
4. **LOW** → `EnrichmentProposal` SKIPPED (ignoré, empêche la synchro de repasser)

L'enrichissement se déclenche :
- À la **création** d'une série (via Messenger, asynchrone)
- Via la commande **`app:auto-enrich`** (scheduler, mar-sam 3h-8h)
- Lors d'un **re-enrichissement** quand des champs sont vidés manuellement

L'enrichissement est **désactivé automatiquement** pendant les imports Excel/Books.

### Symfony Messenger

Transport : `doctrine://default` (messages stockés dans `messenger_messages`). Test env : `in-memory://`.

| Message | Handler | Description |
|---------|---------|-------------|
| `EnrichSeriesMessage` | `EnrichSeriesHandler` | Enrichissement asynchrone à la création |
| `DownloadCoverMessage` | `DownloadCoverHandler` | Téléchargement de couverture asynchrone |

Configuration : `backend/config/packages/messenger.yaml`. Retry : max 3, backoff ×2. Transport `failed` pour les erreurs.

### Symfony Scheduler

Toutes les tâches récurrentes sont gérées par Symfony Scheduler (`backend/src/Schedule.php`) :

| Cron | Commande | Description |
|------|----------|-------------|
| `0 3-8 * * 2-6` | `app:auto-enrich` | Enrichissement automatique (mar-sam, 3h-8h) |
| `0 4 * * *` | `app:check-new-releases` | Vérification nouvelles parutions (quotidien) |
| `0 5 * * *` | `app:download-covers` | Téléchargement des couvertures manquantes (quotidien) |
| `0 3-8 * * 0` | `app:detect-missing-tomes` | Détection tomes manquants (dimanche) |
| `0 3-8 * * 1` | `app:check-author-releases` | Vérification publications auteurs suivis (lundi) |
| `0 1 1 * *` | `app:purge-deleted` | Purge corbeille (1er du mois) |
| `0 2 1 * *` | `app:purge-notifications --days=90` | Purge notifications anciennes (1er du mois) |

Les tâches Gemini (auto-enrich, detect-missing-tomes, check-author-releases) sont réparties sur des jours différents pour exploiter le quota (reset à minuit PT = 9h Paris).

Un **conteneur worker Docker** dédié avec Supervisor exécute Messenger et Scheduler en production.

### Commandes console

| Commande | Description |
|----------|-------------|
| `app:auto-enrich` | Enrichir automatiquement les séries avec scoring de confiance |
| `app:check-new-releases` | Vérifier les nouvelles parutions des séries en cours |
| `app:check-author-releases` | Vérifier les publications des auteurs suivis |
| `app:detect-missing-tomes` | Détecter les tomes manquants des séries en cours/terminées |
| `app:download-covers` | Télécharger les couvertures manquantes |
| `app:scan-nas [--dry-run]` | Scanner le NAS et mettre à jour le statut des tomes |
| `app:import-books <fichier> [--dry-run]` | Importer des livres depuis un fichier Excel |
| `app:import-excel <fichier> [--dry-run]` | Importer une collection depuis un fichier Excel de suivi |
| `app:invalidate-tokens [--email=...]` | Invalider les tokens JWT (tous ou par utilisateur) |
| `app:purge-deleted [--days=30] [--dry-run]` | Supprimer définitivement les séries dans la corbeille |
| `app:purge-notifications [--days=90]` | Purger les notifications anciennes |
| `app:warm-thumbnails` | Pré-générer les miniatures de toutes les couvertures |

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
| idb / idb-keyval | - | IndexedDB (offline queue, JWT miroir) |

### Patterns

**Service API (`services/api.ts`)** :
- `apiFetch<T>(path, options)` — Fonction générique qui gère les headers JWT, Content-Type, erreurs 401
- Le token JWT est stocké dans `localStorage` et miroir dans IndexedDB pour le service worker
- En cas de 401, le token est supprimé et l'utilisateur est redirigé vers `/login`

**Hooks** :
- Chaque interaction API a son propre hook (`useComics`, `useComic`, `useCreateComic`, etc.)
- Les hooks de lecture utilisent `useQuery` (TanStack Query)
- Les hooks de mutation utilisent `useMutation` et invalident les query keys pertinentes au succès
- Hooks spécialisés : `useNotifications`, `useEnrichment`, `useOnlineStatus`, `useSyncStatus`, `useServiceWorker`, `useDarkMode`

**Routing** :
- Routes définies dans `App.tsx`
- Pages chargées en lazy loading (`React.lazy()` avec retry) sauf Home
- `AuthGuard` protège toutes les routes sauf `/login`
- Fallback offline quand un chunk n'est pas en cache

**Pages** :

| Page | Route | Description |
|------|-------|-------------|
| `Home` | `/` | Bibliothèque complète (carrousel + grille) |
| `ToBuy` | `/to-buy` | Tomes manquants des séries en cours |
| `ComicDetail` | `/comic/:id` | Fiche détaillée d'une série |
| `ComicForm` | `/comic/new`, `/comic/:id/edit` | Formulaire de création/édition |
| `Trash` | `/trash` | Corbeille |
| `Tools` | `/tools` | Hub des outils d'administration |
| `EnrichmentReview` | `/tools/enrichment-review` | Revue des propositions d'enrichissement |
| `Suggestions` | `/tools/suggestions` | Suggestions IA de séries |
| `ImportTool` | `/tools/import` | Import Excel |
| `LookupTool` | `/tools/lookup` | Lookup batch |
| `MergeSeries` | `/tools/merge-series` | Fusion de séries |
| `PurgeTool` | `/tools/purge` | Purge corbeille |
| `Notifications` | `/notifications` | Liste des notifications |
| `NotificationSettings` | `/settings/notifications` | Préférences de notifications |
| `Login` | `/login` | Connexion Google OAuth |

**Composants** :

| Composant | Description |
|-----------|-------------|
| `Layout` | Shell de l'app (header sticky, search, notifications, Outlet) |
| `BottomNav` | Navigation bottom (mobile) avec glassmorphism et indicateurs dot |
| `AuthGuard` | Redirige vers /login si non authentifié |
| `ComicCard` | Carte d'une série (couverture, ambient glow, badges) |
| `CoverImage` | Image avec skeleton shimmer et miniatures |
| `CoverLightbox` | Lightbox plein écran pour les couvertures |
| `CoverSearchModal` | Modale de recherche de couvertures (Google Books + Serper) |
| `FilterChips` | Chips de filtre rapide (type, statut) scrollables |
| `NotificationBell` | Cloche avec badge compteur non lu |
| `SeriesEnrichmentProposals` | Propositions d'enrichissement sur la fiche série |
| `ConfirmModal` | Modal de confirmation (Headless UI Dialog) |
| `ComponentErrorBoundary` | Error boundary contextuel (label + retry) pour sections de page |
| `ErrorFallback` | Fallback pour ErrorBoundary app-level |
| `OfflineBanner` | Bannière offline avec liste dépliable des opérations en attente |
| `SyncErrorBanner` | Bannière d'erreur de synchronisation |
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
| `SERPER_API_KEY` | Clé API Serper.dev pour la recherche de couvertures | vide |
| `OAUTH_GOOGLE_ID` | ID client OAuth Google | À définir dans `.env.local` |
| `OAUTH_ALLOWED_EMAIL` | Email Gmail autorisé pour le login | À définir dans `.env.local` |
| `CORS_ALLOW_ORIGIN` | Regex des origines CORS autorisées | `localhost` |
| `VAPID_PUBLIC_KEY` | Clé publique VAPID pour les notifications push | `change_me` |
| `VAPID_PRIVATE_KEY` | Clé privée VAPID pour les notifications push | `change_me` |
| `VAPID_SUBJECT` | Email de contact VAPID | `mailto:contact@example.com` |
| `MESSENGER_TRANSPORT_DSN` | Transport Messenger | `doctrine://default` |
| `VITE_VAPID_PUBLIC_KEY` | Clé publique VAPID côté frontend | vide |
| `VITE_GOOGLE_CLIENT_ID` | ID client OAuth Google côté frontend | vide |

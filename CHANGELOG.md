# Changelog

Toutes les modifications notables de ce projet sont documentées dans ce fichier.

Le format est basé sur [Keep a Changelog](https://keepachangelog.com/fr/1.1.0/),
et ce projet adhère au [Versionnement Sémantique](https://semver.org/lang/fr/).

## [Unreleased]

### Changed

- **Frontend** : Consolidation de la gestion d'erreurs — extraction de `handleUnauthorized()` et `getErrorMessage()` dans `api.ts`, remplacement de `Record<string, unknown>` par des interfaces typées (`CreateComicPayload`, `UpdateComicPayload`, `CreateTomePayload`, `TomePayload`)
- **Frontend** : Centralisation des query keys (`queryKeys.ts`) et des endpoints API (`endpoints.ts`) — supprime les chaînes éparpillées dans 20+ hooks et 12 fichiers de tests
- **Frontend** : Découpage de `MergePreviewModal` (587→80 lignes) en `MergeMetadataForm`, `MergeTomeTable` et `useMergePreviewForm` (useReducer)
- **Frontend** : Découpage de `useComicForm` (420→210 lignes) en `useLookupFeature`, `useTomeManagement` et `useAuthorManagement`
- **Frontend** : `TomeTable` reçoit un objet `TomeManager` au lieu de 12 props individuelles
- **Frontend** : Extraction de `SyncFailureSection` dans `components/`

## [v2.12.0] - 2026-03-16

### Changed

- **Recherche** : Hook `useDebounce` partagé remplace les `setTimeout` manuels dans Home et ToBuy (cleanup automatique)
- **Performance** : Virtualisation des grilles (Home, ToBuy) avec `@tanstack/react-virtual` — seules les lignes visibles sont rendues
- **Performance** : `React.memo` sur `ComicCard`, `CardActionBar` et `MergeGroupCard` pour éviter les re-renders inutiles
- **Performance** : Callbacks mémoïsés dans Home, stats mémoïsées dans ComicDetail
- **Performance** : Invalidation ciblée des queries TanStack — `useUpdateComic` met à jour la collection via `setQueryData` au lieu de refetch
- **Performance** : `staleTime` réduit de 30 min à 5 min, `refetchOnWindowFocus` activé

### Fixed

- **Grille** : Les cartes se chevauchaient dans la grille virtualisée — ajout de `measureElement` et gap vertical
- **Données** : La modification d'une série était écrasée par un refetch obsolète de la collection
- **Sécurité** : Vidage du cache SW (`api-cache`) au logout — les données API ne persistent plus après déconnexion
- **Sécurité** : Validation du schéma URL des couvertures (`http://`/`https://` uniquement) — bloque `javascript:` et `data:`
- **Sécurité** : Messages d'erreur serveur sanitisés — les détails internes (SQL, stack trace) ne sont plus exposés

## [v2.11.0] - 2026-03-15

### Added

- **Formulaire série** : Champ « Date de parution » avec composant `DatePartialSelect` (année/mois/jour partiels)
- **Modal de fusion** : Formulaire complet éditable (type, statut, éditeur, couverture, auteurs, description, flags, Amazon URL)
- **MergePreview** : Tous les champs de série dans le DTO (`amazonUrl`, `status`, `publishedDate`, `defaultTome*`, `notInterested*`)

### Fixed

- **Scan NAS** : Ignore Star Wars (structure incompatible), corrige Block 109 (nombre du titre ≠ tome), descend dans les conteneurs (crossovers, one shots)
- **Scan NAS** : Nettoie les extensions (.cbr/.cbz) et indicateurs (one-shot, complet, underscores, tags source) des titres de séries
- **Scan NAS** : Les one-shots ne comptent plus leurs pages comme des tomes
- **Import Excel/Livres** : Matching fuzzy des titres (normalisation accents, tirets, ponctuation) pour éviter les doublons

## [v2.10.0] - 2026-03-15

### Added

- **Tomes hors-série** : Champ `isHorsSerie` sur `Tome` avec numérotation séparée (HS1, HS2…) indépendante des tomes réguliers
- **Import Excel** : Parsing du format `N+MHS` / `N+HS` (ex: `3+2HS`, `8+HS`) dans la colonne Parution pour créer les tomes hors-série
- **Pas intéressé** : Deux booléens indépendants `notInterestedBuy` et `notInterestedNas` sur `ComicSeries` pour distinguer « pas intéressé par l'achat » et « pas intéressé par le NAS »
- **Import Excel** : Col B `non` → `notInterestedBuy`, col G `non` → `notInterestedNas` (au lieu de STOPPED / simple false)
- **Scan NAS** : Commande `app:scan-nas` qui scanne les fichiers du NAS via SSH et génère un fichier Excel compatible avec l'import
- **Import Excel** : Nouvelle colonne « Parution terminée » (col H) pour marquer une série comme terminée sans perdre les valeurs numériques
- **Import Excel** : Support du format « fini N » (ex: `fini 40`) dans les cellules numériques pour conserver le nombre tout en marquant comme terminé

## [v2.9.5] - 2026-03-15

### Changed

- **Lookup** : Filtre les résultats multi-candidats dont le titre ne correspond pas à la requête de recherche

## [v2.9.4] - 2026-03-15

### Added

- **Sélection manuelle** : Affiche le type (BD, Manga, Comics, Livre) pour chaque série dans la liste
- **Sélection manuelle** : Bouton détail ouvrant une modal avec les informations de la série (type, statut, tomes, auteurs, éditeur, description)

## [v2.9.3] - 2026-03-14

### Fixed

- **Lookup** : Corrige les modèles Gemini invalides (`gemini-3-flash`, `gemini-3.1-flash-lite`) qui causaient des erreurs en prod
- **Lookup** : Ajoute le code 404 aux erreurs retryables du pool Gemini (un modèle inexistant est ignoré au lieu de crasher)
- **Docker** : Passe `COMICVINE_API_KEY` au conteneur PHP en production

## [v2.9.2] - 2026-03-14

### Changed

- **Deploy** : Les logs de déploiement NAS sont maintenant visibles dans les logs GitHub Actions en plus du fichier local

### Removed

- **Rate limiting** : Supprime les rate limiters inutiles sur les endpoints authentifiés (garde uniquement google_login et gemini_api)

## [v2.9.1] - 2026-03-14

### Fixed

- **Merge** : Corrige le timeout 504 sur `/api/merge-series/preview` en rendant l'appel Gemini asynchrone via un nouvel endpoint `/api/merge-series/suggest`

## [v2.9.0] - 2026-03-14

### Added

- **CI** : Pré-build des images Docker (PHP + Nginx) sur ghcr.io à chaque tag, le NAS pull au lieu de rebuild (~30s vs ~10min)

## [v2.8.9] - 2026-03-14

### Fixed

- **Docker** : Corrige la commande healthcheck php-fpm (variables FastCGI manquantes). Cause root du déploiement cassé depuis v2.7.0

## [v2.8.8] - 2026-03-14

### Fixed

- **Docker** : Augmente les délais du healthcheck PHP (start_period 60s, retries 10) pour le warmup sur NAS
- **CI** : Skip les checks pour les PRs ne touchant que CHANGELOG, docs, scripts ou .md

## [v2.8.7] - 2026-03-14

### Fixed

- **Deploy** : Rebuild automatique si les conteneurs ne tournent pas, même si le tag est déjà déployé

## [v2.8.6] - 2026-03-14

### Fixed

- **Deploy** : Le workflow met à jour le repo NAS avant de lancer le script de déploiement

## [v2.8.5] - 2026-03-14

### Fixed

- **Docker** : Lance php-fpm en root (il drop lui-même les privileges). Corrige `Permission denied` sur stderr

## [v2.8.4] - 2026-03-14

### Fixed

- **Scripts NAS** : Correction du commentaire obsolète dans `nas-cleanup-logs.sh`

## [v2.8.3] - 2026-03-14

### Fixed

- **Docker** : Ajout du `cache:warmup` dans l'entrypoint PHP, supprimé par erreur dans #208. Corrige le crash du conteneur PHP en production

## [v2.8.2] - 2026-03-14

### Changed

- **Accessibilité** : Ajout d'`aria-label` sur les boutons icônes, inputs de recherche et checkboxes. Fermeture du `CardActionBar` via Escape (#170)

## [v2.8.1] - 2026-03-14

### Fixed

- **Déploiement SSH** : Ajout de `/usr/local/bin` au PATH du script de mise à jour pour les sessions SSH non-interactives (#218)

## [v2.8.0] - 2026-03-14

### Added

- **Lien Amazon** : Champ `amazonUrl` sur les séries, renseigné automatiquement par le lookup Gemini. Bouton Amazon affiché sur la page détail des séries en cours d'achat (#124)
- **Vérification des nouvelles parutions** : Commande `app:check-new-releases` pour détecter les nouveaux tomes publiés sur les séries en cours d'achat. Badge « Nouveau » sur les cartes de la bibliothèque (#192)
- **Déploiement automatique** : Le workflow release déclenche `nas-update.sh` via SSH après chaque tag, remplaçant le cron nightly (#216)

## [v2.7.0] - 2026-03-14

### Added

- **Nouveaux providers de lookup** : Jikan, Kitsu, MangaDex (manga) et ComicVine (BD/Comics) pour enrichir les métadonnées. Refactoring LookupTitleCleaner (DRY) (#211)
- **Lookup multi-candidats** : Le lookup par titre affiche plusieurs séries candidates regroupées par titre, permettant de choisir avant d'appliquer. Paramètre `limit` sur `/api/lookup/title` (défaut 1, max 10). Tous les providers contribuent aux candidats (#200)
- **pcov** : Installation de pcov dans DDEV pour la couverture de code, commande `make coverage` (#172)
- **Tests manquants** : Tests ImportBooksCommand, sw-custom, MergeGroupCard, SeriesMultiSelect, Tools page (#172)

### Changed

- **PurgeService** : Corrige le problème N+1 en utilisant `findBy()` au lieu de `find()` en boucle (#172)
- **Docker hardening** : Conteneurs PHP et nginx exécutés en non-root, Node.js 22, Composer pinné à v2, healthcheck php-fpm, `.dockerignore` enrichi (#171)

### Fixed

- **Priorité Bedetheque thumbnail BD** : La priorité du champ thumbnail est maintenant 150 (comme les autres champs) pour le type BD, au lieu de 50 (#200)

## [v2.6.0] - 2026-03-14

### Added

- **Backup automatique BDD** : Script `scripts/nas-backup.sh` pour dump quotidien de la base MariaDB avec compression gzip et rotation à 7 jours (#175)
- **Cache HTTP (ETag)** : Les endpoints `GET /api/comic_series` et `GET /api/comic_series/{id}` retournent un ETag basé sur le hash du contenu et répondent `304 Not Modified` si le client envoie un `If-None-Match` valide (#193)
- **CI GitHub Actions** : Workflow lint (PHPStan, CS Fixer, TypeScript) + tests (PHPUnit, Vitest) sur chaque PR, avec protection de la branche `main` (#166)
- **Couvertures locales** : Téléchargement automatique des couvertures externes en WebP local via `CoverDownloader`, intégré au lookup et commande batch `app:download-covers` (#180)
- **Nettoyage centralisé des logs** : Script `scripts/nas-cleanup-logs.sh` pour la rotation des logs `/var/log/bibliotheque/` (7 jours), remplace la logique dupliquée dans chaque script
- **Page « À acheter »** : Nouvelle page `/to-buy` listant les séries en cours d'achat avec tomes manquants, remplacement du tab Wishlist par « À acheter » dans la navigation (#191)
- **Rollback automatique NAS** : Si le build Docker échoue après un `git pull`, le script `nas-update.sh` revient automatiquement aux commits précédents (par merge commit, max 5 tentatives) jusqu'à retrouver un build fonctionnel (#176)

### Changed

- **Backend qualité du code** : Ajout `final` sur ~45 classes feuilles, extraction `GoogleBooksUrlHelper`/`GeminiJsonParser`/`MergePreviewHydrator`, déplacement des requêtes dans les repositories, enum `BatchLookupStatus`, constante `CACHE_TTL` (#167)
- **Cards listing** : remplace la barre de progression par 3 compteurs (achetés, lus, téléchargés) répartis sur la largeur
- **Frontend : extraction composants partagés** : `typeOptions`/`statusOptions` centralisés dans `enums.ts`, `getCoverSrc` dans `coverUtils.ts`, labels de sync dans `syncLabels.ts`, `SelectListbox` réutilisable, et `ComicForm.tsx` découpé en `useComicForm`, `TomeTable`, `LookupSection`, `AuthorAutocomplete` (1180 → 398 lignes) (#169)

### Fixed

- **Bedetheque lookup** : Ajout de safety settings Gemini (`BLOCK_ONLY_HIGH`) pour éviter les faux blocages sur des titres légitimes (ex. « Arawn »), et vérification préventive des candidats avant appel à `text()` avec diagnostic détaillé de la raison du blocage (#199)
- **Dernier tome paru** : Mise à jour automatique de `latestPublishedIssue` quand un tome ajouté/modifié dépasse la valeur actuelle, et calcul du total corrigé côté frontend
- **Filtres mobile** : Remplacement des dropdowns tronqués par un bouton icône ouvrant un bottom sheet avec des `<select>` natifs, suppression du scroll horizontal (#181, #183)
- **Fusion de séries** : Bouton de détection et d'aperçu de fusion en sticky pour rester visibles au scroll (#182)
- **ImportControllerTest** : Assertions corrigées après refactoring du DTO
- **Index composite Tome** : Ajout d'un index `(comic_series_id, number)` pour accélérer les requêtes par série + tri par numéro (#168)
- **PHPStan** : Baseline régénérée, imports inutilisés nettoyés, tolérance des différences DDEV/CI
- **Rotation clés Gemini** : Les erreurs 401/403 (clé invalide) déclenchent maintenant la rotation vers la clé suivante, au lieu de stopper le lookup (#190)
- **Vich Uploader** : Migration des annotations dépréciées vers les attributs PHP 8
- **Vignettes en production** : CSP `connect-src` autorise désormais `https:` pour les couvertures externes, et priorité aux fichiers locaux dans le frontend (#180)

## [v2.5.0] - 2026-03-13

### Added

- **Validation fichiers et rate limiting** : Validation MIME type (.xlsx uniquement) et taille max (10 Mo) sur les endpoints d'import, rate limiting sur les endpoints outils (import 5/min, purge 5/min, batch lookup 2/min, merge 5/min) (#165)
- **Parution terminée et flags par défaut** : Notion de parution terminée (`latestPublishedIssueComplete`) visible et éditable dans l'UI, date de dernière MAJ de la parution, flags par défaut des tomes (`defaultTomeBought`, `defaultTomeDownloaded`, `defaultTomeRead`) dérivés de l'import Excel et utilisés par le lookup pour créer les tomes manquants (#162)
- **Confirmation des séries avant fusion** : Étape intermédiaire affichant la liste des séries avec cases à cocher, permettant d'exclure des séries avant la prévisualisation des tomes (#157)
- **Script biblio.sh** : Raccourcis CLI pour la gestion des conteneurs sur le NAS (`biblio up`, `biblio logs`, `biblio migrate`, etc.)
- **Entrypoint Docker** : `composer dump-env prod` au démarrage du conteneur pour compiler les variables Docker dans `.env.local.php`

### Changed

- **En-têtes de sécurité** : Retrait de `unsafe-inline` et `data:` dans `script-src` (nelmio), ajout de CSP, HSTS et Permissions-Policy dans la configuration nginx de production (#164)
- **Docker Compose** : Renommage de `docker-compose.prod.yml` en `docker-compose.yml`, suppression des fichiers `compose.yaml`/`compose.override.yaml` Symfony par défaut

### Fixed

- **Barres de progression** : Prise en compte des plages de numéros de tomes (`tomeEnd`) dans le calcul de progression des achats, lectures et téléchargements (#160)
- **CSP Google OAuth** : Ajout de `frame-src` et `style-src` pour `accounts.google.com` dans la configuration nginx
- **Variables d'environnement Docker** : Arrêt propre des conteneurs avant rebuild, injection correcte des secrets via l'entrypoint

## [v2.4.0] - 2026-03-06

### Added

- **Bouton vider le cache** : Bouton dans la page Outils pour purger le cache local (IndexedDB + TanStack Query) et recharger les données depuis le serveur, avec spinner et toast (#155)
- **Sélecteur de couverture série** : Bouton de recherche d'images à côté du champ URL de couverture, modale avec grille d'images Google Custom Search, sélection visuelle (#137)
- **Ajout de tomes dans la prévisualisation de fusion** : Bouton "Ajouter un tome" dans la modale de fusion, avec numérotation automatique (#146)

### Changed

- **Logout** : Le logout vide désormais le cache local (IndexedDB + TanStack Query) en plus de supprimer le token JWT (#155)

### Fixed

- **Login multi-appareils** : Le login n'invalide plus les tokens JWT des autres appareils. Le mécanisme de token versioning reste disponible via `app:invalidate-tokens` (#142)

- **UX recherche** : Debounce de la synchronisation URL (300ms) pour supprimer le lag de saisie, indicateur de chargement lors du refetch, transition CSS sur la grille de résultats (#147)

- **Lag de la recherche** : Le filtrage Fuse.js s'exécutait à chaque frappe, bloquant l'affichage. Le filtrage est maintenant déboncé (300ms) et l'index Fuse.js est mis en cache (#153)

- **Tomes supprimés lors de l'édition d'une série** : Le PUT API Platform vidait silencieusement la collection de tomes. Migration vers PATCH (merge-patch+json) avec `@id` pour identifier les tomes existants. Les tomes sont maintenant correctement préservés, ajoutés et supprimés (#145)

- **Doublons à l'import Excel de suivi** : L'import créait systématiquement de nouvelles séries sans vérifier l'existant. Il cherche maintenant par titre + type et met à jour la série existante (status, tomes, latestPublishedIssue) au lieu de créer un doublon

### Changed

- **Tri des tomes par numéro** : Les tomes sont triés par numéro de début dans le formulaire d'édition (#145)
- **Indicateur visuel pour les tomes non sauvegardés** : Les tomes ajoutés via "Ajouter" ou "Générer" sont mis en surbrillance verte avec un badge "Nouveau" (#145)

## [v2.3.0] - 2026-03-06

### Added

- **Rotation des clés API Gemini** : Nouveau service `GeminiClientPool` qui itère modèles × clés API sur erreur 429, avec dégradation progressive vers des modèles plus légers. Variables `GEMINI_API_KEYS` (multi-clés) et `GEMINI_MODELS` (ordre de priorité) (#138)
- **Lookup batch depuis le frontend** : Page `/tools/lookup` avec streaming SSE en temps réel, filtres par type, option force/limite/délai, log de progression avec barre et icônes de statut, résumé final. Refactoring de la commande CLI pour réutiliser le service (#135)
- **Import Excel depuis le frontend** : Page `/tools/import` avec deux onglets (suivi et livres), upload drag-drop, mode simulation (dry run), affichage des résultats détaillés (#135)
- **Fusion de séries** : Détection automatique via Gemini AI des séries à fusionner (par type + lettre), avec aperçu complet et éditable avant exécution. Sélection manuelle possible. Tous les champs des tomes sont modifiables (numéro, fin, titre, ISBN, statuts). Détection des doublons de numéros avec blocage (#136)
- **Page Outils** : Hub centralisé `/tools` pour accéder aux outils d'administration (fusion, import, lookup, purge) (#136)

## [v2.2.0] - 2026-03-05

### Added

- **Lookup Bedetheque via Gemini Google Search** : Nouveau provider de recherche ciblant bedetheque.com via Gemini avec Google Search grounding. Priorité élevée pour les BD (150), modérée pour manga/comics (110). Recherche par ISBN et titre (#119)
- **Sources des résultats de lookup** : Affichage des providers ayant contribué aux résultats (ex: "Sources : google_books, gemini, bedetheque") et des messages d'erreur/timeout des providers (#130)
- **Bouton titre série dans le lookup** : Bouton pour pré-remplir le champ de recherche titre avec le titre de la série en cours d'édition (#131)
- **Monolog** : Installation de symfony/monolog-bundle pour les logs applicatifs

### Fixed

- **Lookup BnF** : Correction du parsing des noms d'auteurs contenant un suffixe de rôle BnF (ex: `. Auteur du texte`, `. Illustrateur`) (#133)
- **Provider Bedetheque** : Correction du prompt Gemini qui bloquait avec l'opérateur `site:` dans le grounding API. Gestion du ValueError (aucun candidat retourné) (#119)
- **Type apiMessages** : Correction du type frontend (objet clé-valeur, pas tableau)

## [v2.1.0] - 2026-03-05

### Added

- **CRUD offline avec synchronisation automatique** : Toutes les opérations (créer, modifier, supprimer) sur les séries et tomes fonctionnent hors ligne avec mises à jour optimistes, file d'attente persistée en IndexedDB, et synchronisation automatique au retour en ligne via Background Sync API. Indicateurs visuels sur les éléments en attente de sync, bannière d'erreurs extensible avec détails du payload, notifications mobiles via Service Worker, et auto-résolution des erreurs depuis le formulaire d'édition (#126)
- **Date de publication sur la page détail** : Affichage de la date de publication (champ `publishedDate`) dans les métadonnées de la page détail d'une série, formatée en français (#98)

## [v2.0.0] - 2026-03-03

### Added

- **Lookup automatique des métadonnées manquantes** : Commande `app:lookup-missing` pour rechercher automatiquement description, couverture, éditeur, auteurs et date de publication des séries incomplètes. Gestion du rate-limiting avec backoff exponentiel, options `--dry-run`, `--limit`, `--type`, `--series`, `--force`. Champ `lookupCompletedAt` pour éviter les re-lookups. Service `LookupApplier` réutilisable pour appliquer un `LookupResult` sur une série (#112)
- **Transitions animées entre les pages** : Fade subtil entre les pages via la View Transition API native (CSS `::view-transition`) intégrée avec React Router (`viewTransition` sur les Links et `navigate()`). Respect de `prefers-reduced-motion`. Aucune dépendance ajoutée (#96)
- **Tomes multi-numéros (intégrales)** : Champ optionnel `tomeEnd` sur l'entité Tome pour représenter une plage de numéros (ex : tome 4-6). Affiché dans la page détail et éditable dans le formulaire. Enrichissement Gemini : détection automatique des intégrales lors du lookup ISBN avec pré-remplissage de `tomeEnd` (#111)
- **Cache sur findAllForApi()** : Cache applicatif Symfony (15 min, filesystem) sur la requête principale de l'API PWA avec invalidation automatique via listener Doctrine lors de modifications sur ComicSeries, Tome ou Author (#23)
- **Événements domaine ComicSeries** : Système d'événements Symfony dispatché via un listener Doctrine — `ComicSeriesCreatedEvent`, `ComicSeriesUpdatedEvent`, `ComicSeriesDeletedEvent` (soft-delete, hard-delete et suppression permanente DBAL) (#36)
- **Placeholder de couverture stylisé** : Les séries sans couverture affichent une illustration spécifique au type (BD, Manga, Comics, Livre) au lieu du placeholder générique (#100)
- **Empty states illustrés** : Remplacement des textes bruts par un composant `EmptyState` réutilisable avec icône Lucide, message contextuel et CTA — bibliothèque vide, liste de souhaits vide, recherche sans résultat, filtres sans résultat, corbeille vide (#94)
- **Indicateur de progression de collection** : Barre de progression achetés/total sur les cartes (ComicCard) et barres détaillées achetés/lus/téléchargés sur la page détail (ComicDetail). Total basé sur `latestPublishedIssue` ou nombre de tomes (#90)
- **Recherche par auteur et éditeur** : La barre de recherche (Accueil + Liste de souhaits) filtre désormais sur le titre, les auteurs et l'éditeur avec recherche floue tolérante aux fautes de frappe via Fuse.js (#89)
- **Ajout de tomes en lot** : Inputs « Du tome X au tome Y » avec bouton « Générer » dans le formulaire de série — création en lot avec numéros pré-remplis, ignore les numéros déjà existants, tri automatique (#88)
- **Toggle inline des tomes** : Checkboxes cliquables directement sur la page détail pour basculer acheté/téléchargé/lu/NAS sans passer par le formulaire d'édition — optimistic update, gestion d'erreur avec revert, support offline (#86)
- **Skeleton loaders** : Remplacement du texte « Chargement… » par des skeleton placeholders animés sur toutes les pages — grille de cartes (Home/Wishlist), détail série, corbeille, formulaire d'édition (#85)
- **Tri des séries** : Sélecteur de tri sur les pages Accueil et Liste de souhaits — titre (A→Z/Z→A), date d'ajout (récent/ancien), nombre de tomes (#84)
- **Mode hors-ligne avec synchronisation différée** : CRUD complet (séries + tomes) en mode offline avec synchronisation automatique au retour en ligne (#3)
  - File d'attente IndexedDB (via `idb`) pour les opérations offline
  - Background Sync API pour la synchronisation automatique (Service Worker custom)
  - Hook `useOfflineMutation` wrappant les mutations TanStack Query existantes
  - Bannière offline enrichie avec compteur d'opérations en attente
  - Lookup et scanner désactivés hors-ligne
  - Toasts Sonner pour le feedback de synchronisation
  - Stratégie last-write-wins pour la résolution de conflits
- **Rate limiting API lookup** : Limitation à 30 requêtes/min par IP sur les endpoints `/api/lookup/isbn` et `/api/lookup/title` (#29)
- **Refonte complète des tests (928 tests)** : Couverture exhaustive backend (549 PHPUnit) et frontend (379 Vitest) avec architecture 3 tiers Unit/Integration/Functional (#83)
- **Symfony Secrets vault** : Les secrets cryptographiques (`APP_SECRET`, `JWT_PASSPHRASE`) sont stockés dans un vault chiffré (`config/secrets/prod/`), éliminant les placeholders en production (CWE-798)
  - Vault chiffré asymétriquement (clé publique committée, clé de déchiffrement gitignorée)
  - Injection en prod via `SYMFONY_DECRYPTION_SECRET` (env var) ou fichier monté
  - `PlaceholderSecretChecker` : bloque le démarrage en prod si des valeurs placeholder sont détectées
- **Guide déploiement NAS Synology** : Guide complet Docker Compose pour NAS Synology avec reverse proxy intégré (`docs/guide-deploiement-nas.md`)
- **Runbook déploiement NAS (Claude)** : Runbook pas-à-pas pour déploiement automatisé via SSH par Claude Code (`docs/guide-deploiement-nas-claude.md`)
- **Guide déploiement OVH** : Guide complet pour serveur Linux bare metal avec nginx + php-fpm + MariaDB (`docs/guide-deploiement-ovh.md`)
- **Invalidation JWT par token versioning** : Chaque connexion invalide automatiquement les tokens précédents
  - Champ `tokenVersion` sur l'entité `User` (incrémenté à chaque login)
  - `JwtTokenVersionListener` : ajoute la version au payload JWT à la création, vérifie la correspondance au décodage
  - Commande `app:invalidate-tokens [--email=...]` pour invalider tous les tokens (ou par utilisateur)
- **AbstractLookupProvider** : Classe abstraite factorant la gestion des messages API (`recordApiMessage`, `getLastApiMessage`, `resetApiMessage`) pour les 6 providers de lookup
- **Login throttling** : Protection contre le brute-force via `login_throttling` Symfony (5 tentatives / minute)
- **SoftDeletedComicSeriesProvider** : Provider API Platform pour accéder aux séries soft-deleted (restore et suppression définitive)
- **TrashCollectionProvider** : Endpoint `/api/trash` pour lister les séries de la corbeille
- **Tests API Platform** : 10 tests fonctionnels couvrant le CRUD, l'authentification JWT, le soft-delete, la restauration et la suppression définitive
- **Suivi de lecture** : Nouveau champ `read` sur les tomes pour suivre la progression de lecture
  - Propriété `read` (lu) sur `Tome` avec checkbox dans le formulaire d'édition
  - Méthodes calculées sur `ComicSeries` : `getLastRead()`, `isLastReadComplete()`, `getReadTomesCount()`, `isCurrentlyReading()`, `isFullyRead()`
  - Filtre "Lecture" sur la page d'accueil (Tous / En cours / Lus / Non lus)
  - Statistique "Lecture" et indicateur visuel (bordure verte) sur la fiche série
  - Données de lecture exposées dans l'API PWA
- **Notification mise à jour SW** : Bandeau "Nouvelle version disponible — Rafraîchir" affiché automatiquement quand le Service Worker se met à jour, avec bouton de rechargement et possibilité de fermer
- **BnfLookup** : Nouveau provider de recherche via l'API SRU du catalogue général de la BnF
  - Recherche par ISBN (`bib.isbn`) et par titre (`bib.title`)
  - Extraction des métadonnées (titre, auteurs, éditeur, date, ISBN) au format Dublin Core
  - Nettoyage automatique des données BnF (auteurs, éditeurs, titres)
  - Priorité 90 (source autoritaire pour les publications françaises)
- **WikipediaLookup** : Nouveau provider de recherche via Wikidata + Wikipedia FR
  - Recherche par ISBN (SPARQL) et par titre (wbsearchentities)
  - Extraction des métadonnées (auteurs, éditeur, date, couverture, one-shot) depuis les claims Wikidata
  - Synopsis depuis l'API REST Wikipedia FR
  - Gestion des éditions (P629) pour remonter automatiquement à l'œuvre originale
  - Cache filesystem (7 jours)
- **Statut API dans les réponses de lookup** : Les endpoints `/api/isbn-lookup` et `/api/title-lookup` incluent désormais un objet `apiMessages` indiquant le statut de chaque API interrogée (success, not_found, error, rate_limited) avec des badges colorés dans l'interface
- **Amélioration upload couverture** : Meilleure UX pour l'upload d'images
  - Activation de Symfony UX Dropzone avec prévisualisation du fichier sélectionné
  - Ajout checkbox "Supprimer" pour effacer l'image existante
  - Le fichier physique est automatiquement supprimé via VichUploader
  - Interface `CoverRemoverInterface` pour découpler la logique (testabilité)
- **Rector** : Outil de refactoring automatique PHP pour moderniser le code
  - Configuration conservatrice dans `rector.php` adaptée au projet
  - Règles PHP 8.3 (types sur constantes), dead code, code quality, Symfony 7.4
  - Règles désactivées : `#[Override]`, injection constructeur, inline route prefix
  - Application sur tout le codebase : 42 fichiers améliorés
  - Documentation d'utilisation ajoutée dans CLAUDE.md
- **Pré-cache automatique des pages** : Les pages principales sont mises en cache automatiquement après la connexion
  - Nouveau contrôleur Stimulus `cache_warmer_controller.js`
  - Pré-charge `/api/comics`, `/`, `/wishlist` et `/comic/new` en arrière-plan
  - Utilise directement l'API Cache du navigateur pour une mise en cache fiable
  - Les pages sont immédiatement disponibles en mode hors ligne après connexion
  - 3 nouveaux tests Playwright pour valider le pré-cache automatique
- **Filtrage et recherche hors ligne** : Toute l'interface de filtrage fonctionne sans requête HTTP
  - Nouveau contrôleur Stimulus `library_controller.js` pour les pages Bibliothèque et Wishlist
  - Filtrage côté client par type, statut, NAS, tri et recherche texte
  - Contrôleur `search_controller.js` pour la page de recherche dédiée
  - Chargement des données depuis `/api/comics` avec cache localStorage
  - Recherche instantanée sur titre, auteurs et description
  - Normalisation des accents pour une recherche insensible aux diacritiques
  - Fonctionne en mode offline grâce au cache local
  - Ajout des champs `hasNasTome`, `isOneShot`, `statusLabel` et `typeLabel` dans l'API
- **Rate limiting authentification** : Protection contre les attaques par force brute
  - Limite de 5 tentatives de connexion par intervalle de 15 minutes
  - Ajout du composant `symfony/rate-limiter`
  - 4 tests couvrant les scénarios : blocage après limite, connexion réussie avant limite, blocage même avec bon mot de passe, réinitialisation après connexion réussie
- **Protection fixtures hors environnement test** : Les fixtures ne s'exécutent qu'en environnement de test
  - Affiche un avertissement et ne charge pas les fixtures si l'environnement n'est pas "test"
  - Empêche le chargement accidentel de credentials de test (`test@example.com` / `password`)
  - Injection propre de l'environnement via `#[Autowire('%kernel.environment%')]`
  - 3 tests unitaires couvrant prod, dev et test
- **Correction vulnérabilité Open Redirect** : Nouvelle fonction Twig `safe_referer()`
  - Valide que le header Referer appartient au même host avant de l'utiliser
  - Protège contre les redirections vers des sites malveillants
  - Mise à jour des templates `comic/show.html.twig` et `comic/_form.html.twig`
  - 9 tests unitaires couvrant les différents scénarios
- **Contrainte UniqueEntity sur User** : Ajout de la validation Symfony pour l'email
  - Message d'erreur explicite : "Cet email est déjà utilisé."
  - Complète la contrainte unique en base de données avec une validation applicative
- **Headers de sécurité HTTP** : Installation de `nelmio/security-bundle`
  - `X-Content-Type-Options: nosniff` - empêche le sniffing MIME
  - `X-Frame-Options: DENY` - protège contre le clickjacking
  - `Referrer-Policy: strict-origin-when-cross-origin` - contrôle les informations de referer
  - `Content-Security-Policy` - CSP basique autorisant self, inline, et polices Google
  - 4 tests fonctionnels vérifiant la présence des headers
- **Documentation complète** : Dossier `docs/` avec documentation catégorisée
  - `docs/installation/` : Guide d'installation et configuration DDEV
  - `docs/fonctionnalites/` : Gestion de collection, recherche ISBN, mode PWA
  - `docs/architecture/` : Architecture, entités Doctrine, services
  - `docs/api/` : Documentation des endpoints REST
  - `docs/tests/` : Guide d'exécution et écriture des tests
  - `docs/developpement/` : Standards de code et workflow TDD
  - `docs/deploiement/` : Guide de mise en production Docker
  - README.md mis à jour avec liens vers la documentation
- **Tests PWA et offline** : Couverture de tests pour le fonctionnement hors ligne
  - `OfflineControllerTest` : 10 tests fonctionnels pour la page `/offline` (accessibilité, contenu, boutons, meta tags, script JS)
  - `ApiControllerTest` : 4 nouveaux tests pour les réponses 404 et le paramètre type des endpoints ISBN/title lookup
  - `OfflineModeTest` : 5 nouveaux tests Panther pour le manifest PWA, les caches et le Service Worker
  - `offline.spec.js` : 11 tests Playwright pour la navigation hors ligne
    - Service Worker installé et actif
    - Cache offline contient la page `/offline`
    - Pages visitées accessibles en mode offline (accueil, wishlist)
    - Navigation Turbo vers pages cachées
    - API `/api/comics` accessible en mode offline après visite
- **Suite de tests Behat** : Tests d'interface web avec BrowserKit et Selenium
  - 9 fichiers de features en français couvrant : authentification, création/édition/suppression de séries, filtrage, wishlist, recherche, one-shots et gestion des tomes
  - 6 contextes Behat : `FeatureContext`, `AuthenticationContext`, `ComicSeriesContext`, `NavigationContext`, `FormContext`, `DatabaseContext`
  - Profile `default` avec BrowserKit pour les tests rapides sans JavaScript
  - Profile `javascript` avec Selenium2 via DDEV Chrome pour les tests interactifs
  - Reset automatique de la base de données avant chaque scénario
- **Suite de tests complète** : 240 tests avec 585 assertions (unitaires, fonctionnels et d'intégration)
  - Tests des entités (83 tests) : `User`, `Author`, `Tome`, `ComicSeries` avec logique métier (`getCurrentIssue`, `getMissingTomesNumbers`, etc.)
  - Tests des enums (14 tests) : `ComicStatus`, `ComicType` (valeurs, labels, conversions)
  - Tests des contrôleurs (54 tests) : `HomeController`, `ComicController`, `SearchController`, `WishlistController`, `ApiController`, `SecurityController` avec authentification et CSRF
  - Tests des repositories (22 tests) : `ComicSeriesRepository` (filtres, recherche, tri), `AuthorRepository` (findOrCreate, findOrCreateMultiple)
  - Tests des formulaires (29 tests) : `TomeType`, `ComicSeriesType`, `AuthorAutocompleteType` avec validation et binding
  - Tests des commandes (10 tests) : `CreateUserCommand`, `ImportExcelCommand` avec hachage de mot de passe
  - Tests des services (17 tests) : `IsbnLookupService` avec mocks HTTP pour Google Books, Open Library et AniList
  - Classe de base `AuthenticatedWebTestCase` pour les tests de contrôleurs protégés
- **Recherche par titre** : Nouveau bouton de recherche à côté du champ titre
  - Recherche sur AniList si le type "manga" est sélectionné
  - Recherche sur Google Books pour les autres types
  - Pré-remplit auteurs, éditeur, date, description et couverture
  - Détection automatique des one-shots via `seriesInfo` de Google Books
  - Endpoint `GET /api/title-lookup?title=XXX&type=YYY`
- **Détection automatique one-shot** : Détection via Google Books (`seriesInfo`) et AniList (`format`, `volumes`, `status`)
  - Google Books : si `seriesInfo` est absent, le livre est détecté comme one-shot
  - AniList : si `format` vaut `ONE_SHOT` OU si `volumes = 1` et `status = FINISHED`
  - La case "One-shot" est cochée automatiquement
  - Un tome avec le numéro 1 est créé automatiquement
  - L'ISBN est extrait de Google Books (`industryIdentifiers`) et pré-rempli dans le tome
- **Champ Type en premier** : Le type est maintenant le premier champ du formulaire pour conditionner la recherche API
- **Flag One-Shot** : Nouveau champ `isOneShot` sur `ComicSeries` pour distinguer les tomes uniques (intégrales, one-shots) des séries multi-tomes
  - Checkbox dans le formulaire
  - Création automatique d'un tome avec numéro 1 si la collection est vide
  - Blocage de la collection à une seule entrée (bouton "Ajouter" et boutons "Supprimer" masqués)
  - Pré-remplissage automatique : `latestPublishedIssue = 1` et `latestPublishedIssueComplete = true`
  - Bouton de recherche ISBN sur le tome pour pré-remplir les champs de la série via les API
  - Badge "Tome unique" sur la page de détail
  - Affichage simplifié sur les cartes (pas de détail des tomes)
- **PHP CS Fixer** : Configuration avec ruleset Symfony et règles strictes
  - `declare(strict_types=1)` obligatoire
  - `native_function_invocation` pour préfixer les fonctions natives
  - `ordered_class_elements` pour l'ordre des éléments de classe
  - `ordered_imports` pour le tri alphabétique des imports
- **PHPStan niveau 9** : Analyse statique stricte avec extension Symfony
  - Configuration dans `phpstan.neon`
  - Baseline générée pour les erreurs existantes
- **Tests IsbnLookupService** : Suite de tests unitaires pour le service de recherche ISBN
  - Tests de recherche Google Books et Open Library
  - Tests de fusion des résultats des deux APIs
  - Tests de normalisation ISBN (suppression tirets/espaces)
  - Tests de gestion des erreurs API
- **Champ ISBN** : Ajout du champ ISBN sur les entrées de la bibliothèque (`ComicSeries`)
  - Recherche par ISBN en plus du titre
  - Affichage dans le formulaire d'édition
- **Recherche ISBN via API** : Intégration de Google Books, Open Library et AniList
  - Service `IsbnLookupService` pour interroger les trois API
  - Fusion des résultats (Google Books prioritaire, Open Library puis AniList en complément)
  - AniList enrichit les données pour les mangas (recherche par titre, couvertures HD)
  - Nettoyage intelligent des titres pour AniList (supprime "Tome X", "Vol. X", etc.)
  - Déduction automatique du type (manga, bd, comics) via AniList ou éditeur connu
  - Préremplissage de tous les champs incluant le type
  - Notification flash listant les champs préremplis et les sources utilisées
  - Mise en surbrillance visuelle des champs modifiés par l'API
  - Endpoint `GET /api/isbn-lookup?isbn=XXX`
  - Bouton de recherche dans le formulaire avec préremplissage automatique
- **Métadonnées enrichies** : Nouveaux champs préremplis par les API
  - `author` → `authors` (relation ManyToMany avec entité `Author`)
  - `publisher` : Éditeur
  - `publishedDate` : Date de publication
  - `description` : Résumé/description
  - `coverUrl` : URL de la couverture
  - `type` : Type déduit automatiquement (manga si AniList, sinon basé sur l'éditeur)
- **Entité Author** : Gestion des auteurs comme entités distinctes
  - Table `author` avec nom unique
  - Table de liaison `comic_series_author`
  - Réutilisation des auteurs entre les séries
- **Autocomplétion des auteurs** : Intégration de Symfony UX Autocomplete
  - Champ de type tags avec Tom Select
  - Autocomplétion sur les auteurs existants
  - Création à la volée des nouveaux auteurs
  - Type de formulaire `AuthorAutocompleteType`
- **Affichage des couvertures** : Ajout des images de couverture sur les cartes
  - URL récupérée automatiquement via les API (Google Books / Open Library)
  - Affichage avec ratio 2:3 et lazy loading
- **Upload de couvertures** : Ajout de l'upload manuel d'images de couverture
  - Intégration de VichUploaderBundle pour la gestion des fichiers
  - Interface drag & drop avec Symfony UX Dropzone
  - Formats acceptés : JPEG, PNG, GIF, WebP (max 5 Mo)
  - Stockage dans `public/uploads/covers`
  - Priorité à l'image uploadée sur l'URL externe

### Changed

- **Menu contextuel des cartes** : Les actions Modifier/Supprimer sont masquées derrière un bouton `⋮` — barre d'actions fixe en bas sur mobile, dropdown Headless UI sur desktop. Suppression de la barre d'actions permanente et du skeleton correspondant (#95)
- **Unification Wishlist dans Home** : Suppression de la page Wishlist séparée, les filtres (statut, type, tri, recherche) sont désormais synchronisés avec les paramètres URL sur la page d'accueil. Le lien Wishlist dans la navigation mène vers `/?status=wishlist` (#92)
- **Layout carte des tomes sur mobile** : Remplacement du tableau à 8 colonnes par des cartes empilées dans le formulaire de série sur mobile (< `sm`) — numéro + titre, ISBN avec lookup, checkboxes en grille 2×2, bouton supprimer. Tableau conservé sur desktop (#87)
- **Authentification Google OAuth** : Remplacement de l'authentification email/password par Google OAuth, restreinte à un seul compte Gmail autorisé (#79)
  - Backend : `GoogleLoginController` vérifie le token Google, whitelist email, crée le user automatiquement au premier login
  - Frontend : bouton Google Login via `@react-oauth/google` + `GoogleOAuthProvider`
  - Suppression de `CreateUserCommand`, password hashers, `json_login` firewall
  - Rate limiting (10 req/min), comparaison email case-insensitive
  - Migration : drop `password`, add `google_id` (unique) sur `User`
  - Documentation prod mise à jour (guides NAS, OVH, Dockerfile, docker-compose)
- **Architecture Docker** : Migration Apache → nginx + php-fpm avec build frontend multi-stage
  - `backend/Dockerfile` : passage de `php:8.3-apache` à `php:8.3-fpm`
  - `backend/docker/nginx/Dockerfile` : multi-stage Node.js (build React) → nginx:alpine
  - `backend/docker/nginx/default.conf` : config nginx (SPA fallback, proxy API, cache assets, gzip, sécurité)
  - `docker-compose.prod.yml` : 3 services (nginx, php, db) avec volumes partagés (uploads, media, jwt_keys)
  - Le frontend React est désormais buildé et servi en production (était absent avant)
- **Migration React + API Platform** : Refonte complète de l'architecture
  - **Backend** : Suppression de Twig/Stimulus/AssetMapper, exposition des entités via API Platform 4 (JSON-LD)
  - **Frontend** : Nouveau SPA React 19 + TypeScript + Vite + TanStack Query + Tailwind CSS 4
  - **Auth** : Migration de session/formulaire vers JWT (LexikJWTAuthenticationBundle, TTL 30 jours pour PWA offline)
  - **Structure** : Monorepo `backend/` + `frontend/` avec Makefile racine délégant aux sous-dossiers
  - **PWA** : vite-plugin-pwa avec Workbox runtime caching (NetworkFirst API, CacheFirst covers)
  - Pages : Bibliothèque, Wishlist, Détail série, Formulaire création/édition (lookup ISBN/titre + scanner), Recherche, Corbeille
  - Composants : Layout responsive (nav mobile bottom + header desktop), ComicCard, Filters, ConfirmModal, BarcodeScanner
- **Refactoring SRP/DRY** : Extraction de la logique métier des contrôleurs vers `ComicSeriesService`, ajout de `findSoftDeleted()`/`findSoftDeletedById()` dans `ComicSeriesRepository`, factorisation des réponses lookup dans `ApiController`
- **Lookup parallélisé** : Les appels API des providers sont désormais lancés en parallèle grâce au multiplexage natif de Symfony HttpClient (`curl_multi`)
  - Interface deux phases : `prepareLookup`/`resolveLookup` (et `prepareEnrich`/`resolveEnrich` pour les enrichables)
  - Timeout global configurable (15s par défaut) protège contre les providers lents
  - Chaque provider en erreur est ignoré sans bloquer les autres
  - Nouveau statut `ApiLookupStatus::TIMEOUT` pour les providers dépassant le timeout
- **Priorité par champ dans le lookup** : L'orchestrateur fusionne les résultats par la plus haute priorité *par champ* au lieu du "first wins" global
  - Chaque provider déclare sa priorité via `getFieldPriority(field, ?type)`
  - Wikipedia : description en dernier recours (priorité 10), autres champs priorité 120
  - AniList : thumbnail/isOneShot priorité 200 pour les mangas (remplace le cas spécial hardcodé)
- **Enrichissement Gemini IA** : Intégration de l'API Google Gemini pour enrichir les données des séries
  - Recherche par ISBN ou titre via Gemini 2.0 Flash avec Google Search grounding
  - Enrichissement automatique des champs manquants après lookup classique
  - Structured output JSON pour des réponses fiables et typées
  - Cache filesystem (30 jours) pour économiser les quotas
  - Rate limiting (10 requêtes/minute) pour respecter le plan gratuit
- **Optimisation des couvertures** : Redimensionnement automatique et conversion WebP des images de couverture via LiipImagineBundle
  - Deux variantes : `cover_thumbnail` (300×450, WebP, q80) pour les listes et `cover_medium` (600×900, WebP, q85) pour les fiches détail
  - Extension Twig `cover_image_url()` centralisant la logique cover uploadée / URL externe / pas de cover
  - Invalidation automatique du cache LiipImagine lors de la suppression d'une couverture
  - Attributs `width`/`height` explicites sur les `<img>` pour éviter le CLS (Cumulative Layout Shift)
  - Extension GD avec support WebP/JPEG dans le Dockerfile de production
  - Cache PWA images augmenté de 60 à 200 entrées
- **Soft delete pour les séries** : La suppression d'une série la déplace dans une corbeille au lieu de la supprimer définitivement
  - Package `knplabs/doctrine-behaviors` pour le trait `SoftDeletable` sur `ComicSeries`
  - Filtre SQL Doctrine `SoftDeleteFilter` excluant automatiquement les séries supprimées des requêtes
  - Page **Corbeille** (`/trash`) avec liste des séries supprimées, restauration et suppression définitive
  - Lien Corbeille dans la navigation desktop (top bar) et mobile (bottom nav)
  - Commande `app:purge-deleted` pour purger les séries supprimées depuis plus de N jours (`--days=30`, `--dry-run`)
  - 13 nouveaux tests (entité, filtre, contrôleur, commande)
- **Spinner de chargement sur les boutons API** : Remplace l'icône de recherche par un spinner animé pendant les appels API (ISBN, titre, tome), avec désactivation du bouton
- **Type picker avant scan rapide** : Sélection du type (BD, Comics, Manga, Livre) via bottom sheet avant d'ouvrir le scanner depuis la page d'accueil, permettant un lookup ISBN ciblé par type
- **Scan ISBN via caméra** : Scanner de code-barres ISBN via l'API native BarcodeDetector (Chrome Android)
  - Scan depuis les formulaires (champ ISBN one-shot et tomes)
  - Saisie rapide : bouton scan sur la page d'accueil → pré-remplissage automatique du formulaire
  - Modal plein écran avec animation de balayage
  - 19 tests Vitest pour les contrôleurs barcode-scanner et quick-scan
- **Tests JavaScript (Vitest)** : Suite de tests unitaires pour tout le code JS du projet
  - 139 tests couvrant 3 modules utilitaires et 6 contrôleurs Stimulus
  - Framework Vitest avec jsdom (support ESM natif compatible AssetMapper)
  - Helper Stimulus pour tester les contrôleurs sans bibliothèque tierce
  - Mocks globaux (fetch, localStorage, Cache API, crypto) dans le setup
  - Scripts npm : `npm test` (run) et `npm run test:watch` (watch)
- **ISBN one-shot** : Champ ISBN virtuel affiché directement dans le formulaire quand one-shot est coché, avec masquage de la section tomes
- **Recherche ISBN one-shot** : Bouton de recherche à côté du champ ISBN pour pré-remplir le formulaire via l'API
- **Nombre de tomes parus** : Le champ « Dernier tome paru » est désormais mis à jour systématiquement lors de l'enrichissement, même s'il est déjà renseigné
- **Boutons de formulaire sticky** : Les boutons « Enregistrer » et « Annuler » restent visibles en bas de l'écran lors du scroll sur les formulaires longs
- **Refactoring architecture lookup** : Extraction du service monolithique `IsbnLookupService` en architecture provider-based
  - Interface `LookupProviderInterface` avec méthode `supports()` pour filtrer les providers par mode (ISBN/titre) et type
  - Providers individuels : `GoogleBooksLookup`, `OpenLibraryLookup`, `AniListLookup`, `GeminiLookup`
  - `LookupOrchestrator` coordonne les appels et fusionne les résultats
  - Interface `EnrichableLookupProviderInterface` pour les providers capables d'enrichir des données existantes
  - DTO `LookupResult` (immutable, `JsonSerializable`) remplace les tableaux associatifs
- **Lookup ISBN parallélisé** : Les appels Google Books et Open Library sont désormais lancés en parallèle (lazy responses de Symfony HttpClient), réduisant le temps d'attente de Google + OpenLibrary à ~max(Google, OpenLibrary). Les fetches d'auteurs Open Library sont également parallélisés.
- **Isolation transactionnelle des tests** : Intégration de `dama/doctrine-test-bundle` pour l'isolation automatique des tests
  - Chaque test PHPUnit et scénario Behat (non-JS) est wrappé dans une transaction rollbackée automatiquement
  - Suppression de ~200 lignes de cleanup manuel (`$em->remove()`/`$em->flush()`) dans 11 fichiers de tests
  - Temps d'exécution PHPUnit réduit de ~2min à ~40s (hors Panther)
  - Behat `DatabaseContext` simplifié : seed idempotent pour le profil default, schema reset conservé pour Selenium
- **Élimination de la duplication `isWishlist`** : La propriété `isWishlist` est maintenant calculée à partir du statut
  - Suppression de la colonne `is_wishlist` en base de données (migration Version20260201132408)
  - `isWishlist()` retourne `true` si `status === ComicStatus::WISHLIST`
  - Le repository filtre désormais sur le statut au lieu de la colonne supprimée
  - Le mapper gère la synchronisation entre le champ formulaire et le statut
- **Extraction des utilitaires JavaScript** : Modules partagés pour les contrôleurs Stimulus
  - `assets/utils/string-utils.js` : `normalizeString()`, `escapeHtml()`
  - `assets/utils/cache-utils.js` : `getFromCache()`, `saveToCache()`
  - `assets/utils/card-renderer.js` : `renderCard()` avec options configurables
  - Élimination de ~200 lignes de code dupliqué entre `library_controller.js` et `search_controller.js`
- **Refactoring ComicSeries** : Extraction de méthodes privées pour éliminer la duplication
  - `getMaxTomeNumber(?Closure $filter)` : utilisée par `getCurrentIssue()`, `getLastBought()`, `getLastDownloaded()`
  - `isIssueComplete(?int $issue)` : utilisée par `isCurrentIssueComplete()`, `isLastBoughtComplete()`, `isLastDownloadedComplete()`
- **DTO ComicFilters avec #[MapQueryString]** : Nouveau DTO pour les filtres de recherche
  - Remplace l'extraction manuelle des paramètres dans les contrôleurs
  - Utilise les attributs Symfony pour le mapping automatique des query strings
  - Gestion gracieuse des valeurs enum invalides via `tryFrom()` (retourne null)
- **Architecture formulaires avec DTOs** : Refactoring des formulaires pour utiliser des DTOs au lieu des entités directement
  - Nouveaux DTOs : `ComicSeriesInput`, `TomeInput`, `AuthorInput` dans `src/Dto/Input/`
  - Service `ComicSeriesMapper` pour le mapping bidirectionnel DTO ↔ Entity
  - `AuthorToInputTransformer` pour gérer l'autocomplete avec les DTOs
  - Entités avec types non-nullable alignés sur les contraintes BDD (`title: string`, `number: int`, `name: string`)
  - Utilise `symfony/object-mapper` pour le mapping automatique des propriétés scalaires
  - Les formulaires Symfony Forms manipulent les DTOs, le mapping vers les entités se fait après validation
- **APP_SECRET** : Remplacement du secret codé en dur par un placeholder, à définir dans `.env.local`
- **Version PHP minimum** : Passage de PHP 8.2 à PHP 8.3 pour aligner `composer.json` avec la stack technique du projet
- **PWA** : Migration vers `spomky-labs/pwa-bundle` pour une gestion déclarative de la PWA
  - Manifest généré automatiquement depuis `config/packages/pwa.yaml`
  - Service Worker généré via Workbox (stratégies de cache, Google Fonts, etc.)
  - Icônes générées automatiquement avec versioning
  - Page de fallback offline (`/offline`) affichée quand une page n'est pas en cache
  - Remplacement du contrôleur Stimulus `offline` par `pwa--connection-status` du bundle
  - Suppression des fichiers manuels `public/sw.js` et `assets/manifest.json`
- **Recherche ISBN** : Le type n'est plus déduit automatiquement, il faut le sélectionner avant la recherche
  - Si type = manga, AniList est utilisé pour enrichir les données
  - Sinon, seuls Google Books et Open Library sont interrogés
- **Page de détail** : Affichage détaillé d'une série accessible en cliquant sur la carte
  - Vue formatée avec couverture, badges, auteurs, éditeur et date
  - Section description et statistiques de la collection
  - Grille des tomes avec indicateurs visuels (acheté, sur NAS)
  - Boutons Modifier et Supprimer
  - Lien de retour vers la page précédente
  - Design responsive (mobile et desktop)
- **Entité Tome** : Nouvelle entité pour gérer les tomes individuels d'une série
  - Champs : numéro, titre, ISBN, acheté, téléchargé, sur NAS
  - Upload de couverture par tome via VichUploader
  - Interface dynamique avec ajout/suppression de tomes dans le formulaire
- **Collection de tomes** : Contrôleur Stimulus pour la gestion dynamique des tomes
  - Ajout/suppression de tomes sans rechargement de page
  - Prototype de formulaire pour nouveaux tomes
- **Layout desktop** : Amélioration de l'affichage sur écrans larges
  - Page de détail et formulaire prennent toute la largeur disponible
  - Statistiques de collection sur 4 colonnes
  - Grille des tomes avec indicateurs visuels (acheté, sur NAS)
- **ImportExcelCommand** : Mise à jour pour le nouveau schéma avec tomes
  - Création automatique des tomes pour chaque série
  - Marquage des tomes achetés, téléchargés et sur NAS
  - Option `--dry-run` pour simuler l'import
  - Gestion des valeurs multiples (ex: "3, 4")
- **ComicSeries** : Refactoring des champs de suivi des tomes
  - `publishedCount` → `latestPublishedIssue` (dernier tome paru)
  - `publishedCountComplete` → `latestPublishedIssueComplete` (série terminée)
  - Calcul automatique depuis la collection de tomes :
    - `getCurrentIssue()` : dernier numéro possédé
    - `getLastBought()` : dernier numéro acheté
    - `getLastDownloaded()` : dernier numéro téléchargé
    - `getOwnedTomesNumbers()` : numéros des tomes possédés
    - `getMissingTomesNumbers()` : numéros manquants (1 à latestPublishedIssue)
    - `isCurrentIssueComplete()`, `isLastBoughtComplete()`, `isLastDownloadedComplete()` : comparaison avec latestPublishedIssue
- **Gitignore** : Alignement sur les recommandations Symfony
  - Ajout de `compose.override.yaml` (configurations Docker locales)
  - Ajout de `.symfony.local.yaml` (Symfony CLI)
  - Ajout des dossiers IDE (`.idea/`, `.vscode/`)
  - Réorganisation en sections thématiques
- **Formulaire ComicSeries** : Réorganisation avec les nouveaux champs
- **Repository ComicSeriesRepository** : Recherche étendue à l'ISBN
- **API `/api/comics`** : Inclut les nouveaux champs dans la réponse

### Fixed

- **Persistance des filtres au retour arrière** : Le bouton retour de la page détail utilise désormais `navigate(-1)` au lieu d'un lien statique vers `/`, les filtres de recherche sont préservés lors de la navigation retour (#93)
- **Positionnement de la barre d'actions sticky** : Remplacement de `fixed bottom-14` par `sticky` avec variable CSS `--bottom-nav-h`, la barre est désormais ancrée au contenu et alignée avec le conteneur sur desktop (#91)
- **Placement des boutons d'action** : Bouton destructif (Supprimer) à gauche, bouton principal (Modifier) à droite sur la fiche série, conformément à la convention UX homogène
- **Enum frontend/backend** : Synchronisation des valeurs (COMPLETE→FINISHED, DROPPED→STOPPED, NOVEL→LIVRE, suppression PAUSED/WEBTOON)
- **SoftDeletedComicSeriesProvider** : Ajout de la vérification `isDeleted()` pour la sécurité
- **PHPStan** : Correction de 64+ erreurs (types mixed, annotations `@var`, guards de type)
- **Tests frontend** : Correction de 2 tests ComicForm (clic bouton ISBN avant lookup)
- **Vulnérabilité npm** : Résolution de 4 vulnérabilités high (serialize-javascript RCE) via override
- **Restore/permanent-delete** : Les opérations ne trouvaient pas les entités soft-deleted (filtre Doctrine actif) — corrigé via un provider custom
- **Restore validation** : Le PUT avec body vide déclenchait une erreur de validation (`input: false`)
- **PHPStan baseline** : Nettoyage des entrées référençant des fichiers supprimés lors de la migration
- **Guard null getId()** : Ajout d'un guard dans `ComicSeriesPermanentDeleteProcessor` pour satisfaire PHPStan
- **Cache corbeille** : Invalidation du cache TanStack Query `trash` lors du soft-delete d'une série
- **Warning React controlled input** : Le formulaire d'édition affiche désormais le loader jusqu'à l'initialisation complète des données
- **Couvertures Google Books** : Les couvertures provenant de Google Books sont désormais récupérées en meilleure résolution (`zoom=0`), suppression de l'effet de page cornée (`edge=curl`) et passage en HTTPS
- **Navigation** : Les boutons précédent/suivant du navigateur fonctionnent désormais correctement vers les pages de liste (bibliothèque, wishlist, recherche) — remplacement des `<turbo-frame>` inutilisés par des `<div>` pour ne pas interférer avec la restauration de page Turbo Drive
- **Import Excel** : Les titres avec un article entre parenthèses (`(le)`, `(la)`, `(les)`, `(l')`) sont désormais normalisés lors de l'import (ex: `monde perdu (le)` → `le monde perdu`)
- **Détection one-shot Google Books** : Ne marque plus les séries comme one-shot par défaut quand l'information `seriesInfo` est absente de l'API
- **Cache lookup périmé** : Gestion de la désérialisation d'objets en cache après l'ajout de nouvelles propriétés (évite les erreurs de connexion)
- **Date de publication** : Remplacement du champ texte par un datepicker Flatpickr en français (DD/MM/YYYY) avec bouton d'effacement — supprime l'heure inutile et normalise le format en YYYY-MM-DD
- **Icône de chargement** : Correction du spinner qui se déplaçait en diagonale lors d'une recherche par titre ou ISBN — conflit entre deux `@keyframes spin` (btn-icon vs fab-scan)
- **Lookup ISBN tome** : La recherche ISBN depuis un tome ne remplit plus que les champs pertinents au niveau série (auteurs, éditeur, couverture) — les champs volume-spécifiques (titre, date, description) et le flag one-shot sont ignorés
- **Actions liste** : Les boutons "Supprimer" et "Ajouter à la bibliothèque" fonctionnent depuis la liste (tokens CSRF inclus dans l'API)
- **Tests Panther flaky** : Correction des 5 tests `OneShotFormTest`/`TomeManagementTest` qui échouaient aléatoirement
  - Migration de `KernelTestCase` vers `TestCase` pour éviter l'isolation transactionnelle DAMA (invisible pour Selenium)
  - Nouveau trait `PantherTestHelper` mutualisant driver, login et exécution SQL entre les 3 fichiers de tests Panther
  - Remplacement des `usleep()`/`sleep()` par des WebDriver waits explicites
- **Gestion des erreurs Doctrine** : Les erreurs de base de données dans les contrôleurs affichent maintenant un message flash
  - Try/catch sur `DriverException` dans `ComicController::new()`, `edit()` et `delete()`
  - Message d'erreur utilisateur au lieu d'une erreur 500
- **Feedback CSRF invalide** : Message flash d'erreur affiché quand le token CSRF est invalide
  - `ComicController::delete()` et `toLibrary()` affichent "Token de sécurité invalide"
  - L'utilisateur sait maintenant que son action n'a pas été effectuée
- **Validation email doublon dans CreateUserCommand** : Message d'erreur clair si l'email existe
  - Utilisation du ValidatorInterface pour vérifier les contraintes de l'entité
  - Réutilise la contrainte UniqueEntity existante sur User
  - Retourne FAILURE au lieu de laisser remonter une exception Doctrine
- **Gestion fichier Excel corrompu** : Message d'erreur clair si le fichier ne peut pas être lu
  - Try/catch sur `Reader\Exception` dans `ImportExcelCommand`
  - Affiche "Impossible de lire le fichier Excel" avec le message d'erreur original
- **Performance API PWA** : Correction du problème N+1 query dans `findAllForApi()`
  - Ajout d'un eager loading avec `leftJoin` + `addSelect` pour les relations `tomes` et `authors`
  - Réduit les requêtes SQL de ~3N à 1 pour l'endpoint `/api/comics`
- **Gestion des erreurs IsbnLookupService** : Remplacement des `catch (\Throwable)` par des catches spécifiques
  - `TransportExceptionInterface` : erreurs réseau (timeout, DNS) → log error
  - `ClientExceptionInterface/ServerExceptionInterface` : erreurs HTTP (4xx, 5xx) → log warning
  - `DecodingExceptionInterface` : réponses JSON invalides → log error
  - Permet un monitoring plus précis des problèmes d'intégration API
  - Ajout du logging dans `fetchOpenLibraryAuthor()` qui avalait les exceptions silencieusement
- **Indicateur hors ligne persistant** : Correction de l'affichage de l'indicateur "Mode hors ligne" après retour depuis la page offline
  - L'indicateur disparaissait après navigation vers une page non cachée puis retour sur une page cachée
  - Ajout d'un gestionnaire `popstate` pour gérer le retour arrière en mode offline
  - Fonction `updateOfflineIndicator()` pour réinitialiser manuellement l'indicateur après injection HTML
  - 4 nouveaux tests Playwright couvrant les scénarios de navigation offline
- **Google Books API** : Fusion des données de plusieurs résultats
  - Auparavant, seul le premier résultat était utilisé (parfois incomplet)
  - Maintenant, les données sont fusionnées depuis tous les résultats disponibles
  - Corrige le cas où les auteurs manquaient (ex: ISBN 2800152850)

### Removed

- **Code mort** : Suppression de `ComicFilters.php`, `AppFixtures.php`, méthodes inutilisées dans `ComicSeriesRepository` et `LookupResult::mergeWith()`
- **Twig/Stimulus/AssetMapper** : Templates, contrôleurs Stimulus, formulaires Symfony Forms, Behat, Panther, Playwright
- **Packages** : symfony/ux-*, symfony/asset-mapper, symfony/stimulus-bundle, symfony/twig-bundle, symfony/form, spomky-labs/pwa-bundle, dbrekelmans/bdi, friends-of-behat/*, knpuniversity/oauth2-client-bundle
- **Wizard multi-étapes** : Suppression du formulaire multi-étapes (FormFlow) pour la création de séries
  - La création utilise désormais le même formulaire standard que l'édition
  - Suppression de `ComicSeriesFlowType`, des 6 types d'étape, du template `_flow_form.html.twig`
  - Suppression du code `sessionStorage` dans le contrôleur Stimulus (plus de persistance inter-étapes)
  - Suppression des styles CSS du wizard (`.wizard-*`, `.step-description`, `.form-separator`)
- **Code mort supprimé** : Nettoyage du code non utilisé
  - `assets/controllers/hello_controller.js` : template par défaut Stimulus non utilisé
  - `ComicSeriesRepository::findLibrary()` et `::findWishlist()` : méthodes dépréciées remplacées par `findWithFilters()`
- **Onglet Recherche** : Suppression du lien "Recherche" dans la navigation (desktop et mobile)
  - La recherche est maintenant intégrée dans les pages Bibliothèque et Wishlist via les filtres
- **ComicSeries** : Champs déplacés vers l'entité Tome ou calculés dynamiquement
  - `currentIssue`, `currentIssueComplete`
  - `lastBought`, `lastBoughtComplete`
  - `lastDownloaded`, `lastDownloadedComplete`
  - `missingIssues`, `ownedIssues`
  - `onNas`, `isbn`
- Contrôleur Stimulus custom `tags_input_controller.js` (remplacé par Symfony UX Autocomplete)
- `AuthorsToStringTransformer` (remplacé par le type Autocomplete)
- Endpoint `GET /api/authors/search` (géré par Symfony UX Autocomplete)

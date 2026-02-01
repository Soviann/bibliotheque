# Changelog

Toutes les modifications notables de ce projet sont documentées dans ce fichier.

Le format est basé sur [Keep a Changelog](https://keepachangelog.com/fr/1.1.0/),
et ce projet adhère au [Versionnement Sémantique](https://semver.org/lang/fr/).

## [Unreleased]

### Added

- **Filtrage et recherche hors ligne** : Toute l'interface de filtrage fonctionne sans requête HTTP
  - Nouveau contrôleur Stimulus `library_controller.js` pour les pages Bibliothèque et Wishlist
  - Filtrage côté client par type, statut, NAS, tri et recherche texte
  - Contrôleur `search_controller.js` pour la page de recherche dédiée
  - Chargement des données depuis `/api/comics` avec cache localStorage
  - Recherche instantanée sur titre, auteurs et description
  - Normalisation des accents pour une recherche insensible aux diacritiques
  - Fonctionne en mode offline grâce au cache local
  - Ajout des champs `hasNasTome`, `isOneShot`, `statusLabel` et `typeLabel` dans l'API

### Removed

- **Onglet Recherche** : Suppression du lien "Recherche" dans la navigation (desktop et mobile)
  - La recherche est maintenant intégrée dans les pages Bibliothèque et Wishlist via les filtres

### Fixed

- **Indicateur hors ligne persistant** : Correction de l'affichage de l'indicateur "Mode hors ligne" après retour depuis la page offline
  - L'indicateur disparaissait après navigation vers une page non cachée puis retour sur une page cachée
  - Ajout d'un gestionnaire `popstate` pour gérer le retour arrière en mode offline
  - Fonction `updateOfflineIndicator()` pour réinitialiser manuellement l'indicateur après injection HTML
  - 4 nouveaux tests Playwright couvrant les scénarios de navigation offline

### Added

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

### Changed

- **APP_SECRET** : Remplacement du secret codé en dur par un placeholder, à définir dans `.env.local`

- **Version PHP minimum** : Passage de PHP 8.2 à PHP 8.3 pour aligner `composer.json` avec la stack technique du projet

- **PWA** : Migration vers `spomky-labs/pwa-bundle` pour une gestion déclarative de la PWA
  - Manifest généré automatiquement depuis `config/packages/pwa.yaml`
  - Service Worker généré via Workbox (stratégies de cache, Google Fonts, etc.)
  - Icônes générées automatiquement avec versioning
  - Page de fallback offline (`/offline`) affichée quand une page n'est pas en cache
  - Remplacement du contrôleur Stimulus `offline` par `pwa--connection-status` du bundle
  - Suppression des fichiers manuels `public/sw.js` et `assets/manifest.json`

### Added

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

### Changed

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

### Changed

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

### Removed

- **ComicSeries** : Champs déplacés vers l'entité Tome ou calculés dynamiquement
  - `currentIssue`, `currentIssueComplete`
  - `lastBought`, `lastBoughtComplete`
  - `lastDownloaded`, `lastDownloadedComplete`
  - `missingIssues`, `ownedIssues`
  - `onNas`, `isbn`

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

- **Gitignore** : Alignement sur les recommandations Symfony
  - Ajout de `compose.override.yaml` (configurations Docker locales)
  - Ajout de `.symfony.local.yaml` (Symfony CLI)
  - Ajout des dossiers IDE (`.idea/`, `.vscode/`)
  - Réorganisation en sections thématiques
- **Formulaire ComicSeries** : Réorganisation avec les nouveaux champs
- **Repository ComicSeriesRepository** : Recherche étendue à l'ISBN
- **API `/api/comics`** : Inclut les nouveaux champs dans la réponse

### Removed

- Contrôleur Stimulus custom `tags_input_controller.js` (remplacé par Symfony UX Autocomplete)
- `AuthorsToStringTransformer` (remplacé par le type Autocomplete)
- Endpoint `GET /api/authors/search` (géré par Symfony UX Autocomplete)

### Fixed

- **Google Books API** : Fusion des données de plusieurs résultats
  - Auparavant, seul le premier résultat était utilisé (parfois incomplet)
  - Maintenant, les données sont fusionnées depuis tous les résultats disponibles
  - Corrige le cas où les auteurs manquaient (ex: ISBN 2800152850)

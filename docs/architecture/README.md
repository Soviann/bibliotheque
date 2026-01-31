# Architecture

Ce guide présente l'architecture technique de Ma Bibliotheque BD.

---

## Vue d'ensemble

```
                                    ┌─────────────────────┐
                                    │    Navigateur       │
                                    │  (PWA + Stimulus)   │
                                    └──────────┬──────────┘
                                               │
                                               ▼
┌──────────────────────────────────────────────────────────────────────┐
│                           Symfony 7.4                                │
├──────────────────────────────────────────────────────────────────────┤
│  Controllers       │  Services        │  Forms           │  Twig    │
│  - Home            │  - IsbnLookup    │  - ComicSeries   │  Pages   │
│  - Comic           │                  │  - Tome          │  Compos  │
│  - Search          │                  │  - Author        │          │
│  - Wishlist        │                  │                  │          │
│  - Api             │                  │                  │          │
│  - Security        │                  │                  │          │
├──────────────────────────────────────────────────────────────────────┤
│                        Doctrine ORM                                  │
├──────────────────────────────────────────────────────────────────────┤
│  ComicSeries  │  Tome  │  Author  │  User                           │
└───────────────┴────────┴──────────┴──────────────────────────────────┘
                                    │
                                    ▼
                          ┌─────────────────┐
                          │   MariaDB       │
                          └─────────────────┘
```

---

## Structure des dossiers

```
bibliotheque/
├── assets/                  # Frontend
│   ├── controllers/         # Contrôleurs Stimulus
│   │   ├── comic_form_controller.js
│   │   ├── csrf_protection_controller.js
│   │   ├── search_controller.js
│   │   └── tomes_collection_controller.js
│   └── styles/              # CSS Material Design
│       └── app.css
│
├── config/                  # Configuration Symfony
│   ├── packages/            # Configuration des bundles
│   │   ├── doctrine.yaml
│   │   ├── pwa.yaml         # Configuration PWA
│   │   ├── security.yaml
│   │   └── vich_uploader.yaml
│   └── routes.yaml
│
├── migrations/              # Migrations Doctrine
│
├── public/                  # Point d'entrée web
│   ├── index.php
│   ├── sw.js               # Service Worker (généré)
│   └── uploads/            # Fichiers uploadés
│       └── covers/         # Couvertures
│
├── src/
│   ├── Command/            # Commandes console
│   │   ├── CreateUserCommand.php
│   │   └── ImportExcelCommand.php
│   │
│   ├── Controller/         # Contrôleurs HTTP
│   │   ├── ApiController.php
│   │   ├── ComicController.php
│   │   ├── HomeController.php
│   │   ├── OfflineController.php
│   │   ├── SearchController.php
│   │   ├── SecurityController.php
│   │   └── WishlistController.php
│   │
│   ├── Entity/             # Entités Doctrine
│   │   ├── Author.php
│   │   ├── ComicSeries.php
│   │   ├── Tome.php
│   │   └── User.php
│   │
│   ├── Enum/               # Enums PHP
│   │   ├── ComicStatus.php
│   │   └── ComicType.php
│   │
│   ├── Form/               # Types de formulaire
│   │   ├── AuthorAutocompleteType.php
│   │   ├── ComicSeriesType.php
│   │   └── TomeType.php
│   │
│   ├── Repository/         # Repositories Doctrine
│   │   ├── AuthorRepository.php
│   │   ├── ComicSeriesRepository.php
│   │   ├── TomeRepository.php
│   │   └── UserRepository.php
│   │
│   └── Service/            # Services métier
│       └── IsbnLookupService.php
│
├── templates/              # Templates Twig
│   ├── base.html.twig
│   ├── comic/              # CRUD séries
│   ├── components/         # Composants réutilisables
│   ├── home/               # Page d'accueil
│   ├── offline/            # Page hors-ligne
│   ├── search/             # Recherche
│   ├── security/           # Login
│   └── wishlist/           # Liste de souhaits
│
└── tests/                  # Tests
    ├── Behat/              # Tests Behat
    ├── Controller/         # Tests fonctionnels
    ├── Entity/             # Tests unitaires entités
    ├── Enum/               # Tests unitaires enums
    ├── Form/               # Tests unitaires formulaires
    ├── Panther/            # Tests navigateur
    ├── Repository/         # Tests intégration
    └── Service/            # Tests unitaires services
```

---

## Couches applicatives

### Contrôleurs

Gèrent les requêtes HTTP et orchestrent la logique :

| Contrôleur | Responsabilité |
|------------|----------------|
| `HomeController` | Page d'accueil, liste des séries |
| `ComicController` | CRUD des séries (create, read, update, delete) |
| `SearchController` | Recherche textuelle |
| `WishlistController` | Liste de souhaits |
| `ApiController` | Endpoints JSON |
| `SecurityController` | Authentification |
| `OfflineController` | Page de fallback PWA |

### Services

Encapsulent la logique métier réutilisable :

| Service | Responsabilité |
|---------|----------------|
| `IsbnLookupService` | Recherche ISBN via APIs externes |

[Documentation complète des services](services.md)

### Repositories

Accès aux données avec requêtes personnalisées :

| Repository | Méthodes clés |
|------------|---------------|
| `ComicSeriesRepository` | `findWithFilters()`, `search()`, `findAllForApi()` |
| `AuthorRepository` | `findOrCreate()`, `findOrCreateMultiple()` |

### Entités

Modèle de données Doctrine :

| Entité | Description |
|--------|-------------|
| `ComicSeries` | Série BD/Comics/Manga/Livre |
| `Tome` | Volume individuel d'une série |
| `Author` | Auteur (scénariste, dessinateur) |
| `User` | Utilisateur authentifié |

[Documentation complète des entités](entites.md)

---

## Patterns utilisés

### Repository Pattern

Les repositories encapsulent l'accès aux données :

```php
// Recherche avec filtres
$series = $comicSeriesRepository->findWithFilters([
    'type' => 'manga',
    'status' => 'buying',
    'search' => 'one piece',
]);
```

### Form Types

Les formulaires Symfony gèrent la validation et le binding :

```php
// Formulaire avec collection de tomes
$form = $this->createForm(ComicSeriesType::class, $series);
$form->handleRequest($request);
```

### Enums PHP 8.1

Les types énumérés pour les valeurs fixes :

```php
enum ComicStatus: string {
    case BUYING = 'buying';
    case FINISHED = 'finished';
    case STOPPED = 'stopped';
    case WISHLIST = 'wishlist';
}
```

---

## Frontend

### Symfony UX

L'application utilise les composants Symfony UX :

| Package | Usage |
|---------|-------|
| `ux-turbo` | Navigation sans rechargement |
| `ux-stimulus` | Contrôleurs JavaScript |
| `ux-autocomplete` | Autocomplétion des auteurs |
| `ux-dropzone` | Upload drag & drop |

### AssetMapper

Gestion des assets sans Node.js :

- Fichiers dans `assets/`
- Mapping dans `importmap.php`
- Compilation automatique

### Contrôleurs Stimulus

| Contrôleur | Responsabilité |
|------------|----------------|
| `comic_form_controller` | Recherche ISBN/titre, préremplissage |
| `tomes_collection_controller` | Ajout/suppression dynamique de tomes |
| `search_controller` | Barre de recherche |
| `csrf_protection_controller` | Tokens CSRF |

---

## Intégrations externes

### APIs de métadonnées

| API | Usage | Type |
|-----|-------|------|
| Google Books | ISBN, titre | REST |
| Open Library | ISBN (enrichissement) | REST |
| AniList | Mangas (titre, couverture) | GraphQL |

### Bundles Symfony

| Bundle | Usage |
|--------|-------|
| `vich/uploader-bundle` | Upload de fichiers |
| `spomky-labs/pwa-bundle` | PWA (manifest, service worker) |

---

## Sécurité

### Authentification

- Login/password classique
- Session PHP
- Remember me optionnel

### Autorisations

Toutes les routes (sauf `/login` et `/offline`) nécessitent une authentification.

### Protection CSRF

Tokens CSRF sur tous les formulaires et actions destructives.

---

## Performance

### Cache

| Type | Outil | Usage |
|------|-------|-------|
| HTTP | Service Worker | Assets, pages, API |
| Application | Symfony Cache | Metadata Doctrine |
| ORM | Doctrine Query Cache | Requêtes fréquentes |

### Optimisations

- Lazy loading des images
- Prefetch des liens
- Assets versionnés

---

## Étapes suivantes

- [Entités Doctrine](entites.md) - Modèle de données complet
- [Services](services.md) - Services métier
- [API REST](../api/README.md) - Endpoints disponibles

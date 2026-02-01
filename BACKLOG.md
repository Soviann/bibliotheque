# Backlog - Revue de code

Liste des tâches issues de la revue de code complète du 2026-02-01.

## Sprint 1 — Critiques (Performance & Erreurs silencieuses)

### 1.1 ~~Corriger le N+1 query dans `findAllForApi()`~~ ✅

- **Priorité** : CRITIQUE
- **Fichier** : `src/Repository/ComicSeriesRepository.php:162-205`
- **Description** : Ajouter un eager loading avec `leftJoin` + `addSelect` pour les relations `tomes` et `authors` dans la méthode `findAllForApi()`. Cette méthode est utilisée par l'API PWA et génère actuellement ~3N requêtes SQL.
- **Statut** : ✅ Terminé

### 1.2 ~~Remplacer les `catch (\Throwable)` par des catches spécifiques dans IsbnLookupService~~ ✅

- **Priorité** : CRITIQUE
- **Fichier** : `src/Service/IsbnLookupService.php` (lignes 149, 234-241, 424-431, 582-590)
- **Description** : Remplacer les blocs `catch (\Throwable)` par des catches spécifiques :
  - `TransportExceptionInterface` pour les erreurs réseau (log error + throw exception custom)
  - `HttpExceptionInterface` pour les erreurs HTTP (log warning + return null)
  - `\JsonException` pour les réponses invalides (log error + throw exception custom)
  - Créer une exception custom `IsbnLookupException` si nécessaire.
- **Statut** : ✅ Terminé - Catches spécifiques ajoutés sans exception custom (pas nécessaire car les méthodes retournent null en cas d'erreur)

### 1.3 ~~Ajouter le logging dans `fetchOpenLibraryAuthor()`~~ ✅

- **Priorité** : CRITIQUE
- **Fichier** : `src/Service/IsbnLookupService.php:447-449`
- **Description** : Le catch block actuel avale silencieusement toutes les exceptions sans aucun log. Ajouter un log `debug` ou `warning` pour tracer les erreurs de récupération des auteurs Open Library.
- **Statut** : ✅ Terminé - Logs debug ajoutés avec catches spécifiques

---

## Sprint 2 — Sécurité

### 2.1 ~~Ajouter le rate limiting sur l'authentification~~ ✅

- **Priorité** : HAUTE
- **Fichier** : `config/packages/security.yaml`
- **Description** : Ajouter la configuration `login_throttling` dans le firewall `main` avec `max_attempts: 5` et `interval: '15 minutes'`.
- **Statut** : ✅ Terminé - Ajout de symfony/rate-limiter et configuration login_throttling

### 2.2 ~~Empêcher le chargement des fixtures en production~~ ✅

- **Priorité** : HAUTE
- **Fichier** : `src/DataFixtures/UserFixtures.php`
- **Description** : Ajouter une vérification au début de la méthode `load()` qui lance une exception si `$_ENV['APP_ENV'] === 'prod'`. Les fixtures contiennent des credentials en dur (`test@example.com` / `password`).
- **Statut** : ✅ Terminé - Injection de `%kernel.environment%` via Autowire, warning si hors env test

### 2.3 ~~Corriger la vulnérabilité Open Redirect via Referer~~ ✅

- **Priorité** : MOYENNE
- **Fichiers** : `templates/comic/show.html.twig:7`, `templates/comic/_form.html.twig:209`
- **Description** : Le header `Referer` est utilisé directement sans validation. Créer une Twig Extension ou une méthode helper qui valide que l'URL de referer commence par le host de l'application avant de l'utiliser.
- **Statut** : ✅ Terminé - Nouvelle extension Twig `safe_referer()` avec 9 tests unitaires

### 2.4 ~~Ajouter `UniqueEntity` sur l'entité User~~ ✅

- **Priorité** : MOYENNE
- **Fichier** : `src/Entity/User.php`
- **Description** : Ajouter l'attribut `#[UniqueEntity('email', message: 'Cet email est déjà utilisé.')]` sur la classe User pour avoir une validation Symfony en plus de la contrainte base de données.
- **Statut** : ✅ Terminé

### 2.5 ~~Ajouter les headers de sécurité HTTP~~ ✅

- **Priorité** : MOYENNE
- **Action** : Installer `nelmio/security-bundle` ou créer un EventSubscriber
- **Description** : Ajouter les headers : `X-Content-Type-Options: nosniff`, `X-Frame-Options: DENY`, `Referrer-Policy`, et une CSP basique.
- **Statut** : ✅ Terminé - Installation de nelmio/security-bundle avec configuration CSP

---

## Sprint 3 — Robustesse (Gestion des erreurs)

### 3.1 ~~Gérer les exceptions Doctrine dans les contrôleurs~~ ✅

- **Priorité** : HAUTE
- **Fichier** : `src/Controller/ComicController.php` (méthodes `new`, `edit`, `delete`)
- **Description** : Envelopper les appels `$entityManager->flush()` dans des try/catch pour gérer `UniqueConstraintViolationException` et afficher un message flash approprié au lieu d'une erreur 500.
- **Statut** : ✅ Terminé - Try/catch sur DriverException dans new(), edit() et delete() avec message flash d'erreur

### 3.2 ~~Améliorer le feedback pour CSRF invalide~~ ✅

- **Priorité** : MOYENNE
- **Fichier** : `src/Controller/ComicController.php` (méthodes `delete`, `toLibrary`)
- **Description** : Quand le token CSRF est invalide, ajouter un message flash d'erreur au lieu de rediriger silencieusement. L'utilisateur doit savoir que son action n'a pas été effectuée.
- **Statut** : ✅ Terminé - Message flash d'erreur "Token de sécurité invalide" avec early return

### 3.3 ~~Vérifier les doublons dans CreateUserCommand~~ ✅

- **Priorité** : MOYENNE
- **Fichier** : `src/Command/CreateUserCommand.php`
- **Description** : Avant de créer l'utilisateur, vérifier si l'email existe déjà avec `UserRepository::findOneBy()`. Afficher un message d'erreur clair au lieu de laisser une exception Doctrine remonter.
- **Statut** : ✅ Terminé - Utilisation du Validator Symfony pour vérifier les contraintes UniqueEntity

### 3.4 ~~Gérer les erreurs de lecture Excel dans ImportExcelCommand~~ ✅

- **Priorité** : MOYENNE
- **Fichier** : `src/Command/ImportExcelCommand.php:79`
- **Description** : Envelopper `IOFactory::load($filePath)` dans un try/catch pour gérer `SpreadsheetReaderException` et afficher un message d'erreur lisible si le fichier est corrompu ou dans un format non supporté.
- **Statut** : ✅ Terminé - Try/catch sur Reader\Exception avec message d'erreur clair

### 3.5 ~~Corriger le null-safe sur `getUpdatedAt()` dans findAllForApi~~ ❌

- **Priorité** : BASSE
- **Fichier** : `src/Repository/ComicSeriesRepository.php:200`
- **Description** : Remplacer `$comic->getUpdatedAt()->format('c')` par `$comic->getUpdatedAt()?->format('c')` pour éviter une erreur si `updatedAt` est null.
- **Statut** : ❌ Non applicable - La colonne `updated_at` est `NOT NULL` en BDD, le constructeur initialise toujours la propriété, et PHPStan confirme que `getUpdatedAt()` retourne `\DateTimeImmutable` (non nullable). L'opérateur null-safe serait du code mort.

---

## Sprint 4 — Types & Cohérence

### 4.1 ~~Aligner les types nullables avec les contraintes de validation~~ ✅

- **Priorité** : HAUTE
- **Fichiers** : `src/Entity/ComicSeries.php`, `src/Entity/Tome.php`, `src/Entity/Author.php`
- **Description** : Corriger les incohérences entre types PHP et contraintes :
  - `ComicSeries.$title: ?string` → `string` (car `Assert\NotBlank`)
  - `Tome.$number: ?int` → `int` (car `Assert\NotNull`)
  - `Author.$name: ?string` → `string` (car `Assert\NotBlank`)
- **Statut** : ✅ Terminé via implémentation d'un pattern DTO avec ObjectMapper :
  1. Création de DTOs (`ComicSeriesInput`, `TomeInput`, `AuthorInput`) avec types non-nullable
  2. Formulaires utilisent les DTOs (`data_class: ComicSeriesInput`)
  3. Service `ComicSeriesMapper` pour mapping DTO ↔ Entity
  4. `AuthorToInputTransformer` pour l'autocomplete (Entity ↔ DTO)
  5. Entités modifiées avec types non-nullable alignés sur les contraintes BDD
  6. Note : `Tome.$comicSeries` reste nullable (pattern bidirectionnel `orphanRemoval`)

### 4.2 ~~Utiliser l'enum ComicType dans IsbnLookupService~~ ✅

- **Priorité** : MOYENNE
- **Fichier** : `src/Service/IsbnLookupService.php`
- **Description** : Changer le paramètre `?string $type` en `?ComicType $type` dans les méthodes `lookup()` et `lookupByTitle()`. Mettre à jour les appels dans `ApiController`.
- **Statut** : ✅ Terminé - Paramètres changés en `?ComicType`, comparaisons avec `ComicType::MANGA`, ApiController convertit la string via `ComicType::tryFrom()`

### 4.3 ~~Supprimer la suppression PHPStan injustifiée~~ ✅

- **Priorité** : BASSE
- **Fichier** : `src/Entity/Tome.php:21`
- **Description** : Retirer le commentaire `/** @phpstan-ignore-next-line */` sur la propriété `$id`. Aucune autre entité n'a besoin de cette suppression.
- **Statut** : ✅ Terminé - Commentaire supprimé, erreur ajoutée au baseline PHPStan

### 4.4 ~~Éliminer la duplication `isWishlist` / `ComicStatus::WISHLIST`~~ ✅

- **Priorité** : BASSE
- **Fichier** : `src/Entity/ComicSeries.php`
- **Description** : Supprimer la propriété `$isWishlist` et la remplacer par une méthode calculée : `public function isWishlist(): bool { return ComicStatus::WISHLIST === $this->status; }`. Créer une migration pour supprimer la colonne.
- **Statut** : ✅ Terminé - Propriété supprimée, méthode calculée à partir du statut, migration créée (Version20260201132408), repository et mapper mis à jour

---

## Sprint 5 — Simplification & Code mort

### 5.1 Extraire les utilitaires JavaScript partagés

- **Priorité** : MOYENNE
- **Fichiers** : `assets/controllers/library_controller.js`, `assets/controllers/search_controller.js`
- **Description** : Créer des modules partagés :
  - `assets/utils/string-utils.js` : `normalizeString()`, `escapeHtml()`
  - `assets/utils/cache-utils.js` : `getFromCache()`, `saveToCache()`
  - `assets/utils/card-renderer.js` : `renderCard()`
  - Modifier les contrôleurs pour importer ces fonctions.

### 5.2 Refactorer les méthodes dupliquées dans ComicSeries

- **Priorité** : MOYENNE
- **Fichier** : `src/Entity/ComicSeries.php`
- **Description** : Extraire une méthode privée `getMaxTomeNumber(?callable $filter): ?int` et l'utiliser dans `getCurrentIssue()`, `getLastBought()`, `getLastDownloaded()`. Extraire une méthode privée `isIssueComplete(?int $issue): bool` et l'utiliser dans `isCurrentIssueComplete()`, `isLastBoughtComplete()`, `isLastDownloadedComplete()`.

### 5.3 Créer un service FilterExtractor

- **Priorité** : BASSE
- **Fichiers** : `src/Controller/HomeController.php`, `src/Controller/WishlistController.php`
- **Description** : Extraire la logique d'extraction des filtres depuis la requête (`type`, `nas`, `search`, `sort`) dans un service `FilterExtractor` pour éviter la duplication entre les deux contrôleurs.

### 5.4 Supprimer le code mort

- **Priorité** : BASSE
- **Fichiers** :
  - `assets/controllers/hello_controller.js` (template par défaut, non utilisé)
  - `src/Repository/ComicSeriesRepository.php` : méthodes `findLibrary()` et `findWishlist()` marquées `@deprecated`
- **Description** : Vérifier qu'aucune référence n'existe avant suppression.

---

## Récapitulatif

| Sprint | Nb tâches | Effort estimé | Focus |
|--------|-----------|---------------|-------|
| **1** | 3 | 1-2 jours | Performance & erreurs critiques |
| **2** | 5 | 1 jour | Sécurité |
| **3** | 5 | 1-2 jours | Robustesse |
| **4** | 4 | 1 jour | Types & cohérence |
| **5** | 4 | 1 jour | Simplification |

---

## Utilisation

Pour traiter une tâche dans une nouvelle session Claude Code :

```
Traite la tâche 1.1 du BACKLOG.md
```

ou

```
Traite le sprint 1 du BACKLOG.md
```

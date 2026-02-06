# Tests

Ce guide explique comment exécuter et écrire des tests pour l'application.

---

## Vue d'ensemble

L'application dispose de plusieurs types de tests :

| Type | Framework | Dossier | Description |
|------|-----------|---------|-------------|
| Unitaires | PHPUnit | `tests/Entity/`, `tests/Enum/`, `tests/Service/`, `tests/Dto/` | Logique métier isolée |
| Formulaires | PHPUnit | `tests/Form/` | Types de formulaire et transformers |
| Fonctionnels | PHPUnit | `tests/Controller/` | Contrôleurs HTTP |
| Intégration | PHPUnit | `tests/Repository/`, `tests/Command/`, `tests/DataFixtures/` | Repositories, commandes, fixtures avec BDD |
| Sécurité | PHPUnit | `tests/Security/` | En-têtes de sécurité HTTP |
| End-to-end | Behat | `features/` | Scénarios utilisateur en Gherkin |
| Navigateur | WebDriver | `tests/Panther/` | Tests JavaScript/PWA avec Chrome distant |

**Total** : 345 tests PHPUnit (872 assertions) + 27 scénarios Behat (218 steps)

---

## Exécuter les tests

### Tous les tests PHPUnit

```bash
ddev exec bin/phpunit
```

### Tous les tests Behat

```bash
# Profil default (session BrowserKit, sans JavaScript)
ddev exec vendor/bin/behat

# Profil javascript (session Selenium, avec Chrome distant)
ddev exec vendor/bin/behat --profile=javascript
```

### Un fichier de test spécifique

```bash
ddev exec bin/phpunit tests/Entity/ComicSeriesTest.php
ddev exec vendor/bin/behat features/authentication.feature
```

### Un test spécifique

```bash
ddev exec bin/phpunit --filter testGetCurrentIssue
```

### Par dossier

```bash
# Tests d'entités
ddev exec bin/phpunit tests/Entity/

# Tests de contrôleurs
ddev exec bin/phpunit tests/Controller/

# Tests navigateur (WebDriver + Chrome)
ddev exec bin/phpunit tests/Panther/
```

### Avec couverture de code

```bash
ddev exec bin/phpunit --coverage-html var/coverage
```

Ouvrez `var/coverage/index.html` dans un navigateur.

---

## Tests unitaires

### Entités

Testent la logique métier des entités Doctrine.

**Fichiers** :
- `tests/Entity/ComicSeriesTest.php` (50+ tests)
- `tests/Entity/TomeTest.php`
- `tests/Entity/AuthorTest.php`
- `tests/Entity/UserTest.php`

**Exemple** :

```php
// tests/Entity/ComicSeriesTest.php

public function testGetCurrentIssueReturnsMaxNumber(): void
{
    $series = new ComicSeries();
    $series->addTome($this->createTome(1));
    $series->addTome($this->createTome(3));
    $series->addTome($this->createTome(2));

    $this->assertEquals(3, $series->getCurrentIssue());
}

public function testGetMissingTomesNumbers(): void
{
    $series = new ComicSeries();
    $series->setLatestPublishedIssue(5);
    $series->addTome($this->createTome(1));
    $series->addTome($this->createTome(3));

    $this->assertEquals([2, 4, 5], $series->getMissingTomesNumbers());
}
```

### Enums

Testent les valeurs et labels des enums PHP.

**Fichiers** :
- `tests/Enum/ComicStatusTest.php`
- `tests/Enum/ComicTypeTest.php`

**Exemple** :

```php
public function testComicStatusValues(): void
{
    $this->assertEquals('buying', ComicStatus::BUYING->value);
    $this->assertEquals('En cours d\'achat', ComicStatus::BUYING->getLabel());
}
```

### Services

Testent les services avec mocks HTTP.

**Fichiers** :
- `tests/Service/IsbnLookupServiceTest.php`
- `tests/Service/ComicSeriesMapperTest.php`

**Exemple** :

```php
public function testLookupReturnsDataFromGoogleBooks(): void
{
    $mockResponse = new MockResponse(json_encode([
        'items' => [[
            'volumeInfo' => [
                'title' => 'Test Book',
                'authors' => ['Test Author'],
            ]
        ]]
    ]));

    $httpClient = new MockHttpClient($mockResponse);
    $service = new IsbnLookupService($httpClient);

    $result = $service->lookup('9781234567890');

    $this->assertEquals('Test Book', $result['title']);
    $this->assertEquals(['Test Author'], $result['authors']);
}
```

### DTOs

Testent les objets de transfert de données.

**Fichier** : `tests/Dto/ComicFiltersTest.php`

---

## Tests de formulaires

Testent les types de formulaire Symfony et les data transformers.

**Fichiers** :
- `tests/Form/ComicSeriesTypeTest.php`
- `tests/Form/TomeTypeTest.php`
- `tests/Form/AuthorAutocompleteTypeTest.php`
- `tests/Form/DataTransformer/AuthorToInputTransformerTest.php`

**Exemple** :

```php
public function testSubmitValidData(): void
{
    $formData = [
        'title' => 'Test Series',
        'type' => 'manga',
        'status' => 'buying',
    ];

    $series = new ComicSeries();
    $form = $this->factory->create(ComicSeriesType::class, $series);
    $form->submit($formData);

    $this->assertTrue($form->isSynchronized());
    $this->assertEquals('Test Series', $series->getTitle());
}
```

---

## Tests fonctionnels (contrôleurs)

Testent les contrôleurs HTTP avec requêtes simulées.

**Fichiers** :
- `tests/Controller/HomeControllerTest.php`
- `tests/Controller/ComicControllerTest.php` + `ComicControllerUnitTest.php`
- `tests/Controller/SearchControllerTest.php`
- `tests/Controller/WishlistControllerTest.php`
- `tests/Controller/ApiControllerTest.php`
- `tests/Controller/SecurityControllerTest.php`
- `tests/Controller/OfflineControllerTest.php`

### Classe de base

`AuthenticatedWebTestCase` fournit l'authentification :

```php
// tests/Controller/AuthenticatedWebTestCase.php

abstract class AuthenticatedWebTestCase extends WebTestCase
{
    protected function createAuthenticatedClient(): KernelBrowser
    {
        $client = static::createClient();
        // Configure l'authentification...
        return $client;
    }
}
```

### Exemple

```php
public function testIndexRequiresAuthentication(): void
{
    $client = static::createClient();
    $client->request('GET', '/');

    $this->assertResponseRedirects('/login');
}

public function testIndexShowsSeriesWhenAuthenticated(): void
{
    $client = $this->createAuthenticatedClient();
    $crawler = $client->request('GET', '/');

    $this->assertResponseIsSuccessful();
    $this->assertSelectorExists('.comic-card');
}
```

---

## Tests d'intégration (repositories)

Testent les repositories avec une vraie base de données.

**Fichiers** :
- `tests/Repository/ComicSeriesRepositoryTest.php`
- `tests/Repository/AuthorRepositoryTest.php`

**Exemple** :

```php
public function testFindWithFiltersFiltersByType(): void
{
    // Fixtures : créer des séries de différents types
    $this->createSeries('Manga 1', ComicType::MANGA);
    $this->createSeries('BD 1', ComicType::BD);

    $results = $this->repository->findWithFilters(['type' => 'manga']);

    $this->assertCount(1, $results);
    $this->assertEquals('Manga 1', $results[0]->getTitle());
}
```

---

## Tests Behat (end-to-end)

Tests de scénarios utilisateur en Gherkin, écrits en francais.

### Exécution

```bash
# Tous les scénarios (hors @javascript)
ddev exec vendor/bin/behat

# Un fichier feature
ddev exec vendor/bin/behat features/comic_creation.feature

# Scénarios @javascript (nécessite le profil javascript + Chrome)
ddev exec vendor/bin/behat --profile=javascript
```

### Structure des features

```
features/
├── authentication.feature     # 4 scénarios : connexion, déconnexion, accès protégé
├── comic_creation.feature     # 4 scénarios : BD, manga, wishlist, one-shot
├── comic_edition.feature      # 3 scénarios : titre, type, statut
├── comic_deletion.feature     # 2 scénarios : bibliothèque, wishlist
├── filtering.feature          # 8 scénarios : type, statut, NAS, recherche
├── search.feature             # 3 scénarios : partiel, insensible casse, sans résultat
├── wishlist.feature           # 3 scénarios : voir, déplacer, isolation
├── one_shot.feature           # 2 scénarios (@javascript @wip) : masquer/afficher tomes
└── tome_management.feature    # 3 scénarios (@javascript @wip) : ajout, NAS, multi-tomes
```

**27 scénarios** passent avec le profil `default` (session BrowserKit).
Les 5 scénarios `@javascript @wip` nécessitent le profil `javascript` (Selenium + Chrome). Leur couverture est assurée par les tests Panther équivalents (voir ci-dessous).

### Contextes

```
tests/Behat/Context/
├── AuthenticationContext.php  # Steps d'authentification (connexion, déconnexion)
├── ComicSeriesContext.php     # Steps de gestion de séries (CRUD, wishlist)
├── DatabaseContext.php        # Création de fixtures par scénario
├── FeatureContext.php         # Steps génériques (redirection page d'accueil)
├── FormContext.php            # Steps de formulaire
├── NavigationContext.php      # Steps de navigation et filtrage
└── PantherContext.php         # Steps JavaScript (sessions Selenium)
```

### Profils Behat

Le fichier `behat.yaml` définit deux profils :

| Profil | Session | Base URL | Usage |
|--------|---------|----------|-------|
| `default` | BrowserKit (Symfony) | `bibliotheque.ddev.site` | Scénarios sans JS |
| `javascript` | Selenium2 (Chrome) | `test.bibliotheque.ddev.site` | Scénarios `@javascript` |

### Exemple de feature

```gherkin
# features/comic_creation.feature

Fonctionnalité: Création de séries
  En tant qu'utilisateur connecté
  Je veux pouvoir créer des séries
  Afin de gérer ma collection

  Contexte:
    Étant donné je suis connecté

  Scénario: Créer une série BD simple
    Étant donné je suis sur la page de création d'une série
    Quand je remplis le titre avec "Astérix"
    Et je sélectionne le type "BD"
    Et je sélectionne le statut "En cours d'achat"
    Et je soumets le formulaire
    Alors je devrais être sur la page d'accueil
    Et la série "Astérix" devrait exister
    Et la série "Astérix" devrait être de type "BD"
```

---

## Tests navigateur (WebDriver + Chrome)

Tests avec un vrai navigateur Chrome via Selenium distant (`ddev-bibliotheque-chrome`).

Ces tests utilisent directement `RemoteWebDriver` (pas Symfony Panther Client) pour un contrôle fin : exécution JavaScript asynchrone, accès au Chrome DevTools Protocol (CDP), vérification des caches et du Service Worker.

**Fichiers** :
- `tests/Panther/OfflineModeTest.php` — 10 tests PWA/offline
- `tests/Panther/OneShotFormTest.php` — 2 tests formulaire dynamique
- `tests/Panther/TomeManagementTest.php` — 3 tests ajout de tomes via Stimulus

### Exécution

```bash
ddev exec bin/phpunit tests/Panther/
```

### OfflineModeTest (PWA)

Teste le comportement hors-ligne de la Progressive Web App :

| Test | Description |
|------|-------------|
| `testOfflinePageIsAccessible` | La page `/offline` est accessible |
| `testServiceWorkerInstalled` | Le SW est installé après visite |
| `testServiceWorkerIsActive` | Le SW est en état `activated` |
| `testOfflineCacheContainsOfflinePage` | Le cache contient `/offline` |
| `testCachedPageAvailableOffline` | Une page visitée reste accessible hors-ligne (via CDP) |
| `testManifestIsAccessible` | Le `manifest.webmanifest` répond |
| `testManifestContainsRequiredFields` | Structure correcte du manifest |
| `testPwaCachesAreInitialized` | Les caches PWA sont créés |
| `testApiComicsIsCached` | L'API `/api/comics` est mise en cache |
| `testTurboFetchErrorEventStructure` | Le listener Turbo est en place |

### OneShotFormTest

Teste le comportement dynamique du formulaire d'édition quand on coche/décoche la case one-shot (masquage de la section tomes via Stimulus).

### TomeManagementTest

Teste l'ajout dynamique de tomes via le contrôleur Stimulus sur le formulaire d'édition : ajout avec numéro/titre/ISBN, marquage NAS, ajout multiple.

### Exemple

```php
public function testServiceWorkerInstalled(): void
{
    $driver = $this->getDriver();

    $driver->get(self::BASE_URL.'/login');
    \sleep(3);

    $swRegistered = $driver->executeScript('
        return navigator.serviceWorker.controller !== null;
    ');

    $this->assertTrue($swRegistered, 'Service Worker devrait être installé');
}
```

---

## Bonnes pratiques

### Méthodologie TDD

1. **RED** : Ecrire le test qui échoue
2. **GREEN** : Implémenter le minimum pour passer
3. **REFACTOR** : Nettoyer sans casser les tests

### Isolation

- Chaque test est indépendant
- La BDD est réinitialisée avant chaque test d'intégration
- Les mocks isolent des dépendances externes

### Nommage

```php
// test + méthode testée + comportement attendu
public function testGetCurrentIssueReturnsNullWhenNoTomes(): void
public function testLookupReturnsNullWhenNotFound(): void
```

### Assertions

```php
// Utiliser les assertions spécifiques
$this->assertEquals($expected, $actual);
$this->assertTrue($condition);
$this->assertCount(3, $array);
$this->assertNull($value);
$this->assertInstanceOf(ComicSeries::class, $object);
```

---

## Configuration

### PHPUnit

**Fichier** : `phpunit.dist.xml`

- Environnement : `APP_ENV=test`
- `failOnDeprecation`, `failOnNotice`, `failOnWarning` activés
- Extension `Symfony\Component\Panther\ServerExtension` pour les tests navigateur

### Behat

**Fichier** : `behat.yaml`

- Extension `FriendsOfBehat\SymfonyExtension` pour l'intégration Symfony
- Extension `Behat\MinkExtension` pour la navigation web
- Profil `javascript` avec Selenium2 + Chrome distant

### Environnement de test

- Base de données : `db_test`
- URL : `https://test.bibliotheque.ddev.site`
- Variables : `.env.test`

---

## Étapes suivantes

- [Guide de développement](../developpement/README.md) - Contribuer au projet
- [Architecture](../architecture/README.md) - Comprendre le code

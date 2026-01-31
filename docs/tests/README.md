# Tests

Ce guide explique comment exécuter et écrire des tests pour l'application.

---

## Vue d'ensemble

L'application dispose de plusieurs types de tests :

| Type | Framework | Dossier | Description |
|------|-----------|---------|-------------|
| Unitaires | PHPUnit | `tests/Entity/`, `tests/Enum/`, `tests/Service/` | Logique métier isolée |
| Formulaires | PHPUnit | `tests/Form/` | Types de formulaire |
| Fonctionnels | PHPUnit | `tests/Controller/` | Contrôleurs HTTP |
| Intégration | PHPUnit | `tests/Repository/` | Repositories avec BDD |
| End-to-end | Behat | `features/` | Scénarios utilisateur |
| Navigateur | Panther | `tests/Panther/` | Tests JavaScript/PWA |

**Total** : 240+ tests, 585+ assertions

---

## Exécuter les tests

### Tous les tests PHPUnit

```bash
ddev exec bin/phpunit
```

### Un fichier de test spécifique

```bash
ddev exec bin/phpunit tests/Entity/ComicSeriesTest.php
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

# Tests de services
ddev exec bin/phpunit tests/Service/
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

**Fichier** : `tests/Service/IsbnLookupServiceTest.php`

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

---

## Tests de formulaires

Testent les types de formulaire Symfony.

**Fichiers** :
- `tests/Form/ComicSeriesTypeTest.php`
- `tests/Form/TomeTypeTest.php`
- `tests/Form/AuthorAutocompleteTypeTest.php`

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
- `tests/Controller/ComicControllerTest.php`
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

Tests de scénarios utilisateur en Gherkin.

### Exécution

```bash
# Tous les tests Behat
ddev exec vendor/bin/behat

# Un fichier feature
ddev exec vendor/bin/behat features/creation_serie.feature

# Avec JavaScript (Selenium)
ddev exec vendor/bin/behat --profile=javascript
```

### Structure

```
features/
├── authentification.feature
├── creation_serie.feature
├── edition_serie.feature
├── suppression_serie.feature
├── filtrage.feature
├── wishlist.feature
├── recherche.feature
├── one_shots.feature
└── gestion_tomes.feature
```

### Contextes

```
tests/Behat/Context/
├── FeatureContext.php         # Contexte principal
├── AuthenticationContext.php  # Steps d'authentification
├── ComicSeriesContext.php     # Steps de gestion de séries
├── NavigationContext.php      # Steps de navigation
├── FormContext.php            # Steps de formulaire
├── DatabaseContext.php        # Reset BDD avant chaque scénario
└── PantherContext.php         # Tests JavaScript
```

### Exemple de feature

```gherkin
# features/creation_serie.feature
@creation
Fonctionnalité: Création d'une série
  En tant qu'utilisateur connecté
  Je veux pouvoir ajouter une nouvelle série
  Afin de gérer ma collection

  Contexte:
    Étant donné que je suis connecté

  Scénario: Créer une série BD simple
    Quand je vais sur la page de création
    Et je remplis le formulaire avec:
      | type   | BD           |
      | titre  | Astérix      |
      | statut | En cours     |
    Et je soumets le formulaire
    Alors je vois le message "Série créée"
    Et la série "Astérix" existe dans la base
```

---

## Tests Panther (navigateur)

Tests avec un vrai navigateur Chrome.

**Fichiers** :
- `tests/Panther/OfflineModeTest.php`
- `tests/Panther/TomeManagementTest.php`
- `tests/Panther/OneShotFormTest.php`

### Exécution

```bash
ddev exec bin/phpunit tests/Panther/
```

### Exemple

```php
public function testServiceWorkerIsRegistered(): void
{
    $client = static::createPantherClient();
    $client->request('GET', '/');

    // Attend l'enregistrement du SW
    $client->waitFor('body');

    $swRegistered = $client->executeScript(
        'return navigator.serviceWorker.controller !== null'
    );

    $this->assertTrue($swRegistered);
}
```

---

## Bonnes pratiques

### Méthodologie TDD

1. **RED** : Écrire le test qui échoue
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

```xml
<phpunit>
    <testsuites>
        <testsuite name="Project Test Suite">
            <directory>tests</directory>
        </testsuite>
    </testsuites>
    <php>
        <env name="APP_ENV" value="test"/>
        <env name="KERNEL_CLASS" value="App\Kernel"/>
    </php>
</phpunit>
```

### Behat

**Fichier** : `behat.yaml`

```yaml
default:
    suites:
        default:
            contexts:
                - App\Tests\Behat\Context\FeatureContext
                - App\Tests\Behat\Context\AuthenticationContext
                # ...

javascript:
    extensions:
        Behat\MinkExtension:
            selenium2: ~
```

---

## Étapes suivantes

- [Guide de développement](../developpement/README.md) - Contribuer au projet
- [Architecture](../architecture/README.md) - Comprendre le code

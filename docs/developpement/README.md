# Guide de développement

Ce guide explique comment contribuer au développement de Ma Bibliotheque BD.

---

## Configuration de l'environnement

### Prérequis

- [Docker Desktop](https://www.docker.com/products/docker-desktop/)
- [DDEV](https://ddev.readthedocs.io/en/stable/users/install/)
- Git

### Installation

```bash
git clone https://github.com/Soviann/bibliotheqe.git
cd bibliotheqe
ddev start
ddev composer install
ddev exec bin/console doctrine:migrations:migrate -n
ddev exec bin/console app:create-user dev@example.com password
ddev launch
```

Voir le [guide d'installation complet](../installation/README.md).

---

## Standards de code

### Règles PHP obligatoires

1. **`declare(strict_types=1);`** en haut de chaque fichier

2. **Préfixer les fonctions natives** avec `\` :
   ```php
   \array_map(), \sprintf(), \count(), \trim()
   ```

3. **Ordre des méthodes** :
   - `__construct()` en premier
   - Puis `public` → `protected` → `private`

4. **Arguments sur une ligne** sauf constructeurs avec promotion :
   ```php
   // Standard
   public function process(string $input, int $count): string

   // Constructeur avec promotion
   public function __construct(
       private readonly HttpClientInterface $httpClient,
       private readonly LoggerInterface $logger,
   ) {}
   ```

5. **Tri alphabétique** :
   - Assignations dans le constructeur
   - Clés des tableaux associatifs
   - Clés YAML

6. **Documentation en français** (PHPDoc, commentaires)

### PHP-CS-Fixer

Corrige automatiquement le style :

```bash
# Sur les fichiers modifiés uniquement
ddev exec vendor/bin/php-cs-fixer fix src/Controller/ComicController.php
```

### PHPStan

Analyse statique niveau 9 :

```bash
# Sur les fichiers modifiés
ddev exec vendor/bin/phpstan analyse src/Controller/ComicController.php
```

---

## Workflow de développement

### 1. Créer une branche

```bash
git checkout -b feature/ma-fonctionnalite
```

Conventions de nommage :
- `feature/xxx` : nouvelle fonctionnalité
- `fix/xxx` : correction de bug
- `refactor/xxx` : refactoring
- `docs/xxx` : documentation

### 2. Développer en TDD

Cycle Red-Green-Refactor :

1. **RED** : Écrire le test
   ```bash
   # Créer tests/Service/MonServiceTest.php
   ddev exec bin/phpunit tests/Service/MonServiceTest.php
   # Le test DOIT échouer
   ```

2. **GREEN** : Implémenter le minimum
   ```bash
   # Créer src/Service/MonService.php
   ddev exec bin/phpunit tests/Service/MonServiceTest.php
   # Le test DOIT passer
   ```

3. **REFACTOR** : Améliorer sans casser les tests

### 3. Vérifier la qualité

```bash
# Style de code
ddev exec vendor/bin/php-cs-fixer fix src/Service/MonService.php

# Analyse statique
ddev exec vendor/bin/phpstan analyse src/Service/MonService.php

# Tests
ddev exec bin/phpunit tests/Service/MonServiceTest.php
```

### 4. Committer

Format Conventional Commits :

```bash
git add src/Service/MonService.php tests/Service/MonServiceTest.php
git commit -m "feat(service): add MonService for X functionality"
```

Types de commits :
- `feat` : nouvelle fonctionnalité
- `fix` : correction de bug
- `refactor` : refactoring (pas de changement fonctionnel)
- `docs` : documentation
- `chore` : maintenance (dépendances, config)

### 5. Mettre à jour CHANGELOG.md

```markdown
## [Unreleased]

### Added

- **MonService** : Description de la fonctionnalité
```

---

## Travailler avec Doctrine

### Modifier une entité

1. Modifiez le fichier PHP dans `src/Entity/`
2. Générez la migration :
   ```bash
   ddev exec bin/console doctrine:migrations:diff -n
   ```
3. Vérifiez la migration générée dans `migrations/`
4. Appliquez-la :
   ```bash
   ddev exec bin/console doctrine:migrations:migrate -n
   ```

### Ajouter une entité

1. Créez le fichier dans `src/Entity/`
2. Ajoutez les annotations Doctrine
3. Créez le repository dans `src/Repository/`
4. Générez la migration

**Ne jamais créer de migration manuellement** - utilisez toujours `doctrine:migrations:diff`.

---

## Travailler avec les formulaires

### Créer un type de formulaire

```php
// src/Form/MonType.php
namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;

class MonType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('champ1')
            ->add('champ2');
    }
}
```

### Tester le formulaire

```php
// tests/Form/MonTypeTest.php
public function testSubmitValidData(): void
{
    $formData = ['champ1' => 'valeur1'];
    $form = $this->factory->create(MonType::class);
    $form->submit($formData);

    $this->assertTrue($form->isSynchronized());
}
```

---

## Travailler avec Symfony UX

### Vérifier les packages existants

Avant d'écrire du JavaScript custom, vérifiez :
1. [Packages Symfony UX](https://ux.symfony.com/packages)
2. Si un package existe, installez-le :
   ```bash
   ddev composer require symfony/ux-xxx
   ```

### Créer un contrôleur Stimulus

Uniquement si aucun package UX ne couvre le besoin :

```javascript
// assets/controllers/mon_controller.js
import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['element'];
    static values = { url: String };

    connect() {
        console.log('Controller connected');
    }

    action() {
        // Logique
    }
}
```

Utilisation dans Twig :
```twig
<div data-controller="mon" data-mon-url-value="/api/endpoint">
    <button data-action="click->mon#action">Clic</button>
</div>
```

---

## Travailler avec les tests

### Ajouter un test unitaire

```php
// tests/Entity/MonEntityTest.php
namespace App\Tests\Entity;

use App\Entity\MonEntity;
use PHPUnit\Framework\TestCase;

class MonEntityTest extends TestCase
{
    public function testMaMethode(): void
    {
        $entity = new MonEntity();
        $entity->setValeur('test');

        $this->assertEquals('expected', $entity->getResultat());
    }
}
```

### Ajouter un test fonctionnel

```php
// tests/Controller/MonControllerTest.php
namespace App\Tests\Controller;

class MonControllerTest extends AuthenticatedWebTestCase
{
    public function testIndex(): void
    {
        $client = $this->createAuthenticatedClient();
        $client->request('GET', '/mon-route');

        $this->assertResponseIsSuccessful();
    }
}
```

### Ajouter un scénario Behat

```gherkin
# features/ma_feature.feature
Fonctionnalité: Ma fonctionnalité

  Scénario: Description du scénario
    Étant donné que je suis connecté
    Quand je vais sur "/page"
    Alors je vois "Texte attendu"
```

---

## Structure d'un contrôleur

```php
<?php

declare(strict_types=1);

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Description du contrôleur.
 */
class MonController extends AbstractController
{
    public function __construct(
        private readonly MonRepository $repository,
        private readonly MonService $service,
    ) {}

    #[Route('/route', name: 'app_route', methods: ['GET'])]
    public function index(): Response
    {
        $items = $this->repository->findAll();

        return $this->render('mon/index.html.twig', [
            'items' => $items,
        ]);
    }

    #[Route('/route/new', name: 'app_route_new', methods: ['GET', 'POST'])]
    public function new(Request $request): Response
    {
        // Logique
    }
}
```

---

## Debugging

### Logs

```bash
# Logs de développement
ddev exec tail -f var/log/dev.log

# Logs d'erreur
ddev exec tail -f var/log/dev.log | grep ERROR
```

### Symfony Profiler

Accessible sur `/_profiler` en mode développement.

### Dump

```php
// Dans le code
dump($variable);

// Arrête l'exécution
dd($variable);
```

```twig
{# Dans Twig #}
{{ dump(variable) }}
```

### Xdebug

Configurez votre IDE pour écouter sur le port 9003.

---

## Mise à jour du CLAUDE.md

Après toute modification structurelle, mettez à jour `CLAUDE.md` :

| Modification | Action |
|--------------|--------|
| Nouvelle entité | Ajouter dans "Entités Doctrine" |
| Nouvelle route | Ajouter dans "Contrôleurs et routes" |
| Nouveau service | Ajouter dans "Services" |
| Nouvel enum | Ajouter dans "Enums" |
| Nouvelle commande | Ajouter dans "Commandes console" |

---

## Étapes suivantes

- [Tests](../tests/README.md) - Exécuter et écrire des tests
- [Architecture](../architecture/README.md) - Comprendre le code
- [Déploiement](../deploiement/README.md) - Mettre en production

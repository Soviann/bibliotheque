# CLAUDE.md

Instructions pour Claude Code. Ces règles sont **obligatoires** et doivent être suivies à chaque intervention.

## Projet

Application Symfony de gestion de bibliothèque BD/Comics/Mangas avec mode PWA.

**Stack technique** : Symfony 7.4, PHP 8.3, MariaDB 10.11, DDEV, Doctrine ORM, Symfony UX.

## Commandes

**Toutes les commandes s'exécutent via DDEV** :

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

## Méthodologie TDD (Test-Driven Development)

**Obligatoire** : tout développement de fonctionnalité ou correction de bug suit le cycle TDD.

### Cycle Red-Green-Refactor

Pour chaque nouvelle fonctionnalité ou bug à corriger :

1. **RED** — Écrire le test en premier
   - Créer le fichier de test avant le code de production
   - Le test décrit le comportement attendu
   - Exécuter le test : il **doit échouer** (rouge)

2. **GREEN** — Écrire le minimum de code pour faire passer le test
   - Implémenter uniquement ce qui est nécessaire
   - Ne pas anticiper les besoins futurs
   - Exécuter le test : il **doit passer** (vert)

3. **REFACTOR** — Améliorer le code sans changer le comportement
   - Nettoyer, simplifier, éliminer les duplications
   - Les tests doivent rester verts après refactoring

### Application pratique

```bash
# 1. Créer/modifier le test
# tests/Service/MonServiceTest.php

# 2. Exécuter le test (doit échouer)
ddev exec bin/phpunit tests/Service/MonServiceTest.php

# 3. Implémenter le code
# src/Service/MonService.php

# 4. Exécuter le test (doit passer)
ddev exec bin/phpunit tests/Service/MonServiceTest.php

# 5. Refactoriser si nécessaire, relancer les tests
```

### Types de tests

- **Tests unitaires** (`tests/`) : services, entités, logique métier
- **Tests fonctionnels** (`tests/Controller/`) : contrôleurs, requêtes HTTP
- **Tests Behat** (`features/`) : scénarios utilisateur end-to-end

### Exceptions au TDD

Le TDD n'est **pas requis** pour :
- Modifications de templates Twig uniquement
- Fichiers de configuration (YAML, .env)
- Migrations Doctrine (générées automatiquement)

## Outils de qualité

**Après chaque modification de code PHP**, exécuter sur les fichiers modifiés :

```bash
# Corriger le style (obligatoire)
ddev exec vendor/bin/php-cs-fixer fix src/MonFichier.php

# Vérifier les types (obligatoire)
ddev exec vendor/bin/phpstan analyse src/MonFichier.php

# Lancer les tests si concernés
ddev exec bin/phpunit tests/MonTest.php
```

**Ne pas exécuter ces outils sur tout le projet**, uniquement sur les fichiers modifiés.

## Frontend & JavaScript

**Symfony UX est obligatoire.** Avant d'écrire du JavaScript custom :

1. Vérifier si un package Symfony UX existe : https://ux.symfony.com/packages
2. Installer le package UX si disponible : `ddev composer require symfony/ux-xxx`
3. Créer un contrôleur Stimulus custom **uniquement si** aucun package UX ne couvre le besoin

**Packages UX courants** :
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
```

## Déploiement

```bash
docker compose -f docker-compose.prod.yml up --build -d
```

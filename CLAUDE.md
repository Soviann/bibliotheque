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

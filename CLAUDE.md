# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

Application web de gestion de bibliothèque BD/Comics avec mode hors connexion (PWA). Utilise Symfony 7.4, PHP 8.3, MariaDB et DDEV comme environnement de développement local.

### Key Technologies
- Symfony 7.4 avec PHP 8.3
- MariaDB 10.11
- DDEV local development environment
- Doctrine ORM avec migrations
- Symfony UX (Turbo, Stimulus, AssetMapper)
- Authentification email/mot de passe
- PWA avec Service Worker

## Quick Start

```bash
ddev start                                    # Démarrer l'environnement DDEV
ddev composer install                         # Installer les dépendances
ddev exec bin/console doctrine:migrations:migrate  # Exécuter les migrations
ddev exec bin/console app:create-user email@example.com motdepasse  # Créer un utilisateur
```

L'application est accessible sur https://bibliotheque.ddev.site

## Commands

**Toutes les commandes s'exécutent dans DDEV** (jamais en local) :

```bash
# Développement
ddev composer install                         # Installer les dépendances
ddev exec bin/console cache:clear             # Vider le cache Symfony
ddev exec bin/console assets:install          # Installer les assets

# Base de données
ddev exec bin/console doctrine:migrations:diff -n     # Générer une migration
ddev exec bin/console doctrine:migrations:migrate -n  # Exécuter les migrations

# Utilisateurs
ddev exec bin/console app:create-user email password  # Créer un utilisateur

# Debug
ddev exec bin/console debug:router            # Lister les routes
ddev exec bin/console debug:container         # Lister les services
```

## Architecture

### Directory Structure
```
src/
├── Command/          # Commandes console
├── Controller/       # Contrôleurs HTTP
├── Entity/           # Entités Doctrine
├── Enum/             # Enums PHP
├── Form/             # Types de formulaire
└── Repository/       # Repositories Doctrine
config/
├── packages/         # Configuration des bundles (YAML)
├── routes/           # Configuration du routing
└── services.yaml     # Définitions des services
templates/
├── base.html.twig    # Layout principal
├── comic/            # Templates CRUD comics
├── home/             # Page d'accueil (bibliothèque)
├── search/           # Page de recherche
├── security/         # Page de connexion
└── wishlist/         # Liste de souhaits
assets/
├── controllers/      # Stimulus controllers
└── styles/           # Fichiers CSS
public/
├── manifest.json     # Manifest PWA
└── sw.js             # Service Worker
```

### Core Components
- **Entities**: `ComicSeries` (série BD) et `User` (utilisateur)
- **Enum**: `ComicStatus` (buying, finished, stopped, wishlist)
- **Controllers**: HomeController, WishlistController, ComicController, SearchController, ApiController, SecurityController
- **Commands**: `app:create-user` pour créer un utilisateur

## Coding Standards

- **MUST** follow [Symfony coding standards](https://symfony.com/doc/current/contributing/code/standards.html)
- **MUST** prefix PHP native functions with backslash (`\array_map()`, `\sprintf()`, `\count()`)
- **MUST** write documentation in French: PHPDoc blocks, inline comments
- **MUST** order methods by visibility: `public` → `protected` → `private`. Exception: always place `__construct()` first
- **MUST** declare all method/function arguments on a single line. **Exception**: constructors with property promotion — each promoted parameter on its own line
- **MUST** sort alphabetically: property assignments in constructor body, associative array keys, YAML keys at each level

## Frontend & JavaScript

**Symfony UX est obligatoire** pour tout développement frontend interactif.

Avant de créer un contrôleur Stimulus custom ou d'écrire du JavaScript, vérifier si un package Symfony UX existe :
- Documentation : https://ux.symfony.com/
- Liste des packages : https://ux.symfony.com/packages

### Packages Symfony UX à utiliser en priorité

| Besoin | Package Symfony UX |
|--------|-------------------|
| Autocomplétion, tags, select amélioré | `symfony/ux-autocomplete` |
| Graphiques / charts | `symfony/ux-chartjs` |
| Recadrage d'images | `symfony/ux-cropperjs` |
| Suppression sans rechargement | `symfony/ux-live-component` |
| Formulaires dynamiques (ajax) | `symfony/ux-live-component` |
| Notifications toast | `symfony/ux-notify` |
| Composants PHP rendus côté serveur | `symfony/ux-twig-component` |
| Toggle, onglets, modales | `symfony/ux-toggle-password`, composants Stimulus UX |
| Upload avec preview | `symfony/ux-dropzone` |
| Lazy loading d'images | `symfony/ux-lazy-image` |
| Traductions JS | `symfony/ux-translator` |
| Icônes | `symfony/ux-icons` |

### Installation d'un package UX

```bash
ddev composer require symfony/ux-autocomplete  # Exemple
```

### Quand créer un contrôleur Stimulus custom ?

Uniquement si :
1. Aucun package Symfony UX ne couvre le besoin
2. Le besoin est très spécifique au projet et non réutilisable
3. Le package UX existant ne peut pas être étendu pour le cas d'usage

## Git

- Commits: follow [Conventional Commits](https://www.conventionalcommits.org/)
  - Format: `<type>(scope): description`
  - Types: `feat`, `fix`, `chore`, `refactor`, `docs`

## Changelog

**Le fichier `CHANGELOG.md` doit être mis à jour à chaque modification du projet.**

Après avoir effectué des modifications (ajout de fonctionnalité, correction de bug, refactoring, etc.), ajouter une entrée dans `CHANGELOG.md` :

1. Ouvrir `CHANGELOG.md` à la racine du projet
2. Ajouter l'entrée dans la section `[Unreleased]` sous la catégorie appropriée :
   - `### Added` : nouvelles fonctionnalités
   - `### Changed` : modifications de fonctionnalités existantes
   - `### Deprecated` : fonctionnalités qui seront supprimées
   - `### Removed` : fonctionnalités supprimées
   - `### Fixed` : corrections de bugs
   - `### Security` : corrections de vulnérabilités
3. Format d'une entrée : `- **Nom court** : Description de la modification`

Le format suit [Keep a Changelog](https://keepachangelog.com/fr/1.1.0/).

## Development Notes

- Toutes les commandes de développement doivent être exécutées dans DDEV (`ddev exec ...`)
- Les migrations sont gérées avec Doctrine Migrations
- L'application supporte le mode hors ligne via Service Worker (PWA)

## Deployment

Pour le déploiement sur Synology (Docker) :

```bash
# Copier et configurer les variables d'environnement
cp .env.prod.example .env

# Build et lancement
docker compose -f docker-compose.prod.yml up --build -d

# Créer un utilisateur (après le premier déploiement)
docker compose -f docker-compose.prod.yml exec app bin/console app:create-user email password
```

Variables à configurer dans `.env` :
- `APP_SECRET` : Clé secrète Symfony
- `MYSQL_PASSWORD` / `MYSQL_ROOT_PASSWORD` : Mots de passe MariaDB

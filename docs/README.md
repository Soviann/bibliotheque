# Documentation Ma Bibliotheque BD

Bienvenue dans la documentation complète de l'application **Ma Bibliotheque BD**.

Cette application Symfony permet de gérer une collection de BD, Comics, Mangas et Livres avec support hors-ligne (PWA).

---

## Sommaire

### Pour commencer

| Guide | Description |
|-------|-------------|
| [Installation](installation/README.md) | Installation locale et configuration |
| [Configuration DDEV](installation/ddev.md) | Environnement de développement recommandé |
| [Déploiement](deploiement/README.md) | Mise en production avec Docker |

### Fonctionnalités

| Guide | Description |
|-------|-------------|
| [Vue d'ensemble](fonctionnalites/README.md) | Présentation des fonctionnalités principales |
| [Gestion de collection](fonctionnalites/gestion-collection.md) | Ajouter, modifier, organiser vos séries |
| [Recherche ISBN](fonctionnalites/recherche-isbn.md) | Préremplissage automatique via APIs |
| [Mode PWA](fonctionnalites/pwa.md) | Installation mobile et mode hors-ligne |

### Référence technique

| Guide | Description |
|-------|-------------|
| [Architecture](architecture/README.md) | Structure du projet et choix techniques |
| [Entités Doctrine](architecture/entites.md) | Modèle de données complet |
| [Services](architecture/services.md) | Services métier et leur utilisation |
| [API REST](api/README.md) | Endpoints et format des réponses |

### Contribution

| Guide | Description |
|-------|-------------|
| [Guide de développement](developpement/README.md) | Standards de code et workflow |
| [Tests](tests/README.md) | Exécuter et écrire des tests |

---

## Stack technique

| Composant | Version | Usage |
|-----------|---------|-------|
| PHP | 8.3+ | Backend |
| Symfony | 7.4 | Framework |
| MariaDB | 10.11+ | Base de données |
| Doctrine ORM | - | Persistence |
| Symfony UX | - | Frontend (Turbo, Stimulus) |
| AssetMapper | - | Gestion des assets |
| Workbox | - | Service Worker PWA |

---

## Liens rapides

- [CHANGELOG](../CHANGELOG.md) - Historique des modifications
- [GitHub](https://github.com/Soviann/bibliotheqe) - Code source
- [Issues](https://github.com/Soviann/bibliotheqe/issues) - Signaler un bug

---

## Licence

Ce projet est sous licence MIT. Voir le fichier [LICENSE](../LICENSE) pour plus de détails.

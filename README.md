# Ma Bibliotheque BD

Application web de gestion de collection de BD, Comics, Mangas et Livres avec support hors-ligne (PWA).

![Symfony](https://img.shields.io/badge/Symfony-7.4-000000?logo=symfony)
![PHP](https://img.shields.io/badge/PHP-8.3-777BB4?logo=php)
![License](https://img.shields.io/badge/License-MIT-green)

---

## Fonctionnalites

- **Gestion de collection** : Ajout, modification, suppression de series
- **Types supportes** : BD, Comics, Manga, Livre
- **Suivi detaille** :
  - Numero actuel possede
  - Dernier numero achete
  - Nombre de parutions
  - Dernier telecharge
  - Tomes possedes (si non consecutifs)
  - Tomes manquants
  - Presence sur NAS
- **Recherche automatique** : Preremplissage via ISBN ou titre (Google Books, Open Library, AniList)
- **Statuts** : En cours d'achat, Termine, Arrete, Liste de souhaits
- **Wishlist** : Liste de souhaits separee avec possibilite de transfert vers la bibliotheque
- **Filtres avances** : Par type, statut, presence NAS, recherche par titre
- **Tri** : Par titre (A-Z/Z-A), date de modification, statut
- **PWA** : Installation sur mobile, mode hors-ligne
- **Design Material** : Interface mobile-first avec navigation en bas d'ecran

---

## Documentation

La documentation complete est disponible dans le dossier [`docs/`](docs/README.md).

### Guides principaux

| Guide | Description |
|-------|-------------|
| [Installation](docs/installation/README.md) | Installation locale et configuration |
| [Fonctionnalites](docs/fonctionnalites/README.md) | Presentation des fonctionnalites |
| [Architecture](docs/architecture/README.md) | Structure du projet et choix techniques |
| [API REST](docs/api/README.md) | Endpoints et format des reponses |
| [Tests](docs/tests/README.md) | Executer et ecrire des tests |
| [Developpement](docs/developpement/README.md) | Standards de code et workflow |
| [Deploiement](docs/deploiement/README.md) | Mise en production avec Docker |

### Guides detailles

- [Configuration DDEV](docs/installation/ddev.md) - Environnement de developpement recommande
- [Gestion de collection](docs/fonctionnalites/gestion-collection.md) - Ajouter, modifier, organiser vos series
- [Recherche ISBN](docs/fonctionnalites/recherche-isbn.md) - Preremplissage automatique via APIs
- [Mode PWA](docs/fonctionnalites/pwa.md) - Installation mobile et mode hors-ligne
- [Entites Doctrine](docs/architecture/entites.md) - Modele de donnees complet
- [Services](docs/architecture/services.md) - Services metier et leur utilisation

---

## Demarrage rapide

### Avec DDEV (recommande)

```bash
# Cloner le projet
git clone https://github.com/Soviann/bibliotheqe.git
cd bibliotheqe

# Demarrer DDEV
ddev start

# Installer les dependances
ddev composer install

# Configurer la base de donnees
ddev exec bin/console doctrine:migrations:migrate -n

# Creer un utilisateur
ddev exec bin/console app:create-user votre@email.com motdepasse

# Ouvrir l'application
ddev launch
```

L'application est accessible sur : **https://bibliotheque.ddev.site**

### Installation manuelle

Voir le [guide d'installation complet](docs/installation/README.md).

---

## Stack technique

| Composant | Version | Usage |
|-----------|---------|-------|
| PHP | 8.3+ | Backend |
| Symfony | 7.4 | Framework |
| MariaDB | 10.11+ | Base de donnees |
| Doctrine ORM | - | Persistence |
| Symfony UX | - | Frontend (Turbo, Stimulus) |
| AssetMapper | - | Gestion des assets |
| Workbox | - | Service Worker PWA |

---

## Structure du projet

```
bibliotheque/
├── assets/               # Frontend (Stimulus, CSS)
├── config/               # Configuration Symfony
├── docs/                 # Documentation
├── migrations/           # Migrations Doctrine
├── public/               # Point d'entree web
├── src/
│   ├── Command/          # Commandes console
│   ├── Controller/       # Controleurs HTTP
│   ├── Entity/           # Entites Doctrine
│   ├── Enum/             # Enums PHP
│   ├── Form/             # Types de formulaire
│   ├── Repository/       # Repositories Doctrine
│   └── Service/          # Services metier
├── templates/            # Templates Twig
└── tests/                # Tests PHPUnit et Behat
```

---

## Commandes utiles

```bash
# Demarrer l'environnement
ddev start

# Console Symfony
ddev exec bin/console <commande>

# Tests
ddev exec bin/phpunit

# Qualite de code
ddev exec vendor/bin/php-cs-fixer fix src/
ddev exec vendor/bin/phpstan analyse src/
```

---

## Deploiement

```bash
# Build et lancement
docker compose -f docker-compose.prod.yml up --build -d

# Migrations
docker compose -f docker-compose.prod.yml exec app bin/console doctrine:migrations:migrate -n
```

Voir le [guide de deploiement complet](docs/deploiement/README.md).

---

## Contribuer

1. Forkez le projet
2. Creez une branche (`git checkout -b feature/ma-fonctionnalite`)
3. Committez vos modifications (`git commit -m 'feat: ajout fonctionnalite'`)
4. Poussez la branche (`git push origin feature/ma-fonctionnalite`)
5. Ouvrez une Pull Request

Voir le [guide de developpement](docs/developpement/README.md) pour les standards de code.

---

## Licence

Ce projet est sous licence MIT. Voir le fichier [LICENSE](LICENSE) pour plus de details.

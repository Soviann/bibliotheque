# Ma Bibliothèque BD

Application web de gestion de collection de BD, Comics, Mangas et Livres avec support hors-ligne (PWA).

## Fonctionnalités

- **Gestion de collection** : Ajout, modification, suppression de séries
- **Types supportés** : BD, Comics, Manga, Livre
- **Suivi détaillé** :
  - Numéro actuel possédé
  - Dernier numéro acheté
  - Nombre de parutions
  - Dernier téléchargé
  - Tomes possédés (si non consécutifs)
  - Tomes manquants
  - Présence sur NAS
- **Statuts** : En cours d'achat, Terminé, Arrêté, Liste de souhaits
- **Wishlist** : Liste de souhaits séparée avec possibilité de transfert vers la bibliothèque
- **Filtres avancés** : Par type, statut, présence NAS, recherche par titre
- **Tri** : Par titre (A-Z/Z-A), date de modification, statut
- **PWA** : Installation sur mobile, mode hors-ligne
- **Design Material** : Interface mobile-first avec navigation en bas d'écran

## Stack technique

- **Backend** : Symfony 7.2 / PHP 8.3
- **Base de données** : MariaDB 10.11
- **Frontend** : Symfony UX (Turbo, Stimulus), AssetMapper
- **Design** : CSS Material Design custom (mobile-first)
- **PWA** : Service Worker avec stratégies de cache

## Installation

### Prérequis

- PHP 8.3+
- Composer
- MariaDB 10.11+ ou MySQL 8+

### Setup

```bash
# Cloner le projet
git clone https://github.com/Soviann/bibliotheqe.git
cd bibliotheqe

# Installer les dépendances
composer install

# Configurer les variables d'environnement
cp .env .env.local
# Éditer .env.local avec vos paramètres DATABASE_URL

# Créer la base de données et exécuter les migrations
bin/console doctrine:database:create
bin/console doctrine:migrations:migrate -n

# Créer un utilisateur
bin/console app:create-user votre@email.com motdepasse

# Lancer le serveur de développement
symfony server:start
```

### Import de données (optionnel)

Pour importer des données depuis un fichier Excel :

```bash
bin/console app:import-excel chemin/vers/fichier.xlsx
```

Le fichier Excel doit contenir des onglets nommés : BD, Comics, Livre, Mangas

## Développement local avec DDEV

[DDEV](https://ddev.readthedocs.io/en/stable/) est l'outil recommandé pour le développement local. Il fournit un environnement Docker pré-configuré.

### Prérequis

- [DDEV](https://ddev.readthedocs.io/en/stable/)
- Docker

### Setup avec DDEV

```bash
# Cloner le projet
git clone https://github.com/Soviann/bibliotheqe.git
cd bibliotheqe

# Démarrer DDEV
ddev start

# Installer les dépendances
ddev composer install

# Créer la base de données et exécuter les migrations
ddev exec bin/console doctrine:database:create
ddev exec bin/console doctrine:migrations:migrate -n

# Créer un utilisateur
ddev exec bin/console app:create-user votre@email.com motdepasse

# Ouvrir l'application
ddev launch
```

L'application est accessible sur https://bibliotheque.ddev.site

### Commandes utiles DDEV

```bash
ddev start          # Démarrer l'environnement
ddev stop           # Arrêter l'environnement
ddev restart        # Redémarrer
ddev ssh            # Accéder au container
ddev describe       # Voir les infos (URLs, DB, etc.)
```

## Déploiement production (Docker)

```bash
# Build et lancement
docker compose -f docker-compose.prod.yml up --build -d

# Migrations
docker compose -f docker-compose.prod.yml exec app bin/console doctrine:migrations:migrate -n
```

Variables d'environnement à configurer dans `.env.prod.local` :
- `APP_SECRET`
- `DATABASE_URL`

## Structure du projet

```
bibliotheque/
├── assets/
│   ├── controllers/       # Stimulus controllers
│   └── styles/           # CSS Material Design
├── config/               # Configuration Symfony
├── migrations/           # Migrations Doctrine
├── public/
│   ├── sw.js            # Service Worker PWA
│   └── manifest.json    # Manifest PWA
├── src/
│   ├── Command/         # Commandes console (import, create-user)
│   ├── Controller/      # Controllers
│   ├── Entity/          # Entités Doctrine
│   ├── Enum/            # Enums (ComicStatus, ComicType)
│   ├── Form/            # Formulaires
│   └── Repository/      # Repositories
└── templates/
    ├── components/      # Composants réutilisables
    ├── comic/           # Templates CRUD comics
    ├── home/            # Page d'accueil (bibliothèque)
    ├── search/          # Page de recherche
    ├── security/        # Login
    └── wishlist/        # Liste de souhaits
```

## Licence

MIT

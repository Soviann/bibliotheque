# Bibliothèque

Application de gestion de collection BD / Manga / Comics / Livres.

## Stack technique

**Backend** : Symfony 7.4, PHP 8.3, API Platform 4, MariaDB 10.11, JWT
**Frontend** : React 19, TypeScript, Vite, TanStack Query, Tailwind CSS 4
**Infra** : DDEV (dev), Docker Compose (prod), PWA

## Production

3 conteneurs Docker : **nginx** (reverse proxy + frontend React) + **php** (PHP-FPM 8.3, Symfony 7) + **db** (MariaDB 10.11).

Les images `php` et `nginx` sont pré-buildées en CI et publiées sur ghcr.io à chaque release :

```bash
docker pull ghcr.io/soviann/bibliotheque-php:latest
docker pull ghcr.io/soviann/bibliotheque-nginx:latest
```

Déploiement (dans `backend/`) :

```bash
TAG=2.9.0 docker compose --env-file .env.nas pull
TAG=2.9.0 docker compose --env-file .env.nas up -d
```

Le script `scripts/nas-update.sh` automatise ce processus : détection du dernier tag, pull des images, démarrage, migrations et rollback en cas d'échec.

## Développement local

Prérequis : [DDEV](https://ddev.readthedocs.io/)

```bash
ddev start
make dev
```

- Frontend : `https://bibliotheque.ddev.site:5173`
- API docs : `https://bibliotheque.ddev.site/api/docs`

### Commandes utiles

```bash
make test          # Tests backend + frontend
make lint          # PHPStan + CS Fixer + TypeScript
make build         # Build production frontend
make db-migrate    # Exécuter les migrations
```

## Documentation

- [Guide utilisateur](docs/guide-utilisateur.md)
- [Guide développeur](docs/guide-developpeur.md)
- [Déploiement NAS Synology](docs/guide-deploiement-nas.md)
- [Runbook NAS (Claude Code)](docs/guide-deploiement-nas-claude.md)
- [CHANGELOG](CHANGELOG.md)

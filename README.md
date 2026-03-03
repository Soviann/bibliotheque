# Bibliothèque

Application de gestion de collection BD / Manga / Comics / Livres.

## Stack technique

**Backend** : Symfony 7.4, PHP 8.3, API Platform 4, MariaDB 10.11, JWT
**Frontend** : React 19, TypeScript, Vite, TanStack Query, Tailwind CSS 4
**Infra** : DDEV (dev), Docker Compose (prod), PWA

## Démarrage rapide

```bash
ddev start
ddev exec make dev
```

- Frontend : `https://bibliotheque.ddev.site:5173`
- API docs : `https://bibliotheque.ddev.site/api/docs`

## Commandes utiles

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
- [Déploiement OVH](docs/guide-deploiement-ovh.md)
- [CHANGELOG](CHANGELOG.md)

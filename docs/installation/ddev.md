# Configuration DDEV

[DDEV](https://ddev.readthedocs.io/) est l'outil recommandé pour le développement local. Il fournit un environnement Docker pré-configuré avec PHP, MariaDB et tous les outils nécessaires.

---

## Configuration du projet

La configuration DDEV se trouve dans `.ddev/config.yaml` :

```yaml
name: bibliotheque
type: php
docroot: public
php_version: "8.3"
webserver_type: nginx-fpm
database:
  type: mariadb
  version: "10.11"
```

---

## Commandes courantes

### Gestion de l'environnement

```bash
# Démarrer l'environnement
ddev start

# Arrêter l'environnement
ddev stop

# Redémarrer
ddev restart

# Voir les infos (URLs, connexion BDD, etc.)
ddev describe

# Accéder au container en SSH
ddev ssh

# Ouvrir l'application dans le navigateur
ddev launch
```

### Commandes Symfony via DDEV

Toutes les commandes Symfony s'exécutent avec `ddev exec` :

```bash
# Console Symfony
ddev exec bin/console <commande>

# Composer
ddev composer <commande>

# PHPUnit
ddev exec bin/phpunit

# PHP-CS-Fixer
ddev exec vendor/bin/php-cs-fixer fix

# PHPStan
ddev exec vendor/bin/phpstan analyse
```

### Raccourcis pratiques

Créez des alias dans votre shell pour accélérer le workflow :

```bash
# ~/.bashrc ou ~/.zshrc
alias dc="ddev exec bin/console"
alias dphp="ddev exec php"
alias dtest="ddev exec bin/phpunit"
```

---

## Base de données

### Connexion à la base de données

```bash
# Via MySQL CLI dans le container
ddev mysql

# Ou via un client externe avec les infos de connexion :
ddev describe
```

Les informations de connexion sont :
- **Host** : `127.0.0.1`
- **Port** : affiché par `ddev describe` (souvent 32XXX)
- **User** : `db`
- **Password** : `db`
- **Database** : `db`

### Migrations Doctrine

```bash
# Générer une migration (après modification d'entité)
ddev exec bin/console doctrine:migrations:diff -n

# Appliquer les migrations
ddev exec bin/console doctrine:migrations:migrate -n

# Voir le statut des migrations
ddev exec bin/console doctrine:migrations:status
```

### Réinitialiser la base de données

```bash
# Supprimer et recréer la base
ddev exec bin/console doctrine:database:drop --force
ddev exec bin/console doctrine:database:create
ddev exec bin/console doctrine:migrations:migrate -n

# Ou en une commande (attention, supprime toutes les données)
ddev exec bin/console doctrine:schema:drop --force && \
ddev exec bin/console doctrine:migrations:migrate -n
```

---

## Outils de qualité

### PHP-CS-Fixer

Corrige automatiquement le style de code selon les standards Symfony :

```bash
# Corriger un fichier spécifique
ddev exec vendor/bin/php-cs-fixer fix src/MonFichier.php

# Vérifier sans corriger (dry-run)
ddev exec vendor/bin/php-cs-fixer fix src/MonFichier.php --dry-run --diff
```

### PHPStan

Analyse statique du code (niveau 9) :

```bash
# Analyser un fichier spécifique
ddev exec vendor/bin/phpstan analyse src/MonFichier.php

# Analyser un dossier
ddev exec vendor/bin/phpstan analyse src/Controller/
```

---

## Tests

### PHPUnit

```bash
# Lancer tous les tests
ddev exec bin/phpunit

# Lancer un fichier de test spécifique
ddev exec bin/phpunit tests/Entity/ComicSeriesTest.php

# Lancer un test spécifique
ddev exec bin/phpunit --filter testGetCurrentIssue

# Avec couverture de code
ddev exec bin/phpunit --coverage-html var/coverage
```

### Behat

Tests end-to-end avec BrowserKit (rapide) ou Selenium (JavaScript) :

```bash
# Tous les tests Behat
ddev exec vendor/bin/behat

# Un fichier feature spécifique
ddev exec vendor/bin/behat features/authentification.feature

# Avec le profil JavaScript (Selenium)
ddev exec vendor/bin/behat --profile=javascript
```

### Panther

Tests de navigateur avec Chrome :

```bash
ddev exec bin/phpunit tests/Panther/
```

---

## Services additionnels

### Mailpit (capture d'emails)

Si vous avez besoin de tester l'envoi d'emails :

```bash
# Dans .ddev/config.yaml, ajouter :
# mailhog_port: 8025

# Puis redémarrer
ddev restart
```

Accédez à Mailpit sur : `https://bibliotheque.ddev.site:8025`

### Redis (cache)

Pour ajouter Redis :

```bash
ddev get ddev/ddev-redis
ddev restart
```

---

## Dépannage

### Le container ne démarre pas

```bash
# Vérifier les logs
ddev logs

# Reconstruire les containers
ddev restart --rebuild
```

### Problèmes de permissions

```bash
# Fixer les permissions des dossiers var/ et public/uploads/
ddev exec chmod -R 777 var public/uploads
```

### Cache corrompu

```bash
ddev exec bin/console cache:clear
ddev exec rm -rf var/cache/*
```

### Réinitialisation complète

En dernier recours, réinitialisez complètement l'environnement :

```bash
ddev delete -O
ddev start
ddev composer install
ddev exec bin/console doctrine:migrations:migrate -n
```

---

## Étape suivante

- [Retour au guide d'installation](README.md)
- [Guide de développement](../developpement/README.md)

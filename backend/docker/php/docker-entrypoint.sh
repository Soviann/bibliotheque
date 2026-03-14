#!/bin/sh
set -e

# Corrige les permissions des volumes (nécessite root)
chown -R www-data:www-data var

# Compile les variables d'environnement pour Symfony (performance)
gosu www-data composer dump-env prod

# Exécute la commande par défaut (php-fpm) en tant que www-data
exec gosu www-data "$@"

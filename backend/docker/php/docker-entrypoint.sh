#!/bin/sh
set -e

# Compile les variables d'environnement pour Symfony (performance)
composer dump-env prod

# Corrige les permissions du cache (le volume app_var persiste entre les rebuilds)
chown -R www-data:www-data var

# Exécute la commande par défaut (php-fpm)
exec "$@"

#!/bin/sh
set -e

# Compile les variables d'environnement pour Symfony (performance)
composer dump-env prod

# Exécute la commande par défaut (php-fpm)
exec "$@"

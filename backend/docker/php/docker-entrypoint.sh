#!/bin/sh
set -e

# Corrige les permissions des volumes (nécessite root)
chown -R www-data:www-data var

# Compile les variables d'environnement pour Symfony (performance)
gosu www-data composer dump-env prod

# Warmup du cache Symfony (nécessite les env vars compilées ci-dessus)
gosu www-data php bin/console cache:warmup --env=prod --no-debug

# Exécute la commande par défaut (php-fpm) en tant que www-data
exec gosu www-data "$@"

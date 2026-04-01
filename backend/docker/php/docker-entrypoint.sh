#!/bin/sh
set -e

# Nettoie le volume persistant (cache/logs périmés des déploiements précédents)
rm -rf var/cache var/log .env.local.php
mkdir -p var/cache var/log
chown -R www-data:www-data var

# Warmup du cache Symfony
gosu www-data php bin/console cache:warmup --env=prod --no-debug

# php-fpm doit démarrer en root (accès stderr, bind port 9000)
# puis drop les privileges via sa config (user = www-data)
exec "$@"

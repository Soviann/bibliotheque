#!/bin/sh
set -e

cd /var/www/html

# Nettoie le volume persistant (cache/logs périmés des déploiements précédents)
rm -rf var/cache var/log .env.local.php
mkdir -p var/cache var/log
chown -R www-data:www-data var

# Warmup du cache Symfony
gosu www-data php bin/console cache:warmup --env=prod --no-debug

# Lancer Supervisor (messenger + scheduler)
exec /usr/bin/supervisord -c /var/www/html/docker/worker/supervisord.conf

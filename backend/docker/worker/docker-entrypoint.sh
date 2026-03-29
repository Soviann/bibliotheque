#!/bin/sh
set -e

cd /var/www/html

# Permissions sur var/ (root → www-data)
chown -R www-data:www-data var

# Compiler les variables d'environnement
gosu www-data composer dump-env prod

# Warmup du cache
gosu www-data php bin/console cache:warmup --env=prod --no-debug

# Lancer Supervisor (messenger + scheduler)
exec /usr/bin/supervisord -c /var/www/html/docker/worker/supervisord.conf

#!/bin/sh
set -e

cd /var/www/html

# Supprime le .env.local.php s'il existe (volume persistant)
rm -f .env.local.php

# Le cache est déjà chaud (le worker démarre après php via depends_on)
# Lancer Supervisor (messenger + scheduler)
exec /usr/bin/supervisord -c /var/www/html/docker/worker/supervisord.conf

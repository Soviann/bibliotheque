#!/bin/sh
set -e

cd /app

# Volume persistant : supprime un éventuel .env.local.php compilé.
# En conteneur, les vraies variables d'environnement font foi.
rm -f .env.local.php

# Répertoires inscriptibles (les volumes montés écrasent les droits du build).
mkdir -p var/cache var/log public/uploads public/media
chown www-data:www-data var var/cache var/log public/uploads public/media

# Stockage interne de Caddy/FrankenPHP (le serveur tourne en www-data).
mkdir -p /data/caddy /config/caddy
chown -R www-data:www-data /data /config

# Vide et réchauffe le cache Symfony (en www-data pour les bonnes permissions).
gosu www-data php bin/console cache:clear --env=prod --no-debug
gosu www-data php bin/console cache:warmup --env=prod --no-debug

# Lance supervisord (web + messenger + scheduler).
exec "$@"

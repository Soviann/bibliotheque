#!/bin/sh
set -e

# Supprime le .env.local.php s'il existe (volume persistant)
# En Docker, les vraies variables d'environnement font foi
rm -f .env.local.php

# Permissions sur le répertoire var (sans récursion pour éviter la lenteur sur NAS)
mkdir -p var/cache var/log
chown www-data:www-data var var/cache var/log

# Vide et réchauffe le cache Symfony
gosu www-data php bin/console cache:clear --env=prod --no-debug
gosu www-data php bin/console cache:warmup --env=prod --no-debug

# php-fpm doit démarrer en root (accès stderr, bind port 9000)
# puis drop les privileges via sa config (user = www-data)
exec "$@"

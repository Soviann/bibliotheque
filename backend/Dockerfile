# syntax=docker/dockerfile:1

# Image unique FrankenPHP : serveur web (Caddy) + PHP en processus + worker Messenger.
# Le contexte de build est la racine du dépôt (besoin de backend/ et frontend/).

# Stage 1 — Build du frontend React (Vite)
FROM node:22-alpine AS frontend-build

ARG VITE_GOOGLE_CLIENT_ID
ENV VITE_GOOGLE_CLIENT_ID=${VITE_GOOGLE_CLIENT_ID}

WORKDIR /app

COPY frontend/package.json frontend/package-lock.json ./
RUN npm ci

COPY frontend/ .
RUN npm run build

# Stage 2 — Runtime FrankenPHP (web + PHP + worker)
FROM dunglas/frankenphp:1-php8.3 AS app

# gosu : drop de privilèges vers www-data ; supervisor : multi-process (web + messenger)
RUN apt-get update && apt-get install -y --no-install-recommends \
        curl \
        gosu \
        supervisor \
    && install-php-extensions \
        gd \
        intl \
        opcache \
        pdo_mysql \
        zip \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

ENV APP_ENV=prod
ENV COMPOSER_ALLOW_SUPERUSER=1

WORKDIR /app

# Dépendances PHP (couche cache séparée du code)
COPY backend/composer.json backend/composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist

# Code applicatif
COPY backend/ .
RUN composer dump-autoload --optimize --no-dev

# Frontend compilé → racine web servie par Caddy (public/)
COPY --from=frontend-build /app/dist ./public

# Configuration FrankenPHP / supervisor / entrypoint
COPY backend/docker/frankenphp/Caddyfile /etc/frankenphp/Caddyfile
COPY backend/docker/frankenphp/supervisord.conf /etc/supervisor/conf.d/app.conf
COPY backend/docker/frankenphp/docker-entrypoint.sh /usr/local/bin/docker-entrypoint.sh
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

# Répertoires inscriptibles (les volumes montés écraseront ces droits au runtime)
RUN mkdir -p public/uploads public/media var/cache var/log \
    && chown -R www-data:www-data public/uploads public/media var

EXPOSE 8080

ENTRYPOINT ["docker-entrypoint.sh"]
CMD ["supervisord", "-c", "/etc/supervisor/conf.d/app.conf"]

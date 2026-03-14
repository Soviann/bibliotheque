FROM php:8.3-fpm

RUN apt-get update && apt-get install -y \
    git \
    gosu \
    libfcgi-bin \
    libicu-dev \
    libjpeg62-turbo-dev \
    libpng-dev \
    libwebp-dev \
    libzip-dev \
    unzip \
    && docker-php-ext-configure gd --with-jpeg --with-webp \
    && docker-php-ext-configure intl \
    && docker-php-ext-install \
    gd \
    intl \
    opcache \
    pdo_mysql \
    zip \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

ENV COMPOSER_ALLOW_SUPERUSER=1

WORKDIR /var/www/html

COPY composer.json composer.lock ./

RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist

COPY . .

RUN composer dump-autoload --optimize

RUN mkdir -p public/uploads public/media var/cache var/log \
    && chown -R www-data:www-data public/uploads public/media var

COPY docker/php/healthcheck.conf /usr/local/etc/php-fpm.d/zz-healthcheck.conf
COPY docker/php/docker-entrypoint.sh /usr/local/bin/docker-entrypoint.sh

EXPOSE 9000

ENTRYPOINT ["docker-entrypoint.sh"]
CMD ["php-fpm"]

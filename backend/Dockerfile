FROM php:8.3-fpm

RUN apt-get update && apt-get install -y \
    git \
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

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

ENV COMPOSER_ALLOW_SUPERUSER=1

WORKDIR /var/www/html

COPY composer.json composer.lock ./

RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist

COPY . .

RUN composer dump-autoload --optimize

RUN mkdir -p public/uploads public/media var/cache var/log

RUN php bin/console cache:clear --env=prod --no-debug \
    && php bin/console cache:warmup --env=prod --no-debug

RUN chown -R www-data:www-data public/uploads public/media var

EXPOSE 9000

CMD ["php-fpm"]

FROM php:8.3-apache

RUN apt-get update && apt-get install -y \
    git \
    libicu-dev \
    libpng-dev \
    libzip-dev \
    unzip \
    && docker-php-ext-configure intl \
    && docker-php-ext-install \
    intl \
    opcache \
    pdo_mysql \
    zip \
    && rm -rf /var/lib/apt/lists/*

RUN a2enmod rewrite

ENV APACHE_DOCUMENT_ROOT /var/www/html/public

RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf
RUN sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf

RUN echo '<Directory ${APACHE_DOCUMENT_ROOT}>\n\
    AllowOverride All\n\
    Require all granted\n\
</Directory>' >> /etc/apache2/apache2.conf

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

COPY composer.json composer.lock ./

RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist

COPY . .

RUN composer dump-autoload --optimize

RUN mkdir -p var/cache var/log \
    && chown -R www-data:www-data var

RUN php bin/console cache:clear --env=prod --no-debug
RUN php bin/console cache:warmup --env=prod --no-debug
RUN php bin/console asset-map:compile --env=prod --no-debug

EXPOSE 80

CMD ["apache2-foreground"]

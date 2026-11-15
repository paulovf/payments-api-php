FROM php:8.5-fpm

RUN apt-get update && apt-get install -y --no-install-recommends \
    git \
    unzip \
    libpq-dev \
    libzip-dev \
    && docker-php-ext-install -j"$(nproc)" \
        pdo_pgsql \
        zip \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

COPY --chown=www-data:www-data . .

RUN composer install \
    --no-interaction \
    --prefer-dist

RUN chown -R www-data:www-data storage bootstrap/cache

USER www-data

EXPOSE 9000

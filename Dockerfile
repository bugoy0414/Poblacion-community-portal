
FROM node:22-alpine AS assets

WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci
COPY . .
RUN npm run build

FROM php:8.3-apache

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        git \
        curl \
        libcurl4-openssl-dev \
        libonig-dev \
        libpq-dev \
        libzip-dev \
        unzip \
    && docker-php-ext-install \
        curl \
        mbstring \
        opcache \
        pdo_pgsql \
        zip \
    && a2enmod headers rewrite \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

COPY . .
COPY --from=assets /app/public/build /var/www/html/public/build
COPY docker/apache-vhost.conf /etc/apache2/sites-available/000-default.conf
COPY docker/entrypoint.sh /usr/local/bin/render-entrypoint

RUN mkdir -p \
        bootstrap/cache \
        storage/app/private \
        storage/framework/cache/data \
        storage/framework/sessions \
        storage/framework/views \
        storage/logs \
    && composer install \
        --no-dev \
        --no-interaction \
        --prefer-dist \
        --optimize-autoloader \
    && chown -R www-data:www-data bootstrap/cache storage \
    && chmod +x /usr/local/bin/render-entrypoint \
    && sed -ri 's/Listen 80/Listen 10000/' /etc/apache2/ports.conf

ENV PORT=10000

EXPOSE 10000

ENTRYPOINT ["/usr/local/bin/render-entrypoint"]

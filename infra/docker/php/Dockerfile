FROM node:24-bookworm-slim AS assets
WORKDIR /var/www/html
COPY apps/backend/package*.json ./
RUN npm ci
COPY apps/backend/resources ./resources
COPY apps/backend/vite.config.js ./vite.config.js
RUN npm run build

FROM php:8.4-fpm-bookworm
RUN apt-get update && apt-get install -y --no-install-recommends libpq-dev libzip-dev libicu-dev unzip git \
    && docker-php-ext-install pdo_pgsql pgsql intl zip pcntl \
    && rm -rf /var/lib/apt/lists/*
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
WORKDIR /var/www/html
COPY apps/backend/composer.json apps/backend/composer.lock ./
RUN composer install --no-interaction --prefer-dist --no-progress --no-scripts
COPY apps/backend ./
COPY --from=assets /var/www/html/public/build ./public/build
RUN composer dump-autoload --no-interaction && chown -R www-data:www-data storage bootstrap/cache
USER www-data
CMD ["php-fpm"]

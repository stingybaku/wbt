FROM php:8.4-cli-alpine AS vendor
WORKDIR /app

RUN apk add --no-cache \
        git unzip \
        icu-dev \
        libzip-dev \
        oniguruma-dev \
        libxml2-dev \
        libpng-dev \
        libjpeg-turbo-dev \
        freetype-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install \
        intl \
        zip \
        mbstring \
        xml \
        dom \
        bcmath \
        pdo \
        pcntl \
        gd

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

COPY backend/composer.json backend/composer.lock ./
RUN composer install \
    --no-dev \
    --optimize-autoloader \
    --no-interaction \
    --no-scripts

FROM node:20-alpine AS assets
WORKDIR /app
COPY backend/package*.json ./
RUN npm install
COPY backend/resources ./resources
COPY backend/public ./public
COPY backend/vite.config.js ./
RUN npm run build

# FrankenPHP (Caddy + PHP in one process) replaces `php artisan serve`, which is
# PHP's single-threaded development server and handles one request at a time.
FROM dunglas/frankenphp:1.9-php8.4-alpine
WORKDIR /app

# install-php-extensions ships with the FrankenPHP images and resolves the
# native build dependencies for each extension itself.
RUN apk add --no-cache bash qpdf \
    && install-php-extensions \
        intl \
        zip \
        mbstring \
        xml \
        dom \
        bcmath \
        pdo_pgsql \
        pcntl \
        gd \
        opcache

# Production opcache settings. validate_timestamps=0 is safe because the code
# never changes inside a running container; a deploy builds a new image.
COPY backend/docker/opcache.ini /usr/local/etc/php/conf.d/opcache.ini

# Large PDF uploads (source material) need more than PHP's 2M/8M defaults.
COPY backend/docker/uploads.ini /usr/local/etc/php/conf.d/uploads.ini

COPY backend/. ./
COPY --from=vendor /app/vendor ./vendor
COPY --from=assets /app/public/build ./public/build

RUN chmod +x scripts/railway-start.sh

EXPOSE 8080

CMD ["./scripts/railway-start.sh"]

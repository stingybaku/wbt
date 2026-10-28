# PHP versions are pinned to the exact patch release in both PHP stages.
# A floating tag (php:8.4-cli-alpine, frankenphp:1.9-php8.4-alpine) can
# resolve to a stale base: build caches reuse whatever they pulled last, and
# a pinned minor line like FrankenPHP 1.9 stops being rebuilt at all - that
# tag was last built in November 2025 with PHP 8.4.15. To take a PHP security
# release, bump MIN_PHP_VERSION and both tags together.
#
# 8.4.27 (2026-10-21) fixes two critical RCEs: CVE-2026-109618
# (GHSA-w8hw-w9fx-j79w) and CVE-2026-109619 (GHSA-6wmf-4rv5-p3rp).
ARG MIN_PHP_VERSION=8.4.27

FROM php:8.4.27-cli-alpine AS vendor
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
FROM dunglas/frankenphp:1.13.4-php8.4.27-alpine
ARG MIN_PHP_VERSION
WORKDIR /app

# Fail the build rather than ship a PHP older than the last security release,
# whatever the tag above ends up resolving to.
RUN php -r 'fwrite(STDERR, "PHP " . PHP_VERSION . PHP_EOL); exit(version_compare(PHP_VERSION, getenv("MIN_PHP_VERSION"), ">=") ? 0 : 1);'

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

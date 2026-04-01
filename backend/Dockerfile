FROM php:8.3-cli-alpine AS vendor
WORKDIR /app

RUN apk add --no-cache \
        git unzip \
        icu-dev \
        libzip-dev \
        oniguruma-dev \
        libxml2-dev \
    && docker-php-ext-install \
        intl \
        zip \
        mbstring \
        xml \
        dom \
        bcmath \
        pdo \
        pcntl

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

FROM php:8.3-cli-alpine
WORKDIR /app

RUN apk add --no-cache \
        bash \
        postgresql-libs postgresql-dev \
        icu-dev \
        libzip-dev \
        oniguruma-dev \
        libxml2-dev \
        qpdf \
    && docker-php-ext-install \
        intl \
        zip \
        mbstring \
        xml \
        dom \
        bcmath \
        pdo \
        pdo_pgsql \
        pcntl

COPY backend/. ./
COPY --from=vendor /app/vendor ./vendor
COPY --from=assets /app/public/build ./public/build

RUN chmod +x scripts/railway-start.sh

EXPOSE 8080

CMD ["./scripts/railway-start.sh"]

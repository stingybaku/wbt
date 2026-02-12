FROM composer:2 AS vendor
WORKDIR /app
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

RUN apk add --no-cache bash libpq \
    && docker-php-ext-install pdo pdo_pgsql

COPY backend/. ./
COPY --from=vendor /app/vendor ./vendor
COPY --from=assets /app/public/build ./public/build

RUN chmod +x scripts/railway-start.sh

EXPOSE 8080

CMD ["./scripts/railway-start.sh"]

FROM php:8.4-fpm-alpine

RUN apk add --no-cache \
    bash \
    curl \
    git \
    unzip \
    libpng-dev \
    libjpeg-turbo-dev \
    libwebp-dev \
    oniguruma-dev \
    libzip-dev \
    libxml2-dev \
    libpq-dev \
    icu-dev \
    freetype-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
    && docker-php-ext-install pdo_pgsql gd zip intl \
    && curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

COPY --from=node:22-alpine /usr/local/bin/node /usr/local/bin/
COPY --from=node:22-alpine /usr/local/bin/npm /usr/local/bin/

WORKDIR /var/www

EXPOSE 9000

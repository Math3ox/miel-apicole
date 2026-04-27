FROM php:8.3-fpm-alpine

# Installer les dépendances système
RUN apk add --no-cache \
    git \
    unzip \
    libzip-dev \
    icu-dev \
    oniguruma-dev \
    libpng-dev \
    libjpeg-turbo-dev \
    freetype-dev \
    nodejs \
    npm \
    bash

# Installer les extensions PHP requises par Symfony
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install \
        pdo_mysql \
        mysqli \
        intl \
        zip \
        gd \
        opcache \
        bcmath

# Installer Composer depuis l'image officielle
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Installer Symfony CLI (pratique pour dev)
RUN curl -sS https://get.symfony.com/cli/installer | bash \
    && mv /root/.symfony*/bin/symfony /usr/local/bin/symfony

# Dossier de travail
WORKDIR /var/www/html

# Copier le php.ini personnalisé
COPY docker/php/php.ini /usr/local/etc/php/conf.d/custom.ini

# Créer un user non-root pour éviter les problèmes de permissions
RUN adduser -D -u 1000 symfony \
    && chown -R symfony:symfony /var/www/html

USER symfony

EXPOSE 9000
CMD ["php-fpm"]

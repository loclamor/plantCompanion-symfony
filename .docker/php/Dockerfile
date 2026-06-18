FROM php:8.3-fpm

RUN apt-get update && apt-get install -y \
    gnupg \
    g++ \
    procps \
    openssl \
    git \
    unzip \
    zlib1g-dev \
    libzip-dev \
    libfreetype6-dev \
    libpng-dev \
    libjpeg-dev \
    libicu-dev  \
    libonig-dev \
    libxslt1-dev \
    acl

# gd doit être configuré AVEC jpeg/freetype, sinon imagecreatefromjpeg() absent
# (les libs sont présentes mais l'extension les ignore sans cette étape).
RUN docker-php-ext-configure gd --with-freetype --with-jpeg
RUN docker-php-ext-install zip intl gd pdo_mysql exif

RUN pecl install pcov && docker-php-ext-enable pcov

RUN sed -i 's/www-data:x:33:33/www-data:x:1000:1000/' /etc/passwd && \
    sed -i 's/www-data:x:33:/www-data:x:1000:/' /etc/group

COPY --from=composer:lts /usr/bin/composer /usr/local/bin/composer

# Limites d'upload des photos
COPY uploads.ini /usr/local/etc/php/conf.d/uploads.ini

RUN chown -R www-data: /var/www/

WORKDIR /usr/src/app

USER www-data

FROM php:8.4-apache

# Abilita mod_rewrite per URL pulite (se ti serviranno)
RUN a2enmod rewrite

# Installa estensioni necessarie: pdo_mysql, intl (facoltativa), zip
RUN apt-get update && apt-get install -y \
        libicu-dev libzip-dev zip git nano \
    && docker-php-ext-install pdo_mysql intl zip \
    && rm -rf /var/lib/apt/lists/*

# Xdebug per debug locale
RUN pecl install xdebug \
    && docker-php-ext-enable xdebug

# OPcache (utile anche in dev, ma con config "soft")
RUN docker-php-ext-install opcache

# Config Apache vhost e PHP ini
COPY vhost.conf /etc/apache2/sites-available/000-default.conf
COPY php.ini /usr/local/etc/php/conf.d/custom-dev.ini

# Imposta DocumentRoot e permessi
WORKDIR /var/www/html
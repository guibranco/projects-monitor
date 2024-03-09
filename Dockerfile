FROM php:8.3-rc-apache

RUN a2enmod rewrite \
    && apt-get clean \
    && apt-get update \
    && apt-get install -y --no-install-recommends libzip-dev zip \
    && docker-php-ext-install mysqli sockets shmop zip \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/* /var/cache/apt/archives/*

COPY ./Src /var/www/html/

FROM php:8.3-rc-zts

RUN a2enmod rewrite

COPY ./Src /var/www/html/
FROM php:8.2-rc-zts

RUN a2enmod rewrite

COPY ./Src /var/www/html/
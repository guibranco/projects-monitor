FROM php:8.3-rc-apache

RUN a2enmod rewrite

COPY ./Src /var/www/html/

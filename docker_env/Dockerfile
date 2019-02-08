FROM php:5.6.38-apache
RUN a2enmod rewrite
RUN docker-php-ext-install mysqli

RUN apt-get update && apt-get install -y zlib1g-dev libpng-dev libjpeg62-turbo-dev

RUN docker-php-ext-install zip
RUN docker-php-ext-install gd
FROM php:7.4-apache AS php_7_4

RUN apt-get update && apt-get install -y \
    zlib1g-dev \
    libpng-dev \
    libjpeg62-turbo-dev \
    libzip-dev \
    pkg-config \
    && rm -rf /var/lib/apt/lists/*

RUN docker-php-ext-install mysqli zip gd

RUN a2enmod rewrite

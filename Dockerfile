FROM php:7.4-apache

RUN apt-get -y update --fix-missing
RUN apt-get upgrade -y

# Install useful tools
RUN apt-get -y install sudo apt-utils nano wget dialog

# Install important libraries
RUN apt-get -y install --fix-missing apt-utils build-essential git zip

RUN useradd -ms /bin/bash app
RUN echo "www-data:0000" | chpasswd
RUN echo "app:0000" | chpasswd
RUN adduser app www-data
RUN adduser app sudo
RUN adduser www-data sudo

# Composer
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

RUN docker-php-ext-configure bcmath && docker-php-ext-install -j$(nproc) bcmath && docker-php-ext-install -j$(nproc) shmop
RUN pecl install redis && docker-php-ext-enable redis

# Other PHP7 Extensions

RUN apt-get -y install libmcrypt-dev

RUN apt-get -y install libsqlite3-dev libsqlite3-0
RUN docker-php-ext-install pdo_mysql
RUN docker-php-ext-install pdo_sqlite
RUN docker-php-ext-install mysqli
RUN docker-php-ext-install tokenizer
RUN docker-php-ext-install json

RUN apt-get -y install libicu-dev
RUN docker-php-ext-install -j$(nproc) intl

RUN apt-get update && apt-get install --no-install-recommends -y libpng-dev libwebp-dev libjpeg62-turbo-dev libpng-dev libxpm-dev libfreetype6-dev pkg-config patch

RUN docker-php-ext-configure gd --with-jpeg=/usr/include/ --with-freetype=/usr/include/

RUN docker-php-ext-install -j$(nproc) gd

RUN pecl install apcu && docker-php-ext-enable apcu

# Enable apache modules
RUN a2enmod rewrite headers proxy

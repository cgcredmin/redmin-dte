#!/bin/bash

echo '#############################################'
echo 'Installing dependencies'
echo '#############################################'

echo "America/Santiago" >/etc/timezone

# Essentials
apt-get update
apt-get install -y curl
apt-get install -y bash
apt-get install -y npm
apt-get install -y python3-pip
apt-get install -y supervisor
apt-get install -y cron

# Install rich package for python
pip3 install rich

# Install nginx
apt-get install -y nginx

# Install the PHP pdo_mysql library
docker-php-ext-install pdo pdo_mysql && docker-php-ext-enable pdo pdo_mysql

# Install the PHP soap library
apt-get install -y libxml2-dev
docker-php-ext-install soap && docker-php-ext-enable soap

# Install the PHP bcmath library
docker-php-ext-install bcmath && docker-php-ext-enable bcmath

# Install the PHP gd library
apt-get update && apt-get install -y libfreetype6-dev libjpeg62-turbo-dev libpng-dev && rm -rf /var/lib/apt/lists/*
docker-php-ext-configure gd --with-freetype --with-jpeg && docker-php-ext-install gd

# Install the PHP imap library
apt-get update && apt-get install -y libc-client-dev libkrb5-dev && rm -rf /var/lib/apt/lists/*
docker-php-ext-configure imap --with-kerberos --with-imap-ssl && docker-php-ext-install imap

#Install PHP extension zip
apt-get update && apt-get install -y libzip-dev && rm -rf /var/lib/apt/lists/*
docker-php-ext-install zip && docker-php-ext-enable zip

apt-get clean && rm -rf /var/lib/apt/lists/*
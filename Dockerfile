FROM php:8.2-fpm-alpine3.16 as app

RUN apk update && apk upgrade

VOLUME [ "/sys/fs/cgroup" ]
# receive port parameter from docker run or set to 8080
ENV PORT=${PORT:-80}

WORKDIR /var/www/html

#PHP
COPY --chmod=777 /config/php/php8/php-fpm.conf /etc/php8/php-fpm.conf
COPY --chmod=777 /config/php/php8/www.conf /etc/php8/php-fpm.d/www.conf
COPY --chmod=777 /config/php/php8/php.ini /etc/php8/php.ini

# NGINX
COPY --chmod=777 /config/nginx/server.prod.conf /etc/nginx/conf.d/default.conf
COPY --chmod=777 /config/nginx/nginx.conf /etc/nginx/nginx.conf

# Configure supervisor
RUN mkdir -p /etc/supervisor.d/
COPY --chmod=777 /config/supervisord.ini /etc/supervisor.d/supervisord.ini

# Essentials
RUN apk add --no-cache bash zip unzip curl sqlite supervisor shadow
# Installing nginx
RUN apk add --no-cache nginx nginx-mod-http-headers-more
# Installing chromium
RUN apk add --no-cache chromium chromium-chromedriver
# Installing node package managers
RUN apk add --no-cache npm yarn
# Install python/pip
ENV PYTHONUNBUFFERED=1
RUN apk add --update --no-cache python3 && ln -sf python3 /usr/bin/python
RUN python3 -m ensurepip
RUN pip3 install --no-cache --upgrade pip setuptools
RUN python -m pip install rich

# Patch to CVE-2020-28928 (musl:1.2.2-r7)
RUN apk --no-cache upgrade musl
RUN apk add --upgrade lz4-doc

# SCRIPTS
COPY --chmod=777 /scripts/. /var/www/html/scripts
# APP
COPY --chmod=777 /src/. /var/www/html/

RUN apk update
RUN apk add --no-cache postgresql-dev libxml2-dev php8-soap php-soap php-xml imap-dev php-imap \
  gd libpng-dev libjpeg-turbo-dev libwebp-dev freetype-dev php-bcmath

# Install the PHP pdo_mysql library
RUN docker-php-ext-install pdo pdo_mysql && docker-php-ext-enable pdo pdo_mysql

# Install the PHP soap library
RUN docker-php-ext-install soap && docker-php-ext-enable soap

# Install the PHP bcmath library
RUN docker-php-ext-install bcmath && docker-php-ext-enable bcmath

# Install the PHP gd library
RUN docker-php-ext-configure gd --enable-gd --with-freetype --with-jpeg --with-webp && docker-php-ext-install gd

# Install the PHP imap library
RUN docker-php-ext-configure imap --with-imap --with-imap-ssl && docker-php-ext-install imap

#Install PHP extension zip
RUN apk add libzip-dev
RUN docker-php-ext-install zip && docker-php-ext-enable zip

# Installing composer
COPY --from=composer:latest /usr/bin/composer /usr/local/bin/composer

# Install dependencies
RUN composer install

# Config cron
COPY config/cron.d/scheduled /var/www/html/scheduled
RUN chmod +x /var/www/html/scheduled && bash /var/www/html/scheduled
COPY config/cron.d /etc/cron.d/
RUN chmod 0644 /etc/cron.d/*

# Config nginx
RUN mkdir -p /run/nginx/
RUN touch /run/nginx/nginx.pid
RUN ln -sf /dev/stdout /var/log/nginx/access.log
RUN ln -sf /dev/stderr /var/log/nginx/error.log

EXPOSE $PORT

ENTRYPOINT ["supervisord", "-c", "/etc/supervisor.d/supervisord.ini"]

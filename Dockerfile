FROM php:8.2-fpm-alpine3.16 as app

RUN apk update && apk upgrade

VOLUME [ "/sys/fs/cgroup" ]

WORKDIR /var/www/html

# receive port parameter from docker run or set to 80
ENV PORT=${PORT:-80}

# Configure supervisor
RUN mkdir -p /etc/supervisor.d/
COPY --chmod=777 /config/supervisord.ini /etc/supervisor.d/supervisord.ini

# Essentials
RUN apk add --no-cache zip
RUN apk add --no-cache unzip
RUN apk add --no-cache curl
RUN apk add --no-cache sqlite
RUN apk add --no-cache nginx
RUN apk add --no-cache supervisor shadow
RUN apk add --no-cache chromium chromium-chromedriver
RUN apk add nginx-mod-http-headers-more npm
# Installing bash
RUN apk add --no-cache bash
RUN apk add --no-cache npm
RUN apk add --no-cache yarn

# Patch to CVE-2020-28928 (musl:1.2.2-r7)
RUN apk --no-cache upgrade musl
RUN apk add --upgrade lz4-doc

# SCRIPTS
COPY --chmod=777 /scripts/. /var/www/html/scripts
# APP
COPY --chmod=777 /src/. /var/www/html/

RUN docker-php-ext-install pdo pdo_mysql
RUN docker-php-ext-enable pdo pdo_mysql

RUN apk add --no-cache php-soap
RUN apk add --no-cache php-xml

# RUN set -eux
RUN apk update
RUN apk add libxml2-dev
RUN apk add php8-soap

RUN apk add --no-cache postgresql-dev
RUN docker-php-ext-install soap
RUN docker-php-ext-enable soap

RUN apk add --no-cache gd libpng-dev libjpeg-turbo-dev libwebp-dev freetype-dev php-bcmath
RUN docker-php-ext-install bcmath
RUN docker-php-ext-enable bcmath

RUN docker-php-ext-configure gd --enable-gd --with-freetype --with-jpeg --with-webp
RUN docker-php-ext-install gd

RUN chmod +x ./scripts/*.sh
RUN bash ./scripts/check_folders.sh

RUN mkdir -p /run/nginx/
RUN touch /run/nginx/nginx.pid

#PHP
COPY --chmod=777 /config/php/php8/php-fpm.conf /etc/php8/php-fpm.conf
COPY --chmod=777 /config/php/php8/www.conf /etc/php8/php-fpm.d/www.conf
COPY --chmod=777 /config/php/php8/php.ini /etc/php8/php.ini

# NGINX
# COPY --from=builder --chown=nginx:nginx --chmod=777 /config/nginx/fastcgi-php.conf /etc/nginx/fastcgi-php.conf
COPY --chmod=777 /config/nginx/server.prod.conf /etc/nginx/conf.d/default.conf
COPY --chmod=777 /config/nginx/nginx.conf /etc/nginx/nginx.conf
RUN mkdir -p /run/nginx/
RUN touch /run/nginx/nginx.pid
RUN ln -sf /dev/stdout /var/log/nginx/access.log
RUN ln -sf /dev/stderr /var/log/nginx/error.log

# Installing composer
COPY --from=composer:latest /usr/bin/composer /usr/local/bin/composer

# Install dependencies
RUN composer install --no-dev --optimize-autoloader

COPY config/cron.d/scheduled /var/www/html/scheduled
RUN chmod +x /var/www/html/scheduled && bash /var/www/html/scheduled

# Configure cron jobs, and ensure crontab-file permissions
COPY config/cron.d /etc/cron.d/
RUN chmod 0644 /etc/cron.d/*

EXPOSE 80 443

ENTRYPOINT ["supervisord", "-c", "/etc/supervisor.d/supervisord.ini"]

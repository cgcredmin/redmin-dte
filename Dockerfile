FROM composer:latest AS composer_dependencies

# SCRIPTS
COPY --chmod=777 /scripts/. /app/scripts
# APP
COPY --chmod=777 /src/. /app

WORKDIR /app

# Install dependencies
RUN composer install --no-interaction --ignore-platform-reqs

FROM php:8.2-fpm-buster as app

# copy from composer_dependencies
COPY --from=composer_dependencies /app /var/www/html

# Copy the script from your host machine to the Docker image
COPY config/dependency_installer.sh /dependency_installer.sh

# Give execution permissions
RUN chmod +x /dependency_installer.sh

# Execute the script
RUN /dependency_installer.sh

#PHP
COPY --chmod=777 /config/php/php8/php-fpm.conf /etc/php8/php-fpm.conf
COPY --chmod=777 /config/php/php8/www.conf /etc/php8/php-fpm.d/www.conf
COPY --chmod=777 /config/php/php8/php.ini /etc/php8/php.ini

# Configure supervisor
RUN mkdir -p /etc/supervisor.d/
COPY --chmod=777 /config/supervisord.ini /etc/supervisor.d/supervisord.ini

# Config cron
COPY config/cron.d/scheduled /var/www/html/scheduled
RUN chmod +x /var/www/html/scheduled && bash /var/www/html/scheduled
COPY config/cron.d /etc/cron.d/
RUN chmod 0644 /etc/cron.d/*

# Config NGINX
COPY --chmod=777 /config/nginx/nginx.conf /etc/nginx/conf.d/nginx.conf
RUN mkdir -p /run/nginx/
RUN touch /run/nginx/nginx.pid
RUN ln -sf /dev/stdout /var/log/nginx/access.log
RUN ln -sf /dev/stderr /var/log/nginx/error.log

# Set the working directory to /app
WORKDIR /var/www/html

EXPOSE 80

ENTRYPOINT ["supervisord", "-c", "/etc/supervisor.d/supervisord.ini"]

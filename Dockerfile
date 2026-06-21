FROM richarvey/nginx-php-fpm:latest

# Install PostgreSQL client dev libraries & build pdo_pgsql
RUN apk add --no-cache postgresql-dev && \
    docker-php-ext-install pdo_pgsql

# Copy the application code
COPY . /var/www/html

# Environment variables for richarvey/nginx-php-fpm
ENV SKIP_COMPOSER 1
ENV WEBROOT /var/www/html/public
ENV PHP_ERRORS_STDERR 1
ENV RUN_SCRIPTS 1
ENV REAL_IP_HEADER 1
ENV APP_ENV production
ENV APP_DEBUG false
ENV LOG_CHANNEL stderr
ENV COMPOSER_ALLOW_SUPERUSER 1

# Command default to start the image
CMD ["/start.sh"]

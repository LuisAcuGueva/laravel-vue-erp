# ==============================================================================
# Base PHP 8.3 FPM Image with Required Extensions
# ==============================================================================
FROM php:8.3-fpm-alpine AS base

# Install system utilities and postgresql client
RUN apk add --no-cache \
    curl \
    git \
    unzip \
    bash \
    postgresql-client \
    shadow

# Install PHP extensions using official installer
COPY --from=mlocati/php-extension-installer /usr/bin/install-php-extensions /usr/local/bin/
RUN install-php-extensions \
    pdo_pgsql \
    pgsql \
    bcmath \
    zip \
    gd \
    intl \
    opcache \
    pcntl

# redis se compila desde GitHub: pecl.php.net suele responder 502/504
RUN apk add --no-cache --virtual .redis-build-deps $PHPIZE_DEPS \
    && git clone --depth 1 --branch 6.3.0 https://github.com/phpredis/phpredis.git /tmp/phpredis \
    && cd /tmp/phpredis \
    && phpize \
    && ./configure \
    && make -j"$(nproc)" \
    && make install \
    && docker-php-ext-enable redis \
    && cd / && rm -rf /tmp/phpredis \
    && apk del .redis-build-deps

# Install Composer
COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer

WORKDIR /var/www/html

# ==============================================================================
# Development Stage
# ==============================================================================
FROM base AS dev

ARG UID=1000
ARG GID=1000

# Map www-data to match host user UID/GID to avoid permission issues
RUN usermod -u ${UID} www-data && groupmod -g ${GID} www-data

# Copy dev PHP configuration
COPY docker/php/php.dev.ini /usr/local/etc/php/conf.d/99-custom.ini

# Copy entrypoint scripts
COPY docker/php/entrypoint.dev.sh /usr/local/bin/entrypoint-dev.sh
COPY docker/php/entrypoint.test.sh /usr/local/bin/entrypoint-test.sh
RUN chmod +x /usr/local/bin/entrypoint-dev.sh /usr/local/bin/entrypoint-test.sh

ENTRYPOINT ["/usr/local/bin/entrypoint-dev.sh"]
CMD ["php-fpm"]

# ==============================================================================
# Production Stage
# ==============================================================================
FROM base AS prod

# Copy production PHP configuration
COPY docker/php/php.prod.ini /usr/local/etc/php/conf.d/99-custom.ini

# Copy application source code
COPY back-laravel /var/www/html

# Install dependencies for production
RUN composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction

# Adjust storage permissions
RUN chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache \
    && chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache

USER www-data

CMD ["php-fpm"]

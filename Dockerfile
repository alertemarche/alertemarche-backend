# AlerteMarché Backend — PHP 8.2 FPM + extensions Laravel 11
FROM php:8.2-fpm

# Dépendances système
RUN apt-get update && apt-get install -y \
    git curl zip unzip \
    libpq-dev libzip-dev libpng-dev libonig-dev libxml2-dev \
    && rm -rf /var/lib/apt/lists/*

# Extensions PHP requises par Laravel + PostgreSQL + Redis
RUN docker-php-ext-install pdo pdo_pgsql pgsql mbstring zip exif pcntl bcmath gd \
    && pecl install redis \
    && docker-php-ext-enable redis

# Composer (version épinglée : composer:latest bloque Laravel 11 via sa
# nouvelle politique d'advisories ; 2.7 installe sans souci)
COPY --from=composer:2.7 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# Installer d'abord les dépendances (cache Docker) ...
COPY composer.json composer.lock* ./
RUN composer install --no-interaction --no-scripts --no-autoloader --prefer-dist --no-dev || \
    composer install --no-interaction --no-scripts --no-autoloader --prefer-dist

# ... puis le code applicatif
COPY . .

RUN composer dump-autoload --optimize --no-dev \
    && mkdir -p storage/framework/{cache/data,sessions,views} storage/logs bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R 775 storage bootstrap/cache

EXPOSE 9000

COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

ENTRYPOINT ["entrypoint.sh"]
CMD ["php-fpm"]

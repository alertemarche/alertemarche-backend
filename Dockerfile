# AlerteMarché Backend — PHP 8.2 FPM + extensions Laravel
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

# Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

COPY . .

RUN composer install --no-interaction --optimize-autoloader --no-dev || true \
    && chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache 2>/dev/null || true

EXPOSE 9000
CMD ["php-fpm"]

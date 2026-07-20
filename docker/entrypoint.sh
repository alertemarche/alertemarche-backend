#!/bin/sh
set -e

# Générer APP_KEY seulement si aucune n'est fournie par l'environnement (env_file)
if [ -z "${APP_KEY:-}" ] && [ -f artisan ]; then
    # Un fichier .env est requis par key:generate ; on en crée un minimal si absent.
    [ -f .env ] || printf "APP_KEY=\n" > .env
    php artisan key:generate --force || true
fi

# Attendre PostgreSQL
echo "Attente de PostgreSQL..."
until php -r "try { new PDO('pgsql:host=${DB_HOST:-postgres};port=${DB_PORT:-5432};dbname=${DB_DATABASE:-alertemarche}', '${DB_USERNAME:-alertemarche}', '${DB_PASSWORD}'); exit(0); } catch (Exception \$e) { exit(1); }" 2>/dev/null; do
    sleep 2
done
echo "PostgreSQL prêt."

# Migrations + cache (uniquement pour le conteneur applicatif principal)
if [ "$1" = "php-fpm" ]; then
    php artisan migrate --force || true
    php artisan db:seed --force || true
    php artisan config:cache || true
    php artisan route:cache || true
fi

exec "$@"

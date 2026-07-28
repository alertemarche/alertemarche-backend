#!/bin/bash
#
# Déploiement du module Newsletter & Pub — AlerteMarché
# Applique les migrations, alimente les secteurs et reconstruit les caches.
#
set -e

echo "==> Déploiement Newsletter AlerteMarché..."

echo "--> Migrations de la base de données"
php artisan migrate --force

echo "--> Alimentation des secteurs (référentiel canonique)"
php artisan db:seed --class=SectorSeeder --force

echo "--> Nettoyage et reconstruction des caches"
php artisan optimize:clear
php artisan optimize

echo "==> Déploiement terminé."
echo "NB : assurez-vous qu'un worker de file d'attente tourne pour l'envoi des e-mails :"
echo "     php artisan queue:work --queue=default"

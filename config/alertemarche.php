<?php

return [
    // Pays couverts
    'countries' => ['BJ', 'TG', 'CI'],

    // Tarifs de base (FCFA / mois / pays) — tarif normal
    'prices' => [
        'artisan' => (int) env('PRICE_ARTISAN', 10000),
        'prestataire' => (int) env('PRICE_PRESTATAIRE', 50000),
        'admin' => (int) env('PRICE_ADMIN_ONG', 150000),
        'ong' => (int) env('PRICE_ADMIN_ONG', 150000),
    ],

    // Remise de lancement (%)
    'launch_discount_percent' => (int) env('LAUNCH_DISCOUNT_PERCENT', 50),

    // Freemium désactivé : aucune alerte gratuite. L'abonnement est obligatoire
    // pour recevoir des alertes. La consultation du site reste libre.
    'freemium_alerts' => (int) env('FREEMIUM_ALERTS', 0),
];

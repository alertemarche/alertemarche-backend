<?php

return [
    // Pays couverts
    'countries' => ['BJ', 'TG', 'CI', 'SN', 'BF'],

    // Tarifs de base (FCFA / mois / pays) — tarif normal
    'prices' => [
        'artisan' => (int) env('PRICE_ARTISAN', 10000),
        'prestataire' => (int) env('PRICE_PRESTATAIRE', 50000),
        'admin' => (int) env('PRICE_ADMIN_ONG', 150000),
        'ong' => (int) env('PRICE_ADMIN_ONG', 150000),
    ],

    // Remise de lancement (%)
    'launch_discount_percent' => (int) env('LAUNCH_DISCOUNT_PERCENT', 50),

    // Modèle 100% GRATUIT : tout le monde reçoit jusqu'à N alertes de marchés
    // ACTIFS par jour par e-mail. Plafond quotidien (remis à zéro chaque jour),
    // appliqué à tous les utilisateurs. Valeur fixée en dur pour rester
    // déterministe quel que soit l'environnement du conteneur.
    'daily_alerts' => 5,

    // Nombre maximum de domaines (secteurs) sélectionnables par un utilisateur.
    'max_sectors' => 3,

    // (Obsolète) ancien quota freemium « à vie ». Conservé pour compatibilité.
    'freemium_alerts' => 5,
];

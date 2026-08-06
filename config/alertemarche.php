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

    // Modèle « teaser » : un non-abonné reçoit UNE seule alerte l'informant que des
    // marchés correspondent à son domaine (sans les détails), l'invitant à s'abonner
    // pour y accéder. Ensuite, plus aucune alerte tant qu'il n'a pas payé.
    // Valeur fixée en dur (et non via env) pour rester déterministe quel que soit
    // l'environnement du conteneur. La consultation du site reste libre.
    'freemium_alerts' => 1,
];

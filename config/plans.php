<?php

/*
|--------------------------------------------------------------------------
| Formules d'abonnement AlerteMarché
|--------------------------------------------------------------------------
| Modèle par durée (une seule offre donnant accès à tout le service).
| Montants en FCFA. La durée est exprimée en mois pour le calcul de
| la date d'expiration. La remise (%) est purement informative/affichage.
*/

return [

    'currency' => 'XOF',

    'plans' => [
        'mensuel' => [
            'label' => 'Mensuel',
            'amount' => 20000,
            'duration_months' => 1,
            'discount' => 0,
            'period_label' => '/ mois',
        ],
        'trimestriel' => [
            'label' => 'Trimestriel',
            'amount' => 50000,
            'duration_months' => 3,
            'discount' => 7,
            'period_label' => '/ 3 mois',
        ],
        'semestriel' => [
            'label' => 'Semestriel',
            'amount' => 90000,
            'duration_months' => 6,
            'discount' => 10,
            'period_label' => '/ 6 mois',
        ],
        'annuel' => [
            'label' => 'Annuel',
            'amount' => 170000,
            'duration_months' => 12,
            'discount' => 20,
            'period_label' => '/ an',
        ],
    ],
];

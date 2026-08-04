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
            'amount' => 29500,
            'duration_months' => 1,
            'discount' => 0,
            'period_label' => '/ mois',
        ],
        'trimestriel' => [
            'label' => 'Trimestriel',
            'amount' => 73750,
            'duration_months' => 3,
            'discount' => 17,
            'period_label' => '/ 3 mois',
        ],
        'semestriel' => [
            'label' => 'Semestriel',
            'amount' => 132750,
            'duration_months' => 6,
            'discount' => 25,
            'period_label' => '/ 6 mois',
        ],
        'annuel' => [
            'label' => 'Annuel',
            'amount' => 265500,
            'duration_months' => 12,
            'discount' => 25,
            'period_label' => '/ an',
        ],
    ],
];

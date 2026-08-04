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
        'hebdomadaire' => [
            'label' => 'Hebdomadaire',
            'amount' => 5000,
            'duration_months' => 0.25,  // ~1 semaine (pour affichage)
            'duration_days' => 7,       // Durée exacte en jours (prioritaire pour calcul)
            'discount' => 0,
            'period_label' => '/ semaine',
        ],
        'mensuel' => [
            'label' => 'Mensuel',
            'amount' => 17700,
            'duration_months' => 1,
            'discount' => 0,
            'period_label' => '/ mois',
        ],
        'trimestriel' => [
            'label' => 'Trimestriel',
            'amount' => 44250,
            'duration_months' => 3,
            'discount' => 17,
            'period_label' => '/ 3 mois',
        ],
        'semestriel' => [
            'label' => 'Semestriel',
            'amount' => 79650,
            'duration_months' => 6,
            'discount' => 25,
            'period_label' => '/ 6 mois',
        ],
        'annuel' => [
            'label' => 'Annuel',
            'amount' => 159300,
            'duration_months' => 12,
            'discount' => 25,
            'period_label' => '/ an',
        ],
    ],
];

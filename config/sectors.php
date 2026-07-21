<?php

/*
|--------------------------------------------------------------------------
| Référentiel canonique des secteurs d'activité — AlerteMarché Bénin
|--------------------------------------------------------------------------
|
| SOURCE DE VÉRITÉ UNIQUE partagée par :
|   - App\Jobs\ProcessTenderJob::guessSectors()  (taggage local des marchés)
|   - App\Services\OpenAIService                 (prompt de classification IA)
|   - route GET /api/sectors                     (liste proposée au frontend)
|   - App\Services\MatchingService               (filtrage des notifications)
|
| La valeur stockée dans tenders.sectors ET users.sectors = le champ "name".
| Le matching se fait par intersection exacte de ces "name" (+ mots-clés perso).
|
| Les 21 secteurs et leurs mots-clés ont été dérivés par analyse de fréquence
| sur ~3900 intitulés réels de marchés béninois (couverture ≈ 91 %).
| Les mots-clés sont normalisés : minuscules + sans accents (comparaison via
| Illuminate\Support\Str::ascii). Un mot-clé matche par sous-chaîne (str_contains).
|
*/

return [

    'list' => [
        [
            'code' => 'btp',
            'name' => 'BTP & Travaux',
            'keywords' => ['travaux', 'construction', 'batiment', 'rehabilitation', 'refection', 'amenagement', 'voirie', 'route', 'pont', 'genie civil', 'ouvrage', 'cloture', 'pavage', 'asphalt', 'bitum', 'maconnerie', 'gros uvre', 'second uvre', 'terrassement', 'viabilisation', 'infrastructure', 'logement', 'siege', 'hangar', 'magasin', 'cantine', 'latrine', 'dallage', 'carrelage', 'peinture', 'etancheite', 'charpente', 'toiture', 'salles de classe', 'salle de classe', 'bureaux', 'module de classe'],
        ],
        [
            'code' => 'informatique',
            'name' => 'Informatique & Numérique',
            'keywords' => ['informatique', 'logiciel', 'numerique', 'digital', 'reseau informatique', 'ordinateur', 'application', "systeme d'information", 'plateforme', 'progiciel', 'erp', 'site web', 'serveur', 'base de donnees', "developpement d'application", 'solution logicielle', 'teleinformatique', 'cablage', 'cybersecurite', 'hebergement', 'licence', 'systeme de gestion', 'dematerialisation'],
        ],
        [
            'code' => 'telecom',
            'name' => 'Télécommunications',
            'keywords' => ['telecom', 'telephonie', 'fibre optique', 'gsm', 'internet', 'connexion', 'liaison', 'antenne', 'bande passante', 'voip', 'reseau telephonique'],
        ],
        [
            'code' => 'sante',
            'name' => 'Santé',
            'keywords' => ['sante', 'medic', 'hopital', 'hospitalier', 'sanitaire', 'pharmac', 'clinique', 'soins', 'chirurg', "laboratoire d'analyse", 'reactif', 'dispositif medical', 'consommable medical', 'vaccin', 'ambulance', 'imagerie', 'echograph', 'dentaire', 'maternite', 'zone sanitaire'],
        ],
        [
            'code' => 'agriculture',
            'name' => 'Agriculture, Élevage & Pêche',
            'keywords' => ['agric', 'semence', 'engrais', 'elevage', 'peche', 'intrant', 'plant', 'irrigation', 'betail', 'volaille', 'aviculture', 'pisciculture', 'aquacole', 'tracteur', 'phytosanitaire', 'cultur', 'fertilisant', 'vivrier', 'provende', 'fourrage', 'aliment betail', 'agroalimentaire', 'transformation agricole'],
        ],
        [
            'code' => 'energie',
            'name' => 'Énergie & Électricité',
            'keywords' => ['energie', 'electr', 'solaire', 'photovolt', 'groupe electrogene', 'lampadaire', 'eclairage public', 'transformateur', 'panneau solaire', 'kit solaire', 'reseau electrique', 'poste de transformation', 'onduleur', 'batterie', 'sbee', 'cablage electrique', 'hydrocarbure', 'petrolier'],
        ],
        [
            'code' => 'eau',
            'name' => 'Eau & Hydraulique',
            'keywords' => ['hydraulique', 'forage', "adduction d'eau", "chateau d'eau", 'pompe', 'aep', "point d'eau", 'potable', 'canalisation', "reseau d'eau", 'station de traitement', 'borne fontaine', 'puits', 'eau potable'],
        ],
        [
            'code' => 'transport',
            'name' => 'Transport & Logistique',
            'keywords' => ['transport', 'vehicule', 'roulant', 'logistique', 'automobile', 'moto', 'engin', 'camion', 'autobus', 'pirogue', 'carburant', 'fret', 'charroi', 'pneu', 'piece de rechange', 'aeroport', 'portuaire', 'aeroportuaire'],
        ],
        [
            'code' => 'education',
            'name' => 'Éducation & Formation',
            'keywords' => ['ecol', 'education', 'enseign', 'scolaire', 'formation', 'universite', 'pedagog', 'module de formation', 'renforcement de capacites', 'atelier de formation', 'apprentissage', 'alphabetisation', 'manuel scolaire', 'table-banc', 'kit scolaire', 'bourse', 'academique', 'lycee', 'college'],
        ],
        [
            'code' => 'environnement',
            'name' => 'Environnement & Assainissement',
            'keywords' => ['environnement', 'dechet', 'assainissement', 'hygiene', 'reboisement', 'climat', 'ordures', 'salubrite', 'gestion des dechets', 'boues', 'recyclage', 'depollution', 'espaces verts', 'impact environnemental', 'changement climatique', 'biodiversite'],
        ],
        [
            'code' => 'finance',
            'name' => 'Finance, Audit & Assurance',
            'keywords' => ['financ', 'assurance', 'comptab', 'audit', 'banque', 'fiscal', 'budget', 'microfinance', 'credit', 'recouvrement', 'controle de gestion', 'commissaire aux comptes', 'expertise comptable', 'tresorerie', 'bancaire'],
        ],
        [
            'code' => 'fournitures',
            'name' => 'Fournitures, Équipements & Mobilier',
            'keywords' => ['fourniture', 'acquisition', 'equipement', 'mobilier', 'materiel', 'consommable', 'climatiseur', 'imprimante', 'photocopieur', 'kit', 'dotation', 'reprographie', 'toner', 'cartouche', 'groupe froid'],
        ],
        [
            'code' => 'communication',
            'name' => 'Communication, Média & Impression',
            'keywords' => ['communication', 'media', 'impression', 'imprimerie', 'publicite', 'edition', 'reportage', 'audiovisuel', 'spot', 'affichage', 'banderole', 'gadget', 'serigraphie', 'production audiovisuelle', 'couverture mediatique', 'boites a images', 'relations publiques'],
        ],
        [
            'code' => 'conseil',
            'name' => 'Conseil, Études & Consultance',
            'keywords' => ['consultant', 'cabinet', 'etude', 'faisabilite', 'diagnostic', 'strategie', 'elaboration', 'evaluation', "maitrise d'uvre", 'assistance technique', 'expertise', 'schema directeur', 'plan strategique', 'enquete', 'recensement', 'cartographie', 'actualisation', "maitrise d'ouvrage", 'termes de reference', 'appui technique', 'suivi-evaluation'],
        ],
        [
            'code' => 'securite',
            'name' => 'Sécurité & Défense',
            'keywords' => ['securite', 'gardiennage', 'surveillance', 'defense', 'militaire', 'police', 'incendie', 'extincteur', 'camera', 'videosurveillance', "controle d'acces", 'secours', 'protection civile', 'sapeur'],
        ],
        [
            'code' => 'nettoyage',
            'name' => 'Nettoyage & Entretien',
            'keywords' => ['nettoyage', 'entretien', 'gardienn', 'salubrite', 'desinfection', 'desinsectisation', 'curage', 'maintenance des locaux', 'proprete', 'espaces verts'],
        ],
        [
            'code' => 'hotellerie',
            'name' => 'Hôtellerie, Restauration & Événementiel',
            'keywords' => ['hotel', 'restauration', 'hebergement', 'evenement', 'seminaire', 'pause-cafe', 'traiteur', 'location de salle', 'ceremonie', 'cantine scolaire', 'organisation de'],
        ],
        [
            'code' => 'juridique',
            'name' => 'Juridique & Réglementaire',
            'keywords' => ['juridique', 'avocat', 'notaire', 'huissier', 'contentieux', 'reglementaire', 'textes de loi', 'legislation', 'redaction de textes'],
        ],
        [
            'code' => 'textile',
            'name' => 'Textile & Habillement',
            'keywords' => ['textile', 'habillement', 'uniforme', 'tenue', 'couture', 'confection', 'tissu', 'chaussure', "equipement de protection individuelle", 'epi', 'pagne'],
        ],
        [
            'code' => 'mines',
            'name' => 'Mines, Géologie & Foncier',
            'keywords' => ['mine', 'geolog', 'carriere', 'topograph', 'cadastre', 'foncier', 'domanial', 'borne', 'lotissement', 'geometre', 'releve topographique', 'immobilier'],
        ],
        [
            'code' => 'culture',
            'name' => 'Culture, Tourisme & Sport',
            'keywords' => ['culture', 'tourisme', 'sport', 'artisanat', 'patrimoine', 'musee', 'festival', 'stade', 'terrain de sport', 'loisir', 'jeunesse'],
        ],
    ],
];

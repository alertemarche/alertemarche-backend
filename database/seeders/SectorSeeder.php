<?php

namespace Database\Seeders;

use App\Models\Sector;
use Illuminate\Database\Seeder;

class SectorSeeder extends Seeder
{
    public function run(): void
    {
        $secteurs = [
            'btp' => 'BTP & Construction', 'informatique' => 'Informatique & Numérique',
            'sante' => 'Santé', 'agriculture' => 'Agriculture & Agroalimentaire',
            'energie' => 'Énergie', 'transport' => 'Transport & Logistique',
            'education' => 'Éducation & Formation', 'environnement' => 'Environnement',
            'finance' => 'Finance & Assurance', 'fournitures' => 'Fournitures & Équipements',
        ];
        foreach ($secteurs as $code => $name) {
            Sector::updateOrCreate(['code' => $code], ['name' => $name, 'type' => 'secteur']);
        }

        $metiers = [
            'maconnerie' => 'Maçonnerie', 'electricite' => 'Électricité', 'plomberie' => 'Plomberie',
            'menuiserie' => 'Menuiserie', 'peinture' => 'Peinture', 'soudure' => 'Soudure',
            'carrelage' => 'Carrelage', 'climatisation' => 'Climatisation & Froid',
        ];
        foreach ($metiers as $code => $name) {
            Sector::updateOrCreate(['code' => $code], ['name' => $name, 'type' => 'metier']);
        }
    }
}

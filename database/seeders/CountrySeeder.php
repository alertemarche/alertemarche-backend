<?php

namespace Database\Seeders;

use App\Models\Country;
use Illuminate\Database\Seeder;

class CountrySeeder extends Seeder
{
    public function run(): void
    {
        $countries = [
            ['code' => 'BJ', 'name' => 'Bénin', 'flag_emoji' => '🇧🇯'],
            ['code' => 'TG', 'name' => 'Togo', 'flag_emoji' => '🇹🇬'],
            ['code' => 'CI', 'name' => "Côte d'Ivoire", 'flag_emoji' => '🇨🇮'],
            ['code' => 'SN', 'name' => 'Sénégal', 'flag_emoji' => '🇸🇳'],
        ];

        foreach ($countries as $c) {
            Country::updateOrCreate(['code' => $c['code']], $c + ['currency' => 'FCFA', 'active' => true]);
        }
    }
}

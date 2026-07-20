<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class UserFactory extends Factory
{
    protected $model = \App\Models\User::class;

    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'phone' => '+229'.fake()->numerify('########'),
            'email_verified_at' => now(),
            'password' => Hash::make('password'),
            'profile_type' => fake()->randomElement(['prestataire', 'artisan', 'admin_public', 'ong']),
            'primary_country' => fake()->randomElement(['BJ', 'TG', 'CI']),
            'remember_token' => Str::random(10),
        ];
    }

    public function admin(): static
    {
        return $this->state(fn () => ['is_admin' => true, 'profile_type' => 'admin_public']);
    }

    public function artisan(): static
    {
        return $this->state(fn () => [
            'profile_type' => 'artisan',
            'artisan_trade' => 'Maçonnerie',
            'artisan_locality' => 'Parakou',
            'artisan_radius_km' => 50,
        ]);
    }
}

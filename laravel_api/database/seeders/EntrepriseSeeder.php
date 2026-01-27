<?php

namespace Database\Seeders;

use App\Models\Entreprise;
use Illuminate\Database\Seeder;

class EntrepriseSeeder extends Seeder
{
    public function run()
    {
        $entreprises = [
            ['nom' => 'Entreprise A'],
            ['nom' => 'Entreprise B'],
            ['nom' => 'Entreprise C'],
            ['nom' => 'Entreprise D'],
            ['nom' => 'Entreprise E'],
        ];

        foreach ($entreprises as $entreprise) {
            Entreprise::create($entreprise);
        }
    }
}
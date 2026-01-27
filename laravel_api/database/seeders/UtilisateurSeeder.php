<?php

namespace Database\Seeders;

use App\Models\Utilisateur;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UtilisateurSeeder extends Seeder
{
    public function run()
    {
        $utilisateurs = [
            [
                'nom' => 'Admin User',
                'email' => 'admin@example.com',
                'mot_de_passe' => 'admin123',
                'role'=>3,
            ],
            [
                'nom' => 'Regular User',
                'email' => 'user@example.com',
                'mot_de_passe' => 'user123',
                'role'=>2,
            ],
            [
                'nom' => 'Technicien',
                'email' => 'tech@example.com',
                'mot_de_passe' => 'tech123',
                'role'=>3,
            ],
        ];

        foreach ($utilisateurs as $utilisateur) {
            Utilisateur::create($utilisateur);
        }
    }
}
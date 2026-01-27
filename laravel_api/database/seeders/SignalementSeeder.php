<?php

namespace Database\Seeders;

use App\Models\Signalement;
use App\Models\Utilisateur;
use App\Models\HistoSignalement;
use Illuminate\Database\Seeder;

class SignalementSeeder extends Seeder
{
    public function run()
    {
        $utilisateurs = Utilisateur::all();
        
        $signalements = [
            [
                'titre' => 'Route endommagée',
                'description' => 'Nid de poule important sur la route principale',
                'latitude' => 48.856614,
                'longitude' => 2.3522219,
                'statut' => 1,
                'surface_m2' => 5.5,
                'budget' => 1500.00,
                'avancement' => 0,
                'entreprise_responsable' => 'Entreprise A',
                'utilisateur_id' => $utilisateurs->first()->id,
            ],
            [
                'titre' => 'Éclairage public défectueux',
                'description' => 'Lampadaire non fonctionnel dans la rue des Lilas',
                'latitude' => 48.8583701,
                'longitude' => 2.2944813,
                'statut' => 2,
                'surface_m2' => null,
                'budget' => 800.00,
                'avancement' => 50,
                'entreprise_responsable' => 'Entreprise B',
                'utilisateur_id' => $utilisateurs->get(1)->id,
            ],
            [
                'titre' => 'Déchets sauvages',
                'description' => 'Amas de déchets près du parc',
                'latitude' => 48.8462207,
                'longitude' => 2.3371608,
                'statut' => 3,
                'surface_m2' => 12.3,
                'budget' => 500.00,
                'avancement' => 100,
                'entreprise_responsable' => 'Entreprise C',
                'utilisateur_id' => $utilisateurs->first()->id,
                'synchronise_firebase' => true,
            ],
        ];

        foreach ($signalements as $signalementData) {
            // Créer le signalement
            $signalement = Signalement::create($signalementData);
            
            // Créer un historique pour ce signalement
            HistoSignalement::create([
                'signalement_id' => $signalement->id,
                'statut' => $signalement->statut,
            ]);
        }
    }
}
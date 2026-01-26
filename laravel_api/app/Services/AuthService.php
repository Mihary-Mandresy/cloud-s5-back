<?php

namespace App\Services;

use App\Models\Utilisateur;
use App\Models\TentativeConnexion;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Tymon\JWTAuth\Facades\JWTAuth;
use Illuminate\Support\Facades\Log;

class AuthService
{
    private $maxTentatives = 5;
    private $dureeBlocageMinutes = 15;

    /**
     * Vérifier les informations de connexion
     */
    public function verifierConnexion($email, $password, $ip)
    {
        // Vérifier les tentatives
        if ($this->estBloqueParIP($ip)) {
            return ['success' => false, 'message' => 'Trop de tentatives. Veuillez réessayer plus tard.'];
        }

        // Trouver l'utilisateur
        $utilisateur = Utilisateur::where('email', $email)->first();

        if (!$utilisateur) {
            $this->enregistrerTentative($email, $ip, false);
            return ['success' => false, 'message' => 'Identifiants incorrects'];
        }

        // Vérifier si le compte est bloqué
        if ($utilisateur->est_bloque) {
            if ($utilisateur->derniere_tentative) {
                $derniereTentative = Carbon::parse($utilisateur->derniere_tentative);
                if (Carbon::now()->diffInMinutes($derniereTentative) < $this->dureeBlocageMinutes) {
                    return ['success' => false, 'message' => 'Compte temporairement bloqué'];
                } else {
                    $utilisateur->est_bloque = false;
                    $utilisateur->tentatives_connexion = 0;
                }
            }
        }

        // Vérifier le mot de passe (non crypté dans votre cas)
        if ($utilisateur->mot_de_passe !== $password) {
            $utilisateur->tentatives_connexion += 1;
            $utilisateur->derniere_tentative = Carbon::now();
            
            if ($utilisateur->tentatives_connexion >= $this->maxTentatives) {
                $utilisateur->est_bloque = true;
                $utilisateur->save();
                return ['success' => false, 'message' => 'Compte bloqué après trop de tentatives'];
            }
            
            $utilisateur->save();
            $this->enregistrerTentative($email, $ip, false);
            return ['success' => false, 'message' => 'Identifiants incorrects'];
        }

        // Connexion réussie
        $utilisateur->tentatives_connexion = 0;
        $utilisateur->est_bloque = false;
        $utilisateur->save();

        $this->enregistrerTentative($email, $ip, true);
        $this->reinitialiserTentativesIP($ip);

        return ['success' => true, 'utilisateur' => $utilisateur];
    }

    public function genererToken($utilisateur)
    {   
        $ttl = 60; 
        
        if ((int) $utilisateur->role === 3) {
            $ttl = 120;
        }
        
        $originalTtl = config('jwt.ttl');
        
        config(['jwt.ttl' => (int) $ttl]);
        
        try {
            $customClaims = [
                'role' => (int) $utilisateur->role,
                'email' => $utilisateur->email,
            ];
            
            return JWTAuth::claims($customClaims)->fromUser($utilisateur);
        } catch (\Exception $e) {
            config(['jwt.ttl' => $originalTtl]);
            throw $e;
        }
    }

    public function verifierTentativesIP($ip)
    {
        $limite = (int) config('auth.tentatives.max', 10);
        $periode = (int) config('auth.tentatives.periode', 15);
        Log::info('PERIODE TYPE', [
            'value' => $periode,
            'type' => gettype($periode)
        ]);
        $tentatives = TentativeConnexion::where('ip', $ip)
            ->where('date_tentative', '>=', Carbon::now()->subMinutes($periode))
            ->where('nombre_tentative', '>', 0)
            ->sum('nombre_tentative');

        return $tentatives >= $limite;
    }


    /**
     * Est bloqué par IP
     */
    public function estBloqueParIP($ip)
    {
        return $this->verifierTentativesIP($ip);
    }

    /**
     * Enregistrer une tentative
     */
    private function enregistrerTentative($email, $ip, $success)
    {
        TentativeConnexion::create([
            'email' => $email,
            'ip' => $ip,
            'nombre_tentative' => $success ? 0 : 1,
            'date_tentative' => Carbon::now(),
        ]);
    }

    public function reinitialiserTentativesIP($ip)
    {
        TentativeConnexion::where('ip', $ip)
            ->where('date_tentative', '>=', Carbon::now()->subMinutes(30))
            ->update(['nombre_tentative' => 0]);
    }


    /**
     * Inscrire un nouvel utilisateur
     */
    public function inscrireUtilisateur($data)
    {
        DB::beginTransaction();
        
        try {
            $utilisateur = Utilisateur::create([
                'email' => $data['email'],
                'mot_de_passe' => $data['mot_de_passe'], // Note: non crypté
                'role' => $data['role'] ?? 2, // utilisateur par défaut
                'nom' => $data['nom'] ?? null,
                'date_inscription' => Carbon::now(),
            ]);

            DB::commit();
            return ['success' => true, 'utilisateur' => $utilisateur];
        } catch (\Exception $e) {
            DB::rollBack();
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Modifier email/mot de passe
     */
    public function modifierProfil($utilisateurId, $data)
    {
        $utilisateur = Utilisateur::find($utilisateurId);
        
        if (!$utilisateur) {
            return ['success' => false, 'message' => 'Utilisateur non trouvé'];
        }

        if (isset($data['email'])) {
            $utilisateur->email = $data['email'];
        }
        
        if (isset($data['mot_de_passe'])) {
            $utilisateur->mot_de_passe = $data['mot_de_passe']; // Note: non crypté
        }

        if (isset($data['nom'])) {
            $utilisateur->nom = $data['nom'];
        }

        $utilisateur->save();
        
        return ['success' => true, 'utilisateur' => $utilisateur];
    }
}
<?php

namespace App\Http\Controllers;

use App\Services\AuthService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Tymon\JWTAuth\Facades\JWTAuth;


class AuthController extends Controller
{
    protected $authService;

    public function __construct(AuthService $authService)
    {
        $this->authService = $authService;
    }

    /**
     * @OA\Post(
     *     path="/login",
     *     summary="Connexion utilisateur",
     *     tags={"Authentication"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"email","password"},
     *             @OA\Property(property="email", type="string", format="email"),
     *             @OA\Property(property="password", type="string", format="password")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Connexion réussie",
     *         @OA\JsonContent(ref="#/components/schemas/LoginResponse")
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Échec de connexion",
     *         @OA\JsonContent(ref="#/components/schemas/ErrorResponse")
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Erreur de validation",
     *         @OA\JsonContent(ref="#/components/schemas/ErrorResponse")
     *     )
     * )
     */
    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $ip = $request->ip();
        $result = $this->authService->verifierConnexion(
            $request->email,
            $request->password,
            $ip
        );

        if (!$result['success']) {
            return response()->json([
                'success' => false,
                'message' => $result['message']
            ], 401);
        }

        // Générer le token
        $token = $this->authService->genererToken($result['utilisateur']);

        return response()->json([
            'success' => true,
            'token' => $token,
            'token_type' => 'bearer',
            'expires_in' =>(int) config('jwt.ttl') * 60,
            'user' => [
                'id' => $result['utilisateur']->id,
                'email' => $result['utilisateur']->email,
                'role' => $result['utilisateur']->role,
                'nom' => $result['utilisateur']->nom,
            ]
        ]);
    }

    /**
     * API d'inscription
     */
    public function register(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email|unique:utilisateurs,email',
            'mot_de_passe' => 'required|string|min:6',
            'nom' => 'nullable|string|max:100',
            'role' => 'nullable|integer|in:1,2,3',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $result = $this->authService->inscrireUtilisateur($request->all());

        if (!$result['success']) {
            return response()->json([
                'success' => false,
                'message' => $result['message']
            ], 400);
        }

        // Connecter l'utilisateur après inscription
        $token = $this->authService->genererToken($result['utilisateur']);

        return response()->json([
            'success' => true,
            'message' => 'Inscription réussie',
            'token' => $token,
            'user' => $result['utilisateur']
        ], 201);
    }

    /**
     * API de modification de profil
     */
    public function updateProfile(Request $request)
    {
        $user = \Illuminate\Support\Facades\Auth::user();
        
        $validator = Validator::make($request->all(), [
            'email' => 'sometimes|email|unique:utilisateurs,email,' . $user->id,
            'mot_de_passe' => 'sometimes|string|min:6',
            'nom' => 'nullable|string|max:100',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $result = $this->authService->modifierProfil($user->id, $request->all());

        if (!$result['success']) {
            return response()->json([
                'success' => false,
                'message' => $result['message']
            ], 400);
        }

        return response()->json([
            'success' => true,
            'message' => 'Profil mis à jour avec succès',
            'user' => $result['utilisateur']
        ]);
    }

    /**
     * API de déconnexion
     */
    public function logout()
    {
        \Illuminate\Support\Facades\Auth::logout();
        
        return response()->json([
            'success' => true,
            'message' => 'Déconnexion réussie'
        ]);
    }

    /**
     * API de rafraîchissement de token
     */
    public function refresh()
    {
        $user = \Illuminate\Support\Facades\Auth::user();
        $token = $this->authService->genererToken($user);
        
        return response()->json([
            'success' => true,
            'token' => $token,
            'token_type' => 'bearer',
            'expires_in' => config('jwt.ttl') * 60
        ]);
    }

    /**
     * API pour réinitialiser les tentatives (admin seulement)
     */
    public function resetTentatives(Request $request)
    {
        $user = \Illuminate\Support\Facades\Auth::user();
        
        // Vérifier si c'est un manager
        if ($user->role != 3) {
            return response()->json([
                'success' => false,
                'message' => 'Accès non autorisé'
            ], 403);
        }

        $ip = $request->ip;
        
        if ($ip) {
            $this->authService->reinitialiserTentativesIP($ip);
            return response()->json([
                'success' => true,
                'message' => 'Tentatives réinitialisées pour IP: ' . $ip
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'IP requise'
        ], 400);
    }


    public function me()
    {
        try {
            $user = JWTAuth::parseToken()->authenticate();
            
            return response()->json([
                'success' => true,
                'user' => $user
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Utilisateur non authentifié'
            ], 401);
        }
    }
    
}
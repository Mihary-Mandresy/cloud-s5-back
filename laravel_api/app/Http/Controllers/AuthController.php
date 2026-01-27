<?php

namespace App\Http\Controllers;

use App\Services\AuthService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Tymon\JWTAuth\Facades\JWTAuth;
use OpenApi\Attributes as OA;

class AuthController extends Controller
{
    protected $authService;

    public function __construct(AuthService $authService)
    {
        $this->authService = $authService;
    }

    #[OA\Post(
        path: '/login',
        operationId: 'login',
        summary: 'Connexion utilisateur',
        description: 'Authentifie un utilisateur et retourne un token JWT',
        requestBody: new OA\RequestBody(
            required: true,
            description: 'Credentials de l\'utilisateur',
            content: new OA\JsonContent(ref: '#/components/schemas/LoginRequest')
        ),
        tags: ['Authentication'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Connexion réussie',
                content: new OA\JsonContent(ref: '#/components/schemas/LoginResponse')
            ),
            new OA\Response(
                response: 401,
                description: 'Échec de connexion',
                content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')
            ),
            new OA\Response(
                response: 422,
                description: 'Erreur de validation',
                content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')
            )
        ]
    )]
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

    #[OA\Post(
        path: '/register',
        operationId: 'register',
        summary: 'Inscription utilisateur',
        description: 'Crée un nouveau compte utilisateur',
        requestBody: new OA\RequestBody(
            required: true,
            description: 'Données d\'inscription',
            content: new OA\JsonContent(ref: '#/components/schemas/RegisterRequest')
        ),
        tags: ['Authentication'],
        responses: [
            new OA\Response(
                response: 201,
                description: 'Inscription réussie',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean'),
                        new OA\Property(property: 'message', type: 'string'),
                        new OA\Property(property: 'token', type: 'string'),
                        new OA\Property(property: 'user', ref: '#/components/schemas/User')
                    ]
                )
            ),
            new OA\Response(
                response: 422,
                description: 'Erreur de validation',
                content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')
            ),
            new OA\Response(
                response: 400,
                description: 'Erreur lors de l\'inscription',
                content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')
            )
        ]
    )]
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

    #[OA\Put(
        path: '/profile',
        operationId: 'updateProfile',
        summary: 'Mettre à jour le profil',
        description: 'Met à jour les informations du profil utilisateur',
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            description: 'Données de mise à jour',
            content: new OA\JsonContent(ref: '#/components/schemas/UpdateProfileRequest')
        ),
        tags: ['User'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Profil mis à jour avec succès',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean'),
                        new OA\Property(property: 'message', type: 'string'),
                        new OA\Property(property: 'user', ref: '#/components/schemas/User')
                    ]
                )
            ),
            new OA\Response(
                response: 422,
                description: 'Erreur de validation',
                content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')
            ),
            new OA\Response(
                response: 400,
                description: 'Erreur lors de la mise à jour',
                content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')
            ),
            new OA\Response(
                response: 401,
                description: 'Non authentifié',
                content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')
            )
        ]
    )]
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

    #[OA\Post(
        path: '/logout',
        operationId: 'logout',
        summary: 'Déconnexion',
        description: 'Déconnecte l\'utilisateur et invalide le token',
        security: [['bearerAuth' => []]],
        tags: ['Authentication'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Déconnexion réussie',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean'),
                        new OA\Property(property: 'message', type: 'string')
                    ]
                )
            ),
            new OA\Response(
                response: 401,
                description: 'Non authentifié',
                content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')
            )
        ]
    )]
    public function logout()
    {
        \Illuminate\Support\Facades\Auth::logout();
        
        return response()->json([
            'success' => true,
            'message' => 'Déconnexion réussie'
        ]);
    }

    #[OA\Post(
        path: '/refresh',
        operationId: 'refreshToken',
        summary: 'Rafraîchir le token',
        description: 'Génère un nouveau token JWT',
        security: [['bearerAuth' => []]],
        tags: ['Authentication'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Token rafraîchi avec succès',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean'),
                        new OA\Property(property: 'token', type: 'string'),
                        new OA\Property(property: 'token_type', type: 'string'),
                        new OA\Property(property: 'expires_in', type: 'integer')
                    ]
                )
            ),
            new OA\Response(
                response: 401,
                description: 'Non authentifié',
                content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')
            )
        ]
    )]
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

    #[OA\Post(
        path: '/reset-tentatives',
        operationId: 'resetTentatives',
        summary: 'Réinitialiser les tentatives de connexion',
        description: 'Réinitialise les tentatives de connexion échouées pour une IP (Admin seulement)',
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            description: 'Adresse IP à réinitialiser',
            content: new OA\JsonContent(
                required: ['ip'],
                properties: [
                    new OA\Property(property: 'ip', type: 'string', format: 'ipv4')
                ]
            )
        ),
        tags: ['Admin'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Tentatives réinitialisées',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean'),
                        new OA\Property(property: 'message', type: 'string')
                    ]
                )
            ),
            new OA\Response(
                response: 400,
                description: 'IP requise',
                content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')
            ),
            new OA\Response(
                response: 403,
                description: 'Accès non autorisé',
                content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')
            ),
            new OA\Response(
                response: 401,
                description: 'Non authentifié',
                content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')
            )
        ]
    )]
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

    #[OA\Get(
        path: '/me',
        operationId: 'getUser',
        summary: 'Obtenir l\'utilisateur courant',
        description: 'Retourne les informations de l\'utilisateur authentifié',
        security: [['bearerAuth' => []]],
        tags: ['User'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Utilisateur récupéré avec succès',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean'),
                        new OA\Property(property: 'user', ref: '#/components/schemas/User')
                    ]
                )
            ),
            new OA\Response(
                response: 401,
                description: 'Non authentifié',
                content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')
            )
        ]
    )]
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
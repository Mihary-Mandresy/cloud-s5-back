<?php

namespace App\Http\Controllers;

/**
 * @OA\Post(
 *     path="/api/login",
 *     tags={"Authentication"},
 *     summary="Connexion utilisateur",
 *     description="Authentifie un utilisateur et retourne un token JWT",
 *     @OA\RequestBody(
 *         required=true,
 *         @OA\JsonContent(ref="#/components/schemas/LoginRequest")
 *     ),
 *     @OA\Response(
 *         response=200,
 *         description="Connexion réussie",
 *         @OA\JsonContent(ref="#/components/schemas/LoginResponse")
 *     ),
 *     @OA\Response(
 *         response=401,
 *         description="Identifiants incorrects",
 *         @OA\JsonContent(ref="#/components/schemas/ErrorResponse")
 *     )
 * )
 */
class SwaggerController extends Controller
{
    // Cette classe sert uniquement à contenir les annotations Swagger
}
<?php

namespace App\Http\Controllers;

use OpenApi\Attributes as OA;

#[OA\Info(
    version: '1.0.0',
    title: 'API Authentication',
    description: 'API d\'authentification avec JWT',
    contact: new OA\Contact(email: 'support@example.com')
)]
#[OA\Server(
    url: 'http://localhost:8000/api',
    description: 'Serveur API local'
)]
#[OA\SecurityScheme(
    securityScheme: 'bearerAuth',
    type: 'http',
    scheme: 'bearer',
    bearerFormat: 'JWT'
)]
#[OA\Schema(
    schema: 'LoginRequest',
    required: ['email', 'password'],
    properties: [
        new OA\Property(property: 'email', type: 'string', format: 'email', example: 'user@example.com'),
        new OA\Property(property: 'password', type: 'string', format: 'password', example: 'password123')
    ]
)]
#[OA\Schema(
    schema: 'RegisterRequest',
    required: ['email', 'mot_de_passe'],
    properties: [
        new OA\Property(property: 'email', type: 'string', format: 'email', example: 'newuser@example.com'),
        new OA\Property(property: 'mot_de_passe', type: 'string', format: 'password', minLength: 6, example: 'password123'),
        new OA\Property(property: 'nom', type: 'string', maxLength: 100, example: 'John Doe'),
        new OA\Property(property: 'role', type: 'integer', example: 2, description: '1=user, 2=admin, 3=manager')
    ]
)]
#[OA\Schema(
    schema: 'UpdateProfileRequest',
    properties: [
        new OA\Property(property: 'email', type: 'string', format: 'email', example: 'updated@example.com'),
        new OA\Property(property: 'mot_de_passe', type: 'string', format: 'password', minLength: 6, example: 'newpassword123'),
        new OA\Property(property: 'nom', type: 'string', maxLength: 100, example: 'Jane Doe')
    ]
)]
#[OA\Schema(
    schema: 'User',
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 1),
        new OA\Property(property: 'email', type: 'string', example: 'user@example.com'),
        new OA\Property(property: 'role', type: 'integer', example: 2),
        new OA\Property(property: 'nom', type: 'string', example: 'John Doe')
    ]
)]
#[OA\Schema(
    schema: 'LoginResponse',
    properties: [
        new OA\Property(property: 'success', type: 'boolean', example: true),
        new OA\Property(property: 'token', type: 'string', example: 'eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9...'),
        new OA\Property(property: 'token_type', type: 'string', example: 'bearer'),
        new OA\Property(property: 'expires_in', type: 'integer', example: 3600),
        new OA\Property(property: 'user', ref: '#/components/schemas/User')
    ]
)]
#[OA\Schema(
    schema: 'ErrorResponse',
    properties: [
        new OA\Property(property: 'success', type: 'boolean', example: false),
        new OA\Property(property: 'message', type: 'string', example: 'Erreur d\'authentification')
    ]
)]
class OpenApi
{
    // Cette classe ne contient que des annotations Swagger
}
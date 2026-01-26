<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Tymon\JWTAuth\Facades\JWTAuth;
use Carbon\Carbon;

class CheckTokenExpiration
{
    public function handle(Request $request, Closure $next)
    {
        try {
            $token = JWTAuth::parseToken();
            $payload = $token->getPayload();
            
            // Vérifier l'expiration
            if ($payload->get('exp') < Carbon::now()->timestamp) {
                return response()->json([
                    'success' => false,
                    'message' => 'Token expiré'
                ], 401);
            }
            
            // Vérifier la durée de vie restante
            $expiresAt = $payload->get('exp');
            $remaining = $expiresAt - Carbon::now()->timestamp;
            
            if ($remaining < 300) { // 5 minutes restantes
                $request->headers->set('X-Token-Refresh-Needed', 'true');
            }
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Token invalide'
            ], 401);
        }
        
        return $next($request);
    }
}
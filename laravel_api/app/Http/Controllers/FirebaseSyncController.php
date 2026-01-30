<?php

namespace App\Http\Controllers;

use App\Services\FirebaseSyncService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class FirebaseSyncController extends Controller
{
    private $syncService;
    
    public function __construct(FirebaseSyncService $syncService)
    {
        $this->syncService = $syncService;
    }
    
    /**
     * Compare les données locales et Firebase
     */
    public function compare()
    {
        try {
            Log::info("API: Comparaison des données appelée");
            $result = $this->syncService->compareData();
            
            return response()->json($result);
            
        } catch (\Exception $e) {
            Log::error("Erreur API comparaison", ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Synchronise toutes les données (bidirectionnel)
     */
    public function synchronizeAll()
    {
        try {
            Log::info("API: Synchronisation complète appelée");
            $result = $this->syncService->synchronizeAll();
            
            return response()->json($result);
            
        } catch (\Exception $e) {
            Log::error("Erreur API synchronisation", ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Synchronise local → Firebase seulement
     */
    public function syncToFirebase()
    {
        try {
            Log::info("API: Sync vers Firebase appelée");
            $result = $this->syncService->forceSyncToFirebase();
            
            return response()->json($result);
            
        } catch (\Exception $e) {
            Log::error("Erreur API sync vers Firebase", ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Synchronise Firebase → local seulement
     */
    public function syncFromFirebase()
    {
        try {
            Log::info("API: Sync depuis Firebase appelée");
            $result = $this->syncService->forceSyncFromFirebase();
            
            return response()->json($result);
            
        } catch (\Exception $e) {
            Log::error("Erreur API sync depuis Firebase", ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Statistiques de synchronisation
     */
    public function statistics()
    {
        try {
            $comparison = $this->syncService->compareData();
            
            if (!$comparison['success']) {
                throw new \Exception($comparison['error']);
            }
            
            $stats = $comparison['comparison']['summary'];
            
            $response = [
                'success' => true,
                'statistics' => [
                    'total_local' => $stats['total']['local_count'],
                    'total_firebase' => $stats['total']['firebase_count'],
                    'synchronised' => $stats['total']['synchronised'],
                    'missing_data' => $stats['total']['total_missing'],
                    'by_type' => []
                ]
            ];
            
            foreach (['utilisateurs', 'signalements', 'entreprises', 'roles'] as $type) {
                $response['statistics']['by_type'][$type] = [
                    'local' => $stats[$type]['local_count'],
                    'firebase' => $stats[$type]['firebase_count'],
                    'missing_local' => $stats[$type]['missing_in_local'],
                    'missing_firebase' => $stats[$type]['missing_in_firebase'],
                    'synchronised' => $stats[$type]['missing_in_local'] == 0 && 
                                   $stats[$type]['missing_in_firebase'] == 0
                ];
            }
            
            return response()->json($response);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Vérifie l'état de la connexion Firebase
     */
    public function checkConnection()
    {
        try {
            // Vérifier la connexion via le service FirebaseSimple
            $service = app(\App\Services\FirebaseSimpleService::class);
            $test = $service->testConnection();
            
            // Comparer les données
            $comparison = $this->syncService->compareData();
            
            return response()->json([
                'success' => true,
                'firebase_connected' => $test['success'] && $test['firestore_accessible'],
                'database_status' => 'connected',
                'synchronisation_status' => $comparison['success'] ? 
                    ($comparison['comparison']['summary']['total']['synchronised'] ? 
                        'synchronised' : 'not_synchronised') : 'error',
                'comparison_available' => $comparison['success']
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
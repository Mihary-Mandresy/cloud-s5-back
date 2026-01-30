<?php

namespace App\Services;

use App\Models\Utilisateur;
use App\Models\Signalement;
use App\Models\Entreprise;
use App\Models\HistoSignalement;
use App\Models\Role;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class FirebaseSyncService
{
    private $firebaseService;
    
    public function __construct(FirebaseSimpleService $firebaseService)
    {
        $this->firebaseService = $firebaseService;
    }
    
    /**
     * Compare les données locales avec Firebase
     */
    public function compareData()
    {
        try {
            Log::info("Début comparaison des données");
            
            $comparison = [
                'utilisateurs' => $this->compareUtilisateurs(),
                'signalements' => $this->compareSignalements(),
                'entreprises' => $this->compareEntreprises(),
                'roles' => $this->compareRoles(),
                'summary' => []
            ];
            
            // Calculer le résumé
            $totalLocal = $totalFirebase = $totalMissing = $totalToSync = 0;
            
            foreach (['utilisateurs', 'signalements', 'entreprises', 'roles'] as $type) {
                $comparison['summary'][$type] = [
                    'local_count' => $comparison[$type]['local_count'],
                    'firebase_count' => $comparison[$type]['firebase_count'],
                    'missing_in_firebase' => count($comparison[$type]['missing_in_firebase']),
                    'missing_in_local' => count($comparison[$type]['missing_in_local']),
                    'total_to_sync' => count($comparison[$type]['missing_in_firebase']) + 
                                      count($comparison[$type]['missing_in_local'])
                ];
                
                $totalLocal += $comparison[$type]['local_count'];
                $totalFirebase += $comparison[$type]['firebase_count'];
                $totalMissing += count($comparison[$type]['missing_in_firebase']) + 
                               count($comparison[$type]['missing_in_local']);
                $totalToSync += count($comparison[$type]['missing_in_firebase']) + 
                               count($comparison[$type]['missing_in_local']);
            }
            
            $comparison['summary']['total'] = [
                'local_count' => $totalLocal,
                'firebase_count' => $totalFirebase,
                'total_missing' => $totalMissing,
                'total_to_sync' => $totalToSync,
                'synchronised' => $totalMissing === 0
            ];
            
            Log::info("Comparaison terminée", [
                'total_local' => $totalLocal,
                'total_firebase' => $totalFirebase,
                'total_to_sync' => $totalToSync
            ]);
            
            return [
                'success' => true,
                'comparison' => $comparison,
                'timestamp' => now()->toISOString()
            ];
            
        } catch (\Exception $e) {
            Log::error("Erreur lors de la comparaison", ['error' => $e->getMessage()]);
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Compare les utilisateurs
     */
    private function compareUtilisateurs()
    {
        $localUsers = Utilisateur::all(['id', 'email', 'nom', 'role', 'firebase_uid'])->keyBy('id');
        $firebaseUsers = $this->getFirebaseDocuments('utilisateurs');
        
        $localIds = $localUsers->pluck('id')->map(function($id) {
            return (string) $id;
        })->toArray();
        
        $firebaseIds = array_column($firebaseUsers, 'id');
        
        $missingInFirebase = $localUsers->filter(function($user) use ($firebaseIds) {
            return !in_array((string) $user->id, $firebaseIds) && 
                   !$user->firebase_uid; // Pas déjà dans Firebase
        })->values();
        
        $missingInLocal = array_filter($firebaseUsers, function($doc) use ($localIds) {
            return !in_array($doc['id'], $localIds);
        });
        
        return [
            'local_count' => $localUsers->count(),
            'firebase_count' => count($firebaseUsers),
            'local_ids' => $localIds,
            'firebase_ids' => $firebaseIds,
            'missing_in_firebase' => $missingInFirebase->toArray(),
            'missing_in_local' => array_values($missingInLocal)
        ];
    }
    
    /**
     * Compare les signalements
     */
    private function compareSignalements()
    {
        $localSignalements = Signalement::with(['historiques', 'utilisateur'])
            ->get(['id', 'titre', 'description', 'statut', 'synchronise_firebase', 'utilisateur_id'])
            ->keyBy('id');
        
        $firebaseSignalements = $this->getFirebaseDocuments('signalements');
        
        $localIds = $localSignalements->pluck('id')->map(function($id) {
            return (string) $id;
        })->toArray();
        
        $firebaseIds = array_column($firebaseSignalements, 'id');
        
        $missingInFirebase = $localSignalements->filter(function($signalement) use ($firebaseIds) {
            return !in_array((string) $signalement->id, $firebaseIds) && 
                   !$signalement->synchronise_firebase;
        })->values();
        
        $missingInLocal = array_filter($firebaseSignalements, function($doc) use ($localIds) {
            return !in_array($doc['id'], $localIds);
        });
        
        return [
            'local_count' => $localSignalements->count(),
            'firebase_count' => count($firebaseSignalements),
            'local_ids' => $localIds,
            'firebase_ids' => $firebaseIds,
            'missing_in_firebase' => $missingInFirebase->toArray(),
            'missing_in_local' => array_values($missingInLocal)
        ];
    }
    
    /**
     * Compare les entreprises
     */
    private function compareEntreprises()
    {
        $localEntreprises = Entreprise::all(['id', 'nom'])->keyBy('id');
        $firebaseEntreprises = $this->getFirebaseDocuments('entreprises');
        
        $localIds = $localEntreprises->pluck('id')->map(function($id) {
            return (string) $id;
        })->toArray();
        
        $firebaseIds = array_column($firebaseEntreprises, 'id');
        
        $missingInFirebase = $localEntreprises->filter(function($entreprise) use ($firebaseIds) {
            return !in_array((string) $entreprise->id, $firebaseIds);
        })->values();
        
        $missingInLocal = array_filter($firebaseEntreprises, function($doc) use ($localIds) {
            return !in_array($doc['id'], $localIds);
        });
        
        return [
            'local_count' => $localEntreprises->count(),
            'firebase_count' => count($firebaseEntreprises),
            'local_ids' => $localIds,
            'firebase_ids' => $firebaseIds,
            'missing_in_firebase' => $missingInFirebase->toArray(),
            'missing_in_local' => array_values($missingInLocal)
        ];
    }
    
    /**
     * Compare les rôles
     */
    private function compareRoles()
    {
        $localRoles = Role::all(['id', 'libelle'])->keyBy('id');
        $firebaseRoles = $this->getFirebaseDocuments('roles');
        
        $localIds = $localRoles->pluck('id')->map(function($id) {
            return (string) $id;
        })->toArray();
        
        $firebaseIds = array_column($firebaseRoles, 'id');
        
        $missingInFirebase = $localRoles->filter(function($role) use ($firebaseIds) {
            return !in_array((string) $role->id, $firebaseIds);
        })->values();
        
        $missingInLocal = array_filter($firebaseRoles, function($doc) use ($localIds) {
            return !in_array($doc['id'], $localIds);
        });
        
        return [
            'local_count' => $localRoles->count(),
            'firebase_count' => count($firebaseRoles),
            'local_ids' => $localIds,
            'firebase_ids' => $firebaseIds,
            'missing_in_firebase' => $missingInFirebase->toArray(),
            'missing_in_local' => array_values($missingInLocal)
        ];
    }
    
    /**
     * Récupère les documents d'une collection Firebase
     */
    private function getFirebaseDocuments($collection)
    {
        try {
            // Utiliser la méthode getDocuments si disponible, sinon getCollections
            if (method_exists($this->firebaseService, 'getDocuments')) {
                $documents = $this->firebaseService->getDocuments($collection);
            } else {
                // Fallback: utiliser runQuery pour récupérer tous les documents
                $documents = $this->fetchAllFirebaseDocuments($collection);
            }
            
            // Normaliser les documents pour avoir un format cohérent
            $normalized = [];
            foreach ($documents as $doc) {
                if (isset($doc['id'])) {
                    $normalized[] = [
                        'id' => $doc['id'],
                        'data' => $doc['fields'] ?? $doc
                    ];
                }
            }
            
            return $normalized;
            
        } catch (\Exception $e) {
            Log::warning("Impossible de récupérer les documents Firebase", [
                'collection' => $collection,
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }
    
    /**
     * Récupère tous les documents d'une collection Firebase
     */
    private function fetchAllFirebaseDocuments($collection)
    {
        $token = $this->firebaseService->getAccessToken();
        $projectId = $this->firebaseService->getProjectId();
        
        $url = "https://firestore.googleapis.com/v1/projects/{$projectId}/databases/(default)/documents:runQuery";
        
        $response = \Illuminate\Support\Facades\Http::withToken($token)
            ->withHeaders(['Content-Type' => 'application/json'])
            ->post($url, [
                'structuredQuery' => [
                    'from' => [['collectionId' => $collection]],
                    'select' => ['field' => ['fieldPath' => '__name__']]
                ]
            ]);
        
        if (!$response->successful()) {
            throw new \Exception('Erreur récupération documents: ' . $response->body());
        }
        
        $documents = [];
        $data = $response->json();
        
        foreach ($data as $item) {
            if (isset($item['document'])) {
                $doc = $this->parseFirebaseDocument($item['document']);
                $documents[] = $doc;
            }
        }
        
        return $documents;
    }
    
    /**
     * Parse un document Firebase
     */
    private function parseFirebaseDocument($document)
    {
        $result = [
            'id' => basename($document['name']),
            'name' => $document['name'],
            'fields' => []
        ];
        
        if (isset($document['fields'])) {
            foreach ($document['fields'] as $fieldName => $fieldValue) {
                $result['fields'][$fieldName] = $this->parseFirebaseFieldValue($fieldValue);
            }
        }
        
        return $result;
    }
    
    /**
     * Parse une valeur de champ Firebase
     */
    private function parseFirebaseFieldValue($fieldValue)
    {
        $key = array_keys($fieldValue)[0] ?? null;
        $value = $fieldValue[$key] ?? null;
        
        switch ($key) {
            case 'stringValue':
                return $value;
            case 'integerValue':
                return (int)$value;
            case 'doubleValue':
                return (float)$value;
            case 'booleanValue':
                return (bool)$value;
            case 'timestampValue':
                return $value;
            case 'nullValue':
                return null;
            default:
                return $value;
        }
    }
    
    /**
     * Synchronise toutes les données
     */
    public function synchronizeAll()
    {
        DB::beginTransaction();
        
        try {
            Log::info("Début synchronisation complète");
            
            $results = [
                'local_to_firebase' => $this->syncLocalToFirebase(),
                'firebase_to_local' => $this->syncFirebaseToLocal(),
                'timestamp' => now()->toISOString()
            ];
            
            // Vérifier si la synchronisation a réussi
            $check = $this->compareData();
            
            if ($check['success'] && $check['comparison']['summary']['total']['synchronised']) {
                DB::commit();
                Log::info("Synchronisation complète réussie");
                
                return [
                    'success' => true,
                    'message' => 'Synchronisation réussie',
                    'results' => $results,
                    'verified' => true
                ];
            } else {
                DB::rollBack();
                Log::error("Synchronisation incomplète après vérification");
                
                return [
                    'success' => false,
                    'message' => 'Synchronisation incomplète',
                    'results' => $results,
                    'verification' => $check
                ];
            }
            
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Erreur lors de la synchronisation", ['error' => $e->getMessage()]);
            
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Synchronise les données locales vers Firebase
     */
    public function syncLocalToFirebase()
    {
        Log::info("Début synchronisation local → Firebase");
        
        $results = [
            'utilisateurs' => $this->syncLocalUtilisateursToFirebase(),
            'signalements' => $this->syncLocalSignalementsToFirebase(),
            'entreprises' => $this->syncLocalEntreprisesToFirebase(),
            'roles' => $this->syncLocalRolesToFirebase()
        ];
        
        Log::info("Synchronisation local → Firebase terminée", ['results' => $results]);
        return $results;
    }
    
    /**
     * Synchronise les utilisateurs locaux vers Firebase
     */
    private function syncLocalUtilisateursToFirebase()
    {
        $comparison = $this->compareUtilisateurs();
        $missingInFirebase = $comparison['missing_in_firebase'];
        $synced = 0;
        $failed = 0;
        
        foreach ($missingInFirebase as $user) {
            try {
                $userData = [
                    'id' => (string) $user['id'],
                    'email' => $user['email'],
                    'nom' => $user['nom'] ?? '',
                    'role' => $user['role'],
                    'date_inscription' => now()->toISOString()
                ];
                
                // Créer dans Firebase
                $this->createFirebaseDocument('utilisateurs', (string) $user['id'], $userData);
                
                // Mettre à jour le champ firebase_uid localement
                Utilisateur::where('id', $user['id'])->update([
                    'firebase_uid' => (string) $user['id']
                ]);
                
                $synced++;
                Log::info("Utilisateur synchronisé vers Firebase", ['id' => $user['id']]);
                
            } catch (\Exception $e) {
                $failed++;
                Log::error("Erreur synchronisation utilisateur", [
                    'id' => $user['id'],
                    'error' => $e->getMessage()
                ]);
            }
        }
        
        return ['synced' => $synced, 'failed' => $failed, 'total' => count($missingInFirebase)];
    }
    
    /**
     * Synchronise les signalements locaux vers Firebase
     */
    private function syncLocalSignalementsToFirebase()
    {
        $comparison = $this->compareSignalements();
        $missingInFirebase = $comparison['missing_in_firebase'];
        $synced = 0;
        $failed = 0;
        
        foreach ($missingInFirebase as $signalement) {
            try {
                $signalementModel = Signalement::with(['historiques', 'utilisateur'])
                    ->find($signalement['id']);
                
                if (!$signalementModel) continue;
                
                $signalementData = [
                    'id' => (string) $signalementModel->id,
                    'titre' => $signalementModel->titre,
                    'description' => $signalementModel->description,
                    'latitude' => $signalementModel->latitude,
                    'longitude' => $signalementModel->longitude,
                    'date_creation' => $signalementModel->date_creation?->toISOString(),
                    'date_modification' => $signalementModel->date_modification?->toISOString(),
                    'statut' => $signalementModel->statut,
                    'surface_m2' => $signalementModel->surface_m2,
                    'budget' => $signalementModel->budget,
                    'avancement' => $signalementModel->avancement,
                    'entreprise_responsable' => $signalementModel->entreprise_responsable,
                    'utilisateur_id' => (string) $signalementModel->utilisateur_id,
                    'created_at' => $signalementModel->created_at?->toISOString(),
                    'updated_at' => $signalementModel->updated_at?->toISOString()
                ];
                
                // Ajouter l'historique si disponible
                if ($signalementModel->historiques->isNotEmpty()) {
                    $signalementData['historique'] = $signalementModel->historiques->map(function($histo) {
                        return [
                            'date_chargement' => $histo->date_chargement?->toISOString(),
                            'statut' => $histo->statut
                        ];
                    })->toArray();
                }
                
                // Créer dans Firebase
                $this->createFirebaseDocument('signalements', (string) $signalementModel->id, $signalementData);
                
                // Marquer comme synchronisé localement
                $signalementModel->update(['synchronise_firebase' => true]);
                
                $synced++;
                Log::info("Signalement synchronisé vers Firebase", ['id' => $signalementModel->id]);
                
            } catch (\Exception $e) {
                $failed++;
                Log::error("Erreur synchronisation signalement", [
                    'id' => $signalement['id'],
                    'error' => $e->getMessage()
                ]);
            }
        }
        
        return ['synced' => $synced, 'failed' => $failed, 'total' => count($missingInFirebase)];
    }
    
    /**
     * Synchronise les entreprises locales vers Firebase
     */
    private function syncLocalEntreprisesToFirebase()
    {
        $comparison = $this->compareEntreprises();
        $missingInFirebase = $comparison['missing_in_firebase'];
        $synced = 0;
        $failed = 0;
        
        foreach ($missingInFirebase as $entreprise) {
            try {
                $entrepriseData = [
                    'id' => (string) $entreprise['id'],
                    'nom' => $entreprise['nom']
                ];
                
                $this->createFirebaseDocument('entreprises', (string) $entreprise['id'], $entrepriseData);
                $synced++;
                
            } catch (\Exception $e) {
                $failed++;
                Log::error("Erreur synchronisation entreprise", [
                    'id' => $entreprise['id'],
                    'error' => $e->getMessage()
                ]);
            }
        }
        
        return ['synced' => $synced, 'failed' => $failed, 'total' => count($missingInFirebase)];
    }
    
    /**
     * Synchronise les rôles locaux vers Firebase
     */
    private function syncLocalRolesToFirebase()
    {
        $comparison = $this->compareRoles();
        $missingInFirebase = $comparison['missing_in_firebase'];
        $synced = 0;
        $failed = 0;
        
        foreach ($missingInFirebase as $role) {
            try {
                $roleData = [
                    'id' => (string) $role['id'],
                    'libelle' => $role['libelle']
                ];
                
                $this->createFirebaseDocument('roles', (string) $role['id'], $roleData);
                $synced++;
                
            } catch (\Exception $e) {
                $failed++;
                Log::error("Erreur synchronisation rôle", [
                    'id' => $role['id'],
                    'error' => $e->getMessage()
                ]);
            }
        }
        
        return ['synced' => $synced, 'failed' => $failed, 'total' => count($missingInFirebase)];
    }
    
    /**
     * Synchronise les données Firebase vers le local
     */
    public function syncFirebaseToLocal()
    {
        Log::info("Début synchronisation Firebase → local");
        
        $results = [
            'utilisateurs' => $this->syncFirebaseUtilisateursToLocal(),
            'signalements' => $this->syncFirebaseSignalementsToLocal(),
            'entreprises' => $this->syncFirebaseEntreprisesToLocal(),
            'roles' => $this->syncFirebaseRolesToLocal()
        ];
        
        Log::info("Synchronisation Firebase → local terminée", ['results' => $results]);
        return $results;
    }
    
    /**
     * Synchronise les utilisateurs Firebase vers le local
     */
    private function syncFirebaseUtilisateursToLocal()
    {
        $comparison = $this->compareUtilisateurs();
        $missingInLocal = $comparison['missing_in_local'];
        $synced = 0;
        $failed = 0;
        
        foreach ($missingInLocal as $doc) {
            try {
                $data = $doc['data'] ?? $doc;
                
                // Vérifier si l'utilisateur existe déjà avec firebase_uid
                $existing = Utilisateur::where('firebase_uid', $doc['id'])->first();
                
                if ($existing) {
                    // Mettre à jour l'existant
                    $existing->update([
                        'email' => $data['email'] ?? '',
                        'nom' => $data['nom'] ?? '',
                        'role' => $data['role'] ?? 'user'
                    ]);
                } else {
                    // Créer un nouvel utilisateur
                    Utilisateur::create([
                        'email' => $data['email'] ?? '',
                        'mot_de_passe' => bcrypt('temp_password_' . time()), // Mot de passe temporaire
                        'nom' => $data['nom'] ?? '',
                        'role' => $data['role'] ?? 'user',
                        'firebase_uid' => $doc['id'],
                        'est_bloque' => false,
                        'tentatives_connexion' => 0
                    ]);
                }
                
                $synced++;
                Log::info("Utilisateur Firebase importé localement", ['id' => $doc['id']]);
                
            } catch (\Exception $e) {
                $failed++;
                Log::error("Erreur import utilisateur Firebase", [
                    'id' => $doc['id'],
                    'error' => $e->getMessage()
                ]);
            }
        }
        
        return ['synced' => $synced, 'failed' => $failed, 'total' => count($missingInLocal)];
    }
    
    /**
     * Synchronise les signalements Firebase vers le local
     */
    private function syncFirebaseSignalementsToLocal()
    {
        $comparison = $this->compareSignalements();
        $missingInLocal = $comparison['missing_in_local'];
        $synced = 0;
        $failed = 0;
        
        foreach ($missingInLocal as $doc) {
            try {
                $data = $doc['data'] ?? $doc;
                
                // Vérifier si l'utilisateur existe
                $utilisateurId = $data['utilisateur_id'] ?? null;
                $utilisateur = null;
                
                if ($utilisateurId) {
                    // Chercher par firebase_uid d'abord, puis par id
                    $utilisateur = Utilisateur::where('firebase_uid', $utilisateurId)
                        ->orWhere('id', $utilisateurId)
                        ->first();
                }
                
                // Créer le signalement
                $signalement = Signalement::create([
                    'titre' => $data['titre'] ?? '',
                    'description' => $data['description'] ?? '',
                    'latitude' => $data['latitude'] ?? 0,
                    'longitude' => $data['longitude'] ?? 0,
                    'date_creation' => isset($data['date_creation']) ? 
                        \Carbon\Carbon::parse($data['date_creation']) : now(),
                    'date_modification' => isset($data['date_modification']) ? 
                        \Carbon\Carbon::parse($data['date_modification']) : null,
                    'statut' => $data['statut'] ?? 1,
                    'surface_m2' => $data['surface_m2'] ?? null,
                    'budget' => $data['budget'] ?? 0,
                    'avancement' => $data['avancement'] ?? 0,
                    'entreprise_responsable' => $data['entreprise_responsable'] ?? '',
                    'utilisateur_id' => $utilisateur ? $utilisateur->id : null,
                    'synchronise_firebase' => true
                ]);
                
                // Créer l'historique si présent
                if (isset($data['historique']) && is_array($data['historique'])) {
                    foreach ($data['historique'] as $histo) {
                        HistoSignalement::create([
                            'signalement_id' => $signalement->id,
                            'statut' => $histo['statut'] ?? 1,
                            'date_chargement' => isset($histo['date_chargement']) ? 
                                \Carbon\Carbon::parse($histo['date_chargement']) : now()
                        ]);
                    }
                }
                
                $synced++;
                Log::info("Signalement Firebase importé localement", ['id' => $doc['id']]);
                
            } catch (\Exception $e) {
                $failed++;
                Log::error("Erreur import signalement Firebase", [
                    'id' => $doc['id'],
                    'error' => $e->getMessage()
                ]);
            }
        }
        
        return ['synced' => $synced, 'failed' => $failed, 'total' => count($missingInLocal)];
    }
    
    /**
     * Crée un document Firebase avec ID spécifique
     */
    private function createFirebaseDocument($collection, $documentId, $data)
    {
        $token = $this->firebaseService->getAccessToken();
        $projectId = $this->firebaseService->getProjectId();
        
        $url = "https://firestore.googleapis.com/v1/projects/{$projectId}/databases/(default)/documents/{$collection}/{$documentId}";
        
        $response = \Illuminate\Support\Facades\Http::withToken($token)
            ->withHeaders(['Content-Type' => 'application/json'])
            ->patch($url, [
                'fields' => $this->formatForFirestore($data)
            ]);
        
        if (!$response->successful()) {
            throw new \Exception('Erreur création document: ' . $response->body());
        }
        
        return $response->json();
    }
    
    /**
     * Formate les données pour Firestore
     */
    private function formatForFirestore($data)
    {
        $fields = [];
        
        foreach ($data as $key => $value) {
            if ($value === null) {
                $fields[$key] = ['nullValue' => null];
            } elseif (is_string($value)) {
                $fields[$key] = ['stringValue' => $value];
            } elseif (is_int($value)) {
                $fields[$key] = ['integerValue' => (string)$value];
            } elseif (is_float($value)) {
                $fields[$key] = ['doubleValue' => $value];
            } elseif (is_bool($value)) {
                $fields[$key] = ['booleanValue' => $value];
            } elseif (is_array($value)) {
                $fields[$key] = ['arrayValue' => ['values' => array_map([$this, 'formatForFirestore'], $value)]];
            } else {
                $fields[$key] = ['stringValue' => (string)$value];
            }
        }
        
        return $fields;
    }
    
    /**
     * Force la synchronisation dans une seule direction
     */
    public function forceSyncToFirebase()
    {
        try {
            Log::info("Force sync vers Firebase");
            $results = $this->syncLocalToFirebase();
            
            return [
                'success' => true,
                'message' => 'Synchronisation forcée vers Firebase réussie',
                'results' => $results
            ];
            
        } catch (\Exception $e) {
            Log::error("Erreur force sync vers Firebase", ['error' => $e->getMessage()]);
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Force la synchronisation depuis Firebase
     */
    public function forceSyncFromFirebase()
    {
        try {
            Log::info("Force sync depuis Firebase");
            $results = $this->syncFirebaseToLocal();
            
            return [
                'success' => true,
                'message' => 'Synchronisation forcée depuis Firebase réussie',
                'results' => $results
            ];
            
        } catch (\Exception $e) {
            Log::error("Erreur force sync depuis Firebase", ['error' => $e->getMessage()]);
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
}
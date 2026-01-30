<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

class FirebaseImportService
{
    private $firebaseService;
    private $jsonBasePath;
    
    public function __construct(FirebaseSimpleService $firebaseService)
    {
        $this->firebaseService = $firebaseService;
        $this->jsonBasePath = base_path('baseJSON');
    }
    
    /**
     * Trouve tous les fichiers JSON dans le dossier baseJSON
     */
    public function findJsonFiles()
    {
        $files = [];
        
        if (!is_dir($this->jsonBasePath)) {
            Log::error("Le dossier baseJSON n'existe pas : " . $this->jsonBasePath);
            return $files;
        }
        
        $directoryIterator = new \RecursiveDirectoryIterator($this->jsonBasePath);
        $iterator = new \RecursiveIteratorIterator($directoryIterator);
        
        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'json') {
                $relativePath = str_replace($this->jsonBasePath . '/', '', $file->getPathname());
                $files[] = [
                    'full_path' => $file->getPathname(),
                    'relative_path' => $relativePath,
                    'filename' => $file->getFilename(),
                    'collection_name' => $this->getCollectionName($file->getFilename()),
                    'size' => $file->getSize()
                ];
            }
        }
        
        Log::info('Fichiers JSON trouvés', ['count' => count($files), 'files' => $files]);
        return $files;
    }
    
    /**
     * Extrait le nom de collection depuis le nom de fichier
     */
    private function getCollectionName($filename)
    {
        // Enlève l'extension .json et met au pluriel si nécessaire
        $name = pathinfo($filename, PATHINFO_FILENAME);
        
        // Règles simples pour le pluriel
        $lastChar = substr($name, -1);
        if ($lastChar !== 's') {
            $name .= 's'; // Ajoute 's' pour le pluriel
        }
        
        return strtolower($name);
    }
    
    /**
     * Importe un fichier JSON dans Firestore
     */
    public function importJsonFile($filePath, $collectionName = null)
    {
        try {
            Log::info("Début import fichier", ['file' => $filePath]);
            
            if (!file_exists($filePath)) {
                throw new \Exception("Fichier non trouvé : " . $filePath);
            }
            
            $content = file_get_contents($filePath);
            $data = json_decode($content, true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new \Exception("JSON invalide : " . json_last_error_msg());
            }
            
            if (!$collectionName) {
                $collectionName = $this->getCollectionName(basename($filePath));
            }
            
            Log::info("Import vers collection", [
                'collection' => $collectionName,
                'items_count' => count($data),
                'file' => basename($filePath)
            ]);
            
            $importedCount = 0;
            $failedCount = 0;
            $importedIds = [];
            
            foreach ($data as $index => $item) {
                try {
                    // Préparer les données pour Firestore
                    $firestoreData = $this->prepareDataForFirestore($item);
                    
                    // Utiliser l'ID existant si disponible, sinon créer un ID
                    $documentId = $item['id'] ?? null;
                    
                    if ($documentId) {
                        // Créer le document avec ID spécifique
                        $result = $this->createDocumentWithId($collectionName, $documentId, $firestoreData);
                    } else {
                        // Laisser Firestore générer un ID
                        $result = $this->firebaseService->createDocument($collectionName, $firestoreData);
                    }
                    
                    $importedCount++;
                    $importedIds[] = $documentId ?? ($result['name'] ?? 'auto-generated');
                    
                    Log::debug("Document importé", [
                        'index' => $index,
                        'id' => $documentId,
                        'collection' => $collectionName
                    ]);
                    
                } catch (\Exception $e) {
                    $failedCount++;
                    Log::error("Erreur import document", [
                        'index' => $index,
                        'error' => $e->getMessage(),
                        'item' => $item
                    ]);
                }
            }
            
            Log::info("Import terminé", [
                'file' => basename($filePath),
                'collection' => $collectionName,
                'imported' => $importedCount,
                'failed' => $failedCount,
                'total' => count($data)
            ]);
            
            return [
                'success' => true,
                'filename' => basename($filePath),
                'collection' => $collectionName,
                'imported_count' => $importedCount,
                'failed_count' => $failedCount,
                'total_count' => count($data),
                'imported_ids' => $importedIds
            ];
            
        } catch (\Exception $e) {
            Log::error("Erreur import fichier", [
                'file' => $filePath,
                'error' => $e->getMessage()
            ]);
            
            return [
                'success' => false,
                'filename' => basename($filePath),
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Crée un document avec un ID spécifique
     */
    private function createDocumentWithId($collection, $documentId, $data)
    {
        $token = $this->firebaseService->getAccessTokenPublic();
        $projectId = $this->firebaseService->getProjectId();
        
        $url = "https://firestore.googleapis.com/v1/projects/{$projectId}/databases/(default)/documents/{$collection}/{$documentId}";
        
        // Utiliser PATCH pour créer/mettre à jour avec ID spécifique
        $response = \Illuminate\Support\Facades\Http::withToken($token)
            ->withHeaders(['Content-Type' => 'application/json'])
            ->patch($url, [
                'fields' => $this->firebaseService->formatFields($data)
            ]);
        
        if ($response->successful()) {
            return $response->json();
        }
        
        throw new \Exception('Erreur création document avec ID: ' . $response->body());
    }
    
    /**
     * Prépare les données pour Firestore
     */
    private function prepareDataForFirestore($data)
    {
        // Convertit les dates au format ISO si nécessaire
        foreach ($data as $key => $value) {
            if (in_array($key, ['dateCreation', 'dateModification', 'createdAt', 'updatedAt', 'dateChargement'])) {
                if ($value && is_string($value)) {
                    try {
                        $date = new \DateTime($value);
                        $data[$key] = $date->format('Y-m-d\TH:i:s.u\Z');
                    } catch (\Exception $e) {
                        // Garde la valeur originale si conversion échoue
                    }
                }
            }
        }
        
        return $data;
    }
    
    /**
     * Importe tous les fichiers JSON du dossier
     */
    public function importAllJsonFiles()
    {
        $files = $this->findJsonFiles();
        $results = [];
        
        Log::info("Début import de tous les fichiers", ['total_files' => count($files)]);
        
        foreach ($files as $file) {
            Log::info("Traitement fichier", [
                'file' => $file['filename'],
                'collection' => $file['collection_name']
            ]);
            
            $result = $this->importJsonFile($file['full_path'], $file['collection_name']);
            $results[$file['filename']] = $result;
            
            // Petite pause pour éviter les rate limits
            usleep(100000); // 100ms
        }
        
        Log::info("Import de tous les fichiers terminé", [
            'total_files' => count($files),
            'results' => $results
        ]);
        
        return $results;
    }
    
    /**
     * Teste la connexion avant import
     */
    public function testBeforeImport()
    {
        try {
            $connectionTest = $this->firebaseService->testConnection();
            
            if (!$connectionTest['success']) {
                throw new \Exception('Connexion Firestore échouée: ' . ($connectionTest['error'] ?? 'Inconnu'));
            }
            
            $files = $this->findJsonFiles();
            
            return [
                'success' => true,
                'firestore_connected' => true,
                'files_found' => count($files),
                'files' => $files,
                'project_id' => $this->firebaseService->getProjectId()
            ];
            
        } catch (\Exception $e) {
            Log::error("Test avant import échoué", ['error' => $e->getMessage()]);
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
}
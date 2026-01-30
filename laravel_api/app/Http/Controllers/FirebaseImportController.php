<?php

namespace App\Http\Controllers;

use App\Services\FirebaseImportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class FirebaseImportController extends Controller
{
    private $importService;
    
    public function __construct(FirebaseImportService $importService)
    {
        $this->importService = $importService;
    }
    
    /**
     * Liste tous les fichiers JSON disponibles
     */
    public function listJsonFiles()
    {
        try {
            $files = $this->importService->findJsonFiles();
            
            return response()->json([
                'success' => true,
                'base_path' => base_path('baseJSON'),
                'files_count' => count($files),
                'files' => $files
            ]);
            
        } catch (\Exception $e) {
            Log::error("Erreur liste fichiers JSON", ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Teste la connexion et affiche les fichiers
     */
    public function testImport()
    {
        try {
            $testResult = $this->importService->testBeforeImport();
            
            return response()->json($testResult);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Importe un fichier spécifique
     */
    public function importFile(Request $request)
    {
        try {
            $request->validate([
                'filename' => 'required|string'
            ]);
            
            $filename = $request->input('filename');
            $filePath = base_path('baseJSON/' . $filename);
            
            if (!file_exists($filePath)) {
                return response()->json([
                    'success' => false,
                    'error' => 'Fichier non trouvé: ' . $filename
                ], 404);
            }
            
            $result = $this->importService->importJsonFile($filePath);
            
            return response()->json($result);
            
        } catch (\Exception $e) {
            Log::error("Erreur import fichier", [
                'filename' => $request->input('filename'),
                'error' => $e->getMessage()
            ]);
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Importe tous les fichiers JSON
     */
    public function importAll()
    {
        try {
            Log::info("Début import de tous les fichiers - appel API");
            
            $results = $this->importService->importAllJsonFiles();
            
            $totalFiles = count($results);
            $totalImported = 0;
            $totalFailed = 0;
            
            foreach ($results as $result) {
                if ($result['success']) {
                    $totalImported += $result['imported_count'] ?? 0;
                    $totalFailed += $result['failed_count'] ?? 0;
                }
            }
            
            return response()->json([
                'success' => true,
                'message' => 'Import terminé',
                'total_files' => $totalFiles,
                'total_imported_items' => $totalImported,
                'total_failed_items' => $totalFailed,
                'results' => $results
            ]);
            
        } catch (\Exception $e) {
            Log::error("Erreur import tous fichiers", ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Vérifie ce qui serait importé sans réellement importer
     */
    public function previewImport()
    {
        try {
            $files = $this->importService->findJsonFiles();
            $previewData = [];
            
            foreach ($files as $file) {
                if (file_exists($file['full_path'])) {
                    $content = file_get_contents($file['full_path']);
                    $data = json_decode($content, true);
                    
                    $previewData[] = [
                        'filename' => $file['filename'],
                        'collection' => $file['collection_name'],
                        'item_count' => count($data),
                        'first_item' => isset($data[0]) ? array_keys($data[0]) : [],
                        'sample_data' => isset($data[0]) ? $this->getSampleData($data[0]) : null
                    ];
                }
            }
            
            return response()->json([
                'success' => true,
                'files_found' => count($files),
                'preview' => $previewData
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Retourne un échantillon de données (limité)
     */
    private function getSampleData($item)
    {
        $sample = [];
        $count = 0;
        
        foreach ($item as $key => $value) {
            if ($count >= 5) break; // Limite à 5 champs pour l'aperçu
            
            if (is_array($value)) {
                $sample[$key] = 'Array[' . count($value) . ' items]';
            } elseif (is_string($value) && strlen($value) > 50) {
                $sample[$key] = substr($value, 0, 50) . '...';
            } else {
                $sample[$key] = $value;
            }
            
            $count++;
        }
        
        return $sample;
    }
}
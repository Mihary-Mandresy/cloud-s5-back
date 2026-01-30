<?php

namespace App\Http\Controllers;

use App\Services\FirebaseSimpleService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;


class TestFirebaseController extends Controller
{
    public function checkConnection()
    {
        try {
            $service = new FirebaseSimpleService();
            $connectionTest = $service->testConnection();
            
            if (!$connectionTest['success']) {
                throw new \Exception($connectionTest['error']);
            }
            $collections = $service->getCollections();
            $documents = $service->getDocuments('Test');
            $documents1 = $service->getDocuments('test');
            
            return response()->json([
                'success' => true,
                'connection_test' => $connectionTest,
                'collections' => $collections,
                'Test_documents' =>  $documents,
                'test_documents1' => $documents1
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
                'trace' => env('APP_DEBUG') ? $e->getTraceAsString() : null
            ], 500);
        }
    }
    
    public function test()
    {
        try {
            $service = new FirebaseSimpleService();
            $connectionTest = $service->testConnection();
            if (!$connectionTest['success']) {
                throw new \Exception('Échec connexion: ' . $connectionTest['error']);
            }
            
            $created = $service->createDocument('test', [
                'id' => 1,
                'libelle' => 'Mon premier document via REST API',
                'created_at' => date('Y-m-d H:i:s'),
                'active' => true,
                'counter' => 1
            ]);
            
            $collections = $service->getCollections();
            
            return response()->json([
                'success' => true,
                'connection' => $connectionTest,
                'created_document' => $created,
                'collections' => $collections
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
                'trace' => env('APP_DEBUG') ? $e->getTraceAsString() : null
            ], 500);
        }
    }
    
    public function simpleTest()
    {
        $credentials = json_decode(
            file_get_contents(storage_path('app/firebase-key.json')),
            true
        );
        
        return response()->json([
            'project_id' => $credentials['project_id'] ?? 'non trouvé',
            'client_email' => $credentials['client_email'] ?? 'non trouvé',
            'has_private_key' => !empty($credentials['private_key']),
            'private_key_preview' => isset($credentials['private_key']) 
                ? substr($credentials['private_key'], 0, 50) . '...' 
                : 'non trouvé'
        ]);
    }

    public function testBasicFirestore()
    {
        try {
            $service = new FirebaseSimpleService();
            $token = $service->getAccessTokenPublic();
            $url = "https://firestore.googleapis.com/v1/projects/{$service->getProjectId()}/databases";
            $response = Http::withToken($token)
                ->withHeaders(['Accept' => 'application/json'])
                ->get($url);
            return response()->json([
                'success' => $response->successful(),
                'status' => $response->status(),
                'response' => $response->successful() ? $response->json() : $response->body()
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
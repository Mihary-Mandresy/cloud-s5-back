<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;

class FirebaseSimpleService
{
    private $projectId = 'braided-period-423115-r3';
    private $credentials;
    
    public function __construct()
    {
        $this->credentials = json_decode(
            file_get_contents(storage_path('app/firebase-key.json')),
            true
        );
    }

    public function getProjectId() 
    {
        return $this->projectId;
    }

    public function getAccessTokenPublic()
    {
        return $this->getAccessToken();
    }
    
    private function getAccessToken()
    {
        return Cache::remember('firebase_access_token', 3500, function () {
            $jwt = $this->createSimpleJWT();
            
            $response = Http::asForm()->post('https://oauth2.googleapis.com/token', [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion' => $jwt
            ]);
            
            if ($response->successful()) {
                return $response->json()['access_token'];
            }
            
            throw new \Exception('Impossible d\'obtenir le token d\'accès: ' . $response->body());
        });
    }

    private function createSimpleJWT()
    {
        $header = json_encode([
            'alg' => 'RS256',
            'typ' => 'JWT'
        ]);
        
        $now = time();
        $payload = json_encode([
            'iss' => $this->credentials['client_email'],
            'scope' => 'https://www.googleapis.com/auth/datastore',
            'aud' => 'https://oauth2.googleapis.com/token',
            'exp' => $now + 3600,
            'iat' => $now
        ]);
        
        $encodedHeader = $this->base64UrlEncode($header);
        $encodedPayload = $this->base64UrlEncode($payload);
        
        $signature = '';
        $dataToSign = $encodedHeader . '.' . $encodedPayload;
        
        $privateKey = str_replace(['\n', '\r'], "\n", $this->credentials['private_key']);
        
        if (!str_contains($privateKey, 'BEGIN PRIVATE KEY')) {
            $privateKey = "-----BEGIN PRIVATE KEY-----\n" . 
                         chunk_split($privateKey, 64, "\n") . 
                         "-----END PRIVATE KEY-----\n";
        }
        
        openssl_sign(
            $dataToSign,
            $signature,
            $privateKey,
            'SHA256'
        );
        
        $encodedSignature = $this->base64UrlEncode($signature);
        
        return $encodedHeader . '.' . $encodedPayload . '.' . $encodedSignature;
    }
    
    private function base64UrlEncode($data)
    {
        return str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($data));
    }
    
    public function verifyToken()
    {
        try {
            $token = $this->getAccessToken();
            $url = "https://oauth2.googleapis.com/tokeninfo?access_token=" . urlencode($token);
            
            $response = Http::get($url);
            
            return [
                'valid' => $response->successful(),
                'response' => $response->json()
            ];
            
        } catch (\Exception $e) {
            return [
                'valid' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    public function getCollections()
    {
        $token = $this->getAccessToken();
        
        $url = "https://firestore.googleapis.com/v1/projects/{$this->projectId}/databases/(default)/documents:listCollectionIds";
        
        $response = Http::withToken($token)
            ->withHeaders([
                'Content-Type' => 'application/json',
                'Accept' => 'application/json'
            ])
            ->post($url, [
                'pageSize' => 10
            ]); 
        
        if ($response->successful()) {
            return $response->json();
        }
        
        if ($response->status() === 401) {
            Cache::forget('firebase_access_token');
            throw new \Exception('Token invalide. Veuillez réessayer.');
        }
        
        throw new \Exception('Erreur Firestore (' . $response->status() . '): ' . $response->body());
    }
    
    public function createDocument($collection, $data)
    {
        $token = $this->getAccessToken();
        
        $url = "https://firestore.googleapis.com/v1/projects/{$this->projectId}/databases/(default)/documents/{$collection}";
        
        $response = Http::withToken($token)
            ->withHeaders(['Content-Type' => 'application/json'])
            ->post($url, [
                'fields' => $this->formatFields($data)
            ]);
        
        if ($response->successful()) {
            return $response->json();
        }
        
        throw new \Exception('Erreur création document: ' . $response->body());
    }
    public function formatFields($data)
    {
        $fields = [];
        
        foreach ($data as $key => $value) {
            if (is_string($value)) {
                $fields[$key] = ['stringValue' => $value];
            } elseif (is_int($value)) {
                $fields[$key] = ['integerValue' => (string)$value];
            } elseif (is_float($value)) {
                $fields[$key] = ['doubleValue' => $value];
            } elseif (is_bool($value)) {
                $fields[$key] = ['booleanValue' => $value];
            } elseif ($value instanceof \DateTime) {
                $fields[$key] = ['timestampValue' => $value->format('Y-m-d\TH:i:s.u\Z')];
            } else {
                $fields[$key] = ['stringValue' => (string) $value];
            }
        }
        
        return $fields;
    }
    
    public function testConnection()
    {
        try {
            if (empty($this->credentials['private_key']) || empty($this->credentials['client_email'])) {
                throw new \Exception('Credentials incomplets');
            }
            
            $token = $this->getAccessToken();
            
            $tokenInfo = $this->verifyToken();
            
            $simpleTestUrl = "https://firestore.googleapis.com/v1/projects/{$this->projectId}/databases/(default)/documents:listCollectionIds";
            $testResponse = Http::withToken($token)
                ->withHeaders(['Content-Type' => 'application/json'])
                ->post($simpleTestUrl, [
                    'pageSize' => 10
                ]);
            
            return [
                'success' => true,
                'project_id' => $this->projectId,
                'client_email' => $this->credentials['client_email'],
                'token_valid' => $tokenInfo['valid'],
                'firestore_accessible' => $testResponse->successful(),
                'firestore_status' => $testResponse->status()
            ];
            
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    public function getDocuments($collection, $filters = [])
    {
        $token = $this->getAccessToken();
        
        $url = "https://firestore.googleapis.com/v1/projects/{$this->projectId}/databases/(default)/documents:runQuery";
        
        $query = [
            'structuredQuery' => [
                'from' => [
                    ['collectionId' => $collection]
                ]
            ]
        ];
        
        if (!empty($filters)) {
            $where = [];
            
            foreach ($filters as $field => $value) {
                $where[] = [
                    'fieldFilter' => [
                        'field' => [
                            'fieldPath' => $field
                        ],
                        'op' => 'EQUAL',
                        'value' => $this->formatFieldValue($value)
                    ]
                ];
            }
            
            if (count($where) === 1) {
                $query['structuredQuery']['where'] = $where[0];
            } elseif (count($where) > 1) {
                $query['structuredQuery']['where'] = [
                    'compositeFilter' => [
                        'op' => 'AND',
                        'filters' => $where
                    ]
                ];
            }
        }
        
        $response = Http::withToken($token)
            ->withHeaders([
                'Content-Type' => 'application/json',
                'Accept' => 'application/json'
            ])
            ->post($url, $query);
        
        if ($response->successful()) {
            $documents = [];
            $data = $response->json();
            
            foreach ($data as $item) {
                if (isset($item['document'])) {
                    $doc = $this->parseDocument($item['document']);
                    $documents[] = $doc;
                }
            }
            
            return $documents;
        }
        
        if ($response->status() === 401) {
            Cache::forget('firebase_access_token');
            throw new \Exception('Token invalide. Veuillez réessayer.');
        }
        
        throw new \Exception('Erreur récupération documents (' . $response->status() . '): ' . $response->body());
    }
    public function getDocumentsWithPagination($collection, $limit = 50, $nextPageToken = null)
    {
        $token = $this->getAccessToken();
        
        $url = "https://firestore.googleapis.com/v1/projects/{$this->projectId}/databases/(default)/documents/{$collection}";
        
        $params = [
            'pageSize' => $limit,
            'orderBy' => '__name__' 
        ];
        
        if ($nextPageToken) {
            $params['pageToken'] = $nextPageToken;
        }
        
        $response = Http::withToken($token)
            ->withHeaders([
                'Accept' => 'application/json'
            ])
            ->get($url, $params);
        
        if ($response->successful()) {
            $data = $response->json();
            $documents = [];
            
            if (isset($data['documents'])) {
                foreach ($data['documents'] as $docData) {
                    $documents[] = $this->parseDocument($docData);
                }
            }
            
            return [
                'documents' => $documents,
                'nextPageToken' => $data['nextPageToken'] ?? null
            ];
        }
        
        if ($response->status() === 401) {
            Cache::forget('firebase_access_token');
            throw new \Exception('Token invalide. Veuillez réessayer.');
        }
        
        throw new \Exception('Erreur récupération documents (' . $response->status() . '): ' . $response->body());
    }

    /**
     * Formate une valeur pour un filtre
     */
    private function formatFieldValue($value)
    {
        if (is_string($value)) {
            return ['stringValue' => $value];
        } elseif (is_int($value)) {
            return ['integerValue' => (string)$value];
        } elseif (is_float($value)) {
            return ['doubleValue' => $value];
        } elseif (is_bool($value)) {
            return ['booleanValue' => $value];
        } elseif (is_array($value)) {
            return ['arrayValue' => ['values' => array_map([$this, 'formatFieldValue'], $value)]];
        } else {
            return ['stringValue' => (string) $value];
        }
    }

    /**
     * Parse un document Firestore en tableau PHP
     */
    private function parseDocument($document)
    {
        $result = [
            'id' => basename($document['name']),
            'name' => $document['name'],
            'createTime' => $document['createTime'] ?? null,
            'updateTime' => $document['updateTime'] ?? null,
            'fields' => []
        ];
        
        if (isset($document['fields'])) {
            foreach ($document['fields'] as $fieldName => $fieldValue) {
                $result['fields'][$fieldName] = $this->parseFieldValue($fieldValue);
            }
        }
        
        return $result;
    }

    /**
     * Parse une valeur de champ Firestore
     */
    private function parseFieldValue($fieldValue)
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
            case 'mapValue':
                $parsedMap = [];
                if (isset($value['fields'])) {
                    foreach ($value['fields'] as $mapFieldName => $mapFieldValue) {
                        $parsedMap[$mapFieldName] = $this->parseFieldValue($mapFieldValue);
                    }
                }
                return $parsedMap;
            case 'arrayValue':
                $parsedArray = [];
                if (isset($value['values'])) {
                    foreach ($value['values'] as $arrayValue) {
                        $parsedArray[] = $this->parseFieldValue($arrayValue);
                    }
                }
                return $parsedArray;
            case 'nullValue':
                return null;
            default:
                return $value;
        }
    }
}

// // Pour récupérer tous les documents d'une collection
// $documents = $service->getDocuments('ma_collection');

// // Pour récupérer avec pagination
// $result = $service->getDocumentsWithPagination('ma_collection', 100);
// $documents = $result['documents'];
// $nextPageToken = $result['nextPageToken'];

// // Pour récupérer avec filtres
// $filters = ['status' => 'actif', 'category' => 'urgent'];
// $documents = $service->getDocuments('ma_collection', $filters);
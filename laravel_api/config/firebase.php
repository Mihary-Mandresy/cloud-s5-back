<?php

return [
    'credentials' => [
        'file' => env('FIREBASE_CREDENTIALS', storage_path('app/firebase-key.json')),
    ],
    
    // Force le mode REST pour éviter gRPC
    'firestore' => [
        'database' => '(default)',
    ],
    
    // Option pour forcer REST si nécessaire
    'http_client_options' => [
        'base_uri' => 'https://firestore.googleapis.com/v1/',
    ],
];


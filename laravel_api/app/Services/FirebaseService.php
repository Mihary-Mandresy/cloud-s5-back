<?php

namespace App\Services;

use Kreait\Firebase\Factory;
use Google\Cloud\Firestore\FirestoreClient;

class FirebaseService
{
    protected FirestoreClient $firestore;

    public function __construct()
    {
        $factory = (new Factory)
            ->withServiceAccount(config('firebase.credentials'));

        $this->firestore = $factory->createFirestore()->database();
    }

    public function firestore(): FirestoreClient
    {
        return $this->firestore;
    }
}

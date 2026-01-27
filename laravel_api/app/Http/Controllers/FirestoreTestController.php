<?php

namespace App\Http\Controllers;

use App\Services\FirebaseService;

class FirestoreTestController extends Controller
{
    public function test(FirebaseService $firebase)
    {
        $collections = $firebase->firestore()->collections();

        $result = [];

        foreach ($collections as $collection) {
            $result[] = $collection->id();
        }

        return response()->json($result);
    }
}

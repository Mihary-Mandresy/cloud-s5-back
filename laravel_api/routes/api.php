<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\SignalementController;
use App\Http\Controllers\FirestoreTestController;

Route::post('/login', [AuthController::class, 'login']);
Route::post('/register', [AuthController::class, 'register']);

    Route::prefix('/signalements')->group(function () {
        Route::get('/', [SignalementController::class, 'index']);
        Route::post('/', [SignalementController::class, 'store']);
        Route::get('/{id}', [SignalementController::class, 'show']);
        Route::put('/{id}', [SignalementController::class, 'update']);
        Route::get('/statistiques/general', [SignalementController::class, 'statistiques']);
        Route::get('/statistiques/par-entreprise', [SignalementController::class, 'statistiquesParEntreprise']);
        Route::post('/sync-firebase', [SignalementController::class, 'syncFromFirebase']);
    });

// Routes protégées
Route::middleware(['auth:api'])->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::post('/refresh', [AuthController::class, 'refresh']);
    Route::get('/me', [AuthController::class, 'me']);
    Route::put('/profile', [AuthController::class, 'updateProfile']);
    
    

    // Route admin seulement
    Route::middleware(['role:manager'])->group(function () {
        Route::post('/reset-tentatives', [AuthController::class, 'resetTentatives']);
    });
});

Route::get('/firestore-test', [FirestoreTestController::class, 'test']);


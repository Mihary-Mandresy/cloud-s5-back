<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\SignalementController;
use App\Http\Controllers\TestFirebaseController;
use App\Http\Controllers\FirebaseImportController;
use App\Http\Controllers\FirebaseSyncController;

Route::prefix('sync')->group(function () {
    Route::get('/compare', [FirebaseSyncController::class, 'compare']);
    Route::get('/statistics', [FirebaseSyncController::class, 'statistics']);
    Route::get('/check', [FirebaseSyncController::class, 'checkConnection']);
    Route::post('/all', [FirebaseSyncController::class, 'synchronizeAll']);
    Route::post('/to-firebase', [FirebaseSyncController::class, 'syncToFirebase']);
    Route::post('/from-firebase', [FirebaseSyncController::class, 'syncFromFirebase']);
});

Route::prefix('firebase-import')->group(function () {
    Route::get('/list-files', [FirebaseImportController::class, 'listJsonFiles']);
    Route::get('/test', [FirebaseImportController::class, 'testImport']);
    Route::get('/preview', [FirebaseImportController::class, 'previewImport']);
    Route::post('/import-file', [FirebaseImportController::class, 'importFile']);
    Route::get('/import-all', [FirebaseImportController::class, 'importAll']);
});

Route::get('/firebase-test', [TestFirebaseController::class, 'test']);
Route::get('/firebase-check', [TestFirebaseController::class, 'checkConnection']);
Route::get('/firebase-simple', [TestFirebaseController::class, 'simpleTest']);
Route::get('/firebase-basic-test', [TestFirebaseController::class, 'testBasicFirestore']);

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

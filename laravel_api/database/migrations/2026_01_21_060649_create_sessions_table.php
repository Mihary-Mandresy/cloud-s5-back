<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Vérifier si la table n'existe pas déjà
        if (!Schema::hasTable('sessions')) {
            Schema::create('sessions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('utilisateur_id')
                    ->nullable()
                    ->constrained('utilisateurs')
                    ->onDelete('cascade');
                $table->text('token_session')->unique();
                $table->timestamp('date_creation')->useCurrent();
                $table->timestamp('date_expiration')->nullable();
                $table->text('payload')->nullable();
                $table->integer('last_activity')->nullable();
                $table->string('ip_address', 45)->nullable();
                $table->text('user_agent')->nullable();
                
                // Index pour les performances
                $table->index('utilisateur_id');
                $table->index('token_session');
                $table->index('date_expiration');
            });
        } else {
            // Si la table existe déjà, ajouter les colonnes manquantes
            Schema::table('sessions', function (Blueprint $table) {
                // Vérifier et ajouter les colonnes si elles n'existent pas
                if (!Schema::hasColumn('sessions', 'payload')) {
                    $table->text('payload')->nullable()->after('date_expiration');
                }
                if (!Schema::hasColumn('sessions', 'last_activity')) {
                    $table->integer('last_activity')->nullable()->after('payload');
                }
                if (!Schema::hasColumn('sessions', 'ip_address')) {
                    $table->string('ip_address', 45)->nullable()->after('last_activity');
                }
                if (!Schema::hasColumn('sessions', 'user_agent')) {
                    $table->text('user_agent')->nullable()->after('ip_address');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sessions');
    }
};
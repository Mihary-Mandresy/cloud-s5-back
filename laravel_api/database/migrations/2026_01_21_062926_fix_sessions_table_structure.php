<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sessions', function (Blueprint $table) {
            $table->dropUnique('sessions_token_session_key');
            
            // 2. Renommer les colonnes pour correspondre à Laravel
            $table->renameColumn('token_session', 'id');
            $table->renameColumn('utilisateur_id', 'user_id');
            
            // 3. Changer le type de 'id' de integer à string
            $table->text('id')->change();
            
            // 4. Supprimer la séquence auto-incrémentée
            $table->dropColumn('id_seq');
            
            // 5. Ajouter les colonnes manquantes
            if (!Schema::hasColumn('sessions', 'ip_address')) {
                $table->string('ip_address', 45)->nullable();
            }
            
            if (!Schema::hasColumn('sessions', 'user_agent')) {
                $table->text('user_agent')->nullable();
            }
            
            if (!Schema::hasColumn('sessions', 'payload')) {
                $table->text('payload');
            }
            
            if (!Schema::hasColumn('sessions', 'last_activity')) {
                $table->integer('last_activity')->index();
            }
            
            // 6. Supprimer les colonnes inutiles pour Laravel
            $table->dropColumn('date_creation');
            $table->dropColumn('date_expiration');
        });
        
        
    }
    
    public function down(): void
    {
        // Rollback si nécessaire
    }
};
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('utilisateurs', function (Blueprint $table) {
            $table->id();
            $table->string('email', 255)->unique();
            $table->string('mot_de_passe', 255);
            $table->foreignId('role')->constrained('role')->default(1);
            $table->string('nom', 100)->nullable();
            $table->timestamp('date_inscription')->useCurrent();
            $table->boolean('est_bloque')->default(false);
            $table->integer('tentatives_connexion')->default(0);
            $table->timestamp('derniere_tentative')->nullable();
            $table->string('firebase_uid', 128)->unique()->nullable();
            $table->timestamps();
            
            $table->index(['email']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('utilisateurs');
    }
};
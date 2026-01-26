<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tentatives_connexion', function (Blueprint $table) {
            $table->id();
            $table->string('email', 255);
            $table->string('ip', 45)->nullable();
            $table->timestamp('date_tentative')->useCurrent();
            $table->integer('nombre_tentative')->default(0);
            $table->timestamps();
            
            $table->index(['email']);
            $table->index(['date_tentative']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tentatives_connexion');
    }
};
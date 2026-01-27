<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('signalements', function (Blueprint $table) {
            $table->id();
            $table->string('titre', 200);
            $table->text('description')->nullable();
            $table->decimal('latitude', 10, 8);
            $table->decimal('longitude', 11, 8);
            $table->timestamp('date_creation')->useCurrent();
            $table->timestamp('date_modification')->nullable();
            $table->integer('statut')->default(1); // 1=nouveau, 2=en_cours, 3=termine
            $table->decimal('surface_m2', 10, 2)->nullable();
            $table->decimal('budget', 15, 2)->nullable();
            $table->decimal('avancement', 10, 2)->nullable()->default(0);
            $table->string('entreprise_responsable', 255)->nullable();
            $table->foreignId('utilisateur_id')
                  ->nullable()
                  ->constrained('utilisateurs')
                  ->onDelete('set null');
            $table->boolean('synchronise_firebase')->default(false);
            $table->timestamps(); // Pour les timestamps de Laravel
        });
    }

    public function down()
    {
        Schema::dropIfExists('signalements');
    }
};
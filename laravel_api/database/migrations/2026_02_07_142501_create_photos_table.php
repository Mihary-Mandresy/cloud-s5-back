<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('photos', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('signalement_id');
            $table->text('image_base64'); // Stocke l'image en base64
            $table->string('mime_type')->nullable(); // Type MIME (image/jpeg, etc.)
            $table->string('nom_fichier')->nullable(); // Nom original du fichier
            $table->integer('ordre')->default(0); // Pour ordonner les photos
            $table->timestamps();
            
            // Clé étrangère
            $table->foreign('signalement_id')
                  ->references('id')
                  ->on('signalements')
                  ->onDelete('cascade');
        });
    }

    public function down()
    {
        Schema::dropIfExists('photos');
    }
};
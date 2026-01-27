<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('histo_signalements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('signalement_id')
                  ->constrained('signalements')
                  ->onDelete('cascade');
            $table->timestamp('date_chargement')->useCurrent();
            $table->integer('statut')->default(1);
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('histo_signalements');
    }
};
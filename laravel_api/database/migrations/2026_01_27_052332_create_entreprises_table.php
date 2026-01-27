<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('entreprises', function (Blueprint $table) {
            $table->id(); // Equivalent à SERIAL PRIMARY KEY
            $table->string('nom', 200);
            $table->timestamps(); // Ajoute created_at et updated_at
        });
    }

    public function down()
    {
        Schema::dropIfExists('entreprises');
    }
};
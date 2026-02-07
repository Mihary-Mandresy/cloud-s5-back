<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('sessions')) {
            Schema::create('sessions', function (Blueprint $table) {
                $table->string('id')->primary();
                $table->foreignId('user_id')
                    ->nullable()
                    ->constrained()
                    ->onDelete('cascade');
                $table->string('ip_address', 45)->nullable();
                $table->text('user_agent')->nullable();
                $table->text('payload');
                $table->integer('last_activity')->index();
                
                $table->index('user_id');
            });
        } else {
            Schema::table('sessions', function (Blueprint $table) {
                if (!Schema::hasColumn('sessions', 'id')) {
                    $table->string('id')->primary();
                } else {
                    if (Schema::hasColumn('sessions', 'token_session')) {
                        $table->renameColumn('token_session', 'id');
                        $table->string('id')->primary()->change();
                    }
                }
                
                if (Schema::hasColumn('sessions', 'utilisateur_id') && !Schema::hasColumn('sessions', 'user_id')) {
                    $table->renameColumn('utilisateur_id', 'user_id');
                }
                
                $columnsToDrop = ['id_seq', 'date_creation', 'date_expiration'];
                foreach ($columnsToDrop as $column) {
                    if (Schema::hasColumn('sessions', $column)) {
                        $table->dropColumn($column);
                    }
                }
                
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
                
                if (Schema::hasColumn('sessions', 'user_id')) {
                    $table->foreign('user_id')
                        ->references('id')
                        ->on('users')
                        ->onDelete('cascade');
                }
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('sessions');
    }
};
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
        if (Schema::hasTable('app_users') && !Schema::hasColumn('app_users', 'remember_token')) {
            Schema::table('app_users', function (Blueprint $table) {
                $table->rememberToken()->nullable();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('app_users') && Schema::hasColumn('app_users', 'remember_token')) {
            Schema::table('app_users', function (Blueprint $table) {
                $table->dropColumn('remember_token');
            });
        }
    }
};


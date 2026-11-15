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
        Schema::table('app_users', function (Blueprint $table) {
            if (!Schema::hasColumn('app_users', 'signature_path')) {
                $table->string('signature_path')->nullable()->after('departemen');
            }
            if (!Schema::hasColumn('app_users', 'jabatan')) {
                $table->string('jabatan')->nullable()->after('signature_path');
            }
            if (!Schema::hasColumn('app_users', 'posisi')) {
                $table->string('posisi')->nullable()->after('jabatan');
            }
            if (!Schema::hasColumn('app_users', 'divisi')) {
                $table->string('divisi')->nullable()->after('posisi');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('app_users', function (Blueprint $table) {
            $cols = [];
            if (Schema::hasColumn('app_users', 'signature_path')) $cols[] = 'signature_path';
            if (Schema::hasColumn('app_users', 'jabatan')) $cols[] = 'jabatan';
            if (Schema::hasColumn('app_users', 'posisi')) $cols[] = 'posisi';
            if (Schema::hasColumn('app_users', 'divisi')) $cols[] = 'divisi';
            if (!empty($cols)) {
                $table->dropColumn($cols);
            }
        });
    }
};


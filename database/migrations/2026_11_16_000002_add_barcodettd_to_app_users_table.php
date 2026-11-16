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
            if (!Schema::hasColumn('app_users', 'barcodettd')) {
                $table->text('barcodettd')->nullable()->after('signature_path');
            }
            if (!Schema::hasColumn('app_users', 'ttdbarcode')) {
                $table->text('ttdbarcode')->nullable()->after('barcodettd');
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
            if (Schema::hasColumn('app_users', 'barcodettd')) $cols[] = 'barcodettd';
            if (Schema::hasColumn('app_users', 'ttdbarcode')) $cols[] = 'ttdbarcode';
            if (!empty($cols)) {
                $table->dropColumn($cols);
            }
        });
    }
};

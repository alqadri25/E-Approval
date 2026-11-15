<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('karyawan', function (Blueprint $table) {
            if (!Schema::hasColumn('karyawan', 'line_category')) {
                $table->string('line_category')->nullable()->after('nama_bagian');
            }
            if (!Schema::hasColumn('karyawan', 'division')) {
                $table->string('division')->nullable()->after('line_category');
            }
        });
    }

    public function down(): void
    {
        Schema::table('karyawan', function (Blueprint $table) {
            $dropCols = [];
            if (Schema::hasColumn('karyawan', 'line_category')) {
                $dropCols[] = 'line_category';
            }
            if (Schema::hasColumn('karyawan', 'division')) {
                $dropCols[] = 'division';
            }
            if (!empty($dropCols)) {
                $table->dropColumn($dropCols);
            }
        });
    }
};


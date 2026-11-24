<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('karyawan', function (Blueprint $table) {
            if (!Schema::hasColumn('karyawan', 'email')) {
                $table->string('email')->nullable()->after('tgl_masuk');
            }
            if (!Schema::hasColumn('karyawan', 'keterangan')) {
                $table->text('keterangan')->nullable()->after('email');
            }
            if (!Schema::hasColumn('karyawan', 'employment_type')) {
                $table->string('employment_type')->nullable()->after('seksi');
            }
        });
    }

    public function down(): void
    {
        Schema::table('karyawan', function (Blueprint $table) {
            $dropCols = [];
            if (Schema::hasColumn('karyawan', 'email')) {
                $dropCols[] = 'email';
            }
            if (Schema::hasColumn('karyawan', 'keterangan')) {
                $dropCols[] = 'keterangan';
            }
            if (Schema::hasColumn('karyawan', 'employment_type')) {
                $dropCols[] = 'employment_type';
            }
            if (!empty($dropCols)) {
                $table->dropColumn($dropCols);
            }
        });
    }
};

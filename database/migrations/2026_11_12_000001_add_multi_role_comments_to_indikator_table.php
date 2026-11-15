<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('indikator')) {
            Schema::table('indikator', function (Blueprint $table) {
                if (!Schema::hasColumn('indikator', 'kelebihan_foreman')) {
                    $table->text('kelebihan_foreman')->nullable();
                }
                if (!Schema::hasColumn('indikator', 'kelebihan_section')) {
                    $table->text('kelebihan_section')->nullable();
                }
                if (!Schema::hasColumn('indikator', 'kelebihan_dept')) {
                    $table->text('kelebihan_dept')->nullable();
                }
                if (!Schema::hasColumn('indikator', 'kelebihan_div')) {
                    $table->text('kelebihan_div')->nullable();
                }

                if (!Schema::hasColumn('indikator', 'kekurangan_foreman')) {
                    $table->text('kekurangan_foreman')->nullable();
                }
                if (!Schema::hasColumn('indikator', 'kekurangan_section')) {
                    $table->text('kekurangan_section')->nullable();
                }
                if (!Schema::hasColumn('indikator', 'kekurangan_dept')) {
                    $table->text('kekurangan_dept')->nullable();
                }
                if (!Schema::hasColumn('indikator', 'kekurangan_div')) {
                    $table->text('kekurangan_div')->nullable();
                }

                if (!Schema::hasColumn('indikator', 'catatan_potensi_foreman')) {
                    $table->text('catatan_potensi_foreman')->nullable();
                }
                if (!Schema::hasColumn('indikator', 'catatan_potensi_section')) {
                    $table->text('catatan_potensi_section')->nullable();
                }
                if (!Schema::hasColumn('indikator', 'catatan_potensi_dept')) {
                    $table->text('catatan_potensi_dept')->nullable();
                }
                if (!Schema::hasColumn('indikator', 'catatan_potensi_div')) {
                    $table->text('catatan_potensi_div')->nullable();
                }

                if (!Schema::hasColumn('indikator', 'komentar_history')) {
                    $table->longText('komentar_history')->nullable();
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('indikator')) {
            Schema::table('indikator', function (Blueprint $table) {
                $columns = [
                    'kelebihan_foreman',
                    'kelebihan_section',
                    'kelebihan_dept',
                    'kelebihan_div',
                    'kekurangan_foreman',
                    'kekurangan_section',
                    'kekurangan_dept',
                    'kekurangan_div',
                    'catatan_potensi_foreman',
                    'catatan_potensi_section',
                    'catatan_potensi_dept',
                    'catatan_potensi_div',
                    'komentar_history',
                ];
                foreach ($columns as $col) {
                    if (Schema::hasColumn('indikator', $col)) {
                        $table->dropColumn($col);
                    }
                }
            });
        }
    }
};


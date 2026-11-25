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
        if (!Schema::hasTable('targetdivision')) {
            Schema::create('targetdivision', function (Blueprint $table) {
                $table->id();
                $table->string('divisi')->index();
                $table->integer('target_s')->default(0);
                $table->integer('target_a')->default(0);
                $table->integer('target_b')->default(0);
                $table->integer('target_c')->default(0);
                $table->integer('target_k')->default(0);
                $table->integer('total_target')->default(0);
                $table->integer('actual_s')->default(0);
                $table->integer('actual_a')->default(0);
                $table->integer('actual_b')->default(0);
                $table->integer('actual_c')->default(0);
                $table->integer('actual_k')->default(0);
                $table->integer('total_actual')->default(0);
                $table->integer('total_karyawan_07006')->default(0);
                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('targetdivision');
    }
};

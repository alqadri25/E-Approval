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
        if (!Schema::hasTable('master_tanda_tangan')) {
            Schema::create('master_tanda_tangan', function (Blueprint $table) {
                $table->id();
                $table->string('nrp')->index()->nullable();
                $table->string('nama')->nullable();
                $table->string('position')->nullable();
                $table->string('department')->nullable();
                $table->string('division')->nullable();
                $table->text('signature_url')->nullable();
                $table->string('drive_file_id')->nullable();
                $table->longText('signature_base64')->nullable();
                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('master_tanda_tangan');
    }
};

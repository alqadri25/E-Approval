<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. users
        if (!Schema::hasTable('app_users')) {
            Schema::create('app_users', function (Blueprint $table) {
                $table->id();
                $table->string('nama');
                $table->string('email')->unique();
                $table->string('password_hash');
                $table->string('role'); // superadmin, admin, hr, foreman, section_head, department_head, ketua, etc.
                $table->string('nrp')->nullable()->index();
                $table->string('departemen')->nullable()->index();
                $table->boolean('aktif')->default(true);
                $table->rememberToken();
                $table->timestamps();
            });
        }

        // 2. karyawan
        if (!Schema::hasTable('karyawan')) {
            Schema::create('karyawan', function (Blueprint $table) {
                $table->string('nrp')->primary();
                $table->string('nama_lengkap');
                $table->string('golongan')->nullable();
                $table->string('jabatan')->nullable();
                $table->string('tgl_masuk')->nullable();
                $table->string('seksi')->nullable();
                $table->string('departemen')->nullable()->index();
                $table->string('kj')->nullable(); // KJ 1 | KJ 2 | KJ 3 | KJ 4 | KJ 5
                $table->string('sub_gol')->nullable(); // IIA | IIB | IIC | IID | IIIA | IIIB | IIIC | IIID | IVA | IVB
                $table->string('level_form'); // KJ13 | KJ45 | LEADER | FOREMAN | SECTION_HEAD | DEPT_HEAD | DIV_HEAD
                $table->string('nrp_foreman')->nullable();
                $table->string('nrp_section_head')->nullable();
                $table->string('nrp_dept_head')->nullable();
                $table->string('nrp_div_head')->nullable();
                $table->string('code_bagian')->nullable();
                $table->string('nama_bagian')->nullable();
                $table->string('nama_sect')->nullable();
                $table->timestamps();
            });
        }

        // 3. periode
        if (!Schema::hasTable('periode')) {
            Schema::create('periode', function (Blueprint $table) {
                $table->id();
                $table->integer('tahun');
                $table->date('tgl_mulai')->nullable();
                $table->date('tgl_selesai')->nullable();
                $table->string('status')->default('aktif'); // aktif | nonaktif | selesai
                $table->timestamps();
            });
        }

        // 4. dokumen
        if (!Schema::hasTable('dokumen')) {
            Schema::create('dokumen', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('periode_id')->index();
                $table->string('nrp')->index();
                $table->string('kode_form'); // 07-001 | 07-002 | 07-003 | 07-004 | 07-006-SH | 07-006-DH | 07-006-DIV
                $table->string('status')->default('menunggu_foreman'); // draft|menunggu_foreman|menunggu_section_head|menunggu_dept_head|menunggu_div_head|selesai|revisi
                $table->decimal('total_nilai', 4, 2)->nullable();
                $table->string('grade_original', 2)->nullable(); // S | A | B | C | K
                $table->string('docking_final', 2)->nullable(); // S | A | B | C | K
                $table->string('drive_file_id')->nullable();
                $table->text('drive_url')->nullable();
                $table->string('hash_dokumen')->nullable();
                $table->string('kode_verifikasi')->unique();
                $table->integer('version')->default(1);
                $table->timestamps();
            });
        }

        // 5. nilai
        if (!Schema::hasTable('nilai')) {
            Schema::create('nilai', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('dokumen_id')->index();
                $table->string('level'); // foreman | section_head | dept_head
                $table->string('aspek_kode');
                $table->decimal('skor', 4, 2)->default(0);
                $table->text('catatan')->nullable();
                $table->timestamps();
            });
        }

        // 6. indikator
        if (!Schema::hasTable('indikator')) {
            Schema::create('indikator', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('dokumen_id')->index();
                $table->integer('pilihan_potensi')->nullable(); // 1 - 4
                $table->text('catatan_potensi')->nullable();
                $table->text('kelebihan')->nullable();
                $table->text('kekurangan')->nullable();
                $table->text('kelebihan_foreman')->nullable();
                $table->text('kelebihan_section')->nullable();
                $table->text('kelebihan_dept')->nullable();
                $table->text('kelebihan_div')->nullable();
                $table->text('kekurangan_foreman')->nullable();
                $table->text('kekurangan_section')->nullable();
                $table->text('kekurangan_dept')->nullable();
                $table->text('kekurangan_div')->nullable();
                $table->text('catatan_potensi_foreman')->nullable();
                $table->text('catatan_potensi_section')->nullable();
                $table->text('catatan_potensi_dept')->nullable();
                $table->text('catatan_potensi_div')->nullable();
                $table->longText('komentar_history')->nullable();
                $table->timestamps();
            });
        }

        // 7. tanda_tangan
        if (!Schema::hasTable('tanda_tangan')) {
            Schema::create('tanda_tangan', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('dokumen_id')->index();
                $table->string('level'); // foreman | section_head | dept_head
                $table->unsignedBigInteger('user_id');
                $table->timestamp('tgl_ttd')->nullable();
                $table->string('ip')->nullable();
                $table->text('user_agent')->nullable();
                $table->string('hash_snapshot')->nullable();
                $table->longText('ttd_image')->nullable(); // base64 / path
                $table->timestamps();
            });
        }

        // 8. master_aspek
        if (!Schema::hasTable('master_aspek')) {
            Schema::create('master_aspek', function (Blueprint $table) {
                $table->id();
                $table->string('kode_form'); // 07-001 | 07-002 | 07-003 | 07-004
                $table->string('grup'); // Hasil Kerja | Proses Kerja | Sikap Kerja | Man Management
                $table->string('aspek_kode');
                $table->string('nama');
                $table->decimal('bobot_grup', 5, 2); // e.g. 60.00, 20.00, 40.00
                $table->integer('urutan')->default(1);
                $table->timestamps();
            });
        }

        // 9. master_kriteria
        if (!Schema::hasTable('master_kriteria')) {
            Schema::create('master_kriteria', function (Blueprint $table) {
                $table->id();
                $table->string('aspek_kode');
                $table->integer('skor'); // 1 - 5
                $table->text('deskripsi');
                $table->timestamps();
            });
        }

        // 10. audit_log
        if (!Schema::hasTable('audit_log')) {
            Schema::create('audit_log', function (Blueprint $table) {
                $table->id();
                $table->timestamp('waktu')->useCurrent();
                $table->unsignedBigInteger('user_id')->nullable();
                $table->string('aksi');
                $table->unsignedBigInteger('dokumen_id')->nullable();
                $table->text('detail')->nullable();
                $table->timestamps();
            });
        }

        // 11. kuota_departemen (Target kuota S, A, B, C, K per KJ seperti di Summary PK)
        if (!Schema::hasTable('kuota_departemen')) {
            Schema::create('kuota_departemen', function (Blueprint $table) {
                $table->id();
                $table->string('departemen')->index(); // e.g. PROD 2
                $table->string('kj'); // KJ 2 | KJ 3 | KJ 4 | KJ 5
                $table->integer('kuota_s')->default(0);
                $table->integer('kuota_a')->default(0);
                $table->integer('kuota_b')->default(0);
                $table->integer('kuota_c')->default(0);
                $table->integer('kuota_k')->default(0);
                $table->integer('total_karyawan')->default(0);
                $table->timestamps();
            });
        }

        // 12. pengaturan
        if (!Schema::hasTable('pengaturan')) {
            Schema::create('pengaturan', function (Blueprint $table) {
                $table->string('key')->primary();
                $table->text('value')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('pengaturan');
        Schema::dropIfExists('audit_log');
        Schema::dropIfExists('master_kriteria');
        Schema::dropIfExists('master_aspek');
        Schema::dropIfExists('tanda_tangan');
        Schema::dropIfExists('indikator');
        Schema::dropIfExists('nilai');
        Schema::dropIfExists('dokumen');
        Schema::dropIfExists('periode');
        Schema::dropIfExists('karyawan');
        Schema::dropIfExists('app_users');
    }
};


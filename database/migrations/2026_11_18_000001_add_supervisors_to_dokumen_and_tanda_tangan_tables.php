<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Tambah kolom atasan/supervisor ke tabel 'dokumen'
        Schema::table('dokumen', function (Blueprint $table) {
            if (!Schema::hasColumn('dokumen', 'nrp_foreman')) {
                $table->string('nrp_foreman', 50)->nullable()->after('kode_verifikasi');
            }
            if (!Schema::hasColumn('dokumen', 'nama_foreman')) {
                $table->string('nama_foreman', 150)->nullable()->after('nrp_foreman');
            }
            if (!Schema::hasColumn('dokumen', 'nrp_section_head')) {
                $table->string('nrp_section_head', 50)->nullable()->after('nama_foreman');
            }
            if (!Schema::hasColumn('dokumen', 'nama_section_head')) {
                $table->string('nama_section_head', 150)->nullable()->after('nrp_section_head');
            }
            if (!Schema::hasColumn('dokumen', 'nrp_dept_head')) {
                $table->string('nrp_dept_head', 50)->nullable()->after('nama_section_head');
            }
            if (!Schema::hasColumn('dokumen', 'nama_dept_head')) {
                $table->string('nama_dept_head', 150)->nullable()->after('nrp_dept_head');
            }
            if (!Schema::hasColumn('dokumen', 'nrp_div_head')) {
                $table->string('nrp_div_head', 50)->nullable()->after('nama_dept_head');
            }
            if (!Schema::hasColumn('dokumen', 'nama_div_head')) {
                $table->string('nama_div_head', 150)->nullable()->after('nrp_div_head');
            }
        });

        // 2. Tambah kolom nrp, nama, role ke tabel 'tanda_tangan'
        Schema::table('tanda_tangan', function (Blueprint $table) {
            if (!Schema::hasColumn('tanda_tangan', 'nrp')) {
                $table->string('nrp', 50)->nullable()->after('user_id');
            }
            if (!Schema::hasColumn('tanda_tangan', 'nama')) {
                $table->string('nama', 150)->nullable()->after('nrp');
            }
            if (!Schema::hasColumn('tanda_tangan', 'role')) {
                $table->string('role', 50)->nullable()->after('nama');
            }
        });

        // 3. Backfill data atasan ke tabel dokumen dari tabel karyawan
        $karyawanRows = DB::table('karyawan')->get()->keyBy('nrp');
        $namaByNrp = [];
        foreach ($karyawanRows as $nrp => $k) {
            $namaByNrp[(string)$nrp] = trim((string)($k->nama_lengkap ?? ''));
        }

        // Tambahkan juga nama dari tabel app_users jika ada
        $userRows = DB::table('app_users')->get();
        foreach ($userRows as $u) {
            if (!empty($u->nrp) && !isset($namaByNrp[(string)$u->nrp])) {
                $namaByNrp[(string)$u->nrp] = trim((string)($u->nama ?? ''));
            }
        }

        $allDocs = DB::table('dokumen')->get();
        foreach ($allDocs as $doc) {
            $k = $karyawanRows[(string)$doc->nrp] ?? null;
            if (!$k) continue;

            $nrpForeman = !empty($k->nrp_foreman) ? trim((string)$k->nrp_foreman) : null;
            $namaForeman = ($nrpForeman && isset($namaByNrp[$nrpForeman])) ? $namaByNrp[$nrpForeman] : null;

            $nrpSection = !empty($k->nrp_section_head) ? trim((string)$k->nrp_section_head) : null;
            $namaSection = ($nrpSection && isset($namaByNrp[$nrpSection])) ? $namaByNrp[$nrpSection] : null;

            $nrpDept = !empty($k->nrp_dept_head) ? trim((string)$k->nrp_dept_head) : null;
            $namaDept = ($nrpDept && isset($namaByNrp[$nrpDept])) ? $namaByNrp[$nrpDept] : null;

            $nrpDiv = !empty($k->nrp_div_head) ? trim((string)$k->nrp_div_head) : null;
            $namaDiv = ($nrpDiv && isset($namaByNrp[$nrpDiv])) ? $namaByNrp[$nrpDiv] : null;

            DB::table('dokumen')->where('id', $doc->id)->update([
                'nrp_foreman' => $nrpForeman,
                'nama_foreman' => $namaForeman,
                'nrp_section_head' => $nrpSection,
                'nama_section_head' => $namaSection,
                'nrp_dept_head' => $nrpDept,
                'nama_dept_head' => $namaDept,
                'nrp_div_head' => $nrpDiv,
                'nama_div_head' => $namaDiv,
            ]);
        }

        // 4. Backfill tanda_tangan dengan nrp, nama, role
        $allTtd = DB::table('tanda_tangan')->get();
        $userById = $userRows->keyBy('id');
        foreach ($allTtd as $ttd) {
            $u = $userById[$ttd->user_id] ?? null;
            if ($u) {
                DB::table('tanda_tangan')->where('id', $ttd->id)->update([
                    'nrp' => $u->nrp ?? null,
                    'nama' => $u->nama ?? null,
                    'role' => $u->role ?? null,
                ]);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('dokumen', function (Blueprint $table) {
            $table->dropColumn([
                'nrp_foreman', 'nama_foreman',
                'nrp_section_head', 'nama_section_head',
                'nrp_dept_head', 'nama_dept_head',
                'nrp_div_head', 'nama_div_head'
            ]);
        });

        Schema::table('tanda_tangan', function (Blueprint $table) {
            $table->dropColumn(['nrp', 'nama', 'role']);
        });
    }
};

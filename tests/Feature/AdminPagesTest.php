<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AdminPagesTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('migrate:fresh --seed');
    }

    /**
     * Test Admin Dashboard displays Mockup Screen 1 (2. Admin).
     */
    public function test_admin_dashboard_displays_mockup_screen_1(): void
    {
        $admin = User::where('email', 'admin@musashi.co.id')->first();
        $this->assertNotNull($admin);

        $response = $this->actingAs($admin)->get('/dashboard');
        $response->assertStatus(200);

        // Header & Role
        $response->assertSee('Dashboard Admin');
        $response->assertSee('E-Approval');
        $response->assertSee('Admin Penilaian Karya');

        // 4 Stat Cards: Dynamic real employee & department stats
        $response->assertSee('Total Karyawan');
        $response->assertSee('16');
        $response->assertSee('Total Departemen');
        $response->assertSee('3');
        $response->assertSee('Total Sudah Dinilai');
        $response->assertSee('Total Belum Dinilai');

        // Middle Chart: Aktivitas Penilaian
        $response->assertSee('Aktivitas Penilaian');
        $response->assertSee('PROD 1');
        $response->assertSee('PROD 2');
        $response->assertSee('PROD 3');
        $response->assertSee('PROD 4');
        $response->assertDontSee('QA/QC');

        // Bottom Left: Total Penilaian (Donut Chart)
        $response->assertSee('Total Penilaian (Seluruh Perusahaan)');
        $response->assertSee('S (Sangat Baik)');
        $response->assertSee('A (Baik)');
        $response->assertSee('B (Cukup)');
        $response->assertSee('C (Kurang)');
        $response->assertSee('K (Kurang Sekali)');

        // Bottom Right: Ringkasan Divisi
        $response->assertSee('Ringkasan Divisi');
        $response->assertSee('Production 1 (PROD 1)');
        $response->assertSee('Production 2 (PROD 2)');
        $response->assertSee('Production 3 (PROD 3)');
        $response->assertSee('Production 4 (PROD 4)');
    }

    /**
     * Test Admin Data Karyawan displays Mockup Screen 2 (Admin: Data Karyawan).
     */
    public function test_admin_data_karyawan_displays_mockup_screen_2(): void
    {
        $admin = User::where('email', 'admin@musashi.co.id')->first();
        $this->assertNotNull($admin);

        $response = $this->actingAs($admin)->get('/admin/karyawan');
        $response->assertStatus(200);

        // Header & Actions
        $response->assertSee('Data Karyawan');
        $response->assertSee('Tambah Karyawan');
        $response->assertSee('Upload Sheet');
        $response->assertSee('Export');

        // Filter Bar
        $response->assertSee('Cari Karyawan');
        $response->assertSee('Division');
        $response->assertSee('Department');
        $response->assertSee('Section');
        $response->assertSee('KJ / Golongan');

        // Subordinate rows from real DATA PK.xlsx PROD 2
        $response->assertSee('39');
        $response->assertSee('NUR EKA PURNAMA');
        $response->assertSee('24');
        $response->assertSee('HADI PRIYONO');
        $response->assertSee('110');
        $response->assertSee('LAKSONO FITRIYANTO');
        $response->assertSee('62');
        $response->assertSee('SARYANTA');
        $response->assertDontSee('Karyawan Yang Dinilai');
    }

    /**
     * Test Admin Divisi & Departemen displays Mockup Screen 3 (Admin: Divisi & Departemen).
     */
    public function test_admin_divisi_departemen_displays_mockup_screen_3(): void
    {
        $admin = User::where('email', 'admin@musashi.co.id')->first();
        $this->assertNotNull($admin);

        $response = $this->actingAs($admin)->get('/admin/divisi-departemen');
        $response->assertStatus(200);

        $response->assertSee('Divisi &amp; Departemen', false);
        $response->assertSee('Struktur Divisi &amp; Departemen', false);
        $response->assertSee('Tambah Divisi');
        $response->assertSee('Tambah Departemen');

        // Departments
        $response->assertSee('Production 1 (PROD 1)');
        $response->assertSee('Production 2 (PROD 2)');
        $response->assertSee('Production 3 (PROD 3)');
        $response->assertSee('Production 4 (PROD 4)');
        $response->assertSee('PRODUCTION A DIV');
        $response->assertSee('PRODUCTION B DIV');
        $response->assertDontSee('Quality Assurance (QA)');
        $response->assertSee('SUPARDI');
        $response->assertSee('Bambang Widodo');
        $response->assertSee('Simpan Departemen');
    }

    /**
     * Test Admin can save new department in Divisi & Departemen.
     */
    public function test_admin_can_save_new_departemen(): void
    {
        $admin = User::where('email', 'admin@musashi.co.id')->first();
        $this->assertNotNull($admin);

        $response = $this->actingAs($admin)->post('/admin/divisi-departemen', [
            'kode_departemen' => 'LOG-01',
            'nama_departemen' => 'Logistics & Warehouse Dept.',
            'divisi' => 'Manufacturing Division',
            'dept_head' => 'Chandra Kusuma',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');
    }

    /**
     * Test Admin Target & Kuota Nilai displays Mockup Screen 4 (Admin: Target & Kuota Nilai).
     */
    public function test_admin_target_kuota_displays_mockup_screen_4(): void
    {
        $admin = User::where('email', 'admin@musashi.co.id')->first();
        $this->assertNotNull($admin);

        $response = $this->actingAs($admin)->get('/admin/target-kuota');
        $response->assertStatus(200);

        // Header & Quota per Departemen & KJ
        $response->assertSee('Target &amp; Kuota Nilai', false);
        $response->assertSee('Pengaturan Target Departemen per Golongan/KJ', false);
        $response->assertSee('S (Sangat Baik)');
        $response->assertSee('A (Baik)');
        $response->assertSee('B (Cukup)');
        $response->assertSee('C (Kurang)');
        $response->assertSee('K (Kurang Sekali)');

        // Target table
        $response->assertSee('Rincian Pengaturan Target per KJ &amp; Departemen', false);
        $response->assertSee('PROD 1');
        $response->assertSee('PROD 2');
        $response->assertSee('PROD 3');
        $response->assertSee('PROD 4');
    }

    /**
     * Test Admin can save Target & Kuota Nilai.
     */
    public function test_admin_can_save_target_kuota(): void
    {
        $admin = User::where('email', 'admin@musashi.co.id')->first();
        $this->assertNotNull($admin);

        $response = $this->actingAs($admin)->post('/admin/target-kuota', [
            'quota_s' => 10,
            'quota_a' => 30,
            'quota_b' => 30,
            'quota_c' => 20,
            'quota_k' => 10,
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');
    }

    /**
     * Test Admin and Superadmin can access Kelola Form & Periode
     */
    public function test_admin_and_superadmin_can_access_pengaturan_form(): void
    {
        $admin = User::where('email', 'admin@musashi.co.id')->first();
        $superadmin = User::where('email', 'superadmin@musashi.co.id')->first();

        // 1. Admin access
        $resAdmin = $this->actingAs($admin)->get('/admin/pengaturan-form');
        $resAdmin->assertStatus(200);
        $resAdmin->assertSee('Kelola Formulir &amp; Periode Penilaian (PK)', false);
        $resAdmin->assertSee('Tahun Penilaian (Aktif)');
        $resAdmin->assertSee('Katalog 7 Template Form PK');

        // 2. Superadmin access
        $resSuper = $this->actingAs($superadmin)->get('/admin/pengaturan-form');
        $resSuper->assertStatus(200);
        $resSuper->assertSee('Kelola Formulir &amp; Periode Penilaian (PK)', false);
    }

    /**
     * Test Admin can save form settings and verify forms reflect the update
     */
    public function test_admin_can_save_pengaturan_form_and_reflects_in_assessment_forms(): void
    {
        $admin = User::where('email', 'admin@musashi.co.id')->first();
        $foreman = User::where('email', 'saryanta@musashi.co.id')->firstOrFail();

        // 1. Save new year and period
        $resSave = $this->actingAs($admin)->post('/admin/pengaturan-form', [
            'tahun_penilaian' => 2026,
            'periode_penilaian' => 'Semester I - 2026',
            'judul_form_pk' => 'FORMULIR EVALUASI KINERJA MUSASHI',
            'nama_perusahaan' => 'PT MUSASHI AUTO PARTS INDONESIA',
            'status_periode' => 'aktif',
            'catatan_petunjuk_penilai' => 'Pilihlah rubrik penilaian yang paling mencerminkan hasil kinerja karyawan.',
            'label_kerahasiaan' => 'RAHASIA DOKUMEN',
        ]);

        $resSave->assertRedirect();
        $resSave->assertSessionHas('success');

        // 2. Open form evaluation as Foreman
        $docSub = DB::table('karyawan')
            ->join('dokumen', 'dokumen.nrp', '=', 'karyawan.nrp')
            ->where('karyawan.nrp_foreman', $foreman->nrp)
            ->select('dokumen.id')
            ->first();
        $this->assertNotNull($docSub);
        $resForm = $this->actingAs($foreman)->get("/penilaian/foreman/dokumen/{$docSub->id}");
        $resForm->assertStatus(200);
        $resForm->assertSee('2026');
        $resForm->assertSee('Semester I - 2026');
        $resForm->assertSee('FORMULIR EVALUASI KINERJA MUSASHI');
    }

    /**
     * Test Admin and Superadmin can access Manajemen Akun from sidebar
     */
    public function test_admin_and_superadmin_can_access_manajemen_akun(): void
    {
        $admin = User::where('email', 'admin@musashi.co.id')->first();
        $superadmin = User::where('email', 'superadmin@musashi.co.id')->first();

        // Admin access via /admin/manajemen-akun: Admin does NOT see Superadmin
        $resAdmin = $this->actingAs($admin)->get('/admin/manajemen-akun');
        $resAdmin->assertStatus(200);
        $resAdmin->assertSee('Manajemen Akun');
        $resAdmin->assertSee('Kelola User');
        $resAdmin->assertSee('SARYANTA');
        $resAdmin->assertSee('KRISTIYANTO');
        $resAdmin->assertSee('SUPARDI');
        $resAdmin->assertSee('Bambang Widodo');
        $resAdmin->assertDontSee('Admin Utama');
        $resAdmin->assertDontSee('Login: foreman.a@musashi.co.id');
        $resAdmin->assertDontSee('superadmin@musashi.co.id');
        $resAdmin->assertDontSee('Super Administrator');
        $resAdmin->assertDontSee('Sasaran: Hak Akses Penuh Sistem');

        // Admin sidebar contains Manajemen Akun
        $resDash = $this->actingAs($admin)->get('/dashboard');
        $resDash->assertStatus(200);
        $resDash->assertSee('Manajemen Akun');

        // Superadmin access via /superadmin/users: Superadmin DOES see Superadmin management
        $resSuper = $this->actingAs($superadmin)->get('/superadmin/users');
        $resSuper->assertStatus(200);
        $resSuper->assertSee('Manajemen Akun');
        $resSuper->assertSee('Kelola User');
        $resSuper->assertSee('Super Admin');
        $resSuper->assertSee('Super Administrator');
        $resSuper->assertSee('superadmin@musashi.co.id');
    }
}

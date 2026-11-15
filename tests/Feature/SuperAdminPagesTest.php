<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SuperAdminPagesTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('migrate:fresh --seed');
    }

    /**
     * Test Screen 1: Dashboard Super Admin (Sama persis dengan Dashboard Admin)
     */
    public function test_superadmin_dashboard_renders_all_mockup_components(): void
    {
        $superadmin = User::where('role', 'superadmin')->first();
        $this->assertNotNull($superadmin);

        $response = $this->actingAs($superadmin)->get('/dashboard');

        $response->assertStatus(200);

        // Header & Title
        $response->assertSee('Dashboard Super Admin');
        $response->assertSee('Pantauan realisasi');

        // 5 Stat Cards
        $response->assertSee('Total Karyawan');
        $response->assertSee('Total Divisi');
        $response->assertSee('Total Departemen');
        $response->assertSee('Total Section Head');
        $response->assertSee('Total Foreman');

        // Middle Section: Aktivitas Penilaian
        $response->assertSee('Aktivitas Penilaian');
        $response->assertSee('PROD 1');
        $response->assertSee('PROD 2');

        // Donut Chart: Total Penilaian (S, A, B, C, K)
        $response->assertSee('Total Penilaian (Seluruh Perusahaan)');
        $response->assertSee('S (Sangat Baik)');
        $response->assertSee('A (Baik)');
        $response->assertSee('B (Cukup)');
        $response->assertSee('C (Kurang)');
        $response->assertSee('K (Kurang Sekali)');

        // Ringkasan Divisi
        $response->assertSee('Ringkasan Divisi');

        // Sidebar Menus
        $response->assertSee('Manajemen Akun');
        $response->assertSee('Master Karyawan');
        $response->assertSee('Divisi &amp; Departemen', false);
        $response->assertSee('Lembar Penilaian PK');
        $response->assertSee('Pengaturan Sistem');
    }

    /**
     * Test Screen 2: Super Admin: Kelola User
     */
    public function test_superadmin_kelola_user_page(): void
    {
        $superadmin = User::where('role', 'superadmin')->first();

        $response = $this->actingAs($superadmin)->get('/superadmin/users');

        $response->assertStatus(200);
        $response->assertSee('Kelola User');
        $response->assertSee('Manajemen akun dan hak akses pengguna sistem');
        $response->assertSee('+ Tambah User');

        // Role Tabs
        $response->assertSee('Semua');
        $response->assertSee('Super Admin');
        $response->assertSee('Admin');
        $response->assertSee('Foreman');
        $response->assertSee('Section Head');
        $response->assertSee('Dept. Head');
        $response->assertSee('Div. Head');

        // Table headers
        $response->assertSee('NRP');
        $response->assertSee('Division');
        $response->assertSee('Department');
        $response->assertSee('Status');
        $response->assertSee('Aksi');
    }

    /**
     * Test Kelola User: Filter role tab works
     */
    public function test_superadmin_kelola_user_filters_by_role(): void
    {
        $superadmin = User::where('role', 'superadmin')->first();

        $response = $this->actingAs($superadmin)->get('/superadmin/users?role=foreman');

        $response->assertStatus(200);
        $response->assertSee('SARYANTA');
    }

    /**
     * Test Kelola User: Add new user, update, toggle status, and reset password
     */
    public function test_superadmin_user_crud_actions(): void
    {
        $superadmin = User::where('role', 'superadmin')->first();

        // 1. Store User
        $postResponse = $this->actingAs($superadmin)->post('/superadmin/users', [
            'nama' => 'Test Operator Baru',
            'email' => 'operator.baru@musashi.co.id',
            'password' => 'secret123',
            'role' => 'foreman',
            'nrp' => 'F-99999',
            'departemen' => 'PROD 2',
        ]);
        $postResponse->assertSessionHas('success');

        $newUser = User::where('email', 'operator.baru@musashi.co.id')->first();
        $this->assertNotNull($newUser);
        $this->assertEquals('Test Operator Baru', $newUser->nama);
        $this->assertEquals('F-99999', $newUser->nrp);

        // 2. Update User
        $updateResponse = $this->actingAs($superadmin)->post("/superadmin/users/{$newUser->id}/update", [
            'nama' => 'Test Operator Terupdate',
            'email' => 'operator.baru@musashi.co.id',
            'role' => 'section_head',
            'nrp' => 'SH-99999',
            'departemen' => 'PROD 2',
        ]);
        $updateResponse->assertSessionHas('success');
        $newUser->refresh();
        $this->assertEquals('Test Operator Terupdate', $newUser->nama);
        $this->assertEquals('section_head', $newUser->role);

        // 3. Toggle Status (Active -> Inactive)
        $toggleResponse = $this->actingAs($superadmin)->post("/superadmin/users/{$newUser->id}/toggle-status");
        $toggleResponse->assertSessionHas('success');
        $newUser->refresh();
        $this->assertFalse((bool)$newUser->aktif);

        // 4. Reset Password
        $resetResponse = $this->actingAs($superadmin)->post("/superadmin/users/{$newUser->id}/reset-password");
        $resetResponse->assertSessionHas('success');
    }

    /**
     * Test Screen 3: Super Admin: Pengaturan Database, Google Spreadsheet & Apps Script
     */
    public function test_superadmin_pengaturan_page_renders_google_integration(): void
    {
        $superadmin = User::where('role', 'superadmin')->first();

        $response = $this->actingAs($superadmin)->get('/superadmin/pengaturan');

        $response->assertStatus(200);
        $response->assertSee('Pengaturan');
        $response->assertSee('Konfigurasi database Google Spreadsheet &amp; Apps Script', false);

        // Pusat Sinkronisasi Database
        $response->assertSee('Pusat Sinkronisasi Database');
        $response->assertSee('Sinkronkan Seluruh Database Sekarang');

        // Download Database Excel
        $response->assertSee('Download Database (Format Excel .xlsx)');
        $response->assertSee('Unduh Seluruh Database (Multi-Sheet .xlsx)');

        // Integrasi Google Spreadsheet & Apps Script (Menggantikan 3 Kolom Lama)
        $response->assertSee('Integrasi Google Spreadsheet &amp; Apps Script (Database Utama)', false);
        $response->assertSee('Link Google Spreadsheet (Basis Data Utama)');
        $response->assertSee('Link Folder Google Drive (Arsip Dokumen PDF)');
        $response->assertSee('Link Google Apps Script (Web App URL / API)');
        $response->assertSee('Uji Koneksi Link');
        $response->assertSee('Simpan Konfigurasi Link Baru');
        $response->assertSee('Tarik / Sinkronkan dari Spreadsheet');
        $response->assertSee('Kirim / Push Data ke Spreadsheet');
        $response->assertSee('Salin Seluruh Kode Apps Script');
        $response->assertSee('Standar Struktur Folder Google Drive &amp; Format Penamaan PDF', false);
        $response->assertSee('14-xI_m24eXSj1L2aiX_gytMWrrS9HnjH5HzcRgWV_9g');
        $response->assertSee('1zB2loWV14fCpLWMCnNiEa4dVKAvhLZYE');

        // Kolom Lama Dihilangkan Sesuai Instruksi User
        $response->assertDontSee('Periode Penilaian');
        $response->assertDontSee('Kriteria Penilaian');
        $response->assertDontSee('Batas Nilai / Predikat');
    }

    /**
     * Test Simpan Konfigurasi Link Google Spreadsheet & Apps Script
     */
    public function test_superadmin_simpan_google_links(): void
    {
        $superadmin = User::where('role', 'superadmin')->first();

        $response = $this->actingAs($superadmin)->post('/superadmin/google/links', [
            'google_spreadsheet_url' => 'https://docs.google.com/spreadsheets/d/14-xI_m24eXSj1L2aiX_gytMWrrS9HnjH5HzcRgWV_9g/edit?usp=sharing',
            'google_drive_folder_url' => 'https://drive.google.com/drive/folders/1zB2loWV14fCpLWMCnNiEa4dVKAvhLZYE?usp=sharing',
            'google_appscript_url' => 'https://script.google.com/macros/s/AKfycbx_testing/exec',
            'google_sync_mode' => 'hybrid',
        ]);

        $response->assertSessionHas('success');

        $this->assertEquals(
            'https://docs.google.com/spreadsheets/d/14-xI_m24eXSj1L2aiX_gytMWrrS9HnjH5HzcRgWV_9g/edit?usp=sharing',
            \Illuminate\Support\Facades\DB::table('pengaturan')->where('key', 'google_spreadsheet_url')->value('value')
        );

        $this->assertEquals(
            '14-xI_m24eXSj1L2aiX_gytMWrrS9HnjH5HzcRgWV_9g',
            \Illuminate\Support\Facades\DB::table('pengaturan')->where('key', 'google_spreadsheet_id')->value('value')
        );

        $this->assertEquals(
            '1zB2loWV14fCpLWMCnNiEa4dVKAvhLZYE',
            \Illuminate\Support\Facades\DB::table('pengaturan')->where('key', 'google_drive_folder_id')->value('value')
        );

        $this->assertEquals(
            'https://script.google.com/macros/s/AKfycbx_testing/exec',
            \Illuminate\Support\Facades\DB::table('pengaturan')->where('key', 'google_appscript_url')->value('value')
        );
    }

    /**
     * Test Uji Koneksi & Sinkron Google Spreadsheet
     */
    public function test_superadmin_test_and_sync_google(): void
    {
        $superadmin = User::where('role', 'superadmin')->first();

        // Uji Koneksi Link
        $testRes = $this->actingAs($superadmin)->post('/superadmin/google/test', [
            'google_spreadsheet_url' => 'https://docs.google.com/spreadsheets/d/1M9rUwWr15cBHRC4U5DjCgi0kYr4GlbNOQ5GDykszS-g/edit',
            'google_appscript_url' => 'https://script.google.com/macros/s/AKfycbx_testing/exec',
        ]);
        $testRes->assertSessionHas('test_results');

        // Tarik / Sinkron Data Google
        $syncRes = $this->actingAs($superadmin)->post('/superadmin/google/sync');
        $syncRes->assertSessionHas('success');

        // Kirim / Push Data Google
        $pushRes = $this->actingAs($superadmin)->post('/superadmin/google/push');
        $pushRes->assertSessionHas('success');
    }

    /**
     * Test Sinkronisasi Database for Super Admin and Admin
     */
    public function test_superadmin_and_admin_can_sync_database(): void
    {
        $superadmin = User::where('role', 'superadmin')->first();
        $admin = User::where('role', 'admin')->first();

        // Superadmin sync
        $resSuper = $this->actingAs($superadmin)->post('/superadmin/database/sync');
        $resSuper->assertRedirect();
        $resSuper->assertSessionHas('success');

        // Admin sync
        $resAdmin = $this->actingAs($admin)->post('/admin/database/sync');
        $resAdmin->assertRedirect();
        $resAdmin->assertSessionHas('success');
    }

    /**
     * Test Export Database Excel (.xlsx) for Super Admin and Admin
     */
    public function test_superadmin_and_admin_can_export_database_excel(): void
    {
        $superadmin = User::where('role', 'superadmin')->first();
        $admin = User::where('role', 'admin')->first();

        // Superadmin full database export
        $resSuper = $this->actingAs($superadmin)->get('/superadmin/database/export-excel');
        $resSuper->assertStatus(200);
        $this->assertStringContainsString('spreadsheetml', $resSuper->headers->get('content-type'));

        // Admin full database export
        $resAdmin = $this->actingAs($admin)->get('/admin/database/export-excel');
        $resAdmin->assertStatus(200);
        $this->assertStringContainsString('spreadsheetml', $resAdmin->headers->get('content-type'));

        // Single table export
        $resTable = $this->actingAs($admin)->get('/admin/database/export-excel?table=karyawan');
        $resTable->assertStatus(200);
        $this->assertStringContainsString('spreadsheetml', $resTable->headers->get('content-type'));
    }

    /**
     * Test Admin can access Pengaturan Sistem page
     */
    public function test_admin_can_access_pengaturan_sistem(): void
    {
        $admin = User::where('role', 'admin')->first();

        $response = $this->actingAs($admin)->get('/admin/pengaturan');
        $response->assertStatus(200);
        $response->assertSee('Pengaturan');
        $response->assertSee('Pusat Sinkronisasi Database');
        $response->assertSee('Download Database (Format Excel .xlsx)');
    }
}

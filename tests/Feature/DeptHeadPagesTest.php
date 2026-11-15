<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DeptHeadPagesTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('migrate:fresh --seed');
    }

    /**
     * Test Department Head Dashboard displays Screen 1 in mockup.
     */
    public function test_dept_head_dashboard_displays_mockup_screen_1(): void
    {
        $deptHead = User::where('email', 'supardi@musashi.co.id')->first();
        $this->assertNotNull($deptHead);

        $response = $this->actingAs($deptHead)->get('/dashboard');
        $response->assertStatus(200);

        // Header & Role
        $response->assertSee('Dashboard');
        $response->assertSee('E-Approval');
        $response->assertSee('SUPARDI');

        // 3 Stat Cards (Dynamic according to PROD 2 subordinates)
        $response->assertSee('Total Karyawan');
        $response->assertSee('Total Target');
        $response->assertSee('Total Dinilai');

        // Target & Hasil Tables
        $response->assertSee('Target Penilaian');
        $response->assertSee('Dinilai Section Head');
        $response->assertSee('Hasil Penilaian Department Head');

        // Pipeline & Subordinate Section Heads (KRISTIYANTO, MURYADI, AL QADRI)
        $response->assertSee('Pelacakan Alur Status Dokumen');
        $response->assertSee('KRISTIYANTO');
        $response->assertSee('MURYADI');
        $response->assertSee('AL QADRI');

        // Data Penilaian link for Dept Head
        $response->assertSee('Data Penilaian');
    }

    /**
     * Test Department Head Data Penilaian page displays list of evaluations and self-evaluation (SUPARDI 07-006-DH).
     */
    public function test_dept_head_data_penilaian_displays_evaluations(): void
    {
        $deptHead = User::where('email', 'supardi@musashi.co.id')->first();
        $this->assertNotNull($deptHead);

        $response = $this->actingAs($deptHead)->get('/penilaian/dept');
        $response->assertStatus(200);

        $response->assertSee('Data Penilaian');
        $response->assertSee('Semua');
        $response->assertDontSee('Target &amp; Aturan Kuota', false);
        $response->assertSee('Simpan Draft');

        // Verify self (SUPARDI) and subordinates are listed
        $response->assertSee('31');
        $response->assertSee('SUPARDI');
        $response->assertSee('ANDA');
        $response->assertSee('39');
        $response->assertSee('NUR EKA PURNAMA');
    }

    /**
     * Test Target & Aturan is restricted to Admin/Superadmin and denied for Dept Head.
     */
    public function test_dept_head_target_aturan_displays_mockup_screen_2(): void
    {
        $deptHead = User::where('email', 'supardi@musashi.co.id')->first();
        $this->assertNotNull($deptHead);

        // Dept Head must be forbidden (403)
        $response = $this->actingAs($deptHead)->get('/target-aturan');
        $response->assertStatus(403);

        // Admin can access
        $admin = User::where('role', 'admin')->first();
        $resAdmin = $this->actingAs($admin)->get('/target-aturan');
        $resAdmin->assertStatus(200);

        // Page title
        $resAdmin->assertSee('Target &amp; Aturan Nilai', false);

        // 5 Quota Cards
        $resAdmin->assertSee('Nilai S');
        $resAdmin->assertSee('Nilai A');
        $resAdmin->assertSee('Nilai B');
        $resAdmin->assertSee('Nilai C');
        $resAdmin->assertSee('Nilai K');

        // Alert Quota A
        $resAdmin->assertSee('LEBIH KUOTA');
        $resAdmin->assertSee('Kuota A lebih pada');

        // Table employees matching PROD 2 real data
        $resAdmin->assertSee('NUR EKA PURNAMA');
        $resAdmin->assertSee('39');
        $resAdmin->assertSee('HADI PRIYONO');
        $resAdmin->assertSee('24');
        $resAdmin->assertSee('LAKSONO FITRIYANTO');
        $resAdmin->assertSee('110');
        $resAdmin->assertSee('SLAMET');
        $resAdmin->assertSee('66');

        // Submit button
        $resAdmin->assertSee('Simpan &amp; Kirim ke Division Head', false);
    }

    /**
     * Test saving Target & Aturan adjustment (Docking) is restricted to Admin/Superadmin.
     */
    public function test_dept_head_can_save_target_aturan_adjustment(): void
    {
        $deptHead = User::where('email', 'supardi@musashi.co.id')->first();
        $this->assertNotNull($deptHead);

        $doc = DB::table('dokumen')->where('nrp', '39')->first();
        $this->assertNotNull($doc);

        // Dept Head cannot save (403)
        $responseDept = $this->actingAs($deptHead)->post('/target-aturan/simpan', [
            'docking' => [
                $doc->id => 'B'
            ]
        ]);
        $responseDept->assertStatus(403);

        // Admin can save
        $admin = User::where('role', 'admin')->first();
        $response = $this->actingAs($admin)->post('/target-aturan/simpan', [
            'docking' => [
                $doc->id => 'B'
            ]
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('dokumen', [
            'id' => $doc->id,
            'docking_final' => 'B',
            'status' => 'selesai',
        ]);
    }

    /**
     * Test Department Head Laporan page displays Screen 3 in mockup.
     */
    public function test_dept_head_laporan_displays_mockup_screen_3(): void
    {
        $deptHead = User::where('email', 'supardi@musashi.co.id')->first();
        $this->assertNotNull($deptHead);

        $response = $this->actingAs($deptHead)->get('/laporan');
        $response->assertStatus(200);

        // Title and actions
        $response->assertSee('Laporan');
        $response->assertSee('Rekap penilaian dan export data');
        $response->assertSee('Export PDF');
        $response->assertSee('Export Excel');

        // Filter Bar
        $response->assertSee('Indikator Unit / Seksi');
        $response->assertSee('KJ / Golongan');
        $response->assertSee('Grade Nilai');
        $response->assertSee('Terapkan');

        // 5 Workflow Cards
        $response->assertSee('Total Karyawan');
        $response->assertSee('Sudah:');
        $response->assertSee('Belum:');

        // Target vs Actual Matrix Table for Department Head
        $response->assertSee('Tabel Perbandingan: Target Penilaian vs Actual Department Head');
        $response->assertSee('Target Penilaian (Kuota)');
        $response->assertSee('Actual Department Head');
        $response->assertSee('KJ \\ PK');
    }

    /**
     * Test Department Head can export Laporan to formatted Excel (.xlsx).
     */
    public function test_dept_head_can_export_laporan_excel(): void
    {
        $deptHead = User::where('email', 'supardi@musashi.co.id')->first();
        $this->assertNotNull($deptHead);

        $response = $this->actingAs($deptHead)->get('/laporan/export-excel');
        $response->assertStatus(200);
        $this->assertStringContainsString(
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            (string)$response->headers->get('Content-Type')
        );
        $this->assertStringContainsString('attachment', (string)$response->headers->get('Content-Disposition'));
        $this->assertStringContainsString('Laporan_Penilaian_Karya_', (string)$response->headers->get('Content-Disposition'));
        $this->assertStringContainsString('.xlsx', (string)$response->headers->get('Content-Disposition'));
    }
}

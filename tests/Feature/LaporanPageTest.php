<?php

namespace Tests\Feature;

use App\Models\User;
use Tests\TestCase;

class LaporanPageTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('migrate:fresh --seed');
    }

    /**
     * Test admin and superadmin can view Laporan page with 5 hierarchy cards and 18-column table.
     */
    public function test_admin_and_superadmin_can_access_laporan(): void
    {
        $superadmin = User::where('email', 'superadmin@musashi.co.id')->first();
        $this->assertNotNull($superadmin);

        $response = $this->actingAs($superadmin)->get('/laporan');
        $response->assertStatus(200);

        // Header & Titles
        $response->assertSee('Laporan');
        $response->assertSee('Rekap penilaian dan export data');
        $response->assertSee('Export PDF');
        $response->assertSee('Export Excel');

        // 5 Hierarchy Cards
        $response->assertSee('Total Karyawan');
        $response->assertSee('Total Foreman');
        $response->assertSee('Total Section Head');
        $response->assertSee('Total Department Head');
        $response->assertSee('Total Division Head');
        $response->assertSee('karyawan binaan');

        // Target vs Actual Bar Chart & Category Breakdown
        $response->assertSee('Grafik Batang: Target Kuota vs Realisasi Aktual Penilaian');
        $response->assertSee('Rekap Nilai per Kategori');

        // 19 Column Table Headers
        $response->assertSee('NO');
        $response->assertSee('NRP');
        $response->assertSee('NAMA LENGKAP');
        $response->assertSee('CODE BAGIAN');
        $response->assertSee('NAMA BAGIAN');
        $response->assertSee('SECTION');
        $response->assertSee('DEPARTMENT');
        $response->assertSee('DIVISION');
        $response->assertSee('POSITION');
        $response->assertSee('START WORK');
        $response->assertSee('KJ');
        $response->assertSee('GOLONGAN');
        $response->assertSee('Hasil Kerja');
        $response->assertSee('Proses Kerja');
        $response->assertSee('Sikap Kerja');
        $response->assertSee('Man Management');
        $response->assertSee('Total Nilai');
        $response->assertSee('NILAI PK ORIGINAL');
        $response->assertSee('AKSI');
    }

    /**
     * Test Laporan filters by department.
     */
    public function test_laporan_filters_by_department(): void
    {
        $admin = User::where('email', 'admin@musashi.co.id')->first();
        $this->assertNotNull($admin);

        // Filter to PROD 2
        $response = $this->actingAs($admin)->get('/laporan?departemen=PROD+2');
        $response->assertStatus(200);
        $response->assertSee('PROD 2');

        // Filter to ENGINEERING
        $responseEng = $this->actingAs($admin)->get('/laporan?departemen=ENGINEERING');
        $responseEng->assertStatus(200);
        $responseEng->assertSee('ENGINEERING');
    }

    /**
     * Test Laporan filters by atasan level (e.g. foreman).
     */
    public function test_laporan_filters_by_atasan(): void
    {
        $admin = User::where('email', 'admin@musashi.co.id')->first();
        $this->assertNotNull($admin);

        $response = $this->actingAs($admin)->get('/laporan?atasan=foreman');
        $response->assertStatus(200);
        $response->assertSee('Total Foreman');
    }

    /**
     * Test Export Excel (.xlsx) contains exact 17 columns and filters correctly.
     */
    public function test_export_excel_contains_all_17_columns(): void
    {
        $superadmin = User::where('email', 'superadmin@musashi.co.id')->first();
        $this->assertNotNull($superadmin);

        $response = $this->actingAs($superadmin)->get('/laporan/export-excel?departemen=PROD+2');
        $response->assertStatus(200);
        $this->assertStringContainsString(
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            (string)$response->headers->get('Content-Type')
        );
        $this->assertStringContainsString('Laporan_Penilaian_Karya_PROD_2_', (string)$response->headers->get('Content-Disposition'));
        $this->assertStringContainsString('.xlsx', (string)$response->headers->get('Content-Disposition'));

        $tmpPath = tempnam(sys_get_temp_dir(), 'laporan_xlsx_') . '.xlsx';
        file_put_contents($tmpPath, $response->getContent());

        try {
            $parsed = \App\Services\ExcelKaryawanReader::parseXlsx($tmpPath);
            $this->assertNotEmpty($parsed);
            $this->assertSame([
                'NO',
                'NRP',
                'NAMA LENGKAP',
                'CODE BAGIAN',
                'NAMA BAGIAN',
                'SECTION',
                'DEPARTMENT',
                'DIVISION',
                'POSITION',
                'START WORK',
                'KJ',
                'GOLONGAN',
                'HASIL KERJA',
                'PROSES KERJA',
                'SIKAP KERJA',
                'MAN MANAGEMENT',
                'TOTAL NILAI',
                'NILAI PK ORIGINAL',
            ], array_keys($parsed[0]));
        } finally {
            @unlink($tmpPath);
        }
    }

    /**
     * Test Export PDF: Single PDF when filtering by specific NRP.
     */
    public function test_export_pdf_single(): void
    {
        $superadmin = User::where('email', 'superadmin@musashi.co.id')->first();
        $this->assertNotNull($superadmin);

        $response = $this->actingAs($superadmin)->get('/laporan/export-pdf?q=NUR+EKA+PURNAMA');
        $response->assertStatus(200);
        $this->assertStringContainsString('application/pdf', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('.pdf', $response->headers->get('Content-Disposition'));
    }

    /**
     * Test Export PDF: Bulk ZIP when multiple employees match.
     */
    public function test_export_pdf_bulk_zip(): void
    {
        $superadmin = User::where('email', 'superadmin@musashi.co.id')->first();
        $this->assertNotNull($superadmin);

        $response = $this->actingAs($superadmin)->get('/laporan/export-pdf?departemen=PROD+2');
        $response->assertStatus(200);
        $this->assertStringContainsString('.zip', $response->headers->get('Content-Disposition'));
    }

    /**
     * Test Foreman viewing Laporan: Atasan/Level filter is hidden and subordinate scores are from Foreman.
     */
    public function test_foreman_laporan_hides_atasan_filter_and_shows_foreman_scores(): void
    {
        $foreman = User::where('email', 'saryanta@musashi.co.id')->first();
        $this->assertNotNull($foreman);

        $response = $this->actingAs($foreman)->get('/laporan');
        $response->assertStatus(200);

        // Atasan / Level filter is hidden for Foreman
        $response->assertDontSee('Atasan / Level');

        // Other filters and table headers are visible
        $response->assertSee('Cari NRP / Nama');
        $response->assertSee('NAMA BAGIAN');
        $response->assertSee('POSITION');
        $response->assertSee('KJ / Golongan');
        $response->assertSee('Hasil Kerja');
        $response->assertSee('Proses Kerja');
        $response->assertSee('Sikap Kerja');
        $response->assertSee('Total Nilai');
        $response->assertSee('NILAI PK FOREMAN');

        // Verify 5 Workflow Tracking Cards for Foreman (replaces 5 Admin hierarchy cards)
        $response->assertSee('Total Karyawan');
        $response->assertSee('Anggota Foreman');
        $response->assertSee('Siap Disahkan (To-Do)');
        $response->assertSee('Diteruskan Ke Section Head');
        $response->assertSee('Menunggu Section Head');
        $response->assertSee('Menunggu Department Head');
        $response->assertSee('Tahap Sect');
        $response->assertSee('Tingkat Akhir');

        // Verify 5 hierarchy cards are not shown to Foreman
        $response->assertDontSee('Total Foreman');
        $response->assertDontSee('Total Section Head');
        $response->assertDontSee('Total Department Head');
        $response->assertDontSee('Total Division Head');

        // Verify subordinate NUR EKA PURNAMA is displayed
        $response->assertSee('NUR EKA PURNAMA');
        $response->assertSee('39');

        // Verify Foreman can filter by NAMA BAGIAN
        $bagianList = $response->viewData('distinctBagians') ?? [];
        $this->assertNotEmpty($bagianList);
        $targetBagian = $bagianList[0];
        $responseBagian = $this->actingAs($foreman)->get('/laporan?bagian=' . urlencode($targetBagian));
        $responseBagian->assertStatus(200);
        $responseBagian->assertSee($targetBagian);
    }
}

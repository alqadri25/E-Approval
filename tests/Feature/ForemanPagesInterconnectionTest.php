<?php

namespace Tests\Feature;

use App\Models\User;
use Tests\TestCase;

class ForemanPagesInterconnectionTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('migrate:fresh --seed');
    }

    /**
     * Test all 5 Foreman pages are interconnected and employee/target metrics match.
     */
    public function test_foreman_all_pages_interconnected_and_counts_consistent(): void
    {
        $foreman = User::where('email', 'saryanta@musashi.co.id')->first();
        $this->assertNotNull($foreman, 'Foreman user must exist');
        $this->assertEquals('62', $foreman->nrp);
        $this->assertTrue($foreman->isForeman());

        // =====================================================================
        // 1. DASHBOARD PAGE (/dashboard)
        // =====================================================================
        $dashRes = $this->actingAs($foreman)->get('/dashboard');
        $dashRes->assertStatus(200);

        // Sidebar Navigation
        $dashRes->assertSee(route('dashboard'));
        $dashRes->assertSee(route('karyawan.index'));
        $dashRes->assertSee(route('penilaian.cari'));
        $dashRes->assertSee(route('penilaian.riwayat'));
        $dashRes->assertSee(route('laporan.index'));

        // Metric cards
        $dashRes->assertSee('Total Karyawan');
        $dashRes->assertSee('Total Target');
        $dashRes->assertSee('Total Dinilai');
        $dashRes->assertSee('Terkirim ke Section Head');

        // Anchors & Scroll Interconnections
        $dashRes->assertSee("scrollToSection('section-data-karyawan', 'all')", false);
        $dashRes->assertSee("scrollToSection('chart-target-kategori')", false);
        $dashRes->assertSee("scrollToSection('section-data-karyawan', 'evaluated')", false);
        $dashRes->assertSee("scrollToSection('section-data-karyawan', 'done')", false);
        $dashRes->assertSee("scrollToSection('section-data-karyawan', 'todo')", false);

        // Subordinates check in table (F1 CAT 1: 39 KJ13, 24 KJ45, 110 Leader, 62 Foreman Self)
        $dashRes->assertSee('39');
        $dashRes->assertSee('24');
        $dashRes->assertSee('110');
        $dashRes->assertSee('62');

        $dashData = $dashRes->viewData('stats');
        $dashSubordinates = $dashRes->viewData('foremanSubordinates');
        $dashTargets = $dashRes->viewData('targetCounts');

        $this->assertCount(23, $dashSubordinates, 'Foreman SARYANTA must have exactly 23 subordinates (22 F1 CAT 1 + self)');
        $this->assertEquals(23, $dashData['total_karyawan']);
        $this->assertEquals(23, $dashData['total_target']);
        $this->assertEquals(23, array_sum($dashTargets), 'Target counts sum must equal total subordinates');

        // =====================================================================
        // 2. DATA KARYAWAN PAGE (/karyawan)
        // =====================================================================
        $karyawanRes = $this->actingAs($foreman)->get('/karyawan');
        $karyawanRes->assertStatus(200);

        $karyawanRows = $karyawanRes->viewData('karyawanRows');
        $hierarchySummary = $karyawanRes->viewData('hierarchySummary');

        $this->assertCount(23, $karyawanRows, 'Data Karyawan must show exactly 23 subordinates for Foreman (including self)');
        $this->assertEquals(23, $hierarchySummary['total_karyawan'] ?? count($karyawanRows));

        // Header buttons and links (Foreman only sees Export; Tambah Karyawan & Upload Sheet are Admin/HR only)
        $karyawanRes->assertSee(route('karyawan.export'));
        $karyawanRes->assertDontSee('Tambah Karyawan');
        $karyawanRes->assertDontSee('Upload Sheet');

        // Check Form Code determination in table
        $karyawanRes->assertSee('07-001'); // KJ 1-3
        $karyawanRes->assertSee('07-002'); // KJ 4-5 Non Jabatan
        $karyawanRes->assertSee('07-003'); // Leader
        $karyawanRes->assertSee('07-004'); // Foreman

        // Check Action links to penilaian
        $karyawanRes->assertSee(route('penilaian.show', $karyawanRows[0]['doc_id']));

        // =====================================================================
        // 3. FORM PENILAIAN / CARI NRP (/penilaian/cari)
        // =====================================================================
        $cariRes = $this->actingAs($foreman)->get('/penilaian/cari');
        $cariRes->assertStatus(200);

        $cariTotal = $cariRes->viewData('totalCount');
        $this->assertEquals(23, $cariTotal, 'Penilaian Cari totalCount must match subordinate count (23)');

        $cariRes->assertSee('23 Bawahan Terdaftar');
        $cariRes->assertSee('NUR EKA PURNAMA');
        $cariRes->assertSee('HADI PRIYONO');
        $cariRes->assertSee('LAKSONO FITRIYANTO');
        $cariRes->assertSee('SARYANTA');
        $cariRes->assertSee(route('penilaian.buka'));

        // =====================================================================
        // 4. RIWAYAT PENILAIAN PAGE (/penilaian/riwayat)
        // =====================================================================
        $riwayatRes = $this->actingAs($foreman)->get('/penilaian/riwayat');
        $riwayatRes->assertStatus(200);

        $riwayatDinilai = $riwayatRes->viewData('totalDinilai');
        $riwayatBelum = $riwayatRes->viewData('totalBelum');
        $riwayatTargets = $riwayatRes->viewData('targetCounts');

        $this->assertEquals(23, $riwayatDinilai + $riwayatBelum, 'Riwayat total (dinilai + belum) must equal 23');
        $this->assertEquals($dashTargets, $riwayatTargets, 'Target counts in Riwayat must match Dashboard targets');

        // Navigation back to form
        $riwayatRes->assertSee(route('penilaian.cari'));

        // =====================================================================
        // 5. LAPORAN PENILAIAN PAGE (/laporan)
        // =====================================================================
        $laporanRes = $this->actingAs($foreman)->get('/laporan');
        $laporanRes->assertStatus(200);

        $laporanTotal = $laporanRes->viewData('totalKaryawan');
        $laporanRows = $laporanRes->viewData('employeeRows');
        $laporanTarget = $laporanRes->viewData('targetKuota');

        $this->assertEquals(23, $laporanTotal, 'Laporan totalKaryawan must match 23');
        $this->assertCount(23, $laporanRows, 'Laporan employeeRows must be exactly 23 subordinates');

        // Export links
        $laporanRes->assertSee(route('laporan.exportExcel'));
        $laporanRes->assertSee(route('laporan.exportPdf'));

        // Check target counts match dashboard
        $this->assertEquals($dashTargets['S'], $laporanTarget['S'], 'Target S in Laporan must match Dashboard');
        $this->assertEquals($dashTargets['A'], $laporanTarget['A'], 'Target A in Laporan must match Dashboard');
        $this->assertEquals($dashTargets['B'], $laporanTarget['B'], 'Target B in Laporan must match Dashboard');
        $this->assertEquals($dashTargets['C'], $laporanTarget['C'], 'Target C in Laporan must match Dashboard');
        $this->assertEquals($dashTargets['K'], $laporanTarget['K'], 'Target K in Laporan must match Dashboard');
    }

    /**
     * Test Foreman can seamlessly navigate from Dashboard, Karyawan, Penilaian Cari into Assessment Form.
     */
    public function test_foreman_can_open_forms_for_all_subordinates(): void
    {
        $foreman = User::where('email', 'saryanta@musashi.co.id')->first();

        // 1. Open Assessment via route('penilaian.buka') for representative F1 CAT 1 subordinates (07-001, 07-002, 07-003, 07-004)
        $subordinates = ['39', '24', '110', '62'];

        foreach ($subordinates as $nrp) {
            $postRes = $this->actingAs($foreman)->post('/penilaian/buka', ['nrp' => $nrp]);
            $postRes->assertStatus(302);
            $targetUrl = $postRes->headers->get('Location');
            $this->assertNotNull($targetUrl);

            // Follow redirect to the assessment form
            $formRes = $this->actingAs($foreman)->get($targetUrl);
            $formRes->assertStatus(200);
            $formRes->assertSee('Penilaian Karya');
            $formRes->assertSee($nrp);

            // Check that PDF button exists and works
            $docId = $formRes->viewData('dokumen')['id'] ?? null;
            $this->assertNotNull($docId);

            $pdfRes = $this->actingAs($foreman)->get("/dokumen/{$docId}/pdf");
            $pdfRes->assertStatus(200);
        }
    }

    /**
     * Test Foreman API lookup returns correct form determinations.
     */
    public function test_foreman_api_karyawan_lookup(): void
    {
        $foreman = User::where('email', 'saryanta@musashi.co.id')->first();

        // Lookup KJ 1-3 Non-Jabatan
        $res1 = $this->actingAs($foreman)->getJson('/api/karyawan-lookup?nrp=39');
        $res1->assertStatus(200)
             ->assertJsonPath('found', true)
             ->assertJsonPath('karyawan.kode_form', '07-001');

        // Lookup KJ 4-5 Non-Jabatan
        $res2 = $this->actingAs($foreman)->getJson('/api/karyawan-lookup?nrp=24');
        $res2->assertStatus(200)
             ->assertJsonPath('found', true)
             ->assertJsonPath('karyawan.kode_form', '07-002');

        // Lookup Leader
        $res3 = $this->actingAs($foreman)->getJson('/api/karyawan-lookup?nrp=110');
        $res3->assertStatus(200)
             ->assertJsonPath('found', true)
             ->assertJsonPath('karyawan.kode_form', '07-003');

        // Lookup Foreman
        $res4 = $this->actingAs($foreman)->getJson('/api/karyawan-lookup?nrp=62');
        $res4->assertStatus(200)
             ->assertJsonPath('found', true)
             ->assertJsonPath('karyawan.kode_form', '07-004');
    }

    /**
     * Test export Excel (.xlsx) & PDF from Laporan works for Foreman.
     */
    public function test_foreman_laporan_exports(): void
    {
        $foreman = User::where('email', 'saryanta@musashi.co.id')->first();

        // Export Excel (.xlsx)
        $resExcel = $this->actingAs($foreman)->get('/laporan/export-excel');
        $resExcel->assertStatus(200);
        $this->assertStringContainsString(
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            (string)$resExcel->headers->get('Content-Type')
        );
        $this->assertStringContainsString('.xlsx', (string)$resExcel->headers->get('Content-Disposition'));

        $tmpPath = tempnam(sys_get_temp_dir(), 'foreman_laporan_') . '.xlsx';
        file_put_contents($tmpPath, $resExcel->getContent());
        try {
            $parsed = \App\Services\ExcelKaryawanReader::parseXlsx($tmpPath);
            $this->assertNotEmpty($parsed);
            $headers = array_keys($parsed[0]);
            $this->assertSame('NO', $headers[0] ?? null);
            $this->assertSame('NRP', $headers[1] ?? null);
            $this->assertSame('NAMA LENGKAP', $headers[2] ?? null);
            $this->assertSame('DIVISION', $headers[7] ?? null);
            $this->assertCount(18, $headers);
        } finally {
            @unlink($tmpPath);
        }

        // Export PDF
        $resPdf = $this->actingAs($foreman)->get('/laporan/export-pdf');
        $resPdf->assertStatus(200);
    }
}

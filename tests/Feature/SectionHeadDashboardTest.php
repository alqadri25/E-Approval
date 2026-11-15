<?php

namespace Tests\Feature;

use App\Models\User;
use Tests\TestCase;

class SectionHeadDashboardTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('migrate:fresh --seed');
    }

    /**
     * Test Section Head TANPA Foreman (SH-11310 Dwi Cahyono) dashboard structure:
     */
    public function test_section_head_without_foreman_has_foreman_style_dashboard(): void
    {
        // Section Head C (Dwi Cahyono) has direct staff (Rudi & Siti) without any foreman
        $sectionHead = User::where('email', 'section.p1a@musashi.co.id')->first();
        $this->assertNotNull($sectionHead);
        $this->assertEquals('SH-11310', $sectionHead->nrp);
        $this->assertTrue($sectionHead->isSectionHead());

        $response = $this->actingAs($sectionHead)->get('/dashboard');
        $response->assertStatus(200);

        // Header Card
        $response->assertSee('Selamat Datang Dwi Cahyono', false);
        $response->assertDontSee('SECTION HEAD PROD 1');
        $response->assertSee(route('penilaian.cari'));
        $response->assertSee('Form Penilaian');

        // 4 Key Stat Cards
        $response->assertSee('Total Karyawan');
        $response->assertSee('Total Target');
        $response->assertSee('Total Dinilai');
        $response->assertSee('Anggota Section Head');
        $response->assertSee('Terkirim ke Dept. Head');

        // Clickable card scroll anchors
        $response->assertSee("scrollToSection('section-data-karyawan', 'all')", false);
        $response->assertSee("scrollToSection('chart-target-kategori')", false);
        $response->assertSee("scrollToSection('section-data-karyawan', 'evaluated')", false);
        $response->assertSee("scrollToSection('section-data-karyawan', 'done')", false);
        $response->assertSee("scrollToSection('section-data-karyawan', 'todo')", false);

        // Workflow Pipeline Tracker
        $response->assertSee('Pelacakan Alur Status Dokumen Bawahan');
        $response->assertSee('1. Penilaian Section Head (To-Do)');
        $response->assertSee('2. Tinjauan Department Head');
        $response->assertSee('3. Approval Division Head');
        $response->assertSee('4. Selesai & Terarsip', false);

        // 2 KJ Matrix Tables
        $response->assertSee('Target Penilaian');
        $response->assertSee('(berdasarkan kategori &amp; KJ)', false);
        $response->assertSee('Hasil Section Head');
        $response->assertSee('(nilai dari anda per KJ)');

        // Subordinate Table & Filters
        $response->assertSee('Data Karyawan & Penilaian', false);
        $response->assertSee('3 Karyawan');
        $response->assertSee('Rudi Hermawan');
        $response->assertSee('2023020');
        $response->assertSee('Siti Aminah');
        $response->assertSee('2023021');

        // Status Dropdown filter options
        $response->assertSee('Semua (3)');
        $response->assertSee('Perlu Tindakan / To-Do (');
        $response->assertSee('Draft Tersimpan (');
        $response->assertSee('Sudah Terkirim / Selesai (');
        $response->assertSee('Sudah Dinilai (');
        $response->assertSee('Cari nama, NRP, position...');

        // Should NOT see Foreman charts/recap
        $response->assertDontSee('Rekap Penilaian per Foreman');
        $response->assertDontSee('foremanResultChart');
    }

    /**
     * Test Section Head DENGAN Foreman (112 KRISTIYANTO) has Foreman workflow & KJ matrices:
     */
    public function test_section_head_with_foreman_has_rekap_foreman_dashboard(): void
    {
        $sectionHead = User::where('email', 'kristiyanto@musashi.co.id')->first();
        $this->assertNotNull($sectionHead);

        $response = $this->actingAs($sectionHead)->get('/dashboard');
        $response->assertStatus(200);

        $response->assertSee('Pelacakan Alur Status Dokumen Bawahan');
        $response->assertSee('1. Penilaian Foreman');
        $response->assertSee('Target Penilaian');
        $response->assertSee('Hasil Section Head');
        $response->assertSee('SARYANTA');
    }

    /**
     * Test Section Head Factory 3 Tanpa Foreman (99999 AL QADRI) dashboard & redirect:
     */
    public function test_section_head_e_has_modern_gambar_2_dashboard(): void
    {
        $sectionHeadF3 = User::where('email', 'al.qadri@musashi.co.id')->firstOrFail();

        $response = $this->actingAs($sectionHeadF3)->get('/dashboard');
        $response->assertStatus(200);

        // Header Card (Merah Musashi)
        $response->assertSee('Dashboard');
        $response->assertSee('Form Penilaian');
        $response->assertSee('Selamat Datang AL QADRI', false);
        $response->assertDontSee('SECTION HEAD PROD 2');

        // 4 Key Stat Cards
        $response->assertSee('Total Karyawan');
        $response->assertSee('Total Target');
        $response->assertSee('Total Dinilai');
        $response->assertSee('Anggota Section Head');
        $response->assertSee('Terkirim ke Dept. Head');

        // Pipeline Tracker: Tahap 1 Penilaian Section Head s/d Tahap 4 Selesai & Terarsip
        $response->assertSee('Pelacakan Alur Status Dokumen Bawahan');
        $response->assertSee('1. Penilaian Section Head (To-Do)');
        $response->assertSee('2. Tinjauan Department Head');
        $response->assertSee('3. Approval Division Head');
        $response->assertSee('4. Selesai & Terarsip', false);

        // 2 KJ Matrix Tables
        $response->assertSee('Target Penilaian');
        $response->assertSee('(berdasarkan kategori &amp; KJ)', false);
        $response->assertSee('Hasil Section Head');
        $response->assertSee('(nilai dari anda per KJ)');

        // Bottom Table
        $response->assertSee('Data Karyawan & Penilaian', false);
        $response->assertSee('Semua (');
        $response->assertSee('Cari nama, NRP, position...');

        // Should NOT see Foreman elements
        $response->assertDontSee('Pemberitahuan Penilaian Masuk dari Foreman');
        $response->assertDontSee('Hasil Foreman (nilai dari Foreman)');

        // Sidebar shows Data Karyawan and PENILAIAN KARYAWAN
        $response->assertSee('PENILAIAN KARYAWAN');
        $response->assertSee('Data Karyawan');
        $response->assertSee(route('karyawan.index'));
        $response->assertDontSee('Data Penilaian');
        $response->assertDontSee('href="' . route('penilaian.section') . '"', false);

        // Direct access to /penilaian/section redirects to /karyawan
        $redirectResponse = $this->actingAs($sectionHeadF3)->get('/penilaian/section');
        $redirectResponse->assertRedirect(route('karyawan.index'));
    }

    /**
     * Test Section Head TANPA Foreman sidebar shows "Data Karyawan" and redirects /penilaian/section to /karyawan:
     */
    public function test_section_head_without_foreman_sidebar_and_route_redirect(): void
    {
        $sectionHead = User::where('email', 'section.p1a@musashi.co.id')->first();
        $this->assertNotNull($sectionHead);
        $this->assertFalse($sectionHead->hasForemanSubordinates());

        // 1. Sidebar check on /dashboard
        $response = $this->actingAs($sectionHead)->get('/dashboard');
        $response->assertStatus(200);
        $response->assertSee('Data Karyawan');
        $response->assertSee(route('karyawan.index'));
        $response->assertDontSee('Data Penilaian');
        $response->assertDontSee('href="' . route('penilaian.section') . '"', false);

        // 2. Access /penilaian/section directly -> should redirect to /karyawan
        $redirectResponse = $this->actingAs($sectionHead)->get('/penilaian/section');
        $redirectResponse->assertRedirect(route('karyawan.index'));
    }

    /**
     * Test Section Head DENGAN Foreman sidebar shows "Data Penilaian" and accesses /penilaian/section normally:
     */
    public function test_section_head_with_foreman_sidebar_and_route_access(): void
    {
        $sectionHead = User::where('email', 'kristiyanto@musashi.co.id')->first();
        $this->assertNotNull($sectionHead);
        $this->assertTrue($sectionHead->hasForemanSubordinates());

        // 1. Sidebar check on /dashboard
        $response = $this->actingAs($sectionHead)->get('/dashboard');
        $response->assertStatus(200);
        $response->assertSee('Data Penilaian');
        $response->assertSee(route('penilaian.section'));

        // 2. Access /penilaian/section directly -> should return 200
        $sectionResponse = $this->actingAs($sectionHead)->get('/penilaian/section');
        $sectionResponse->assertStatus(200);
    }

    /**
     * Test Section Head TANPA Foreman (99999 AL QADRI & SH-11310 Dwi Cahyono) use 07-006-SH for self and KJ forms for subs:
     */
    public function test_section_head_without_foreman_subordinates_use_form_07_006(): void
    {
        // 1. Test Section Head Factory 3 Tanpa Foreman (99999 AL QADRI)
        $sectionHeadF3 = User::where('nrp', '99999')->firstOrFail();

        $responseF3 = $this->actingAs($sectionHeadF3)->get('/karyawan');
        $responseF3->assertStatus(200);
        $responseF3->assertSee('Data Karyawan');
        $responseF3->assertSee('AL QADRI');
        $responseF3->assertSee('TES 1');
        $responseF3->assertSee('TES 2');
        $responseF3->assertSee('TES 3');

        // Self uses 07-006-SH; direct KJ 2 staff use 07-001
        $karyawanListF3 = collect($responseF3->viewData('karyawanRows'))->keyBy('nrp');
        $this->assertNotEmpty($karyawanListF3);
        $this->assertEquals('07-006-SH', $karyawanListF3['99999']['kode_form']);
        $this->assertEquals('07-001', $karyawanListF3['99998']['kode_form']);
        $this->assertEquals('07-001', $karyawanListF3['99997']['kode_form']);
        $this->assertEquals('07-001', $karyawanListF3['99996']['kode_form']);

        // 2. Test Section Head C (SH-11310 / Dwi Cahyono)
        $sectionHeadC = User::where('email', 'section.p1a@musashi.co.id')->first();
        $this->assertNotNull($sectionHeadC);

        $responseC = $this->actingAs($sectionHeadC)->get('/karyawan');
        $responseC->assertStatus(200);
        $responseC->assertSee('Dwi Cahyono');
        $responseC->assertSee('Rudi Hermawan');
        $responseC->assertSee('Siti Aminah');

        $karyawanListC = collect($responseC->viewData('karyawanRows'))->keyBy('nrp');
        $this->assertNotEmpty($karyawanListC);
        $this->assertEquals('07-006-SH', $karyawanListC['SH-11310']['kode_form']);
        $this->assertEquals('07-003', $karyawanListC['2023020']['kode_form']);
        $this->assertEquals('07-001', $karyawanListC['2023021']['kode_form']);
    }

    /**
     * Test Section Head TANPA Foreman has Foreman-style 4 Stage Workflow tracking and layout on /laporan:
     */
    public function test_section_head_without_foreman_has_foreman_style_laporan(): void
    {
        $sectionHeadF3 = User::where('nrp', '99999')->firstOrFail();

        $response = $this->actingAs($sectionHeadF3)->get('/laporan');
        $response->assertStatus(200);

        // 5 Workflow Cards for Section Head
        $response->assertSee('Total Karyawan');
        $response->assertSee('Siap Disahkan (To-Do)');
        $response->assertSee('Diteruskan Dept Head');
        $response->assertSee('Approval Department Head');
        $response->assertSee('Tahap Dept');

        // Unified matrix table subtitle & column header
        $response->assertSee('Berdasarkan penilaian section head');
        $response->assertSee('NILAI PK SECT HEAD');

        // Filters: Atasan / Level hidden, Bagian visible
        $response->assertDontSee('Atasan / Level');
        $response->assertSee('Bagian');

        // Check view data
        $foremanStageCounts = $response->viewData('foremanStageCounts');
        $this->assertNotNull($foremanStageCounts);
        $this->assertArrayHasKey('need_action', $foremanStageCounts);
        $this->assertArrayHasKey('waiting_dept', $foremanStageCounts);
        $this->assertArrayHasKey('waiting_div', $foremanStageCounts);
        $this->assertArrayHasKey('selesai', $foremanStageCounts);

        // JABATAN filter options must match active scope only (SECTION HEAD, STAFF)
        $distinctJabatans = $response->viewData('distinctJabatans');
        $this->assertCount(2, $distinctJabatans);
        $this->assertContains('SECTION HEAD', $distinctJabatans);
        $this->assertContains('STAFF', $distinctJabatans);
        $this->assertNotContains('FOREMAN', $distinctJabatans);
        $this->assertNotContains('DIV HEAD', $distinctJabatans);
    }

    /**
     * Test Section Head DENGAN Foreman keeps 5 Hierarchy Cards on /laporan:
     */
    public function test_section_head_with_foreman_has_hierarchy_cards_laporan(): void
    {
        $sectionHead = User::where('email', 'kristiyanto@musashi.co.id')->first();
        $this->assertNotNull($sectionHead);

        $response = $this->actingAs($sectionHead)->get('/laporan');
        $response->assertStatus(200);

        // Section Head 5 workflow cards
        $response->assertSee('Total Karyawan');
        $response->assertSee('Anggota Section Head');
        $response->assertSee('Siap Disahkan (To-Do)');
        $response->assertSee('Proses Foreman');
        $response->assertSee('Diteruskan Dept Head');
        $response->assertSee('Approval Department Head');
        $response->assertSee('Berdasarkan penilaian section head');
        $response->assertSee('Tabel Perbandingan: Target Penilaian vs Actual Section Head');
        $response->assertDontSee('targetVsActualChart');
        $response->assertSee('NILAI PK SECT HEAD');
        $response->assertDontSee('Atasan / Level');

        // Section Head WITH Foreman Riwayat Penilaian matches Section Head WITHOUT Foreman matrix design
        $riwayatRes = $this->actingAs($sectionHead)->get('/penilaian/riwayat');
        $riwayatRes->assertStatus(200);
        $riwayatRes->assertSee('Tabel Perbandingan: Target Penilaian vs Actual Section Head');
        $riwayatRes->assertDontSee('comparisonBarChart');
        $riwayatRes->assertDontSee('Grafik Perbandingan:');
    }

    /**
     * Test Section Head TANPA Foreman counts self across all pages (Dashboard, Karyawan, Laporan, Form Penilaian):
     */
    public function test_section_head_without_foreman_counts_self_across_all_pages(): void
    {
        $sectionHeadF3 = User::where('nrp', '99999')->firstOrFail();

        // 1. Dashboard: total 4 rows (3 subs + self)
        $responseDash = $this->actingAs($sectionHeadF3)->get('/dashboard');
        $responseDash->assertStatus(200);
        $subs = $responseDash->viewData('sectionSubordinates');
        $this->assertCount(4, $subs);
        $this->assertTrue(collect($subs)->contains('nrp', '99999'));
        $this->assertTrue(collect($subs)->contains('nrp', '99998'));
        $this->assertTrue(collect($subs)->contains('nrp', '99997'));
        $this->assertTrue(collect($subs)->contains('nrp', '99996'));
        $responseDash->assertSee('Memantau posisi dokumen seluruh 4 karyawan');
        $responseDash->assertSee('4 Orang');

        // 2. Data Karyawan: total 4 rows (3 subs + self)
        $responseKaryawan = $this->actingAs($sectionHeadF3)->get('/karyawan');
        $responseKaryawan->assertStatus(200);
        $kRows = $responseKaryawan->viewData('karyawanRows');
        $this->assertCount(4, $kRows);
        $this->assertTrue(collect($kRows)->contains('nrp', '99999'));
        $this->assertTrue(collect($kRows)->contains('nrp', '99998'));
        $this->assertTrue(collect($kRows)->contains('nrp', '99997'));
        $this->assertTrue(collect($kRows)->contains('nrp', '99996'));

        // 3. Laporan Penilaian: total 4 rows (3 subs + self)
        $responseLaporan = $this->actingAs($sectionHeadF3)->get('/laporan');
        $responseLaporan->assertStatus(200);
        $lRows = $responseLaporan->viewData('employeeRows');
        $this->assertCount(4, $lRows);
        $this->assertTrue(collect($lRows)->contains('nrp', '99999'));
        $this->assertTrue(collect($lRows)->contains('nrp', '99998'));
        $this->assertTrue(collect($lRows)->contains('nrp', '99997'));
        $this->assertTrue(collect($lRows)->contains('nrp', '99996'));
        $stageCounts = $responseLaporan->viewData('foremanStageCounts');
        $this->assertEquals(4, $stageCounts['need_action'] ?? 0);

        // 4. Form Penilaian (Cari): total 4 subordinates (3 subs + self)
        $responseCari = $this->actingAs($sectionHeadF3)->get('/penilaian/cari');
        $responseCari->assertStatus(200);
        $cariSubs = $responseCari->viewData('subordinates');
        $this->assertCount(4, $cariSubs);
        $this->assertTrue(collect($cariSubs)->contains('nrp', '99999'));

        // 5. Riwayat Penilaian: totalSubordinates = 4
        $responseRiwayat = $this->actingAs($sectionHeadF3)->get('/penilaian/riwayat');
        $responseRiwayat->assertStatus(200);
        $this->assertEquals(4, $responseRiwayat->viewData('totalSubordinates'));
    }
}

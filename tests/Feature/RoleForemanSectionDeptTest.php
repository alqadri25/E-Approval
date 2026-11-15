<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class RoleForemanSectionDeptTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('migrate:fresh --seed');
    }

    /**
     * Test Foreman Dashboard displays subordinate count, target, and grade metrics.
     */
    public function test_foreman_dashboard_displays_subordinates_and_metrics(): void
    {
        $foreman = User::where('email', 'saryanta@musashi.co.id')->first();
        $this->assertNotNull($foreman);

        $response = $this->actingAs($foreman)->get('/dashboard');
        $response->assertStatus(200);

        // Verify foreman dashboard indicators
        $response->assertSee('Dashboard Foreman');
        $response->assertSee('E-Approval');
        $response->assertSee('SARYANTA');
        $response->assertSee('Total Karyawan');
        $response->assertSee('Total Target');
        $response->assertSee('Total Dinilai');
        $response->assertSee('Target Penilaian');
        $response->assertSee('(berdasarkan kategori &amp; KJ)', false);
        $response->assertSee('Hasil Foreman');
        $response->assertSee('(nilai dari anda per KJ)');
        $response->assertSee('Data Karyawan & Penilaian', false);

        // Subordinate check: NUR EKA PURNAMA (NRP 39)
        $response->assertSee('NUR EKA PURNAMA');
        $response->assertSee('39');

        // Status dropdown options next to search
        $response->assertSee('Semua (');
        $response->assertSee('Perlu Tindakan / To-Do (');
        $response->assertSee('Draft Tersimpan (');
        $response->assertSee('Sudah Terkirim / Selesai (');
        $response->assertSee('Sudah Dinilai (');

        // Verify interactive clickable card navigation and section anchors
        $response->assertSee('id="section-data-karyawan"', false);
        $response->assertSee('id="chart-target-kategori"', false);
        $response->assertSee("scrollToSection('section-data-karyawan', 'all')", false);
        $response->assertSee("scrollToSection('chart-target-kategori')", false);
        $response->assertSee("scrollToSection('section-data-karyawan', 'evaluated')", false);
        $response->assertSee("scrollToSection('section-data-karyawan', 'done')", false);
        $response->assertSee("scrollToSection('section-data-karyawan', 'todo')", false);
        $response->assertDontSee('targetDonutChart');
        $response->assertDontSee('resultDonutChart');
        $response->assertDontSee('Prioritas Tindakan Foreman');
        $response->assertSee('Perlu Tindakan Anda (To-Do)');
        $response->assertSee('Karyawan Menunggu Penilaian Anda!');
        $response->assertSee('Mulai Penilaian');
        $response->assertDontSee('Agenda Penilaian Foreman');

        // Aligned with Section Head without Foreman: Data Karyawan 12-col Excel layout, /penilaian/section redirect, Laporan Matrix
        $karyawanRes = $this->actingAs($foreman)->get('/karyawan');
        $karyawanRes->assertStatus(200);
        $karyawanRes->assertSeeInOrder(['>LINE CATEGORY</th>', '>POSITION</th>', '>DIVISION</th>', '>DEPARTMENT</th>', '>SECTION</th>', '>KJ</th>', '>GOLONGAN</th>', '>START WORK</th>'], false);

        $secRedirectRes = $this->actingAs($foreman)->get('/penilaian/section');
        $secRedirectRes->assertRedirect(route('karyawan.index'));

        $laporanRes = $this->actingAs($foreman)->get('/laporan');
        $laporanRes->assertStatus(200);
        $laporanRes->assertSee('Tabel Perbandingan: Target Penilaian vs Actual Foreman');
        $laporanRes->assertDontSee('targetVsActualChart');
    }

    /**
     * Test Foreman can view Riwayat Penilaian page with target vs actual bar chart.
     */
    public function test_foreman_can_access_riwayat_penilaian_with_bar_chart(): void
    {
        $foreman = User::where('email', 'saryanta@musashi.co.id')->first();

        $response = $this->actingAs($foreman)->get('/penilaian/riwayat');
        $response->assertStatus(200);

        $response->assertSee('Riwayat Penilaian');
        $response->assertSee('Total Sudah Dinilai');
        $response->assertSee('Belum Dinilai');
        $response->assertSee('Akumulasi Poin Skor');
        $response->assertSee('Rata-Rata Skor');
        $response->assertSee('Tabel Perbandingan: Target Penilaian vs Actual Foreman');
        $response->assertDontSee('Grafik Perbandingan:');
        $response->assertSee('Daftar Karyawan yang Baru Saja Selesai Dinilai');
        $response->assertSee('Hasil Kerja');
        $response->assertSee('Proses Kerja');
        $response->assertSee('Sikap Kerja');
        $response->assertSee('Man Management');
        $response->assertSee('Total Nilai');
        $response->assertSee('Nilai PK Foreman');

        // Verify Search and Filter Toolbar (Bagian -> Position -> KJ / Golongan)
        $response->assertSee('Cari NRP / Nama / Bagian');
        $response->assertSeeInOrder(['Semua Bagian', 'Semua Position', 'Semua KJ / Golongan'], false);
        $response->assertDontSee('Semua Jabatan');
        $response->assertDontSee('Semua Section');
        $response->assertSee('Terapkan');

        // Test filtering by query
        $resFilter = $this->actingAs($foreman)->get('/penilaian/riwayat?q=39');
        $resFilter->assertStatus(200);
        $resFilter->assertSee('39');

        // Test filtering by KJ
        $resKj = $this->actingAs($foreman)->get('/penilaian/riwayat?kj=KJ+2');
        $resKj->assertStatus(200);
    }

    /**
     * Test Foreman Data Karyawan page is view-only while Admin handles Add/Delete Employee.
     */
    public function test_foreman_can_view_and_add_subordinate_employee(): void
    {
        $foreman = User::where('email', 'saryanta@musashi.co.id')->first();
        $admin = User::where('email', 'admin@musashi.co.id')->first();

        // 1. Foreman visits Data Karyawan (read-only)
        $response = $this->actingAs($foreman)->get('/karyawan');
        $response->assertStatus(200);
        $response->assertSee('Data Karyawan Bawahan');
        $response->assertDontSee('Tambah Karyawan');
        $response->assertDontSee('Upload Sheet');

        // 2. Admin adds new subordinate under Foreman SARYANTA
        $newNrp = '9901';
        DB::table('karyawan')->where('nrp', $newNrp)->delete();

        $postResponse = $this->actingAs($admin)->post('/karyawan', [
            'nrp' => $newNrp,
            'nama_lengkap' => 'BAMBANG KUSUMA',
            'golongan' => 'GOLONGAN II',
            'sub_gol' => 'IIB',
            'jabatan' => 'OPERATOR MACHINING',
            'tgl_masuk' => '2022-04-01',
            'departemen' => 'PROD 2',
            'seksi' => 'FACTORY 1',
            'nrp_foreman' => $foreman->nrp,
        ]);

        $postResponse->assertRedirect();
        $postResponse->assertSessionHas('success');

        // Verify employee created in DB with automatic KJ13/KJ 2 mapping and visible to Foreman
        $employee = DB::table('karyawan')->where('nrp', $newNrp)->first();
        $this->assertNotNull($employee);
        $this->assertEquals('BAMBANG KUSUMA', $employee->nama_lengkap);
        $this->assertEquals($foreman->nrp, $employee->nrp_foreman);
        $this->assertEquals('KJ13', $employee->level_form);
        $this->assertEquals('KJ 2', $employee->kj);
    }

    public function test_user_can_delete_employee(): void
    {
        $admin = User::where('email', 'admin@musashi.co.id')->first();

        // 1. Create an employee via Admin
        $delNrp = '9998';
        \Illuminate\Support\Facades\DB::table('karyawan')->where('nrp', $delNrp)->delete();

        $this->actingAs($admin)->post('/karyawan', [
            'nrp' => $delNrp,
            'nama_lengkap' => 'KARYAWAN TESTING HAPUS',
            'golongan' => 'GOLONGAN II',
            'sub_gol' => 'IIB',
            'jabatan' => 'OPERATOR TESTING',
            'tgl_masuk' => '2022-04-01',
            'departemen' => 'PROD 2',
            'seksi' => 'FACTORY 1',
            'nrp_foreman' => '62',
        ]);

        $this->assertNotNull(\Illuminate\Support\Facades\DB::table('karyawan')->where('nrp', $delNrp)->first());

        // 2. Delete employee via Admin
        $delResponse = $this->actingAs($admin)->delete("/karyawan/{$delNrp}");
        $delResponse->assertRedirect();
        $delResponse->assertSessionHas('success');

        // 3. Assert employee is deleted
        $this->assertNull(\Illuminate\Support\Facades\DB::table('karyawan')->where('nrp', $delNrp)->first());
    }

    /**
     * Test Admin can download CSV template and import employees while Foreman can export.
     */
    public function test_foreman_can_download_template_and_import_subordinates(): void
    {
        $foreman = User::where('email', 'saryanta@musashi.co.id')->first();
        $admin = User::where('email', 'admin@musashi.co.id')->first();

        // 1. Template download
        $resTemplate = $this->actingAs($admin)->get('/karyawan/template');
        $resTemplate->assertStatus(200);
        $resTemplate->assertHeader('Content-Type', 'text/csv; charset=UTF-8');

        // 2. Upload CSV as Admin
        $csvContent = "nrp,nama_lengkap,golongan,sub_gol,jabatan,tgl_masuk,seksi,departemen,nrp_foreman\n"
                    . "9902,AGUS SULISTYO,GOLONGAN II,IIB,OPERATOR FINISHING,2021-06-15,FACTORY 1,PROD 2,62\n";
        
        $file = UploadedFile::fake()->createWithContent('subordinates.csv', $csvContent);

        DB::table('karyawan')->where('nrp', '9902')->delete();

        $resImport = $this->actingAs($admin)->post('/karyawan/import', [
            'sheet_file' => $file,
        ]);

        $resImport->assertRedirect();
        $resImport->assertSessionHas('success');

        $imported = DB::table('karyawan')->where('nrp', '9902')->first();
        $this->assertNotNull($imported);
        $this->assertEquals('AGUS SULISTYO', $imported->nama_lengkap);
        $this->assertEquals($foreman->nrp, $imported->nrp_foreman);
    }

    /**
     * Test Search / Launch Form Penilaian view (`/penilaian/cari`).
     */
    public function test_foreman_can_access_form_penilaian_search_and_launch(): void
    {
        $foreman = User::where('email', 'saryanta@musashi.co.id')->first();

        $response = $this->actingAs($foreman)->get('/penilaian/cari');
        $response->assertStatus(200);
        $response->assertSee('Form Penilaian Karyawan');
        $response->assertSee('Buka Form Penilaian');
        $response->assertSee('NUR EKA PURNAMA');

        // Submit NRP via /penilaian/buka
        $resBuka = $this->actingAs($foreman)->post('/penilaian/buka', [
            'nrp' => '39'
        ]);
        $doc = DB::table('dokumen')->where('nrp', '39')->first();
        $resBuka->assertRedirect(\App\Http\Controllers\PenilaianController::getFormUrlForUser($foreman, $doc->id));
    }

    /**
     * Test End-to-End Handshake Workflow:
     * Foreman rates & signs -> Document moves to Section Head inbox ->
     * Section Head rates & signs -> Document moves to Dept Head inbox ->
     * Dept Head rates final & signs -> Document reaches 'selesai'.
     */
    public function test_end_to_end_3_role_workflow_progression(): void
    {
        $foreman = User::where('email', 'saryanta@musashi.co.id')->first();
        $sectionHead = User::where('email', 'kristiyanto@musashi.co.id')->first();
        $deptHead = User::where('email', 'supardi@musashi.co.id')->first();

        $doc = DB::table('dokumen')->where('nrp', '39')->first();
        $docId = $doc->id;

        // Reset document to fresh state for clean testing
        DB::table('dokumen')->where('id', $docId)->update([
            'status' => 'menunggu_foreman',
            'total_nilai' => null,
            'grade_original' => null,
        ]);
        DB::table('tanda_tangan')->where('dokumen_id', $docId)->delete();

        // ----------------------------------------------------
        // STEP 1: FOREMAN EVALUATES & SIGNS
        // ----------------------------------------------------
        $foremanScores = [
            'HK_KUALITAS' => 4, 'HK_KUANTITAS' => 4, 'HK_DELIVERY' => 4, 'HK_IMPROVEMENT' => 4,
            'PK_PENGUASAAN' => 4, 'PK_ADMINISTRASI' => 4, 'PK_PROSEDUR' => 4,
            'SK_KOMUNIKASI' => 4, 'SK_INISIATIF' => 4, 'SK_DISIPLIN' => 4,
        ];

        $signRes = $this->actingAs($foreman)->post("/dokumen/{$docId}/ttd", [
            'level' => 'foreman',
            'password' => 'password123',
            'scores' => $foremanScores,
            'signature_base64' => 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==',
            'pilihan_potensi' => 1,
            'catatan_potensi' => 'Siap promosi',
            'kelebihan' => 'Rajin dan teliti',
            'kekurangan' => 'Tingkatkan bahasa asing',
        ]);

        $signRes->assertRedirect(\App\Http\Controllers\PenilaianController::getFormUrlForUser($foreman, $docId));

        // Status must now be 'menunggu_section_head'
        $docAfterForeman = DB::table('dokumen')->where('id', $docId)->first();
        $this->assertEquals('menunggu_section_head', $docAfterForeman->status);
        $this->assertEquals(4.00, $docAfterForeman->total_nilai);
        $this->assertEquals('A', $docAfterForeman->grade_original);

        // ----------------------------------------------------
        // STEP 2: SECTION HEAD SEES DASHBOARD & DATA PENILAIAN
        // ----------------------------------------------------
        $resSectionDash = $this->actingAs($sectionHead)->get('/dashboard');
        $resSectionDash->assertStatus(200);
        $resSectionDash->assertSee('Dashboard Section Head');
        $resSectionDash->assertSee('KRISTIYANTO');
        $resSectionDash->assertSee('Total Karyawan');
        $resSectionDash->assertSee('Target Penilaian');
        $resSectionDash->assertSee('Hasil Section Head');
        $resSectionDash->assertSee('Pelacakan Alur Status Dokumen Bawahan');

        // Section Head visits Data Penilaian
        $resSectionPenilaian = $this->actingAs($sectionHead)->get('/penilaian/section');
        $resSectionPenilaian->assertStatus(200);
        $resSectionPenilaian->assertSee('Data Penilaian');
        $resSectionPenilaian->assertSee('Meninjau hasil penilaian langsung dari Foreman');
        $resSectionPenilaian->assertSee('NUR EKA PURNAMA');
        $resSectionPenilaian->assertSee('Simpan Draft');
        $resSectionPenilaian->assertSee('Teruskan ke Dept. Head');

        $sectionScores = [
            'HK_KUALITAS' => 4, 'HK_KUANTITAS' => 4, 'HK_DELIVERY' => 4, 'HK_IMPROVEMENT' => 4,
            'PK_PENGUASAAN' => 4, 'PK_ADMINISTRASI' => 4, 'PK_PROSEDUR' => 4,
            'SK_KOMUNIKASI' => 4, 'SK_INISIATIF' => 4, 'SK_DISIPLIN' => 4,
        ];

        $signSecRes = $this->actingAs($sectionHead)->post("/dokumen/{$docId}/ttd", [
            'level' => 'section_head',
            'password' => 'password123',
            'scores' => $sectionScores,
            'signature_base64' => 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==',
        ]);

        $signSecRes->assertRedirect(\App\Http\Controllers\PenilaianController::getFormUrlForUser($sectionHead, $docId));

        // Status must now be 'menunggu_dept_head'
        $docAfterSection = DB::table('dokumen')->where('id', $docId)->first();
        $this->assertEquals('menunggu_dept_head', $docAfterSection->status);

        // ----------------------------------------------------
        // STEP 3: DEPT HEAD SEES INCOMING DOC & FINALIZES
        // ----------------------------------------------------
        $resDeptDash = $this->actingAs($deptHead)->get('/dashboard');
        $resDeptDash->assertStatus(200);
        $resDeptDash->assertSee('Dashboard');
        $resDeptDash->assertSee('Target Penilaian');

        // Check incoming evaluations in Data Penilaian (/penilaian/dept)
        $resDeptData = $this->actingAs($deptHead)->get('/penilaian/dept');
        $resDeptData->assertStatus(200);
        $resDeptData->assertSee('Data Penilaian');
        $resDeptData->assertSee('NUR EKA PURNAMA');

        $deptScores = [
            'HK_KUALITAS' => 4, 'HK_KUANTITAS' => 4, 'HK_DELIVERY' => 4, 'HK_IMPROVEMENT' => 4,
            'PK_PENGUASAAN' => 4, 'PK_ADMINISTRASI' => 4, 'PK_PROSEDUR' => 4,
            'SK_KOMUNIKASI' => 4, 'SK_INISIATIF' => 4, 'SK_DISIPLIN' => 4,
        ];

        $signDeptRes = $this->actingAs($deptHead)->post("/dokumen/{$docId}/ttd", [
            'level' => 'dept_head',
            'password' => 'password123',
            'scores' => $deptScores,
            'signature_base64' => 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==',
        ]);

        $signDeptRes->assertRedirect(\App\Http\Controllers\PenilaianController::getFormUrlForUser($deptHead, $docId));

        // Status must now be 'selesai'
        $docFinal = DB::table('dokumen')->where('id', $docId)->first();
        $this->assertEquals('selesai', $docFinal->status);
        $this->assertEquals(4.00, $docFinal->total_nilai);
        $this->assertEquals('A', $docFinal->grade_original);
        $this->assertNotNull($docFinal->hash_dokumen);
    }

    /**
     * Test Section Head Data Penilaian batch draft and submit to Dept Head.
     */
    public function test_section_head_can_batch_save_draft_and_submit_to_dept(): void
    {
        $sectionHead = User::where('email', 'kristiyanto@musashi.co.id')->first();
        $this->assertNotNull($sectionHead);

        // 1. Visit Data Penilaian page
        $res = $this->actingAs($sectionHead)->get('/penilaian/section');
        $res->assertStatus(200);
        $res->assertSee('Data Penilaian');
        $res->assertSee('Meninjau hasil penilaian langsung dari Foreman');
        $res->assertSee('Semua');
        $res->assertSee('Siap Dinilai');
        $res->assertSee('Diteruskan / Selesai');
        $res->assertSee('HASIL FOREMAN');
        $res->assertSee('NILAI AKHIR SECTION');

        // Pick a document to test batch draft & submit (advance to menunggu_section_head as Foreman has evaluated)
        $doc = DB::table('dokumen')->where('nrp', '39')->first();
        DB::table('dokumen')->where('id', $doc->id)->update(['status' => 'menunggu_section_head']);
        \Illuminate\Support\Facades\Cache::flush();

        // 2. Save Draft
        $draftRes = $this->actingAs($sectionHead)->post('/penilaian/section/simpan-draft', [
            'penilaian' => [
                $doc->id => [
                    'grade' => 'A',
                    'catatan' => 'Performa kerja sangat memuaskan di line machining.',
                ]
            ]
        ]);
        $draftRes->assertRedirect();
        $draftRes->assertSessionHas('success');

        // Verify aspect scores were saved
        $hasScores = DB::table('nilai')
            ->where('dokumen_id', $doc->id)
            ->where('level', 'section_head')
            ->count();
        $this->assertGreaterThan(0, $hasScores);

        // 3. Submit to Dept Head
        $submitRes = $this->actingAs($sectionHead)->post('/penilaian/section/submit-dept', [
            'penilaian' => [
                $doc->id => [
                    'grade' => 'A',
                    'catatan' => 'Performa kerja sangat memuaskan di line machining.',
                ]
            ]
        ]);
        $submitRes->assertRedirect();
        $submitRes->assertSessionHas('success');

        // Status must now be 'menunggu_dept_head'
        $docUpdated = DB::table('dokumen')->where('id', $doc->id)->first();
        $this->assertEquals('menunggu_dept_head', $docUpdated->status);
    }

    /**
     * Test Section Head Form Penilaian search by NRP and review Foreman evaluation.
     */
    public function test_section_head_form_penilaian_search_and_recap_display(): void
    {
        $sectionHead = User::where('email', 'kristiyanto@musashi.co.id')->first();

        // 1. Section Head visits Form Penilaian search page
        $res = $this->actingAs($sectionHead)->get('/penilaian/cari');
        $res->assertStatus(200);
        $res->assertSee('Form Penilaian Karyawan', false);
        $res->assertSee('Buka Form Penilaian');
        $res->assertSee('NUR EKA PURNAMA');

        // 2. Real-time API lookup
        $apiRes = $this->actingAs($sectionHead)->getJson('/api/karyawan-lookup?nrp=39');
        $apiRes->assertStatus(200);
        $apiRes->assertJson([
            'found' => true,
            'is_section_head' => true,
            'karyawan' => [
                'nrp' => '39',
                'nama_lengkap' => 'NUR EKA PURNAMA',
            ]
        ]);

        // 3. Section Head opens appraisal form
        $doc = DB::table('dokumen')->where('nrp', '39')->first();
        DB::table('dokumen')->where('id', $doc->id)->update(['status' => 'menunggu_section_head']);
        DB::table('tanda_tangan')->where('dokumen_id', $doc->id)->where('level', 'section_head')->delete();
        \Illuminate\Support\Facades\Cache::flush();
        $formRes = $this->actingAs($sectionHead)->followingRedirects()->get("/dokumen/{$doc->id}/penilaian");
        $formRes->assertStatus(200);
        $formRes->assertSee('Section Head');
    }



    /**
     * Test Status Filter Toolbar on /penilaian/cari for Admin.
     */
    public function test_penilaian_cari_has_status_filter_toolbar_for_admin(): void
    {
        $admin = User::where('role', 'admin')->first() ?? User::where('role', 'superadmin')->first();
        $resAdmin = $this->actingAs($admin)->get('/penilaian/cari');
        $resAdmin->assertStatus(200);
        $resAdmin->assertSee('adminStatusSelect');
        $resAdmin->assertSee('Belum Dinilai');
        $resAdmin->assertSee('Sudah Dinilai Foreman');
        $resAdmin->assertSee('Sudah Dinilai Section Head');
        $resAdmin->assertSee('Selesai Dept Head');
    }

    /**
     * Test Section Head sidebar has Riwayat Penilaian and sharp rounded-none cards.
     */
    public function test_section_head_sidebar_and_sharp_styling(): void
    {
        $sectionHead = User::where('role', 'section_head')->first();
        if (!$sectionHead) {
            $sectionHead = User::create([
                'nrp' => 'SEC999',
                'nama' => 'Section Head Test',
                'role' => 'section_head',
                'password' => bcrypt('password'),
                'seksi' => 'PROD 2',
                'departemen' => 'PRODUCTION',
                'is_active' => true,
            ]);
        }

        // 1. Check Section Head view on /penilaian/section
        $resSection = $this->actingAs($sectionHead)->get('/penilaian/section');
        $resSection->assertStatus(200);
        $resSection->assertSee('Riwayat Penilaian');
        $resSection->assertSee(route('penilaian.riwayat'));
        $content = $resSection->getContent();
        $this->assertStringContainsString('rounded-none', $content);

        // 2. Check Section Head Dashboard on /dashboard
        $resDash = $this->actingAs($sectionHead)->get('/dashboard');
        $resDash->assertStatus(200);
        $resDash->assertSee('Dashboard Section Head');
        $dashContent = $resDash->getContent();
        $this->assertStringContainsString('rounded-none', $dashContent);
    }

    /**
     * Test Department Head sidebar has Riwayat Penilaian.
     */
    public function test_dept_head_sidebar_has_riwayat_penilaian(): void
    {
        $deptHead = User::where('email', 'supardi@musashi.co.id')->firstOrFail();

        $resDept = $this->actingAs($deptHead)->get('/penilaian/dept');
        $resDept->assertStatus(200);
        $resDept->assertSee('Riwayat Penilaian');
        $resDept->assertSee(route('penilaian.riwayat'));
        // Target & Aturan Kuota must NOT be present in Department Head sidebar or page
        $resDept->assertDontSee('Target &amp; Aturan Kuota', false);
        $resDept->assertDontSee(route('penilaian.targetAturan'));

        // Check that dept head can open riwayat page
        $resRiwayat = $this->actingAs($deptHead)->get('/penilaian/riwayat');
        $resRiwayat->assertStatus(200);
        $resRiwayat->assertSee('Riwayat Penilaian');
    }

    /**
     * Test Department Head without Foreman (PROD 1: DH-11300 Agus Setiawan -> SH-11310 Dwi Cahyono, Rudi Hermawan, Siti Aminah)
     * has Red gradient card, title 'Dashboard' (not 'Dashboard Departemen Head'),
     * and informative 4-stage pipeline tracker from Section Head to Division Head.
     */
    public function test_dept_head_without_foreman_dashboard_has_red_card_and_workflow_pipeline(): void
    {
        DB::table('karyawan')->where('nrp', '91227')->delete();
        \Illuminate\Support\Facades\Cache::flush();
        $deptHeadProd1 = User::where('email', 'dept.prod1@musashi.co.id')->firstOrFail();

        $response = $this->actingAs($deptHeadProd1)->get('/dashboard');
        $response->assertStatus(200);

        // Title heading must be 'Dashboard' only, NOT 'Dashboard Departemen Head'
        $response->assertSee('<h1', false);
        $response->assertSee('Dashboard');
        $response->assertDontSee('Dashboard Departemen Head');
        $response->assertDontSee('>Dashboard Department Head</h1>', false);

        // Must have Musashi red gradient card styling like Section Head
        $response->assertSee('bg-gradient-to-r from-[#962226]');

        // Must display the unified 4-stage workflow pipeline tracker
        $response->assertSee('Pelacakan Alur Status Dokumen Bawahan');
        $response->assertSee('1. Penilaian Foreman');
        $response->assertSee('2. Penilaian Section Head');
        $response->assertSee('3. Approval Department Head');
        $response->assertSee('4. Approval Division Head');

        // Employee table
        $response->assertSee('Data Karyawan &amp; Penilaian', false);
        $response->assertSee('Agus Setiawan');
        $response->assertSee('Dwi Cahyono');
        $response->assertSee('Rudi Hermawan');
        $response->assertSee('Siti Aminah');

        // View data assertions (3 subs + self DH-11300 = 4 rows matching /penilaian/dept)
        $this->assertTrue($response->viewData('isDeptWithoutForeman'));
        $this->assertEquals(4, $response->viewData('deptTotalKaryawan'));
        $this->assertCount(4, $response->viewData('deptSubordinateRows'));
    }

    /**
     * Test /penilaian/dept for Department Head without Foreman has Red Card, title 'Data Penilaian',
     * informative assessment stat cards, and clean evaluation table.
     */
    public function test_dept_head_penilaian_page_has_red_card_and_informative_stats(): void
    {
        $deptHeadProd1 = User::where('email', 'dept.prod1@musashi.co.id')->firstOrFail();

        $response = $this->actingAs($deptHeadProd1)->get('/penilaian/dept');
        $response->assertStatus(200);

        // Header has Red Musashi gradient card and title 'Data Penilaian'
        $response->assertSee('bg-gradient-to-r from-[#962226]');
        $response->assertSee('Data Penilaian');

        // Informative summary stats
        $response->assertSee('Total Karyawan');
        $response->assertSee('Siap Dinilai (To-Do)');
        $response->assertSee('Proses Section Head');
        $response->assertSee('Diteruskan / Selesai');

        // Evaluation table
        $response->assertSee('NILAI AKHIR DEPT');
        $response->assertSee('CATATAN DEPT HEAD');
        $response->assertSee('07-006');
        $response->assertSee('Dwi Cahyono');
    }

    /**
     * Test Foreman & Section Head target tables strictly prioritize the highest available rank (S -> A -> B -> C -> K)
     * from Admin HR quotas, keep 0-quota ranks at 0, match subordinate headcount, and never lock Foreman/Section Head.
     */
    public function test_foreman_and_section_head_target_priority_and_quota_rules(): void
    {
        $foreman = User::where('email', 'saryanta@musashi.co.id')->firstOrFail();
        $sectionHead = User::where('email', 'kristiyanto@musashi.co.id')->firstOrFail();
        $deptHead = User::where('email', 'supardi@musashi.co.id')->firstOrFail();

        // 1. Quota lock rule: Foreman & Section Head are NEVER quota enforcers; Dept Head IS quota enforcer
        $this->assertFalse(\App\Http\Controllers\PenilaianController::isQuotaEnforcer($foreman));
        $this->assertFalse(\App\Http\Controllers\PenilaianController::isQuotaEnforcer($sectionHead));
        $this->assertTrue(\App\Http\Controllers\PenilaianController::isQuotaEnforcer($deptHead));

        // 2. Target allocation priority: for 1 subordinate in KJ 2 (PROD 2: kuota_s=0, kuota_a=1, kuota_b=16, kuota_c=47, kuota_k=0)
        // High rank A MUST be selected, NOT lowest rank C
        $matrix1 = \App\Http\Controllers\PenilaianController::buildDirectUnitTargetKjMatrix(
            ['KJ 2'],
            ['KJ 2' => 1],
            'PROD 2'
        );
        $this->assertEquals(0, $matrix1['KJ 2']['S']);
        $this->assertEquals(1, $matrix1['KJ 2']['A'], 'When 1 subordinate in KJ 2, highest available rank A must be prioritized');
        $this->assertEquals(0, $matrix1['KJ 2']['B']);
        $this->assertEquals(0, $matrix1['KJ 2']['C']);
        $this->assertEquals(0, $matrix1['KJ 2']['K']);
        $this->assertEquals(1, $matrix1['KJ 2']['total']);

        // 3. For 5 subordinates: fills A (1), then next highest rank B (4); S and K remain strictly 0
        $matrix5 = \App\Http\Controllers\PenilaianController::buildDirectUnitTargetKjMatrix(
            ['KJ 2'],
            ['KJ 2' => 5],
            'PROD 2'
        );
        $this->assertEquals(0, $matrix5['KJ 2']['S']);
        $this->assertEquals(1, $matrix5['KJ 2']['A']);
        $this->assertEquals(4, $matrix5['KJ 2']['B']);
        $this->assertEquals(0, $matrix5['KJ 2']['C']);
        $this->assertEquals(0, $matrix5['KJ 2']['K']);
        $this->assertEquals(5, $matrix5['KJ 2']['total']);

        // 4. For overflow scenario: if subordinates exceed total Admin quota, remaining goes to highest active rank
        $matrixOverflow = \App\Http\Controllers\PenilaianController::buildDirectUnitTargetKjMatrix(
            ['KJ 3'],
            ['KJ 3' => 5],
            'PROD 1'
        );
        $this->assertEquals(0, $matrixOverflow['KJ 3']['S']);
        $this->assertEquals(5, $matrixOverflow['KJ 3']['A']);
        $this->assertEquals(0, $matrixOverflow['KJ 3']['B']);
        $this->assertEquals(0, $matrixOverflow['KJ 3']['C']);
        $this->assertEquals(0, $matrixOverflow['KJ 3']['K']);
        $this->assertEquals(5, $matrixOverflow['KJ 3']['total']);

        // 5. Dashboard view verification: Foreman dashboard total target matches subordinate headcount
        $resForemanDash = $this->actingAs($foreman)->get('/dashboard');
        $resForemanDash->assertStatus(200);
        $foremanTargetSummary = $resForemanDash->viewData('foremanTargetKjSummary');
        $this->assertNotNull($foremanTargetSummary);
        $this->assertEquals(count($resForemanDash->viewData('foremanSubordinates')), $foremanTargetSummary['total']);
        $this->assertEquals($resForemanDash->viewData('totalTargetForeman'), $foremanTargetSummary['total']);
    }
}

<?php

namespace Tests\Feature;

use App\Contracts\SheetRepositoryInterface;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class Phase3PenilaianTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Seed database
        $this->artisan('migrate:fresh --seed');
    }

    /**
     * Test Foreman can view document of subordinate in the same department.
     */
    public function test_foreman_can_view_subordinate_document_in_same_department(): void
    {
        $foreman = User::where('email', 'saryanta@musashi.co.id')->first();
        $this->assertNotNull($foreman);

        $docId = DB::table('dokumen')->where('nrp', '39')->value('id');

        $response = $this->actingAs($foreman)->followingRedirects()->get("/dokumen/{$docId}/penilaian");
        $response->assertStatus(200);
        $response->assertSee('NUR EKA PURNAMA', false);
        $response->assertSee('PROD 2');
        $response->assertSee('07-001');
        $response->assertSee('Kualitas');
    }

    /**
     * Test Foreman CANNOT view document from another department.
     */
    public function test_foreman_forbidden_from_viewing_other_department_document(): void
    {
        $foreman = User::where('email', 'saryanta@musashi.co.id')->first();

        // Rudi Hermawan is in PROD 1
        $otherDocId = DB::table('dokumen')->where('nrp', '2023020')->value('id');
        $response = $this->actingAs($foreman)->get("/dokumen/{$otherDocId}/penilaian");
        $response->assertStatus(403);
    }

    /**
     * Test Foreman saves draft scores.
     */
    public function test_foreman_can_save_draft_scores(): void
    {
        $foreman = User::where('email', 'saryanta@musashi.co.id')->first();
        $docId = DB::table('dokumen')->where('nrp', '39')->value('id');

        $payload = [
            'level' => 'foreman',
            'scores' => [
                'HK_KUALITAS' => 4,
                'HK_KUANTITAS' => 4,
                'HK_DELIVERY' => 5,
                'HK_IMPROVEMENT' => 3,
            ],
            'pilihan_potensi' => 2,
            'kelebihan' => 'Sangat teliti dan cepat tanggap.',
            'kekurangan' => 'Perlu meningkatkan inisiatif inovasi.',
        ];

        $response = $this->actingAs($foreman)->post("/dokumen/{$docId}/simpan-draft", $payload);
        $response->assertRedirect();

        $savedNilai = DB::table('nilai')
            ->where('dokumen_id', $docId)
            ->where('level', 'foreman')
            ->where('aspek_kode', 'HK_KUALITAS')
            ->first();

        $this->assertNotNull($savedNilai);
        $this->assertEquals(4.00, $savedNilai->skor);

        $savedIndikator = DB::table('indikator')->where('dokumen_id', $docId)->first();
        $this->assertNotNull($savedIndikator);
        $this->assertEquals(2, $savedIndikator->pilihan_potensi);
        $this->assertEquals('Sangat teliti dan cepat tanggap.', $savedIndikator->kelebihan);
    }

    /**
     * Test Foreman digitally signs and advances status to menunggu_section_head.
     */
    public function test_foreman_digitally_signs_and_advances_workflow(): void
    {
        $foreman = User::where('email', 'saryanta@musashi.co.id')->first();
        $docId = DB::table('dokumen')->where('nrp', '39')->value('id');

        // All aspects for 07-001 (KJ 1-3)
        $scores = [
            'HK_KUALITAS' => 4,
            'HK_KUANTITAS' => 4,
            'HK_DELIVERY' => 4,
            'HK_IMPROVEMENT' => 4,
            'PK_PENGUASAAN' => 3,
            'PK_ADMINISTRASI' => 4,
            'PK_PROSEDUR' => 4,
            'SK_KOMUNIKASI' => 4,
            'SK_INISIATIF' => 3,
            'SK_DISIPLIN' => 5,
        ];

        // 1x1 transparent png data url
        $dummyCanvasBase64 = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==';

        $payload = [
            'level' => 'foreman',
            'scores' => $scores,
            'pilihan_potensi' => 2,
            'kelebihan' => 'Bekerja cepat dan patuh SOP.',
            'kekurangan' => 'Komunikasi perlu ditingkatkan.',
            'signature_base64' => $dummyCanvasBase64,
            'password' => 'password123',
        ];

        $response = $this->actingAs($foreman)->post("/dokumen/{$docId}/ttd", $payload);
        $response->assertRedirect(\App\Http\Controllers\PenilaianController::getFormUrlForUser($foreman, $docId));

        // Check document status
        $doc = DB::table('dokumen')->where('id', $docId)->first();
        $this->assertEquals('menunggu_section_head', $doc->status);

        // Check signature record
        $ttd = DB::table('tanda_tangan')->where('dokumen_id', $docId)->where('level', 'foreman')->first();
        $this->assertNotNull($ttd);
        $this->assertEquals($foreman->id, $ttd->user_id);
        $this->assertNotNull($ttd->hash_snapshot);

        // Check audit log
        $audit = DB::table('audit_log')->where('dokumen_id', $docId)->where('aksi', 'tanda_tangan_foreman')->first();
        $this->assertNotNull($audit);
    }

    /**
     * Test Complete Sequential 3-Tier Workflow (Foreman -> Section Head -> Dept Head -> Selesai).
     */
    public function test_complete_3_tier_workflow(): void
    {
        $foreman = User::where('email', 'saryanta@musashi.co.id')->first();
        $sectionHead = User::where('email', 'kristiyanto@musashi.co.id')->first();
        $deptHead = User::where('email', 'supardi@musashi.co.id')->first();
        $docId = DB::table('dokumen')->where('nrp', '39')->value('id');

        $scores = [
            'HK_KUALITAS' => 4,
            'HK_KUANTITAS' => 4,
            'HK_DELIVERY' => 4,
            'HK_IMPROVEMENT' => 4,
            'PK_PENGUASAAN' => 4,
            'PK_ADMINISTRASI' => 4,
            'PK_PROSEDUR' => 4,
            'SK_KOMUNIKASI' => 4,
            'SK_INISIATIF' => 4,
            'SK_DISIPLIN' => 4,
        ];
        $dummyCanvasBase64 = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==';

        // 1. Foreman Signs
        $this->actingAs($foreman)->post("/dokumen/{$docId}/ttd", [
            'level' => 'foreman',
            'scores' => $scores,
            'pilihan_potensi' => 1,
            'signature_base64' => $dummyCanvasBase64,
            'password' => 'password123',
        ]);
        $this->assertEquals('menunggu_section_head', DB::table('dokumen')->where('id', $docId)->value('status'));

        // 2. Section Head Signs
        $this->actingAs($sectionHead)->post("/dokumen/{$docId}/ttd", [
            'level' => 'section_head',
            'scores' => $scores,
            'pilihan_potensi' => 1,
            'signature_base64' => $dummyCanvasBase64,
            'password' => 'password123',
        ]);
        $this->assertEquals('menunggu_dept_head', DB::table('dokumen')->where('id', $docId)->value('status'));

        // 3. Dept Head Signs
        $this->actingAs($deptHead)->post("/dokumen/{$docId}/ttd", [
            'level' => 'dept_head',
            'scores' => $scores,
            'pilihan_potensi' => 1,
            'signature_base64' => $dummyCanvasBase64,
            'password' => 'password123',
        ]);
        $this->assertEquals('selesai', DB::table('dokumen')->where('id', $docId)->value('status'));

        // 3 Signatures recorded
        $ttdCount = DB::table('tanda_tangan')->where('dokumen_id', $docId)->count();
        $this->assertEquals(3, $ttdCount);
    }

    /**
     * Test Revision flow cancels signatures and resets status.
     */
    public function test_section_head_can_request_revision(): void
    {
        $foreman = User::where('email', 'saryanta@musashi.co.id')->first();
        $sectionHead = User::where('email', 'kristiyanto@musashi.co.id')->first();
        $docId = DB::table('dokumen')->where('nrp', '39')->value('id');

        $scores = [
            'HK_KUALITAS' => 4,
            'HK_KUANTITAS' => 4,
            'HK_DELIVERY' => 4,
            'HK_IMPROVEMENT' => 4,
            'PK_PENGUASAAN' => 4,
            'PK_ADMINISTRASI' => 4,
            'PK_PROSEDUR' => 4,
            'SK_KOMUNIKASI' => 4,
            'SK_INISIATIF' => 4,
            'SK_DISIPLIN' => 4,
        ];
        $dummyCanvasBase64 = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==';

        // Foreman signs first
        $this->actingAs($foreman)->post("/dokumen/{$docId}/ttd", [
            'level' => 'foreman',
            'scores' => $scores,
            'signature_base64' => $dummyCanvasBase64,
            'password' => 'password123',
        ]);

        // Section Head requests revision
        $response = $this->actingAs($sectionHead)->post("/dokumen/{$docId}/revisi", [
            'target_level' => 'foreman',
            'catatan_revisi' => 'Mohon cek kembali nilai aspek delivery karena ada keterlambatan di Q3.',
        ]);
        $response->assertRedirect(\App\Http\Controllers\PenilaianController::getFormUrlForUser($sectionHead, $docId));

        $doc = DB::table('dokumen')->where('id', $docId)->first();
        $this->assertEquals('revisi', $doc->status);

        // Signatures voided
        $ttdCount = DB::table('tanda_tangan')->where('dokumen_id', $docId)->count();
        $this->assertEquals(0, $ttdCount);
    }

    /**
     * Test direct navigation to appraisal form using employee NRP.
     */
    public function test_user_can_access_form_via_by_nrp_route(): void
    {
        $foreman = User::where('email', 'saryanta@musashi.co.id')->first();
        $docId = DB::table('dokumen')->where('nrp', '39')->value('id');

        $response = $this->actingAs($foreman)->get('/penilaian/nrp/39');
        $response->assertRedirect(\App\Http\Controllers\PenilaianController::getFormUrlForUser($foreman, $docId));

        // Follow redirect
        $follow = $this->actingAs($foreman)->followingRedirects()->get("/dokumen/{$docId}/penilaian");
        $follow->assertStatus(200);
        $follow->assertSee('Code PDV Mapin - 07-001');
        $follow->assertSee('KJ : 1 - 3');
        $follow->assertSee('PENILAIAN KARYAWAN');
        $follow->assertSee('NUR EKA PURNAMA', false);
    }

    /**
     * Test saving draft with potential indicators and notes.
     */
    public function test_foreman_can_save_draft_with_potential_notes(): void
    {
        $foreman = User::where('email', 'saryanta@musashi.co.id')->first();
        $docId = DB::table('dokumen')->where('nrp', '39')->value('id');

        $scores = [
            'HK_KUALITAS' => 5,
            'HK_KUANTITAS' => 4,
            'HK_DELIVERY' => 4,
            'HK_IMPROVEMENT' => 4,
            'PK_PENGUASAAN' => 5,
            'PK_ADMINISTRASI' => 4,
            'PK_PROSEDUR' => 4,
            'SK_KOMUNIKASI' => 4,
            'SK_INISIATIF' => 4,
            'SK_DISIPLIN' => 5,
        ];

        $response = $this->actingAs($foreman)->post("/dokumen/{$docId}/simpan-draft", [
            'level' => 'foreman',
            'scores' => $scores,
            'pilihan_potensi' => 1,
            'catatan_potensi' => 'Rekomendasi promosi ke Leader Machining',
            'kelebihan' => 'Sangat teliti dan disiplin dalam SOP 5S',
            'kekurangan' => 'Perlu pelatihan komunikasi bahasa Inggris',
        ]);

        $response->assertSessionHas('success');

        $indikator = DB::table('indikator')->where('dokumen_id', $docId)->first();
        $this->assertEquals(1, $indikator->pilihan_potensi);
        $this->assertEquals('Rekomendasi promosi ke Leader Machining', $indikator->catatan_potensi);
    }

    /**
     * Test real-time API lookup returns correct employee data and automatically detected KJ & Form code.
     */
    public function test_nrp_lookup_api_returns_employee_and_detected_kj_form(): void
    {
        $foreman = User::where('email', 'saryanta@musashi.co.id')->first();

        // Query by NRP 39 (NUR EKA PURNAMA, KJ 3)
        $response = $this->actingAs($foreman)->getJson('/api/karyawan-lookup?nrp=39');
        $response->assertStatus(200);
        $response->assertJson([
            'found' => true,
            'can_access' => true,
            'karyawan' => [
                'nrp' => '39',
                'nama_lengkap' => 'NUR EKA PURNAMA',
                'kj' => 'KJ 3',
                'kode_form' => '07-001',
                'departemen' => 'PROD 2',
            ]
        ]);

        // Query Leader NRP 110 (LAKSONO FITRIYANTO, KJ 4 -> Form 07-003)
        $resLeader = $this->actingAs($foreman)->getJson('/api/karyawan-lookup?nrp=110');
        $resLeader->assertStatus(200);
        $resLeader->assertJson([
            'found' => true,
            'karyawan' => [
                'nrp' => '110',
                'nama_lengkap' => 'LAKSONO FITRIYANTO',
                'kj' => 'KJ 4',
                'kode_form' => '07-003',
            ]
        ]);
    }

    /**
     * Test entering NRP via POST /penilaian/buka automatically opens or creates document for appraisal.
     */
    public function test_buka_by_nrp_auto_creates_document_for_employee(): void
    {
        $foreman = User::where('email', 'saryanta@musashi.co.id')->first();

        // 1. Submit existing employee NRP via POST /penilaian/buka
        $response = $this->actingAs($foreman)->post('/penilaian/buka', [
            'nrp' => '39'
        ]);
        $docId = DB::table('dokumen')->where('nrp', '39')->value('id');
        $response->assertRedirect(\App\Http\Controllers\PenilaianController::getFormUrlForUser($foreman, $docId));

        // 2. Submit employee without existing doc (delete doc first to test on-the-fly generation)
        DB::table('dokumen')->where('nrp', '24')->delete();
        $this->assertNull(DB::table('dokumen')->where('nrp', '24')->first());

        $responseNew = $this->actingAs($foreman)->post('/penilaian/buka', [
            'nrp' => '24' // HADI PRIYONO, KJ 4 (Form 07-002)
        ]);

        // Document should be automatically created with form 07-002 (KJ 4-5)
        $newDoc = DB::table('dokumen')->where('nrp', '24')->first();
        $this->assertNotNull($newDoc);
        $this->assertEquals('07-002', $newDoc->kode_form);
        $this->assertEquals('menunggu_foreman', $newDoc->status);
        $responseNew->assertRedirect(\App\Http\Controllers\PenilaianController::getFormUrlForUser($foreman, $newDoc->id));
    }

    /**
     * Test official PDF generation and download.
     */
    public function test_user_can_download_pdf_document(): void
    {
        $foreman = User::where('email', 'saryanta@musashi.co.id')->first();
        $docId = DB::table('dokumen')->where('nrp', '39')->value('id');

        $response = $this->actingAs($foreman)->get("/dokumen/{$docId}/pdf");
        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'application/pdf');

        $pdfContent = $response->getContent();
        // Count /Type /Page (excluding /Type /Pages)
        $pages = preg_match_all('/\/Type\s*\/Page[^s]/', $pdfContent);
        $this->assertEquals(1, $pages, "PDF must fit on exactly 1 page of A4!");
    }

    /**
     * Test public QR verification page.
     */
    public function test_public_qr_verification_endpoint(): void
    {
        $doc = DB::table('dokumen')->where('nrp', '39')->first();
        $this->assertNotNull($doc);

        $response = $this->get("/verifikasi/{$doc->kode_verifikasi}");
        $response->assertStatus(200);
        $response->assertSee('DOKUMEN ASLI');
        $response->assertSee('NUR EKA PURNAMA', false);
        $response->assertSee($doc->kode_verifikasi);
    }

    /**
     * Test invalid verification code shows friendly error.
     */
    public function test_invalid_qr_verification_shows_error(): void
    {
        $response = $this->get('/verifikasi/KODE-PALSU-123');
        $response->assertStatus(200);
        $response->assertSee('Dokumen Tidak Valid');
        $response->assertSee('KODE-PALSU-123');
    }
}


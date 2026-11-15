<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class IndikatorPotensiTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('migrate:fresh --seed');
    }

    /**
     * Test 1: Foreman can save Indikator Potensi (Option 1 with Saran Posisi) via AJAX.
     */
    public function test_foreman_can_save_indikator_potensi_via_ajax(): void
    {
        $foreman = User::where('email', 'saryanta@musashi.co.id')->firstOrFail();
        $doc = DB::table('dokumen')->where('nrp', '39')->firstOrFail();

        $response = $this->actingAs($foreman)->postJson("/dokumen/{$doc->id}/simpan-indikator", [
            'pilihan_potensi' => 1,
            'catatan_potensi' => 'Leader Machining Line 1',
            'kelebihan' => 'Sangat teliti, disiplin tinggi, dan output selalu mencapai target.',
            'kekurangan' => 'Perlu pelatihan Kaizen mandiri dan komunikasi antar shift.',
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'indikator' => [
                'pilihan_potensi' => 1,
                'catatan_potensi' => 'Leader Machining Line 1',
                'kelebihan' => 'Sangat teliti, disiplin tinggi, dan output selalu mencapai target.',
                'kekurangan' => 'Perlu pelatihan Kaizen mandiri dan komunikasi antar shift.',
            ]
        ]);

        // Verify persisted in SQLite database
        $ind = DB::table('indikator')->where('dokumen_id', $doc->id)->first();
        $this->assertNotNull($ind);
        $this->assertEquals(1, $ind->pilihan_potensi);
        $this->assertEquals('Leader Machining Line 1', $ind->catatan_potensi);
        $this->assertEquals('Sangat teliti, disiplin tinggi, dan output selalu mencapai target.', $ind->kelebihan);
        $this->assertEquals('Perlu pelatihan Kaizen mandiri dan komunikasi antar shift.', $ind->kekurangan);

        // Verify form renders with saved values
        $formRes = $this->actingAs($foreman)->followingRedirects()->get("/dokumen/{$doc->id}/penilaian");
        $formRes->assertStatus(200);
        $formRes->assertSee('Leader Machining Line 1');
        $formRes->assertSee('Sangat teliti, disiplin tinggi');
    }

    /**
     * Test 2: Section Head CANNOT save Indikator Potensi when employee document is still awaiting Foreman.
     * But CAN save when employee document reaches Section Head (e.g. 2023020 without foreman, or after foreman signs).
     */
    public function test_section_head_can_save_indikator_potensi_with_option_3_alasan(): void
    {
        $sectionHead = User::where('email', 'kristiyanto@musashi.co.id')->firstOrFail();
        $docForeman = DB::table('dokumen')->where('nrp', '39')->firstOrFail(); // status: menunggu_foreman

        // 1. Attempt while document is waiting for Foreman -> Must be 403 Forbidden
        $blockedResponse = $this->actingAs($sectionHead)->postJson("/dokumen/{$docForeman->id}/simpan-indikator", [
            'pilihan_potensi' => 3,
            'alasan_3' => 'Keahlian teknis spesifik mesin grinding sudah maksimal di posisinya saat ini.',
            'kelebihan' => 'Paham setting parameter mesin dengan akurat.',
            'kekurangan' => 'Perlu bimbingan untuk adaptasi di lini lain.',
        ]);
        $blockedResponse->assertStatus(403);
        $blockedResponse->assertJson(['success' => false]);

        // Form for Section Head should also render read-only warning
        $formBlockedRes = $this->actingAs($sectionHead)->followingRedirects()->get("/dokumen/{$docForeman->id}/penilaian");
        $formBlockedRes->assertStatus(200);
        $formBlockedRes->assertSee('Form Penilaian Terkunci: Masih Menunggu Penilaian Foreman');

        // 2. Advance document to menunggu_section_head (as if Foreman just finished)
        DB::table('dokumen')->where('id', $docForeman->id)->update(['status' => 'menunggu_section_head']);
        \Illuminate\Support\Facades\Cache::flush();

        // Now Section Head CAN save!
        $response = $this->actingAs($sectionHead)->postJson("/dokumen/{$docForeman->id}/simpan-indikator", [
            'pilihan_potensi' => 3,
            'alasan_3' => 'Keahlian teknis spesifik mesin grinding sudah maksimal di posisinya saat ini.',
            'kelebihan' => 'Paham setting parameter mesin dengan akurat.',
            'kekurangan' => 'Perlu bimbingan untuk adaptasi di lini lain.',
        ]);

        $response->assertStatus(200);
        $response->assertJson(['success' => true]);

        $ind = DB::table('indikator')->where('dokumen_id', $docForeman->id)->first();
        $this->assertNotNull($ind);
        $this->assertEquals(3, $ind->pilihan_potensi);
        $this->assertEquals('Keahlian teknis spesifik mesin grinding sudah maksimal di posisinya saat ini.', $ind->catatan_potensi);
        $this->assertEquals('Paham setting parameter mesin dengan akurat.', $ind->kelebihan);

        $formRes = $this->actingAs($sectionHead)->followingRedirects()->get("/dokumen/{$docForeman->id}/penilaian");
        $formRes->assertStatus(200);
        $formRes->assertSee('Keahlian teknis spesifik mesin grinding');
    }

    /**
     * Test 3: Department Head CANNOT save Indikator Potensi when document is still awaiting Foreman or Section Head.
     * But CAN save when document reaches Department Head (status: menunggu_dept_head).
     */
    public function test_dept_head_can_save_indikator_potensi_with_option_4_alasan(): void
    {
        $deptHead = User::where('email', 'supardi@musashi.co.id')->firstOrFail();
        $doc = DB::table('dokumen')->where('nrp', '66')->firstOrFail(); // initially: menunggu_foreman

        // 1. Attempt while waiting for Foreman -> 403 Forbidden
        $blockedResponse = $this->actingAs($deptHead)->postJson("/dokumen/{$doc->id}/simpan-indikator", [
            'pilihan_potensi' => 4,
            'alasan_4' => 'Perlu rotasi ke area Assembly untuk penyegaran kompetensi.',
            'kelebihan' => 'Fisik prima dan kehadiran konsisten.',
            'kekurangan' => 'Tingkat reject machining masih fluktuatif.',
        ]);
        $blockedResponse->assertStatus(403);
        $blockedResponse->assertJson(['success' => false]);

        // Form shows locked warning
        $formBlockedRes = $this->actingAs($deptHead)->followingRedirects()->get("/dokumen/{$doc->id}/penilaian");
        $formBlockedRes->assertStatus(200);
        $formBlockedRes->assertSee('Form Penilaian Terkunci: Masih Menunggu Penilaian Foreman');

        // 2. Advance document to menunggu_dept_head
        DB::table('dokumen')->where('id', $doc->id)->update(['status' => 'menunggu_dept_head']);
        \Illuminate\Support\Facades\Cache::flush();

        $response = $this->actingAs($deptHead)->postJson("/dokumen/{$doc->id}/simpan-indikator", [
            'pilihan_potensi' => 4,
            'alasan_4' => 'Perlu rotasi ke area Assembly untuk penyegaran kompetensi.',
            'kelebihan' => 'Fisik prima dan kehadiran konsisten.',
            'kekurangan' => 'Tingkat reject machining masih fluktuatif.',
        ]);

        $response->assertStatus(200);
        $response->assertJson(['success' => true]);

        $ind = DB::table('indikator')->where('dokumen_id', $doc->id)->first();
        $this->assertNotNull($ind);
        $this->assertEquals(4, $ind->pilihan_potensi);
        $this->assertEquals('Perlu rotasi ke area Assembly untuk penyegaran kompetensi.', $ind->catatan_potensi);
        $this->assertEquals('Fisik prima dan kehadiran konsisten.', $ind->kelebihan);
    }

    /**
     * Test 4: Division Head can save Indikator Potensi when document is at Division Head stage.
     */
    public function test_division_head_can_save_indikator_potensi(): void
    {
        $divHead = User::where('nrp', 'DIV-11000')->firstOrFail();
        $doc = DB::table('dokumen')->where('nrp', '2023020')->firstOrFail(); // Rudi Hermawan (PROD 1)

        // Set status to menunggu_div_head
        DB::table('dokumen')->where('id', $doc->id)->update(['status' => 'menunggu_div_head']);
        \Illuminate\Support\Facades\Cache::flush();

        // Division Head visits form -> canEditIndikator is true
        $formRes = $this->actingAs($divHead)->followingRedirects()->get("/dokumen/{$doc->id}/penilaian");
        $formRes->assertStatus(200);
        $formRes->assertSee('INDIKATOR EVALUASI POTENSI KARYAWAN', false);
        $formRes->assertSee('Simpan');

        // Division Head saves indicator
        $response = $this->actingAs($divHead)->postJson("/dokumen/{$doc->id}/simpan-indikator", [
            'pilihan_potensi' => 2,
            'kelebihan' => 'Leadership matang dan menguasai problem solving 5-Why.',
            'kekurangan' => 'Dapat diperluas dalam sharing knowledge ke junior.',
        ]);

        $response->assertStatus(200);
        $response->assertJson(['success' => true]);

        $ind = DB::table('indikator')->where('dokumen_id', $doc->id)->first();
        $this->assertNotNull($ind);
        $this->assertEquals(2, $ind->pilihan_potensi);
        $this->assertEquals('Leadership matang dan menguasai problem solving 5-Why.', $ind->kelebihan);
    }

    /**
     * Test 5: Save Draft and Digital Sign both persist and retain Indikator Potensi.
     */
    public function test_save_draft_and_sign_persist_indikator(): void
    {
        $foreman = User::where('email', 'saryanta@musashi.co.id')->firstOrFail();
        $doc = DB::table('dokumen')->where('nrp', '39')->firstOrFail();

        // 1. Save draft with scores and indicator
        $draftRes = $this->actingAs($foreman)->post("/dokumen/{$doc->id}/simpan-draft", [
            'level' => 'foreman',
            'scores' => [
                'HK_KUALITAS' => 4,
                'HK_KUANTITAS' => 4,
                'HK_DELIVERY' => 4,
                'HK_IMPROVEMENT' => 4,
            ],
            'pilihan_potensi' => 1,
            'catatan_potensi' => 'Foreman Candidate 2026',
            'kelebihan' => 'Kemampuan analisa tooling sangat baik.',
            'kekurangan' => 'Tingkatkan dokumentasi 5S.',
        ]);
        $draftRes->assertRedirect();

        // Verify draft saved in DB
        $indAfterDraft = DB::table('indikator')->where('dokumen_id', $doc->id)->first();
        $this->assertEquals('Foreman Candidate 2026', $indAfterDraft->catatan_potensi);

        // 2. Sign document
        $signRes = $this->actingAs($foreman)->post("/dokumen/{$doc->id}/ttd", [
            'level' => 'foreman',
            'password' => 'password123',
            'scores' => [
                'HK_KUALITAS' => 4, 'HK_KUANTITAS' => 4, 'HK_DELIVERY' => 4, 'HK_IMPROVEMENT' => 4,
                'PK_PENGUASAAN' => 4, 'PK_ADMINISTRASI' => 4, 'PK_PROSEDUR' => 4,
                'SK_KOMUNIKASI' => 4, 'SK_INISIATIF' => 4, 'SK_DISIPLIN' => 4,
            ],
            'signature_base64' => 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==',
            'pilihan_potensi' => 1,
            'catatan_potensi' => 'Foreman Candidate 2026',
            'kelebihan' => 'Kemampuan analisa tooling sangat baik.',
            'kekurangan' => 'Tingkatkan dokumentasi 5S.',
        ]);
        $signRes->assertRedirect();

        // Verify still safely stored after sign
        $indAfterSign = DB::table('indikator')->where('dokumen_id', $doc->id)->first();
        $this->assertEquals(1, $indAfterSign->pilihan_potensi);
        $this->assertEquals('Foreman Candidate 2026', $indAfterSign->catatan_potensi);
        $this->assertEquals('Kemampuan analisa tooling sangat baik.', $indAfterSign->kelebihan);
        $this->assertEquals('Tingkatkan dokumentasi 5S.', $indAfterSign->kekurangan);
    }

    /**
     * Test 6: Option 2 (Sesuai dengan posisi saat ini...) supports catatan pelatihan / pengembangan.
     */
    public function test_option_2_saves_and_renders_catatan_pelatihan(): void
    {
        $foreman = User::where('email', 'saryanta@musashi.co.id')->firstOrFail();
        $doc = DB::table('dokumen')->where('nrp', '39')->firstOrFail();

        $response = $this->actingAs($foreman)->postJson("/dokumen/{$doc->id}/simpan-indikator", [
            'pilihan_potensi' => 2,
            'catatan_2' => 'Pelatihan lanjutan CNC Turning & TPM Level 2',
            'kelebihan' => 'Konsisten dan dapat diandalkan.',
            'kekurangan' => 'Tingkatkan pemahaman preventive maintenance.',
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'indikator' => [
                'pilihan_potensi' => 2,
                'catatan_potensi' => 'Pelatihan lanjutan CNC Turning & TPM Level 2',
            ]
        ]);

        // Verify in DB
        $ind = DB::table('indikator')->where('dokumen_id', $doc->id)->first();
        $this->assertEquals(2, $ind->pilihan_potensi);
        $this->assertEquals('Pelatihan lanjutan CNC Turning & TPM Level 2', $ind->catatan_potensi);

        // Verify web form renders input with saved note
        $formRes = $this->actingAs($foreman)->followingRedirects()->get("/dokumen/{$doc->id}/penilaian");
        $formRes->assertStatus(200);
        $formRes->assertSee('Pelatihan lanjutan CNC Turning');
        $formRes->assertSee('Tuliskan catatan pelatihan / pengembangan...');

        // Verify PDF render
        $pdfRes = $this->actingAs($foreman)->get("/dokumen/{$doc->id}/pdf");
        $pdfRes->assertStatus(200);
        $pdfRes->assertHeader('content-type', 'application/pdf');
    }
}

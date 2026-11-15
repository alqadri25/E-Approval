<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\UploadedFile;

class SignatureCaptureAndDisplayTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('migrate:fresh --seed');
    }

    public function test_foreman_sign_via_canvas_and_display()
    {
        // 1. Get Foreman user (62 SARYANTA) and subordinate document (39)
        $foreman = User::where('nrp', '62')->first();
        $this->assertNotNull($foreman);

        $doc = DB::table('dokumen')->where('nrp', '39')->first();
        $this->assertNotNull($doc);

        // A realistic base64 PNG (1x1 red PNG for test)
        $canvasBase64 = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==';

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

        // 2. Submit signature as Foreman
        $response = $this->actingAs($foreman)->post(route('penilaian.sign', $doc->id), [
            'level' => 'foreman',
            'password' => 'password123',
            'signature_base64' => $canvasBase64,
            'scores' => $scores,
            'pilihan_potensi' => '1',
            'catatan_potensi' => 'Leader Assembly',
            'kelebihan' => 'Rajin',
            'kekurangan' => 'Perlu teliti',
        ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect('/penilaian/foreman/dokumen/' . $doc->id);

        // 3. Verify tanda_tangan table has signature path
        $ttd = DB::table('tanda_tangan')->where('dokumen_id', $doc->id)->where('level', 'foreman')->first();
        $this->assertNotNull($ttd);
        $this->assertNotNull($ttd->ttd_image);
        $this->assertTrue(Storage::disk('public')->exists($ttd->ttd_image));

        // 4. Verify form page renders the actual signature image, clean name (no NRP under signature, no date, no division)
        $pageResponse = $this->actingAs($foreman)->get('/penilaian/foreman/dokumen/' . $doc->id);
        $pageResponse->assertStatus(200);
        $pageResponse->assertSee('alt="TTD Foreman"', false);
        $pageResponse->assertSee('<span class="text-[10px] sm:text-[11px] text-slate-900 font-bold block leading-tight">SARYANTA</span>', false);
        $pageResponse->assertDontSee('<span class="text-[9px] sm:text-[10px] text-slate-600 font-mono font-semibold block leading-tight mt-0.5">' . $foreman->nrp . '</span>', false);

        // 5. Verify local storage fallback route serves the image file
        $storageRouteResponse = $this->get('/storage/' . $ttd->ttd_image);
        $storageRouteResponse->assertStatus(200);
    }

    public function test_section_head_sign_via_file_upload_and_display()
    {
        $sh = User::where('nrp', '112')->first();
        $this->assertNotNull($sh);

        $doc = DB::table('dokumen')->where('nrp', '39')->first();
        $this->assertNotNull($doc);

        // Ensure status is waiting for section head
        DB::table('dokumen')->where('id', $doc->id)->update(['status' => 'menunggu_section_head']);

        $scores = [
            'HK_KUALITAS' => 5,
            'HK_KUANTITAS' => 4,
            'HK_DELIVERY' => 5,
            'HK_IMPROVEMENT' => 4,
            'PK_PENGUASAAN' => 5,
            'PK_ADMINISTRASI' => 4,
            'PK_PROSEDUR' => 5,
            'SK_KOMUNIKASI' => 4,
            'SK_INISIATIF' => 4,
            'SK_DISIPLIN' => 5,
        ];

        // Create fake image file
        $fakeFile = UploadedFile::fake()->image('ttd_section_head.png', 200, 100);

        $response = $this->actingAs($sh)->post(route('penilaian.sign', $doc->id), [
            'level' => 'section_head',
            'password' => 'password123',
            'signature_file' => $fakeFile,
            'scores' => $scores,
            'pilihan_potensi' => '1',
            'catatan_potensi' => 'Rekomendasi promosi',
            'kelebihan' => 'Komunikatif',
            'kekurangan' => 'Disiplin waktu',
        ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect('/penilaian/sechead/dokumen/' . $doc->id);

        // Verify tanda_tangan table has signature path
        $ttd = DB::table('tanda_tangan')->where('dokumen_id', $doc->id)->where('level', 'section_head')->first();
        $this->assertNotNull($ttd);
        $this->assertNotNull($ttd->ttd_image);
        $this->assertTrue(Storage::disk('public')->exists($ttd->ttd_image));

        // Verify form page renders the Section Head signature image
        $pageResponse = $this->actingAs($sh)->get('/penilaian/sechead/dokumen/' . $doc->id);
        $pageResponse->assertStatus(200);
        $pageResponse->assertSee('alt="TTD Section Head"', false);
        $pageResponse->assertSee('<span class="text-[10px] sm:text-[11px] text-slate-900 font-bold block leading-tight">KRISTIYANTO</span>', false);
        $pageResponse->assertDontSee('<span class="text-[9px] sm:text-[10px] text-slate-600 font-mono font-semibold block leading-tight mt-0.5">' . $sh->nrp . '</span>', false);

        // Verify local storage route serves the uploaded file
        $storageRouteResponse = $this->get('/storage/' . $ttd->ttd_image);
        $storageRouteResponse->assertStatus(200);
    }

    public function test_direct_section_head_without_foreman_signs_and_displays()
    {
        // Form KJ 1-5 (07-003 Leader): Rudi Hermawan (2023020) evaluated directly by Section Head Dwi Cahyono at Kolom 2 (section_head)
        $sh = User::where('role', 'section_head')->where('nrp', 'SH-11310')->first();
        $this->assertNotNull($sh);

        $doc = DB::table('dokumen')->where('nrp', '2023020')->first();
        $this->assertNotNull($doc);
        $this->assertEquals('07-003', $doc->kode_form);

        $canvasBase64 = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==';

        $calc = app(\App\Services\AppraisalCalculationService::class);
        $struct = $calc->getFormStructure($doc->kode_form);
        $scores = [];
        foreach ($struct['groups'] as $g) {
            foreach ($g['aspects'] as $aspekCode => $aspekName) {
                $scores[$aspekCode] = 4;
            }
        }

        // Section Head signs Kolom 2 (level: section_head), Kolom 1 (foreman) remains empty
        $response = $this->actingAs($sh)->post(route('penilaian.sign', $doc->id), [
            'level' => 'section_head',
            'password' => 'password123',
            'signature_base64' => $canvasBase64,
            'scores' => $scores,
            'pilihan_potensi' => '1',
            'catatan_potensi' => 'Siap berkembang',
            'kelebihan' => 'Sangat bertanggung jawab',
            'kekurangan' => 'Tidak ada',
        ]);

        $response->assertSessionHasNoErrors();

        // Check Kolom 2 signature in DB, and no Kolom 1 (foreman) signature
        $this->assertNull(DB::table('tanda_tangan')->where('dokumen_id', $doc->id)->where('level', 'foreman')->first());
        $ttd = DB::table('tanda_tangan')->where('dokumen_id', $doc->id)->where('level', 'section_head')->first();
        $this->assertNotNull($ttd);
        $this->assertTrue(Storage::disk('public')->exists($ttd->ttd_image));

        // Status moved to menunggu_dept_head
        $updatedDoc = DB::table('dokumen')->where('id', $doc->id)->first();
        $this->assertEquals('menunggu_dept_head', $updatedDoc->status);

        // Check Form 07-003 renders Section Head Kolom 2 signature image & leaves Foreman Kolom 1 empty
        $pageResponse = $this->actingAs($sh)->get('/penilaian/sechead/dokumen/' . $doc->id);
        $pageResponse->assertStatus(200);
        $pageResponse->assertSee('Code PDV Mapin - 07-003');
        $pageResponse->assertSee('alt="TTD Section Head"', false);
        $pageResponse->assertSee('<span class="text-[10px] sm:text-[11px] text-slate-900 font-bold block leading-tight">Dwi Cahyono</span>', false);
        $pageResponse->assertDontSee('alt="TTD Foreman"', false);
    }

    public function test_dept_head_signs_and_pdf_renders_signatures()
    {
        $dh = User::where('nrp', '31')->first();
        $this->assertNotNull($dh);

        $doc = DB::table('dokumen')->where('nrp', '39')->first();
        $this->assertNotNull($doc);

        // Put doc into 'menunggu_dept_head'
        DB::table('dokumen')->where('id', $doc->id)->update(['status' => 'menunggu_dept_head']);

        // Grade B (avg 3.0) fits cleanly within PROD 2 KJ 3 target quota (B=20)
        $scores = [
            'HK_KUALITAS' => 3,
            'HK_KUANTITAS' => 3,
            'HK_DELIVERY' => 3,
            'HK_IMPROVEMENT' => 3,
            'PK_PENGUASAAN' => 3,
            'PK_ADMINISTRASI' => 3,
            'PK_PROSEDUR' => 3,
            'SK_KOMUNIKASI' => 3,
            'SK_INISIATIF' => 3,
            'SK_DISIPLIN' => 3,
        ];

        $canvasBase64 = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==';

        $response = $this->actingAs($dh)->post(route('penilaian.sign', $doc->id), [
            'level' => 'dept_head',
            'password' => 'password123',
            'signature_base64' => $canvasBase64,
            'scores' => $scores,
            'pilihan_potensi' => '1',
            'catatan_potensi' => 'Rekomendasi promosi disetujui',
            'kelebihan' => 'Sangat berprestasi',
            'kekurangan' => 'Pertahankan ritme kerja',
        ]);

        $response->assertSessionHasNoErrors();

        // Check Dept Head signature in DB
        $ttd = DB::table('tanda_tangan')->where('dokumen_id', $doc->id)->where('level', 'dept_head')->first();
        $this->assertNotNull($ttd);
        $this->assertNotNull($ttd->ttd_image);
        $this->assertTrue(Storage::disk('public')->exists($ttd->ttd_image));

        // Status moved to selesai
        $updatedDoc = DB::table('dokumen')->where('id', $doc->id)->first();
        $this->assertEquals('selesai', $updatedDoc->status);

        // Check Form renders Dept Head signature image
        $pageResponse = $this->actingAs($dh)->get('/penilaian/depthead/dokumen/' . $doc->id);
        $pageResponse->assertStatus(200);
        $pageResponse->assertSee('alt="TTD Dept Head"', false);
        $pageResponse->assertSee('<span class="text-[10px] sm:text-[11px] text-slate-900 font-bold block leading-tight">SUPARDI</span>', false);
        $pageResponse->assertDontSee('<span class="text-[9px] sm:text-[10px] text-slate-600 font-mono font-semibold block leading-tight mt-0.5">' . $dh->nrp . '</span>', false);

        // Check PDF generation executes successfully with embedded signature
        $pdfResponse = $this->actingAs($dh)->get(route('penilaian.downloadPdf', $doc->id));
        $pdfResponse->assertStatus(200);
        $this->assertEquals('application/pdf', $pdfResponse->headers->get('content-type'));
    }

    /**
     * Test PDF template does not contain placeholder strings (Tuliskan saran posisi promosi, etc.)
     */
    public function test_pdf_does_not_contain_placeholder_strings(): void
    {
        $superadmin = User::where('email', 'superadmin@musashi.co.id')->first();
        $this->assertNotNull($superadmin);

        $doc = DB::table('dokumen')->where('id', 3)->first() ?? DB::table('dokumen')->first();
        $this->assertNotNull($doc);

        $controller = app(\App\Http\Controllers\PenilaianController::class);
        $pdfData = $controller->getPdfInstanceForDoc($doc->id, $superadmin);
        $this->assertNotNull($pdfData);

        // Render the raw view file and assert placeholders are absent
        $rawView = file_get_contents(resource_path('views/pdf/penilaian.blade.php'));
        $this->assertStringNotContainsString('Tuliskan saran posisi promosi...', $rawView);
        $this->assertStringNotContainsString('Tuliskan catatan pelatihan / pengembangan...', $rawView);
        $this->assertStringNotContainsString('Tuliskan alasan...', $rawView);
    }
}

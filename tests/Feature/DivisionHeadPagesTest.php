<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DivisionHeadPagesTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        \Illuminate\Support\Facades\Cache::flush();
        $this->artisan('migrate:fresh --seed');
        \Illuminate\Support\Facades\Cache::flush();
    }

    public function test_division_head_sidebar_and_dashboard_display(): void
    {
        // User with Division Head role: 11 ATASAN (PRODUCTION A DIV / PROD 2)
        $divHead = User::where('email', 'atasan.div@musashi.co.id')->first();
        $this->assertNotNull($divHead);

        // 1. Check Dashboard
        $resDash = $this->actingAs($divHead)->get('/dashboard');
        $resDash->assertStatus(200);
        $resDash->assertSee('Selamat Datang ' . $divHead->nama);
        $resDash->assertDontSee('DIV. HEAD ENGINEERING');

        // Check sidebar menus match Department Head style without Penyesuaian Penilaian
        $resDash->assertSee('PENILAIAN KARYAWAN');
        $resDash->assertSee('Data Penilaian');
        $resDash->assertDontSee('Penyesuaian Penilaian');
        $resDash->assertDontSee('/penilaian/div/penyesuaian');
        $resDash->assertSee('Form Penilaian');
        $resDash->assertSee('Riwayat Penilaian');
        $resDash->assertSee('LAPORAN &amp; REKAP', false);
        $resDash->assertSee('Pusat Laporan &amp; Rekap', false);

        // /penilaian/div/penyesuaian endpoint is disabled for Division Head
        $this->actingAs($divHead)->get('/penilaian/div/penyesuaian')->assertStatus(403);

        // Check dashboard executive elements: 4 stat cards (Dinilai Section Head without Foreman, no Total Target / KJ matrix cards)
        $resDash->assertSee('Total Karyawan');
        $resDash->assertSee('Dinilai Section Head');
        $resDash->assertSee('Diteruskan ke Dept Head');
        $resDash->assertSee('Nilai SH');
        $resDash->assertSee('Dinilai Dept. Head');
        $resDash->assertSee('Disahkan Div. Head');
        $resDash->assertDontSee('Total Target');
        $resDash->assertDontSee('Target Penilaian (berdasarkan kategori &amp; KJ)', false);
        $resDash->assertDontSee('Hasil Penilaian Division Head');
        $resDash->assertDontSee('Dinilai Foreman');

        // Check 4-Stage workflow tracker
        $resDash->assertSee('Pelacakan Alur Status Dokumen (Section Head &rarr; Dept Head &rarr; Division Head)', false);
        $resDash->assertSee('1. Penilaian Section Head');
        $resDash->assertSee('2. Tinjauan Dept. Head');
        $resDash->assertSee('3. Persetujuan Div. Head');
        $resDash->assertSee('4. Selesai &amp; Terarsip', false);

        // No Donut charts
        $resDash->assertDontSee('id="targetDivChart"', false);
        $resDash->assertDontSee('id="hasilDeptDivChart"', false);
        $resDash->assertDontSee('id="hasilAkhirDivChart"', false);
        $resDash->assertDontSee('docking');

        // Check interactive table
        $resDash->assertSee('Data Karyawan &amp; Penilaian', false);
        $resDash->assertSee('id="divStatusFilter"', false);
        $resDash->assertSee('>Start Work</th>', false);
        $resDash->assertSee('⏳ Section Head');
        $resDash->assertSee('🔔 Department Head');
        $resDash->assertSee('⏱️ Division Head');
        $resDash->assertSee('✓ Selesai Final');

        // Check Penilaian Mandiri card replaces Target & Aturan
        $resDash->assertSee('Penilaian Mandiri Anda');
        $resDash->assertSee('Form 07-006-DIV');
        $resDash->assertDontSee('Target &amp; Aturan Kuota Divisi', false);

        // Check self is counted and displayed with badge Anda
        $resDash->assertSee('Anda');
        $resDash->assertSee('Nilai Form Mandiri');

        // 2. Check Data Penilaian route (Dedicated for Division Head - 5 uniform cards, no 'Gol ' prefix)
        $resDiv = $this->actingAs($divHead)->get('/penilaian/div');
        $resDiv->assertStatus(200);
        $resDiv->assertSee('Data Penilaian');
        $resDiv->assertSee('Total Karyawan');
        $resDiv->assertSee('Anggota Division Head');
        $resDiv->assertSee('karyawan terdaftar di Division Head');
        $resDiv->assertSee('SUPARDI');
        $resDiv->assertSee('Siap Disahkan (To-Do)');
        $resDiv->assertSee('Proses Section Head');
        $resDiv->assertSee('Proses Department Head');
        $resDiv->assertSee('Disahkan Final (Selesai)');
        $resDiv->assertSee('Proses Sect Head');
        $resDiv->assertSee('Siap Dinilai');
        $resDiv->assertSee('Form &rarr;', false);
        $resDiv->assertDontSee('Nilai Mandiri &rarr;', false);
        $resDiv->assertDontSee('&bull; Gol ', false);
        $resDiv->assertSeeInOrder([
            '>DEPARTMENT</label>',
            'Semua Department',
            '>SECTION</label>',
            'Semua Section',
        ], false);

        // Accessing /penilaian/dept redirects to /penilaian/div
        $resDept = $this->actingAs($divHead)->get('/penilaian/dept');
        $resDept->assertRedirect(route('penilaian.div'));

        // 3. Check Form Penilaian route (Cari page shows self card, 07-006 ONLY, Department + Section filters only, Department column, nowrap status)
        $resCari = $this->actingAs($divHead)->get('/penilaian/cari');
        $resCari->assertStatus(200);
        $resCari->assertSee('Penilaian Mandiri Saya');
        $resCari->assertSeeInOrder([
            '>POSITION</th>',
            '>DEPARTMENT</th>',
            '>SECTION</th>',
        ], false);
        $resCari->assertSee('Semua Department');
        $resCari->assertSee('Semua Section');
        $resCari->assertDontSee('Semua Position');
        $resCari->assertDontSee('Semua KJ / Golongan');
        $resCari->assertSee('inline-flex items-center whitespace-nowrap', false);

        $cariSubs = array_values($resCari->viewData('subordinates'));
        $this->assertNotEmpty($cariSubs);
        $this->assertTrue((bool)$cariSubs[0]['is_self'], 'Self assessment 07-006-DIV must be pinned at row #1');
        foreach ($cariSubs as $subRow) {
            $kf = strtoupper((string)($subRow['kode_form'] ?? ''));
            $this->assertTrue(str_starts_with($kf, '07-006'), "Division Head /penilaian/cari must only list 07-006 forms, got {$kf} for NRP {$subRow['nrp']}");
        }

        // 4. Check Open Self Evaluation Form directly
        $selfDoc = DB::table('dokumen')->where('nrp', $divHead->nrp)->first();
        $this->assertNotNull($selfDoc);
        $resForm = $this->actingAs($divHead)->get(route('penilaian.show.divhead', $selfDoc->id));
        $resForm->assertStatus(200);
        $resForm->assertSee('Code PDV Mapin - 07-006');
        $resForm->assertSee('Mandiri / 自分');
        $resForm->assertSee('list="accessibleNrpList"', false);
        $resForm->assertDontSee('<select @change="switchEmployee', false);
        $resForm->assertSee('<button type="button" @click="jumpToNrp()"', false);

        // 5. Check Riwayat Penilaian route (self row excluded before Div Head evaluates, Department col + Dept/Section filters only)
        DB::table('dokumen')->where('nrp', $divHead->nrp)->update(['status' => 'menunggu_div_head', 'total_nilai' => null, 'grade_original' => null]);
        \Illuminate\Support\Facades\Cache::flush();

        $resRiw = $this->actingAs($divHead)->get('/penilaian/riwayat');
        $resRiw->assertStatus(200);
        $resRiw->assertSee('>Department</th>', false);
        $resRiw->assertSee('Semua Department');
        $resRiw->assertSee('Semua Section');
        $resRiw->assertDontSee('Semua Position');
        $resRiw->assertDontSee('Semua KJ / Golongan');
        $riwSelfBefore = collect($resRiw->viewData('evaluatedList'))->firstWhere('nrp', $divHead->nrp);
        $this->assertNull($riwSelfBefore, 'Division Head self assessment must NOT appear in Riwayat before Division Head evaluates it');

        // 6. Check Laporan route (11 ATASAN manages 176 PROD 2 employees incl. Foremen; filters Department -> Section -> Grade only)
        $resLap = $this->actingAs($divHead)->get('/laporan');
        $resLap->assertStatus(200);
        $resLap->assertSeeInOrder([
            'Semua Department',
            'Semua Section',
            'Semua Grade',
        ], false);
        $resLap->assertDontSee('Semua Position');
        $resLap->assertDontSee('Semua KJ / Golongan');
        $resLap->assertSee('NILAI PK DIVISION HEAD');
        $resLap->assertDontSee('NILAI PK ORIGINAL');

        // Verify Division Head 5 workflow cards + target_actual_matrix_table (178 PRODUCTION A DIV Excel rows incl. self)
        $this->assertEquals(178, $resLap->viewData('totalKaryawan'));
        $this->assertEquals(0, $resLap->viewData('sudahDinilai'));
        $this->assertEquals(178, $resLap->viewData('belumDinilai'));
        $resLap->assertSee('Total Karyawan');
        $resLap->assertSee('Anggota Division Head');
        $resLap->assertSee('Sudah: 0');
        $resLap->assertSee('Belum: 178');
        $resLap->assertSee('Siap Disahkan (To-Do)');
        $resLap->assertSee('Menunggu approval');
        $resLap->assertDontSee('Menunggu approval DivHead');
        $resLap->assertSee('Proses Section Head');
        $resLap->assertSee('Proses Department Head');
        $resLap->assertSee('Disahkan Final (Selesai)');
        $resLap->assertSee('Tabel Perbandingan: Target Penilaian vs Nilai Dept Head &amp; Actual Division Head', false);
        $resLap->assertDontSee('id="targetVsActualChart"', false);
    }

    public function test_division_head_can_finalize_without_quota_lock_and_reopen_revision(): void
    {
        $divHead = User::where('email', 'atasan.div@musashi.co.id')->first();
        $this->assertNotNull($divHead);

        // Find a document for an operational (non-07-006) subordinate of division head
        $subordinateK = DB::table('karyawan')
            ->where('nrp_div_head', $divHead->nrp)
            ->where('nrp', '!=', $divHead->nrp)
            ->whereIn('kj', ['KJ 1', 'KJ 2', 'KJ 3'])
            ->first();
        $this->assertNotNull($subordinateK);

        $doc = DB::table('dokumen')->where('nrp', $subordinateK->nrp)->first();
        if (!$doc) {
            $docId = DB::table('dokumen')->insertGetId([
                'periode_id' => 1,
                'nrp' => $subordinateK->nrp,
                'kode_form' => '07-005',
                'status' => 'menunggu_div_head',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $doc = DB::table('dokumen')->where('id', $docId)->first();
        } else {
            DB::table('dokumen')->where('id', $doc->id)->update([
                'status' => 'menunggu_div_head',
                'docking_final' => null,
            ]);
            $doc = DB::table('dokumen')->where('id', $doc->id)->first();
        }
        $this->assertNotNull($doc);

        // 1. Visit form page as Division Head: quota calibration panel must NOT lock or show Penyesuaian Kuota
        $resForm = $this->actingAs($divHead)->get("/penilaian/divhead/dokumen/{$doc->id}");
        $resForm->assertStatus(200);
        $this->assertFalse((bool)$resForm->viewData('isDivHeadViewer'));
        $resForm->assertDontSee('Penyesuaian Kuota Divisi');
        $resForm->assertDontSee('Tabel Penyesuaian Kuota');

        // 2. Finalizing with Rank C succeeds directly even when Rank C quota target is 0 (quota enforcement stays at Dept Head level)
        DB::table('pengaturan')->updateOrInsert(
            ['key' => 'target_kuota_div_DIV-MFG'],
            ['value' => json_encode(['S' => 1, 'A' => 3, 'B' => 2, 'C' => 0, 'K' => 0, 'total' => 6]), 'updated_at' => now()]
        );
        $calcService = app(\App\Services\AppraisalCalculationService::class);
        $struct = $calcService->getFormStructure($doc->kode_form);

        $cScores = [];
        foreach ($struct['groups'] as $g) {
            foreach ($g['aspects'] as $aCode => $aName) {
                $cScores[$aCode] = 2;
            }
        }

        $postC = $this->actingAs($divHead)->post("/dokumen/{$doc->id}/ttd", [
            'level' => 'dept_head',
            'role_title' => 'Division Head (Penilai Final)',
            'password' => 'password123',
            'scores' => $cScores,
            'docking_final' => 'C',
            'signature_base64' => 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==',
        ]);
        $postC->assertSessionMissing('error');
        $postC->assertSessionHas('success');

        // 3. Also test signing with Rank A persists A
        DB::table('dokumen')->where('id', $doc->id)->update(['status' => 'menunggu_div_head', 'docking_final' => null]);
        $aScores = [];
        foreach ($struct['groups'] as $g) {
            foreach ($g['aspects'] as $aCode => $aName) {
                $aScores[$aCode] = 4;
            }
        }

        $postA = $this->actingAs($divHead)->post("/dokumen/{$doc->id}/ttd", [
            'level' => 'dept_head',
            'role_title' => 'Division Head (Penilai Final)',
            'password' => 'password123',
            'scores' => $aScores,
            'docking_final' => 'A',
            'signature_base64' => 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==',
        ]);

        $postA->assertSessionHas('success');
        $postA->assertRedirect("/penilaian/divhead/dokumen/{$doc->id}");

        // Check document status is now selesai and docking_final is A
        $updatedDoc = DB::table('dokumen')->where('id', $doc->id)->first();
        $this->assertEquals('selesai', $updatedDoc->status);
        $this->assertEquals('A', $updatedDoc->docking_final);

        // 4. Test bukaRevisi: immediately reopened for editing without delay
        $postRevisi = $this->actingAs($divHead)->post("/dokumen/{$doc->id}/buka-revisi");
        $postRevisi->assertSessionHas('success');
        $postRevisi->assertRedirect("/penilaian/divhead/dokumen/{$doc->id}");

        $reopenedDoc = DB::table('dokumen')->where('id', $doc->id)->first();
        $this->assertEquals('menunggu_dept_head', $reopenedDoc->status);

        $reopenedForm = $this->actingAs($divHead)->get("/penilaian/divhead/dokumen/{$doc->id}");
        $reopenedForm->assertStatus(200);
        $this->assertFalse($reopenedForm->viewData('canEditDeptHead'));
        $this->assertEquals('dept_head', $reopenedForm->viewData('waitingForLevel'));
        $this->assertFalse($reopenedForm->viewData('canEditIndikator'));
    }

    public function test_unevaluated_subordinates_do_not_consume_quota_in_form_view(): void
    {
        $divHead = User::where('email', 'atasan.div@musashi.co.id')->first();
        $this->assertNotNull($divHead);

        $selfDoc = DB::table('dokumen')->where('nrp', $divHead->nrp)->first();
        $this->assertNotNull($selfDoc);

        // Ensure all other subordinates in this division have no docking and no original grade
        $otherNrps = DB::table('karyawan')
            ->where('departemen', $divHead->departemen)
            ->where('nrp', '!=', $divHead->nrp)
            ->pluck('nrp')
            ->toArray();

        DB::table('dokumen')->whereIn('nrp', $otherNrps)->update([
            'grade_original' => null,
            'docking_final' => null,
        ]);

        $res = $this->actingAs($divHead)->get(route('penilaian.show.divhead', $selfDoc->id));
        $res->assertStatus(200);

        $divOtherUsage = $res->viewData('divOtherUsage');
        $this->assertEquals(0, $divOtherUsage['S']);
        $this->assertEquals(0, $divOtherUsage['A']);
        $this->assertEquals(0, $divOtherUsage['B']);
        $this->assertEquals(0, $divOtherUsage['C']);
        $this->assertEquals(0, $divOtherUsage['K']);
    }

    public function test_division_head_dashboard_and_form_penilaian_cards_sync(): void
    {
        $divHead = User::where('email', 'atasan.div@musashi.co.id')->first();
        $this->assertNotNull($divHead);

        $expectedCount = DB::table('karyawan')->where('nrp_div_head', $divHead->nrp)->count();

        // 1. Dashboard total_karyawan matches expected count
        $dashRes = $this->actingAs($divHead)->get('/dashboard');
        $dashRes->assertStatus(200);
        $divSubordinates = $dashRes->viewData('divSubordinates');
        $this->assertCount($expectedCount, $divSubordinates);
        $stats = $dashRes->viewData('stats');
        $this->assertEquals($expectedCount, $stats['total_karyawan']);

        // 2. Form Penilaian (cari) shows only 07-006 forms (DIV, DH, SH) in priority order
        $cariRes = $this->actingAs($divHead)->get('/penilaian/cari');
        $cariRes->assertStatus(200);
        $subs = $cariRes->viewData('subordinates');
        $expected07006Count = DB::table('dokumen')
            ->whereIn('nrp', DB::table('karyawan')->where('nrp_div_head', $divHead->nrp)->pluck('nrp'))
            ->where('kode_form', 'like', '07-006%')
            ->count();
        $this->assertCount($expected07006Count, $subs);

        // Subordinate that hasn't reached div head shows stage waiting button label
        $cariRes->assertSee('🔒 Buka Form (Menunggu');
    }
}


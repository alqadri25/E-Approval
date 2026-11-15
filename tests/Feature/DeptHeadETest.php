<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DeptHeadETest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('migrate:fresh --seed');
    }

    public function test_dept_head_e_counts_and_display(): void
    {
        $deptHeadE = User::where('email', 'supardi@musashi.co.id')->first();
        $this->assertNotNull($deptHeadE);

        // 1. Check Cari page (all Excel PROD 2 incl. Foreman/Leader/SH + FACTORY 3; excludes DEPT HEAD / DIV HEAD in subordinates table)
        $expectedProd2Subs = DB::table('karyawan')->get()
            ->map(fn ($r) => (array)$r)
            ->filter(fn ($k) => $deptHeadE->matchesDeptHeadScope($k))
            ->values();
        $expectedTotal = $expectedProd2Subs->count();
        $expectedExempt = $expectedProd2Subs->filter(fn ($k) => \App\Http\Controllers\PenilaianController::isQuotaExemptEmployee($k))->count();
        $expectedQuotaBound = $expectedTotal - $expectedExempt;
        $expectedTahap1 = $expectedProd2Subs->filter(fn ($k) => !empty($k['nrp_foreman']) && !\App\Http\Controllers\PenilaianController::isQuotaExemptEmployee($k))->count();
        $expectedTahap2 = $expectedTotal - $expectedTahap1;

        $resCari = $this->actingAs($deptHeadE)->get('/penilaian/cari');
        $resCari->assertStatus(200);
        $subsCari = $resCari->viewData('subordinates');
        $this->assertCount($expectedTotal + 1, $subsCari);
        $this->assertTrue(collect($subsCari)->contains('nrp', $deptHeadE->nrp));
        $this->assertFalse(collect($subsCari)->reject(fn ($r) => $r['nrp'] === $deptHeadE->nrp)->contains(fn ($r) => str_contains(strtoupper($r['jabatan'] ?? ''), 'DEPT HEAD') || str_contains(strtoupper($r['jabatan'] ?? ''), 'DEPARTMENT HEAD')));
        $resCari->assertSee('SUPARDI');
        $resCari->assertSee('(Anda)');
        $resCari->assertDontSee('Foreman: -');
        $resCari->assertSee('Penilaian Mandiri Saya');
        $resCari->assertSee('Buka Penilaian Mandiri Saya');
        $resCari->assertSee('Menunggu Penilaian Foreman');
        $resCari->assertSee('Menunggu Penilaian Section Head');

        // 2. Check Dashboard (174 subordinates + self 31 SUPARDI = 175 total; target stays 171 excluding 07-006)
        $resDash = $this->actingAs($deptHeadE)->get('/dashboard');
        $resDash->assertStatus(200);
        $this->assertEquals($expectedTotal + 1, $resDash->viewData('deptTotalKaryawan'));
        $deptTargets = $resDash->viewData('deptTargetCounts');
        // All 07-006 forms (Section Heads incl. KJ 2/4/7 & DEPT HEADs) excluded from target quota calculation
        $this->assertEquals($expectedQuotaBound, array_sum($deptTargets));
        $stageCounts = $resDash->viewData('deptStageCounts');
        $this->assertEquals($expectedTahap1, $stageCounts['tahap1_foreman']);
        $this->assertEquals($expectedTahap2, $stageCounts['tahap2_section']);
        $this->assertEquals(1, $stageCounts['tahap3_dept']);
        $resDash->assertSee('Dinilai Foreman');
        $resDash->assertSee('Diteruskan Ke Sect Head');
        $resDash->assertSee('Nilai Foreman');
        $resDash->assertSee('Dinilai Section Head');
        $resDash->assertSee('Diteruskan ke Dept Head');
        $resDash->assertSee('Nilai SH');
        $resDash->assertSee('Status');
        $resDash->assertSee('Tahap Dokumen');
        $resDash->assertSee('Skor / Grade');
        $resDash->assertDontSee('ALUR WORKFLOW');
        $resDash->assertSee('x-model="activeTab"', false);
        $resDash->assertSee('Data Karyawan &amp; Penilaian', false);
        // Pipeline 4 stages
        $resDash->assertSee('1. Penilaian Foreman');
        $resDash->assertSee('Proses pengerjaan Foreman');
        $resDash->assertSee('Alur For-SH-DH');
        $resDash->assertSee('2. Penilaian Section Head');
        $resDash->assertSee('Proses pengerjaan SH');
        $resDash->assertSee('Alur SH-DH');
        $resDash->assertSee('3. Approval Department Head');
        $resDash->assertSee('KJ 1-5, dan form Section Head &amp; Dept Head diteruskan Division Head.', false);
        $resDash->assertSee('Perlu tindakan Anda');
        $resDash->assertSee('Kerjakan');
        $resDash->assertSee('4. Approval Division Head');
        $resDash->assertSee('Selesai Final');
        $resDash->assertSee('Tuntas');
        // Two KJ matrix tables
        $resDash->assertSee('Target Penilaian');
        $resDash->assertSee('(berdasarkan kategori &amp; KJ)', false);
        $resDash->assertSee('Total Target: ' . $expectedQuotaBound);
        $resDash->assertSee('Hasil Penilaian Department Head');
        $resDash->assertSee('(nilai dari anda per KJ)');
        $resDash->assertSee('Total Dinilai: 0');
        // Priority Action Box removed
        $resDash->assertDontSee('Prioritas Tindakan Department Head');
        $deptTargetKjMatrix = $resDash->viewData('deptTargetKjMatrix');
        $deptTargetKjSummary = $resDash->viewData('deptTargetKjSummary');
        $this->assertSame(['KJ 2', 'KJ 3', 'KJ 4', 'KJ 5'], array_keys($deptTargetKjMatrix));
        $this->assertEquals($expectedQuotaBound, $deptTargetKjSummary['total']);
        $this->assertEquals(0, $deptTargetKjSummary['S']);
        $this->assertEquals(2, $deptTargetKjSummary['A']);
        $this->assertEquals(42, $deptTargetKjSummary['B']);
        $this->assertEquals(127, $deptTargetKjSummary['C']);
        $this->assertEquals(0, $deptTargetKjSummary['K']);
        $this->assertEquals(4, $deptTargetKjMatrix['KJ 5']['total']);

        // 3. Check Data Penilaian (includes SUPARDI 31 self-evaluation 07-006-DH + 174 subordinates = 175)
        $resDept = $this->actingAs($deptHeadE)->get('/penilaian/dept');
        $resDept->assertStatus(200);
        $rowsDept = $resDept->viewData('rows');
        $this->assertCount($expectedTotal + 1, $rowsDept);
        $resDept->assertSee('SUPARDI');
        $resDept->assertSee('ANDA');
        $resDept->assertSee('07-006');
        $resDept->assertSee('Proses Foreman');
        $resDept->assertSee('Proses Section Head');
        $resDept->assertSee('deptFilterSection');
        $resDept->assertSee('deptFilterPosition');
        $resDept->assertDontSee('>Mandiri<', false);
        $resDept->assertSee('Siap Dinilai');

        // 4. Check Riwayat (includes self 07-006-DH)
        $resRiw = $this->actingAs($deptHeadE)->get('/penilaian/riwayat');
        $resRiw->assertStatus(200);
        $this->assertEquals($expectedTotal + 1, $resRiw->viewData('totalSubordinates'));
        $this->assertEquals(0, $resRiw->viewData('totalDinilai'));
        $this->assertEquals($expectedTotal + 1, $resRiw->viewData('totalBelum'));

        // 5. Check Laporan (includes self 07-006-DH; quota targets still strictly $expectedQuotaBound = 171)
        $resLap = $this->actingAs($deptHeadE)->get('/laporan');
        $resLap->assertStatus(200);
        $this->assertEquals($expectedTotal + 1, $resLap->viewData('totalKaryawan'));
        $this->assertEquals(0, $resLap->viewData('sudahDinilai'));
        $this->assertEquals($expectedTotal + 1, $resLap->viewData('belumDinilai'));
        $resLap->assertSee('NILAI PK DEPARTMENT HEAD');
        $resLap->assertDontSee('<option value="div_head"', false);
        $resLap->assertSee('Section');
        $resLap->assertSee('Semua Section');
        $resLap->assertSee('Position');
        $resLap->assertSee('KJ / Golongan');

        // Verify Dept Head 5 workflow cards
        $resLap->assertSee('Total Karyawan');
        $resLap->assertSee('Anggota Department Head');
        $resLap->assertSee('Proses Foreman');
        $resLap->assertSee('Pengerjaan Form Foreman');
        $resLap->assertSee('Proses Section Head');
        $resLap->assertSee('Pengerjaan Form Section Head');
        $resLap->assertSee('Siap Disahkan (To-Do)');
        $resLap->assertDontSee('Menunggu review DeptHead');
        $resLap->assertSee('Approval Division Head');
        $resLap->assertSee('Tabel Perbandingan: Target Penilaian vs Actual Department Head');
    }
}

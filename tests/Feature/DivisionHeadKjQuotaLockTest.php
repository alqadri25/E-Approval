<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DivisionHeadKjQuotaLockTest extends TestCase
{
    protected User $divHead;

    protected function setUp(): void
    {
        parent::setUp();
        \Illuminate\Support\Facades\Cache::flush();
        $this->artisan('migrate:fresh --seed');
        \Illuminate\Support\Facades\Cache::flush();

        $this->divHead = User::where('nrp', '11')->firstOrFail();

        // Set KJ 3 (operational non-07-006) & KJ 9 (07-006-DH SUPARDI) targets with B = 0
        DB::table('pengaturan')->updateOrInsert(
            ['key' => 'target_kuota_kj_DIV-MFG_KJ3'],
            ['value' => json_encode(['S' => 1, 'A' => 1, 'B' => 0, 'C' => 0, 'K' => 0, 'total' => 2]), 'updated_at' => now()]
        );
        DB::table('pengaturan')->updateOrInsert(
            ['key' => 'target_kuota_kj_DIV-MFG_KJ9'],
            ['value' => json_encode(['S' => 1, 'A' => 1, 'B' => 0, 'C' => 0, 'K' => 0, 'total' => 2]), 'updated_at' => now()]
        );
    }

    private function getOperationalKj3Subordinate(): object
    {
        $sub = DB::table('karyawan')
            ->join('dokumen', 'dokumen.nrp', '=', 'karyawan.nrp')
            ->where('karyawan.nrp_div_head', '11')
            ->where('karyawan.kj', 'KJ 3')
            ->where('dokumen.kode_form', 'not like', '07-006%')
            ->select('karyawan.nrp', 'dokumen.id as doc_id')
            ->first();
        $this->assertNotNull($sub);
        return $sub;
    }

    public function test_division_head_is_never_locked_by_kj_quota_and_prioritizes_07_006_forms(): void
    {
        $opSub = $this->getOperationalKj3Subordinate();

        // Operational KJ 3 subordinate has grade B (0 in KJ 3) & SUPARDI (31, 07-006-DH) also ready
        DB::table('dokumen')->where('id', $opSub->doc_id)->update([
            'grade_original' => 'B',
            'docking_final' => null,
            'status' => 'menunggu_div_head',
        ]);
        DB::table('dokumen')->where('nrp', '31')->update([
            'grade_original' => 'B',
            'docking_final' => null,
            'status' => 'menunggu_div_head',
        ]);

        $response = $this->actingAs($this->divHead)->get('/penilaian/cari');
        $response->assertStatus(200);

        // Division Head must NEVER be locked or shown Penyesuaian Kuota prompts
        $response->assertDontSee('Kuota KJ 3 Penuh / Over');
        $response->assertDontSee('🔒 Buka Form (Kuota KJ 3 Penuh)');
        $response->assertDontSee('Lakukan Penyesuaian Kuota');
        $response->assertDontSee('/penilaian/div/penyesuaian');
        $response->assertSee('✍️ Tinjau &amp; Final Approval', false);

        // Verify 07-006-only filtering & priority sorting: self (07-006-DIV) first, then 07-006-DH, 07-006-SH; NO operational 07-001..07-005
        $subs = array_values($response->viewData('subordinates'));
        $this->assertNotEmpty($subs);
        $this->assertEquals('11', $subs[0]['nrp']);
        $this->assertTrue((bool)$subs[0]['is_self']);

        foreach ($subs as $subItem) {
            $this->assertTrue(str_starts_with(strtoupper((string)($subItem['kode_form'] ?? '')), '07-006'));
        }

        $ranks = array_map(function ($s) {
            if (!empty($s['is_self'])) {
                return 0;
            }
            $kf = strtoupper((string)($s['kode_form'] ?? ''));
            if ($kf === '07-006-DIV') return 1;
            if ($kf === '07-006-DH') return 2;
            if (str_starts_with($kf, '07-006')) return 3;
            return 4;
        }, $subs);
        $sortedRanks = $ranks;
        sort($sortedRanks);
        $this->assertSame($sortedRanks, $ranks, '07-006 forms (DIV/DH/SH) must appear in DIV -> DH -> SH priority order');
    }

    public function test_opening_form_for_over_quota_kj_opens_directly_without_redirecting_to_penyesuaian(): void
    {
        $opSub = $this->getOperationalKj3Subordinate();

        DB::table('dokumen')->where('id', $opSub->doc_id)->update([
            'grade_original' => 'B',
            'docking_final' => null,
            'status' => 'menunggu_div_head',
        ]);

        // Division Head opens operational KJ 3 form directly (no redirect to /penilaian/div/penyesuaian)
        $response = $this->actingAs($this->divHead)->post('/penilaian/buka', [
            'nrp' => $opSub->nrp
        ]);
        $response->assertStatus(302);
        $response->assertRedirect("/penilaian/divhead/dokumen/{$opSub->doc_id}");
        $response->assertSessionMissing('error');

        // Division Head Penyesuaian endpoint is completely disabled (403)
        $this->actingAs($this->divHead)->get('/penilaian/div/penyesuaian')->assertStatus(403);
        $this->actingAs($this->divHead)->post('/penilaian/div/penyesuaian/simpan', [
            'docking' => [$opSub->doc_id => 'A'],
        ])->assertStatus(403);
    }
}

<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DivisionHeadPenyesuaianComparisonTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        \Illuminate\Support\Facades\Cache::flush();
        $this->artisan('migrate:fresh --seed');
        \Illuminate\Support\Facades\Cache::flush();
    }

    public function test_division_head_penyesuaian_routes_are_disabled_and_removed_from_sidebar(): void
    {
        $divHead = User::where('email', 'atasan.div@musashi.co.id')->first()
            ?? User::where('email', 'div.e@musashi.co.id')->first()
            ?? User::where('email', 'div.prod@musashi.co.id')->first();
        $this->assertNotNull($divHead);

        // Sidebar omits Penyesuaian Penilaian link
        $dash = $this->actingAs($divHead)->get('/dashboard');
        $dash->assertStatus(200);
        $dash->assertDontSee('Penyesuaian Penilaian');
        $dash->assertDontSee('/penilaian/div/penyesuaian');

        // All Division Head penyesuaian endpoints return 403 Forbidden
        $this->actingAs($divHead)->get('/penilaian/div/penyesuaian')->assertStatus(403);
        $this->actingAs($divHead)->post(route('penilaian.div.penyesuaian.simpan'), [])->assertStatus(403);
        $this->actingAs($divHead)->post(route('penilaian.div.penyesuaian.autoAdjust'))->assertStatus(403);
        $this->actingAs($divHead)->post(route('penilaian.div.penyesuaian.reset'))->assertStatus(403);
    }

    public function test_dept_head_penyesuaian_comparison_table_and_controls_remain_active(): void
    {
        $deptHead = User::where('nrp', '31')->firstOrFail();

        $res = $this->actingAs($deptHead)->get('/penilaian/dept/penyesuaian');
        $res->assertStatus(200);

        $res->assertDontSee('Kalibrasi &amp; Docking Kuota', false);
        $res->assertDontSee('id="comparisonBarChart"', false);
        $res->assertSee('Tabel Perbandingan Target &amp; Nilai', false);
        $res->assertSee('Target (Kuota)');
        $res->assertSee('Target Kuota &amp; Realisasi per Jenjang KJ', false);
        $res->assertSee('Reset');
        $res->assertSee(route('penilaian.dept.penyesuaian.reset'));

        $kjSummary = $res->viewData('kjSummaryCards');
        $this->assertNotEmpty($kjSummary);
    }

    public function test_dept_head_reset_and_auto_adjust_work_exclusively_on_ready_subordinates(): void
    {
        $deptHead = User::where('nrp', '31')->firstOrFail();

        $subNrps = DB::table('karyawan')
            ->where('nrp_dept_head', $deptHead->nrp)
            ->where('nrp', '!=', $deptHead->nrp)
            ->pluck('nrp')
            ->toArray();
        $this->assertNotEmpty($subNrps);

        // 1. Reset clears docking_final for department subordinates
        DB::table('dokumen')->whereIn('nrp', $subNrps)->update(['docking_final' => 'A']);
        $postReset = $this->actingAs($deptHead)->post(route('penilaian.dept.penyesuaian.reset'));
        $postReset->assertRedirect(route('penilaian.dept.penyesuaian'));
        $postReset->assertSessionHas('success');
        foreach (DB::table('dokumen')->whereIn('nrp', $subNrps)->get() as $d) {
            $this->assertNull($d->docking_final);
        }

        // 2. Auto-adjust rejects when zero subordinates have scores/ready status
        DB::table('dokumen')->whereIn('nrp', $subNrps)->update([
            'status' => 'menunggu_section_head',
            'total_nilai' => null,
            'grade_original' => null,
            'docking_final' => null,
        ]);
        DB::table('nilai')->whereIn('dokumen_id', DB::table('dokumen')->whereIn('nrp', $subNrps)->pluck('id'))->delete();

        $resReject = $this->actingAs($deptHead)->post(route('penilaian.dept.penyesuaian.autoAdjust'));
        $resReject->assertRedirect(route('penilaian.dept.penyesuaian'));
        $resReject->assertSessionHas('error');

        // 3. Auto-adjust updates only ready operational subordinates and ignores unready & 07-006 rows
        $readyNrp = DB::table('dokumen')
            ->whereIn('nrp', $subNrps)
            ->where('kode_form', 'not like', '07-006%')
            ->value('nrp');
        $this->assertNotNull($readyNrp);
        DB::table('dokumen')->where('nrp', $readyNrp)->update([
            'status' => 'menunggu_dept_head',
            'total_nilai' => 4.5,
            'grade_original' => 'A',
            'docking_final' => null,
        ]);

        $resOk = $this->actingAs($deptHead)->post(route('penilaian.dept.penyesuaian.autoAdjust'));
        $resOk->assertRedirect(route('penilaian.dept.penyesuaian'));
        $resOk->assertSessionHas('success');

        $this->assertNotNull(DB::table('dokumen')->where('nrp', $readyNrp)->value('docking_final'));
        foreach (DB::table('dokumen')->whereIn('nrp', $subNrps)->where('nrp', '!=', $readyNrp)->get() as $od) {
            $this->assertNull($od->docking_final);
        }
    }
}

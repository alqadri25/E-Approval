<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SummaryAndHasilInputTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('migrate:fresh --seed');
    }

    /**
     * Test accessing Summary PK page
     */
    public function test_user_can_view_summary_pk_page(): void
    {
        $user = User::where('email', 'saryanta@musashi.co.id')->first();
        $this->assertNotNull($user);

        $response = $this->actingAs($user)->get('/summary-pk?departemen=PROD+2');
        $response->assertStatus(200);
        $response->assertSee('Summary PK');
        $response->assertSee('PK ORIGINAL (Based on form PK)');
        $response->assertSee('KONTROL KUOTA (Realisasi vs Target)');
        $response->assertSee('DEPT. : PROD 2 (Target Kuota)');
        $response->assertSee('NUR EKA PURNAMA', false);
    }

    /**
     * Test updating docking final grade
     */
    public function test_user_can_update_docking_final(): void
    {
        $user = User::where('email', 'kristiyanto@musashi.co.id')->first();
        $doc = DB::table('dokumen')->where('nrp', '39')->first();
        $this->assertNotNull($doc);

        $response = $this->actingAs($user)->postJson('/summary-pk/update-docking', [
            'dokumen_id' => $doc->id,
            'docking_final' => 'B',
        ]);

        $response->assertStatus(200);
        $response->assertJson(['success' => true]);

        $updated = DB::table('dokumen')->where('id', $doc->id)->first();
        $this->assertEquals('B', $updated->docking_final);
    }

    /**
     * Test updating target kuota for a department
     */
    public function test_user_can_update_target_kuota(): void
    {
        $user = User::where('email', 'admin@musashi.co.id')->first();
        $this->assertNotNull($user);

        $response = $this->actingAs($user)->postJson('/summary-pk/update-kuota', [
            'departemen' => 'PROD 2',
            'kuota' => [
                'KJ 2' => ['s' => 0, 'a' => 1, 'b' => 1, 'c' => 0, 'k' => 0],
                'KJ 3' => ['s' => 0, 'a' => 1, 'b' => 1, 'c' => 0, 'k' => 0],
                'KJ 4' => ['s' => 0, 'a' => 1, 'b' => 2, 'c' => 1, 'k' => 0],
                'KJ 5' => ['s' => 0, 'a' => 0, 'b' => 0, 'c' => 0, 'k' => 0],
            ],
        ]);

        $response->assertStatus(200);
        $response->assertJson(['success' => true]);

        $rowKj4 = DB::table('kuota_departemen')
            ->where('departemen', 'PROD 2')
            ->where('kj', 'KJ 4')
            ->first();

        $this->assertNotNull($rowKj4);
        $this->assertEquals(1, $rowKj4->kuota_a);
        $this->assertEquals(2, $rowKj4->kuota_b);
        $this->assertEquals(1, $rowKj4->kuota_c);
        $this->assertEquals(4, $rowKj4->total_karyawan);
    }

    /**
     * Test accessing Hasil Input 31-column database page and tabs
     */
    public function test_user_can_view_hasil_input_and_tabs(): void
    {
        $user = User::where('email', 'saryanta@musashi.co.id')->first();

        // 1. All tab
        $response = $this->actingAs($user)->get('/hasil-input?departemen=PROD+2&tab=semua');
        $response->assertStatus(200);
        $response->assertSee('Database Hasil Input PK 2025');
        $response->assertSee('HASIL KERJA');
        $response->assertSee('PROSES KERJA');
        $response->assertSee('SIKAP KERJA');
        $response->assertSee('MAN MGT');

        // 2. KJ 1-3 tab
        $responseKj13 = $this->actingAs($user)->get('/hasil-input?departemen=PROD+2&tab=kj13');
        $responseKj13->assertStatus(200);
        $responseKj13->assertSee('NUR EKA PURNAMA', false);

        // 3. Gol 4-up tab (Section Head self-eval uses 07-006-SH in PROD 1)
        $admin = User::where('email', 'admin@musashi.co.id')->first();
        $responseGol4 = $this->actingAs($admin)->get('/hasil-input?departemen=PROD+1&tab=gol4up');
        $responseGol4->assertStatus(200);
        $responseGol4->assertSee('Dwi Cahyono');
    }

    /**
     * Test CSV Exports for Summary PK and Hasil Input
     */
    public function test_csv_exports(): void
    {
        $user = User::where('email', 'admin@musashi.co.id')->first();

        $resSummary = $this->actingAs($user)->get('/summary-pk/export?departemen=PROD+2');
        $resSummary->assertStatus(200);
        $this->assertEquals('text/csv; charset=utf-8', $resSummary->headers->get('Content-Type'));

        $resHasil = $this->actingAs($user)->get('/hasil-input/export?departemen=PROD+2');
        $resHasil->assertStatus(200);
        $this->assertEquals('text/csv; charset=utf-8', $resHasil->headers->get('Content-Type'));
    }
}

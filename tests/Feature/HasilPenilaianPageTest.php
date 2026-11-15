<?php

namespace Tests\Feature;

use App\Models\User;
use Tests\TestCase;

class HasilPenilaianPageTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('migrate:fresh --seed');
    }

    public function test_user_can_access_hasil_penilaian_page()
    {
        $user = User::where('email', 'admin@musashi.co.id')->first();
        $this->assertNotNull($user);

        $response = $this->actingAs($user)->get('/hasil-penilaian');
        $response->assertStatus(200);
        $response->assertSee('Hasil Penilaian');
        $response->assertSee('NAMA LENGKAP');
        $response->assertSee('DEPARTMENT');
        $response->assertSee('POSITION');
        $response->assertSee('KJ / Golongan');
        $response->assertSee('Hasil Kerja');
        $response->assertSee('Proses Kerja');
        $response->assertSee('Sikap Kerja');
        $response->assertSee('Man Management');
        $response->assertSee('Total Nilai');
        $response->assertSee('NILAI PK ORIGINAL');
        $response->assertSee('CATATAN ATASAN');
    }

    public function test_hasil_penilaian_filter_by_search_and_dept()
    {
        $user = User::where('email', 'admin@musashi.co.id')->first();

        // Search by query
        $res = $this->actingAs($user)->get('/hasil-penilaian?q=SUPARDI');
        $res->assertStatus(200);
        $res->assertSee('SUPARDI', false);

        // Filter by departemen
        $resDept = $this->actingAs($user)->get('/hasil-penilaian?departemen=PROD+2');
        $resDept->assertStatus(200);
        $resDept->assertSee('PROD 2');

        // Filter by KJ
        $resKj = $this->actingAs($user)->get('/hasil-penilaian?departemen=PROD+2&kj=KJ+2');
        $resKj->assertStatus(200);
        $resKj->assertSee('KJ 2');

        // Filter by Semua Departemen (empty string)
        $resAllDepts = $this->actingAs($user)->get('/hasil-penilaian?departemen=&jabatan=&kj=');
        $resAllDepts->assertStatus(200);
        $resAllDepts->assertSee('PROD 1');
        $resAllDepts->assertSee('PROD 2');
    }

    public function test_hasil_penilaian_export_csv()
    {
        $user = User::where('email', 'admin@musashi.co.id')->first();

        // Export specific dept
        $response = $this->actingAs($user)->get('/hasil-penilaian/export?departemen=PROD+2');
        $response->assertStatus(200);
        $this->assertEquals('text/csv; charset=utf-8', $response->headers->get('Content-Type'));

        // Export all departments (Semua Departemen)
        $responseAll = $this->actingAs($user)->get('/hasil-penilaian/export?departemen=&jabatan=&kj=');
        $responseAll->assertStatus(200);
        $this->assertEquals('text/csv; charset=utf-8', $responseAll->headers->get('Content-Type'));
    }
}

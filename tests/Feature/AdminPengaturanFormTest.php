<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AdminPengaturanFormTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('migrate:fresh --seed');
    }

    public function test_admin_can_access_pengaturan_form_page()
    {
        $admin = User::where('role', 'admin')->first() ?? User::where('role', 'superadmin')->first();
        $this->assertNotNull($admin);

        $response = $this->actingAs($admin)->get(route('admin.pengaturanForm'));

        $response->assertStatus(200);
        $response->assertSee('Kelola Formulir &amp; Periode Penilaian (PK)', false);
        $response->assertSee('07-001');
        $response->assertSee('07-004');
        $response->assertSee('07-006-DH');
        $response->assertSee('LIVE PREVIEW');
        $response->assertSee('rounded-none');
    }

    public function test_admin_can_save_pengaturan_form_settings()
    {
        $admin = User::where('role', 'admin')->first() ?? User::where('role', 'superadmin')->first();
        $this->assertNotNull($admin);

        $response = $this->actingAs($admin)->post(route('admin.simpanPengaturanForm'), [
            'tahun_penilaian' => 2026,
            'periode_penilaian' => 'Semester I - 2026',
            'judul_form_pk' => 'PENILAIAN KARYA PT MUSASHI',
            'nama_perusahaan' => 'PT MUSASHI AUTO PARTS INDONESIA',
            'catatan_petunjuk_penilai' => 'Pilih kalimat skor kriteria rubrik yang sesuai.',
            'status_periode' => 'aktif',
            'label_kerahasiaan' => 'RAHASIA RESMI',
            'tgl_mulai' => '2026-01-01',
            'tgl_selesai' => '2026-06-30',
        ]);

        $response->assertSessionHas('success');

        $this->assertEquals('2026', DB::table('pengaturan')->where('key', 'tahun_penilaian')->value('value'));
        $this->assertEquals('Semester I - 2026', DB::table('pengaturan')->where('key', 'periode_penilaian')->value('value'));
        $this->assertEquals('PENILAIAN KARYA PT MUSASHI', DB::table('pengaturan')->where('key', 'judul_form_pk')->value('value'));
    }

    public function test_foreman_forbidden_from_pengaturan_form()
    {
        $foreman = User::where('role', 'foreman')->first();
        $this->assertNotNull($foreman);

        $response = $this->actingAs($foreman)->get(route('admin.pengaturanForm'));
        $response->assertStatus(403);
    }
}

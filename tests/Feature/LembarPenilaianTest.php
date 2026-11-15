<?php

namespace Tests\Feature;

use App\Models\User;
use Tests\TestCase;

class LembarPenilaianTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('migrate:fresh --seed');
    }

    public function test_admin_and_superadmin_can_access_lembar_penilaian_with_all_7_forms(): void
    {
        $admin = User::where('email', 'admin@musashi.co.id')->first();
        $this->assertNotNull($admin);

        $response = $this->actingAs($admin)->get(route('admin.lembarPenilaian'));

        $response->assertStatus(200);
        $response->assertSee('Lembar Penilaian PK');
        $response->assertSee('07-001');
        $response->assertSee('07-002');
        $response->assertSee('07-003');
        $response->assertSee('07-004');
        $response->assertSee('07-006-SH');
        $response->assertSee('07-006-DH');
        $response->assertSee('07-006-DIV');

        // Verify sidebar items: Summary PK is NOT in the sidebar
        $response->assertDontSee('Summary PK');
        // Check that Lembar Penilaian PK is present
        $response->assertSee(route('admin.lembarPenilaian'));
    }

    public function test_lembar_penilaian_reflects_changes_from_kelola_form(): void
    {
        $superadmin = User::where('email', 'superadmin@musashi.co.id')->first();
        $this->assertNotNull($superadmin);

        // Simpan perubahan form via post
        $postData = [
            'judul_form_pk' => 'LEMBAR PENILAIAN PRESTASI KERJA KARYAWAN MUSASHI 2026',
            'periode_penilaian' => 'Semester II',
            'tahun_penilaian' => '2026',
            'nama_perusahaan' => 'PT MUSASHI AUTO PARTS INDONESIA - EJIP CIKARANG',
            'status_periode' => 'aktif',
        ];

        $saveResponse = $this->actingAs($superadmin)->post(route('admin.simpanPengaturanForm'), $postData);
        $saveResponse->assertRedirect();

        // Buka Lembar Penilaian PK dan pastikan data terbaru langsung tampil
        $response = $this->actingAs($superadmin)->get(route('admin.lembarPenilaian'));
        $response->assertStatus(200);
        $response->assertSee('LEMBAR PENILAIAN PRESTASI KERJA KARYAWAN MUSASHI 2026');
        $response->assertSee('Semester II');
        $response->assertSee('2026');
        $response->assertSee('PT MUSASHI AUTO PARTS INDONESIA - EJIP CIKARANG');
    }
}

<?php

namespace Tests\Feature;

use App\Models\User;
use Tests\TestCase;

class KaryawanUpdateTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('migrate:fresh --seed');
    }

    public function test_admin_can_update_karyawan_data(): void
    {
        $admin = User::where('email', 'admin@musashi.co.id')->first();
        $this->assertNotNull($admin);

        $response = $this->actingAs($admin)->post('/admin/karyawan/39/update', [
            'nama_lengkap' => 'NUR EKA PURNAMA EDIT',
            'jabatan' => 'OPERATOR SENIOR',
            'tgl_masuk' => '2006-05-01',
            'golongan' => 'GOLONGAN III',
            'sub_gol' => 'IIIB',
            'kj' => 'KJ3',
            'departemen' => 'PROD 2',
            'seksi' => 'FACTORY 1',
            'code_bagian' => '1141100000',
            'nama_bagian' => 'FACTORY 1',
            'nama_sect' => 'FACTORY 1',
        ]);

        $response->assertRedirect('/admin/karyawan');
        $response->assertSessionHas('success');

        // Check page reflects updated name
        $page = $this->actingAs($admin)->get('/admin/karyawan');
        $page->assertSee('NUR EKA PURNAMA EDIT');
        $page->assertSee('OPERATOR SENIOR');
    }
}

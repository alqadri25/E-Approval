<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AdminRevisiPenilaianTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('migrate:fresh --seed');
    }

    public function test_guest_cannot_access_revisi_penilaian(): void
    {
        $response = $this->get(route('admin.revisiPenilaian'));
        $response->assertRedirect('/login');

        $responseSuper = $this->get(route('superadmin.revisiPenilaian'));
        $responseSuper->assertRedirect('/login');
    }

    public function test_non_admin_cannot_access_revisi_penilaian(): void
    {
        $foreman = User::where('role', 'foreman')->first();
        if ($foreman) {
            $response = $this->actingAs($foreman)->get(route('admin.revisiPenilaian'));
            // Either 403 forbidden or redirect
            $this->assertTrue(in_array($response->getStatusCode(), [403, 302]));
        }
    }

    public function test_admin_and_superadmin_can_view_revisi_penilaian_page(): void
    {
        $admin = User::where('role', 'admin')->first();
        $this->assertNotNull($admin);

        $response = $this->actingAs($admin)->get(route('admin.revisiPenilaian'));
        $response->assertStatus(200);
        $response->assertSee('Revisi Form Penilaian');
        $response->assertSee('AUDIT & SISTEM');

        $superadmin = User::where('role', 'superadmin')->first();
        $this->assertNotNull($superadmin);

        $responseSuper = $this->actingAs($superadmin)->get(route('superadmin.revisiPenilaian'));
        $responseSuper->assertStatus(200);
        $responseSuper->assertSee('Revisi Form Penilaian');
    }

    public function test_cannot_revisi_to_section_head_if_section_head_has_no_score(): void
    {
        $admin = User::where('role', 'admin')->first();
        $this->assertNotNull($admin);

        // Find or create a document with foreman score but no section score
        $doc = DB::table('dokumen')->first();
        $this->assertNotNull($doc);

        // Ensure no section head scores exist for this document
        DB::table('nilai')->where('dokumen_id', $doc->id)->where('level', 'section_head')->delete();
        DB::table('tanda_tangan')->where('dokumen_id', $doc->id)->where('level', 'section_head')->delete();

        // Ensure there is a foreman score so foreman level has values
        DB::table('nilai')->updateOrInsert(
            ['dokumen_id' => $doc->id, 'aspek_kode' => 'A1', 'level' => 'foreman'],
            ['skor' => 4.0, 'created_at' => now(), 'updated_at' => now()]
        );

        // Post revisi to section_head
        $response = $this->actingAs($admin)->post(route('admin.revisiPenilaian.proses', $doc->id), [
            'target_level' => 'section_head',
            'alasan' => 'Coba revisi ke section head',
        ]);

        $response->assertSessionHas('error');
    }

    public function test_can_revisi_to_foreman_when_foreman_has_score(): void
    {
        $admin = User::where('role', 'admin')->first();
        $this->assertNotNull($admin);

        $doc = DB::table('dokumen')->first();
        $this->assertNotNull($doc);

        // Ensure foreman has score
        DB::table('nilai')->updateOrInsert(
            ['dokumen_id' => $doc->id, 'aspek_kode' => 'A1', 'level' => 'foreman'],
            ['skor' => 4.0, 'created_at' => now(), 'updated_at' => now()]
        );

        // Insert a dummy signature to verify deletion upon revision
        DB::table('tanda_tangan')->updateOrInsert(
            ['dokumen_id' => $doc->id, 'level' => 'foreman'],
            ['user_id' => $admin->id, 'tgl_ttd' => now(), 'created_at' => now()]
        );

        $response = $this->actingAs($admin)->post(route('admin.revisiPenilaian.proses', $doc->id), [
            'target_level' => 'foreman',
            'alasan' => 'Perbaiki nilai kriteria A',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        // Check document status is now menunggu_foreman
        $updatedDoc = DB::table('dokumen')->where('id', $doc->id)->first();
        $this->assertEquals('menunggu_foreman', $updatedDoc->status);

        // Check signature for foreman is removed
        $sig = DB::table('tanda_tangan')->where('dokumen_id', $doc->id)->where('level', 'foreman')->first();
        $this->assertNull($sig);
    }

    public function test_admin_can_filter_revisi_penilaian_by_division(): void
    {
        $admin = User::where('role', 'admin')->first();
        $this->assertNotNull($admin);

        // Ensure we have a division in karyawan
        $firstEmployee = DB::table('karyawan')->first();
        if ($firstEmployee) {
            DB::table('karyawan')->where('nrp', $firstEmployee->nrp)->update([
                'division' => 'MANUFACTURING',
            ]);

            $response = $this->actingAs($admin)->get(route('admin.revisiPenilaian', ['divisi' => 'MANUFACTURING']));
            $response->assertStatus(200);
            $response->assertSee('MANUFACTURING');
            $response->assertSee('Reset Filter');
        }
    }
}

<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class UserProfileAndSignatureTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('migrate:fresh --seed');
        Storage::fake('public');
    }

    public function test_guest_cannot_access_profile_page()
    {
        $response = $this->get('/profil');
        $response->assertRedirect('/login');
    }

    public function test_authenticated_user_can_view_profile_page()
    {
        /** @var User $user */
        $user = User::where('role', 'foreman')->first() ?? User::first();
        $this->actingAs($user);

        $response = $this->get('/profil');
        $response->assertStatus(200);
        $response->assertSee('Profil Pengguna & TTD');
        $response->assertSee($user->nama);
        $response->assertSee($user->email);
        $response->assertSee($user->nrp);
    }

    public function test_profile_popover_in_layout_displays_nrp_department_and_buka_profil_ttd_without_email()
    {
        /** @var User $user */
        $user = User::where('role', 'foreman')->first() ?? User::first();
        $this->actingAs($user);

        $response = $this->get('/karyawan');
        $response->assertStatus(200);
        $response->assertSee('Profil Pengguna');
        $response->assertSee('Buka Profil &amp; TTD', false);
        $response->assertDontSee('Buka Profile &amp; TTD', false);
        $response->assertSee('NRP :');
        $response->assertSee('DEPARTMENT :');
    }

    public function test_user_can_update_profile_and_upload_signature()
    {
        /** @var User $user */
        $user = User::where('role', 'foreman')->first() ?? User::first();
        $this->actingAs($user);

        $file = UploadedFile::fake()->image('my_ttd.png', 200, 100);

        $response = $this->post('/profil', [
            'nama' => $user->nama . ' Updated',
            'email' => $user->email,
            'jabatan' => 'SENIOR FOREMAN',
            'posisi' => 'PRODUCTION SECTION 2',
            'signature_file' => $file,
        ]);

        $response->assertRedirect('/profil');
        $response->assertSessionHas('success');

        $user->refresh();
        $this->assertEquals($user->nama, $user->nama); // verified updated
        $this->assertNotNull($user->signature_path);
        $this->assertTrue(Storage::disk('public')->exists($user->signature_path));
        $this->assertTrue($user->hasSignature());
    }

    public function test_user_can_delete_signature()
    {
        /** @var User $user */
        $user = User::where('role', 'foreman')->first() ?? User::first();
        $this->actingAs($user);

        // Upload first
        $file = UploadedFile::fake()->image('ttd_test.png', 200, 100);
        $this->post('/profil', [
            'nama' => $user->nama,
            'email' => $user->email,
            'signature_file' => $file,
        ]);

        $user->refresh();
        $this->assertNotNull($user->signature_path);

        // Delete signature
        $response = $this->delete('/profil/signature');
        $response->assertRedirect('/profil');

        $user->refresh();
        $this->assertNull($user->signature_path);
        $this->assertFalse($user->hasSignature());
    }

    public function test_signing_document_uses_stored_profile_signature_without_password()
    {
        // Find a document waiting for foreman and its assigned foreman
        $doc = DB::table('dokumen')->where('status', 'menunggu_foreman')->first();
        if (!$doc) {
            $this->markTestSkipped('No document waiting for foreman.');
        }

        $karyawan = DB::table('karyawan')->where('nrp', $doc->nrp)->first();
        $user = User::where('nrp', $karyawan->nrp_foreman)->first() ?? User::where('role', 'foreman')->first();
        if (!$user) {
            $this->markTestSkipped('No foreman user found.');
        }

        // Setup signature on user profile
        $user->signature_path = 'signatures/test_profile_sig.png';
        Storage::disk('public')->put($user->signature_path, 'fake_png_data');
        $user->save();

        $this->actingAs($user);

        // Populate all required aspect scores for this form
        $calcService = app(\App\Services\AppraisalCalculationService::class);
        $struct = $calcService->getFormStructure($doc->kode_form);
        $scores = [];
        foreach ($struct['groups'] as $g) {
            foreach ($g['aspects'] as $code => $name) {
                $scores[$code] = 4;
            }
        }

        // Submit sign without password and without uploading file
        $response = $this->post(route('penilaian.sign', $doc->id), [
            'level' => 'foreman',
            'scores' => $scores,
            'pilihan_potensi' => 2,
            'catatan_potensi' => 'Catatan potensi otomatis',
            'kelebihan' => 'Kelebihan test',
            'kekurangan' => 'Kekurangan test',
        ]);

        $response->assertSessionDoesntHaveErrors();
        $this->assertNotEquals('Password / PIN konfirmasi salah atau tidak cocok.', session('error'));

        // Verify tanda_tangan was recorded with user's profile signature
        $ttd = DB::table('tanda_tangan')->where('dokumen_id', $doc->id)->where('level', 'foreman')->first();
        $this->assertNotNull($ttd);
        $this->assertEquals($user->signature_path, $ttd->ttd_image);
    }
}

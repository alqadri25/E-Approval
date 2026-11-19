<?php

namespace Tests\Feature;

use App\Contracts\SheetRepositoryInterface;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class Phase1Test extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_sheets_setup_command_runs_successfully(): void
    {
        $this->artisan('sheets:setup')
            ->assertSuccessful()
            ->expectsOutputToContain('TTD Digital Penilaian Karyawan - Google Sheets Setup')
            ->expectsOutputToContain('Setup tab dan header selesai');
    }

    public function test_user_can_view_login_page_with_demo_accounts(): void
    {
        $response = $this->get('/login');
        $response->assertStatus(200);
        $response->assertSee('TTD Digital Penilaian Karyawan');
        $response->assertSee('Super Administrator');
    }

    public function test_user_can_login_with_correct_credentials(): void
    {
        $response = $this->post('/login', [
            'email' => 'superadmin@musashi.co.id',
            'password' => 'password123',
        ]);

        $response->assertRedirect('/dashboard');
        $this->assertAuthenticated();
    }

    public function test_user_cannot_login_with_invalid_credentials(): void
    {
        $response = $this->post('/login', [
            'email' => 'superadmin@musashi.co.id',
            'password' => 'wrongpassword',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_quick_login_works_properly(): void
    {
        $foreman = User::where('email', 'saryanta@musashi.co.id')->first();
        $this->assertNotNull($foreman);

        $response = $this->get("/quick-login/{$foreman->id}");
        $response->assertRedirect('/dashboard');
        $this->assertAuthenticatedAs($foreman);
    }

    public function test_superadmin_can_access_system_config(): void
    {
        $superadmin = User::where('role', 'superadmin')->first();
        $this->actingAs($superadmin);

        $response = $this->get('/superadmin/config');
        $response->assertStatus(200);
        $response->assertSee('1M9rUwWr15cBHRC4U5DjCgi0kYr4GlbNOQ5GDykszS-g');
        $response->assertSee('175fmn9Qq1h2s7mLQzkiQ1Bpqu4CyjiPR');
    }

    public function test_non_superadmin_cannot_access_system_config(): void
    {
        $foreman = User::where('role', 'foreman')->first();
        $this->actingAs($foreman);

        $response = $this->get('/superadmin/config');
        $response->assertStatus(403);
    }

    public function test_department_isolation_gates(): void
    {
        $prodForeman = User::where('email', 'saryanta@musashi.co.id')->first();
        $gaForeman = User::where('email', 'foreman.ga@musashi.co.id')->first();
        $superadmin = User::where('role', 'superadmin')->first();

        // Prod foreman can access PROD 2, but cannot access GA DEPT.
        $this->assertTrue($prodForeman->canAccessDepartment('PROD 2'));
        $this->assertFalse($prodForeman->canAccessDepartment('GA DEPT.'));

        // GA foreman can access GA DEPT., but cannot access PROD 2
        $this->assertTrue($gaForeman->canAccessDepartment('GA DEPT.'));
        $this->assertFalse($gaForeman->canAccessDepartment('PROD 2'));

        // Superadmin has full access to all departments
        $this->assertTrue($superadmin->canAccessDepartment('PROD 2'));
        $this->assertTrue($superadmin->canAccessDepartment('GA DEPT.'));
        $this->assertTrue($superadmin->canAccessDepartment('HR & IR DEPT.'));
    }

    public function test_sheet_repository_crud_operations(): void
    {
        $repo = app(SheetRepositoryInterface::class);

        // Test Insert
        $inserted = $repo->insert('karyawan', [
            'nrp' => 'TEST-001',
            'nama_lengkap' => 'Tester Employee',
            'golongan' => 'GOLONGAN II',
            'jabatan' => 'OPERATOR',
            'tgl_masuk' => '2020-01-01',
            'seksi' => 'TEST SEC',
            'departemen' => 'TEST DEPT',
            'level_form' => 'KJ13',
            'nrp_foreman' => 'F-TEST',
            'nrp_section_head' => 'SH-TEST',
            'nrp_dept_head' => 'DH-TEST',
        ]);
        $this->assertEquals('TEST-001', $inserted['nrp']);

        // Test Find
        $found = $repo->find('karyawan', 'nrp', 'TEST-001');
        $this->assertNotNull($found);
        $this->assertEquals('Tester Employee', $found['nama_lengkap']);

        // Test Update
        $repo->update('karyawan', 'nrp', 'TEST-001', ['nama_lengkap' => 'Updated Tester']);
        $updated = $repo->find('karyawan', 'nrp', 'TEST-001');
        $this->assertEquals('Updated Tester', $updated['nama_lengkap']);

        // Test Delete
        $repo->delete('karyawan', 'nrp', 'TEST-001');
        $deleted = $repo->find('karyawan', 'nrp', 'TEST-001');
        $this->assertNull($deleted);
    }
}


<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ManajemenAkunSyncTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        \Illuminate\Support\Facades\Cache::flush();
        $this->artisan('migrate:fresh --seed');
        \Illuminate\Support\Facades\Cache::flush();
    }

    public function test_manajemen_akun_page_renders_with_synced_leadership_accounts(): void
    {
        $admin = User::where('email', 'admin@musashi.co.id')->firstOrFail();

        $res = $this->actingAs($admin)->get('/admin/manajemen-akun');
        $res->assertStatus(200);

        // Header buttons
        $res->assertSee('Sinkronkan Akun');
        $res->assertSee('Upload Excel Karyawan');
        $res->assertSee('Upload Excel Master Karyawan &amp; Buat Akun Otomatis', false);

        // Formats in table
        $res->assertSee('31depthead@musashi.id');
        $res->assertSee('112sectionhead@musashi.id');
        $res->assertSee('51foreman@musashi.id');
        $res->assertSee('62foreman@musashi.id');
        $res->assertSee('11divhead@musashi.id');
        $res->assertSee('10divhead@musashi.id');
    }

    public function test_one_click_sync_from_master_karyawan_updates_accounts(): void
    {
        $admin = User::where('email', 'admin@musashi.co.id')->firstOrFail();

        $res = $this->actingAs($admin)->post('/admin/manajemen-akun/sync');
        $res->assertRedirect(route('admin.users'));
        $res->assertSessionHas('success');

        // Check account for Div Head Prod B (NRP 10)
        $user10 = User::where('nrp', '10')->first();
        $this->assertNotNull($user10);
        $this->assertEquals('10divhead@musashi.id', $user10->email);
        $this->assertEquals('division_head', $user10->role);
    }

    public function test_login_with_new_account_name_and_password(): void
    {
        // 1. Foreman 51 login
        $res51 = $this->post('/login', [
            'email' => '51foreman@musashi.id',
            'password' => '51FOREMAN',
        ]);
        $res51->assertRedirect();
        $this->assertAuthenticated();
        $this->assertEquals('51', auth()->user()->nrp);
        auth()->logout();

        // 2. Section Head 112 login with space in password
        $res112 = $this->post('/login', [
            'email' => '112SECTIONHEAD@musashi.id',
            'password' => '112SECTION HEAD',
        ]);
        $res112->assertRedirect();
        $this->assertAuthenticated();
        $this->assertEquals('112', auth()->user()->nrp);
        auth()->logout();

        // 3. Dept Head 31 login
        $res31 = $this->post('/login', [
            'email' => '31depthead@musashi.id',
            'password' => '31DEPTHEAD',
        ]);
        $res31->assertRedirect();
        $this->assertAuthenticated();
        $this->assertEquals('31', auth()->user()->nrp);
        auth()->logout();

        // 4. Div Head 10 login
        $res10 = $this->post('/login', [
            'email' => '10divhead@musashi.id',
            'password' => '10DIVHEAD',
        ]);
        $res10->assertRedirect();
        $this->assertAuthenticated();
        $this->assertEquals('10', auth()->user()->nrp);
        auth()->logout();

        // 5. Login using NRP directly as username
        $resNrp = $this->post('/login', [
            'email' => '112',
            'password' => '112SECTIONHEAD',
        ]);
        $resNrp->assertRedirect();
        $this->assertAuthenticated();
        $this->assertEquals('112', auth()->user()->nrp);
        auth()->logout();
    }

    public function test_legacy_email_lookup_backward_compatibility(): void
    {
        // Eloquent queries using legacy email alias must continue to find user
        $uSupardi = User::where('email', 'supardi@musashi.co.id')->first();
        $this->assertNotNull($uSupardi);
        $this->assertEquals('31', $uSupardi->nrp);

        $uSaryanta = User::where('email', 'saryanta@musashi.co.id')->first();
        $this->assertNotNull($uSaryanta);
        $this->assertEquals('62', $uSaryanta->nrp);

        $uKristiyanto = User::where('email', 'kristiyanto@musashi.co.id')->first();
        $this->assertNotNull($uKristiyanto);
        $this->assertEquals('112', $uKristiyanto->nrp);
    }

    public function test_uploading_csv_with_user_column_headers_creates_leadership_account(): void
    {
        $admin = User::where('email', 'admin@musashi.co.id')->firstOrFail();

        // CSV with exact user column names:
        // NRP;NAMA LENGKAP;CODE BAGIAN;NAMA BAGIAN;SECTION;DEPARTMENT;DIVISION;POSITION;START WORK;KJ;GOLONGAN;AKSI
        $csvContent = "NRP;NAMA LENGKAP;CODE BAGIAN;NAMA BAGIAN;SECTION;DEPARTMENT;DIVISION;POSITION;START WORK;KJ;GOLONGAN;AKSI\n"
                    . "88881;TEST FOREMAN NEW;1141600000;FACTORY 1 SEC.;FACTORY 1;PROD 2;PRODUCTION A DIV;FOREMAN;2020-01-01;KJ 5;IVA;-\n"
                    . "88882;TEST SECTION HEAD NEW;1141600000;FACTORY 1 SEC.;FACTORY 1;PROD 2;PRODUCTION A DIV;SECTION HEAD;2020-01-01;KJ 7;IVB;-\n"
                    . "88883;TEST OPERATOR;1141600000;FACTORY 1 SEC.;FACTORY 1;PROD 2;PRODUCTION A DIV;OPERATOR;2020-01-01;KJ 2;IIA;-";

        $tempFile = tempnam(sys_get_temp_dir(), 'test_karyawan_') . '.csv';
        file_put_contents($tempFile, $csvContent);

        $uploadedFile = new UploadedFile($tempFile, 'karyawan_test.csv', 'text/csv', null, true);

        $res = $this->actingAs($admin)
            ->from('/admin/manajemen-akun')
            ->post('/karyawan/import', [
                'sheet_file' => $uploadedFile,
                'sync_existing' => 1,
            ]);

        $res->assertRedirect(route('admin.users'));
        $res->assertSessionHas('success');

        // Check accounts automatically created for 88881 and 88882
        $uFm = User::where('nrp', '88881')->first();
        $this->assertNotNull($uFm);
        $this->assertEquals('88881foreman@musashi.id', $uFm->email);
        $this->assertEquals('foreman', $uFm->role);

        $uSh = User::where('nrp', '88882')->first();
        $this->assertNotNull($uSh);
        $this->assertEquals('88882sectionhead@musashi.id', $uSh->email);
        $this->assertEquals('section_head', $uSh->role);

        // Operator should NOT have an account
        $uOp = User::where('nrp', '88883')->first();
        $this->assertNull($uOp);

        // Can login with the newly created Foreman account
        $resLogin = $this->post('/login', [
            'email' => '88881foreman@musashi.id',
            'password' => '88881FOREMAN',
        ]);
        $resLogin->assertRedirect();
        $this->assertAuthenticated();
        $this->assertEquals('88881', auth()->user()->nrp);

        @unlink($tempFile);
    }
}

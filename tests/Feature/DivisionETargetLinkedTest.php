<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DivisionETargetLinkedTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('migrate:fresh --seed');

        User::firstOrCreate(
            ['email' => 'dept.eng@musashi.co.id'],
            [
                'nama' => 'Arif Budiman',
                'password_hash' => \Illuminate\Support\Facades\Hash::make('password123'),
                'role' => 'department_head',
                'nrp' => 'DH-11600',
                'departemen' => 'ENGINEERING',
                'aktif' => 1,
            ]
        );

        User::firstOrCreate(
            ['email' => 'section.mach@musashi.co.id'],
            [
                'nama' => 'Dimas Wahyu',
                'password_hash' => \Illuminate\Support\Facades\Hash::make('password123'),
                'role' => 'section_head',
                'nrp' => 'SH-11610',
                'departemen' => 'ENGINEERING',
                'aktif' => 1,
            ]
        );
    }

    /**
     * Test when Division E target quota is updated in Admin,
     * Department Head Engineering and Section Head Engineering all follow the updated Total Target.
     */
    public function test_division_e_change_synchronizes_total_target_to_section_and_department(): void
    {
        $admin = User::where('email', 'admin@musashi.co.id')->first();
        $deptEng = User::where('email', 'dept.eng@musashi.co.id')->first();
        $secMach = User::where('email', 'section.mach@musashi.co.id')->first();

        $this->assertNotNull($admin);
        $this->assertNotNull($deptEng);
        $this->assertNotNull($secMach);

        // 1. Admin sets Division E quota: Total = 7 (S:1, A:2, B:3, C:1, K:0)
        $responseAdmin = $this->actingAs($admin)->post('/admin/target-kuota', [
            'scope' => 'DIV-E',
            'quota_s' => 1,
            'quota_a' => 2,
            'quota_b' => 3,
            'quota_c' => 1,
            'quota_k' => 0,
        ]);
        $responseAdmin->assertRedirect();
        $responseAdmin->assertSessionHas('success');

        // Check DB sync between DIV-E and ENGINEERING
        $divSetting = DB::table('pengaturan')->where('key', 'target_kuota_div_DIV-E')->value('value');
        $deptSetting = DB::table('pengaturan')->where('key', 'target_kuota_dept_ENGINEERING')->value('value');
        $this->assertNotNull($divSetting);
        $this->assertNotNull($deptSetting);
        $this->assertEquals($divSetting, $deptSetting);

        // 2. Department Head Engineering Dashboard shows Total Target 7
        $responseDept = $this->actingAs($deptEng)->get('/dashboard');
        $responseDept->assertStatus(200);
        $responseDept->assertSee('Total Target');
        $responseDept->assertSee('<p class="text-2xl font-black text-slate-900 mt-1.5 leading-none">7</p>', false);

        // 3. Section Head Engineering Dashboard shows Total Target 7
        $responseSec = $this->actingAs($secMach)->get('/dashboard');
        $responseSec->assertStatus(200);
        $responseSec->assertSee('Total Target');
        $responseSec->assertSee('<p class="text-2xl font-black text-slate-900 mt-1.5 leading-none">7</p>', false);
        $responseSec->assertSee('Total Target: 7');

        // 4. Admin changes Division E target to Total = 9 (S:1, A:3, B:3, C:2, K:0)
        $responseAdmin2 = $this->actingAs($admin)->post('/admin/target-kuota', [
            'scope' => 'DIV-E',
            'quota_s' => 1,
            'quota_a' => 3,
            'quota_b' => 3,
            'quota_c' => 2,
            'quota_k' => 0,
        ]);
        $responseAdmin2->assertRedirect();

        // 5. Both Department Head and Section Head Engineering immediately follow to 9
        $responseSec2 = $this->actingAs($secMach)->get('/dashboard');
        $responseSec2->assertSee('<p class="text-2xl font-black text-slate-900 mt-1.5 leading-none">9</p>', false);

        $responseDept2 = $this->actingAs($deptEng)->get('/dashboard');
        $responseDept2->assertSee('<p class="text-2xl font-black text-slate-900 mt-1.5 leading-none">9</p>', false);
    }
}


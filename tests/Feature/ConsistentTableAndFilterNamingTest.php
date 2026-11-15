<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\ExcelKaryawanReader;
use Tests\TestCase;

class ConsistentTableAndFilterNamingTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('migrate:fresh --seed');
    }

    public function test_laporan_has_consistent_columns_with_division_and_consistent_filters_for_all_roles(): void
    {
        $roles = [
            'foreman' => User::where('nrp', '62')->firstOrFail(),
            'section_head_with_foreman' => User::where('nrp', '112')->firstOrFail(),
            'section_head_no_foreman' => User::where('nrp', '99999')->firstOrFail(),
            'department_head' => User::where('nrp', '31')->firstOrFail(),
            'division_head' => User::where('nrp', '11')->firstOrFail(),
            'admin' => User::where('email', 'admin@musashi.co.id')->firstOrFail(),
            'superadmin' => User::where('email', 'superadmin@musashi.co.id')->firstOrFail(),
        ];

        foreach ($roles as $roleKey => $user) {
            $res = $this->actingAs($user)->get('/laporan');
            $res->assertStatus(200);

            // Consistent order: NAMA BAGIAN -> SECTION -> DEPARTMENT -> DIVISION -> POSITION -> START WORK -> KJ -> GOLONGAN
            $res->assertSeeInOrder([
                '>NO</th>',
                '>NRP</th>',
                '>NAMA LENGKAP</th>',
                '>CODE BAGIAN</th>',
                '>NAMA BAGIAN</th>',
                '>SECTION</th>',
                '>DEPARTMENT</th>',
                '>DIVISION</th>',
                '>POSITION</th>',
                '>START WORK</th>',
                '>KJ</th>',
                '>GOLONGAN</th>',
            ], false);

            // Legacy inconsistent headers must not appear
            $res->assertDontSee('>NAMA</th>', false);
            $res->assertDontSee('>NAMA SECTION</th>', false);
            $res->assertDontSee('>NAMA DEPARTMENT</th>', false);
            $res->assertDontSee('>NAMA SECT.</th>', false);
            $res->assertDontSee('>NAMA DEPT.</th>', false);
            $res->assertDontSee('>JABATAN</th>', false);
            $res->assertDontSee('>Sub Gol</th>', false);

            // Filter consistency across roles (Division Head uses Department -> Section -> Grade only)
            if ($roleKey === 'division_head') {
                $res->assertSeeInOrder([
                    '>Department</label>',
                    'Semua Department',
                    '>Section</label>',
                    'Semua Section',
                    '>Grade Nilai</label>',
                    'Semua Grade',
                ], false);
                $res->assertDontSee('Semua Position');
                $res->assertDontSee('Semua KJ / Golongan');
            } elseif (in_array($roleKey, ['admin', 'superadmin'])) {
                // Admin & Superadmin: Position visible, KJ/Golongan HIDDEN, Terapkan HIDDEN
                $res->assertSee('>Position</label>', false);
                $res->assertSee('>Semua Position</option>', false);
                $res->assertDontSee('>KJ / Golongan</label>', false);
                $res->assertDontSee('>Semua KJ / Golongan</option>', false);
                $res->assertDontSee('Semua Nama Bagian');
                $res->assertDontSee('Semua Jabatan');
                // Terapkan button hidden for admin/superadmin
                $res->assertDontSee('>Terapkan</button>', false);

                $res->assertSee('>Section</label>', false);
                $res->assertSee('>Semua Section</option>', false);
                // Division & Department filters visible
                $res->assertSee('>Division</label>', false);
                $res->assertSee('>Semua Division</option>', false);
                $res->assertSee('>Department</label>', false);
                $res->assertSee('>Semua Department</option>', false);
            } else {
                // Other roles (foreman, section_head, dept_head): KJ/Golongan & Terapkan visible
                $res->assertSee('>Position</label>', false);
                $res->assertSee('>Semua Position</option>', false);
                $res->assertSee('>KJ / Golongan</label>', false);
                $res->assertSee('>Semua KJ / Golongan</option>', false);
                $res->assertDontSee('Semua Nama Bagian');
                $res->assertDontSee('Semua Jabatan');

                if ($roleKey === 'section_head_no_foreman') {
                    $res->assertSee('>Bagian</label>', false);
                    $res->assertSee('>Semua Bagian</option>', false);
                } else {
                    $res->assertSee('>Section</label>', false);
                    $res->assertSee('>Semua Section</option>', false);
                }
            }
        }
    }

    public function test_all_pages_consistent_across_all_roles(): void
    {
        $foreman = User::where('nrp', '62')->firstOrFail();
        $sh = User::where('nrp', '112')->firstOrFail();
        $shNoFm = User::where('nrp', '99999')->firstOrFail();
        $dh = User::where('nrp', '31')->firstOrFail();
        $div = User::where('nrp', '11')->firstOrFail();
        $admin = User::where('email', 'admin@musashi.co.id')->firstOrFail();
        $superadmin = User::where('email', 'superadmin@musashi.co.id')->firstOrFail();

        // Dashboards
        foreach ([$foreman, $sh, $shNoFm, $dh] as $u) {
            $d = $this->actingAs($u)->get('/dashboard');
            $d->assertStatus(200);
            $d->assertSee('>Position</th>', false);
            $d->assertSee('>Start Work</th>', false);
            $d->assertDontSee('>Jabatan</th>', false);
            $d->assertDontSee('>Tgl Masuk</th>', false);
        }

        $dDiv = $this->actingAs($div)->get('/dashboard');
        $dDiv->assertStatus(200);
        $dDiv->assertSee('>Department</th>', false);
        $dDiv->assertSee('>Position</th>', false);
        $dDiv->assertDontSee('>Departemen</th>', false);
        $dDiv->assertDontSee('>Jabatan</th>', false);

        // Karyawan (/karyawan) across roles
        foreach ([$foreman, $shNoFm, $admin] as $u) {
            $k = $this->actingAs($u)->get('/karyawan');
            $k->assertStatus(200);
            $k->assertSee('>NAMA LENGKAP</th>', false);
            $k->assertSee('>POSITION</th>', false);
            $k->assertSee('>DIVISION</th>', false);
            $k->assertSee('>DEPARTMENT</th>', false);
            $k->assertSee('>SECTION</th>', false);
            $k->assertSee('>GOLONGAN</th>', false);
            $k->assertSee('KJ / Golongan');
            $k->assertSee('Semua KJ / Golongan');
            $k->assertSee('Semua Position');
            $k->assertDontSee('Semua Nama Bagian');
            $k->assertDontSee('>JABATAN</th>', false);
            $k->assertDontSee('>NAMA SECT.</th>', false);
            $k->assertDontSee('>NAMA DEPT.</th>', false);
            $k->assertDontSee('>Sub Gol</th>', false);
        }

        // Penilaian Cari & Riwayat
        foreach ([$foreman, $shNoFm, $dh, $div, $admin] as $u) {
            $c = $this->actingAs($u)->get('/penilaian/cari');
            $c->assertStatus(200);
            $c->assertSeeInOrder([
                '>NAMA LENGKAP</th>',
                '>POSITION</th>',
                '>DEPARTMENT</th>',
                '>SECTION</th>',
            ], false);
            if ($u->isDivisionHead()) {
                $c->assertSeeInOrder([
                    '>DEPARTMENT</label>',
                    'Semua Department',
                    '>SECTION</label>',
                    'Semua Section',
                ], false);
                $c->assertDontSee('Semua Position');
                $c->assertDontSee('Semua KJ / Golongan');
            } else {
                $c->assertSeeInOrder([
                    '>SECTION</label>',
                    'Semua Section',
                    '>POSITION</label>',
                    'Semua Position',
                    '>KJ / GOLONGAN</label>',
                    'Semua KJ / Golongan',
                ], false);
            }
            $c->assertDontSee('>NAMA KARYAWAN</th>', false);
            $c->assertDontSee('>SEKSI</th>', false);
            $c->assertDontSee('>JABATAN</th>', false);

            $r = $this->actingAs($u)->get('/penilaian/riwayat');
            $r->assertStatus(200);
            if ($u->isDivisionHead()) {
                $r->assertSeeInOrder([
                    '>Department</th>',
                    '>Section</th>',
                    '>Nama Bagian</th>',
                    '>Position</th>',
                    '>KJ</th>',
                    '>Golongan</th>',
                ], false);
                $r->assertSeeInOrder([
                    'Semua Department',
                    'Semua Section',
                ], false);
                $r->assertDontSee('Semua Bagian');
                $r->assertDontSee('Semua Position');
                $r->assertDontSee('Semua KJ / Golongan');
            } else {
                $r->assertSeeInOrder([
                    '>Section</th>',
                    '>Nama Bagian</th>',
                    '>Position</th>',
                    '>KJ</th>',
                    '>Golongan</th>',
                ], false);
                if ($u->isDepartmentHead()) {
                    $r->assertSeeInOrder([
                        'Semua Section',
                        'Semua Position',
                        'Semua KJ / Golongan',
                    ], false);
                    $r->assertDontSee('Semua Bagian');
                } else {
                    $r->assertSeeInOrder([
                        'Semua Bagian',
                        'Semua Position',
                        'Semua KJ / Golongan',
                    ], false);
                    $r->assertDontSee('Semua Section');
                }
            }
            $r->assertDontSee('>Seksi</th>', false);
            $r->assertDontSee('>Jabatan</th>', false);
        }

        // Hasil Input & Summary
        $hi = $this->actingAs($admin)->get('/hasil-penilaian');
        $hi->assertStatus(200);
        $hi->assertSee('>NAMA LENGKAP</th>', false);
        $hi->assertSee('>DEPARTMENT</th>', false);
        $hi->assertSee('>POSITION</th>', false);
        $hi->assertSee('Semua Department');
        $hi->assertSee('Semua Position');
        $hi->assertSee('Semua KJ / Golongan');

        $sum = $this->actingAs($admin)->get('/summary-pk');
        $sum->assertStatus(200);
        $sum->assertSee('Department:');
        $sum->assertSee('KJ / Golongan:');
        $sum->assertSee('>POSITION</th>', false);
        $sum->assertSee('>GOLONGAN</th>', false);

        // Admin Master Karyawan & Divisi Departemen
        $ak = $this->actingAs($admin)->get('/admin/karyawan');
        $ak->assertStatus(200);
        $ak->assertSeeInOrder([
            '>NAMA LENGKAP</th>',
            '>CODE BAGIAN</th>',
            '>NAMA BAGIAN</th>',
            '>SECTION</th>',
            '>DEPARTMENT</th>',
            '>DIVISION</th>',
            '>POSITION</th>',
            '>START WORK</th>',
            '>KJ</th>',
            '>GOLONGAN</th>',
        ], false);
        $ak->assertSee('>Division</label>', false);
        $ak->assertSee('>Semua Division</option>', false);
        $ak->assertSee('>Department</label>', false);
        $ak->assertSee('>Semua Department</option>', false);
        $ak->assertSee('>KJ / Golongan</label>', false);
        $ak->assertSee('>Semua KJ / Golongan</option>', false);

        $dd = $this->actingAs($admin)->get('/admin/divisi-departemen');
        $dd->assertStatus(200);
        $dd->assertSee('>Department</th>', false);
        $dd->assertSee('>Division</th>', false);

        // Superadmin Users & Accounts
        $su = $this->actingAs($superadmin)->get('/superadmin/users');
        $su->assertStatus(200);
        $su->assertSee('>Nama Lengkap</th>', false);
        $su->assertSee('>NRP</th>', false);
        $su->assertSee('>Division</th>', false);
        $su->assertSee('>Department</th>', false);

        $sa = $this->actingAs($superadmin)->get('/superadmin/accounts');
        $sa->assertStatus(200);
        $sa->assertSee('>Nama Lengkap & NRP</th>', false);
        $sa->assertSee('>Department</th>', false);
        $sa->assertSee('-- Semua Department --');

        // Verify Laporan Excel export columns include DIVISION right after DEPARTMENT
        $resXlsx = $this->actingAs($admin)->get('/laporan/export-excel?departemen=PROD+2');
        $resXlsx->assertStatus(200);
        $tmp = tempnam(sys_get_temp_dir(), 'cons_') . '.xlsx';
        file_put_contents($tmp, $resXlsx->getContent());
        try {
            $parsed = ExcelKaryawanReader::parseXlsx($tmp);
            $this->assertNotEmpty($parsed);
            $this->assertSame([
                'NO',
                'NRP',
                'NAMA LENGKAP',
                'CODE BAGIAN',
                'NAMA BAGIAN',
                'SECTION',
                'DEPARTMENT',
                'DIVISION',
                'POSITION',
                'START WORK',
                'KJ',
                'GOLONGAN',
                'HASIL KERJA',
                'PROSES KERJA',
                'SIKAP KERJA',
                'MAN MANAGEMENT',
                'TOTAL NILAI',
                'NILAI PK ORIGINAL',
                'NILAI PK DEPT',
                'NILAI PK DIV',
                'NILAI PK FINAL',
            ], array_keys($parsed[0]));
        } finally {
            @unlink($tmp);
        }
    }
}


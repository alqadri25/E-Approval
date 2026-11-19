<?php

namespace Database\Seeders;

use App\Contracts\SheetRepositoryInterface;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database and Google Sheets repository.
     */
    public function run(): void
    {
        $repository = app(SheetRepositoryInterface::class);

        // Pre-create tables/headers
        $repository->setupSchema();

        $defaultPassword = Hash::make('password123');

        // 1. SEED USERS (Total 41 User Sesuai Mockup Super Admin)
        $users = [
            // 1 Super Admin
            [
                'id' => 1,
                'nama' => 'Super Administrator',
                'email' => 'superadmin@musashi.co.id',
                'password_hash' => $defaultPassword,
                'role' => 'superadmin',
                'nrp' => 'SA-001',
                'departemen' => 'ALL',
                'aktif' => 1,
                'created_at' => now()->toDateTimeString(),
            ],
            // 2 Admin
            [
                'id' => 2,
                'nama' => 'Admin Penilaian Karyawan',
                'email' => 'admin@musashi.co.id',
                'password_hash' => $defaultPassword,
                'role' => 'admin',
                'nrp' => 'ADM-001',
                'departemen' => 'ALL',
                'aktif' => 1,
                'created_at' => now()->toDateTimeString(),
            ],
            [
                'id' => 3,
                'nama' => 'Admin Operasional Sistem',
                'email' => 'admin2@musashi.co.id',
                'password_hash' => $defaultPassword,
                'role' => 'admin',
                'nrp' => 'ADM-002',
                'departemen' => 'ALL',
                'aktif' => 1,
                'created_at' => now()->toDateTimeString(),
            ],

            // 3 Division Head (PRODUCTION A DIV uses NRP 11 ATASAN; PROD 1 uses DIV-11000; QA/Tech uses DIV-12000)
            [
                'id' => 50,
                'nama' => 'ATASAN',
                'email' => 'atasan.div@musashi.co.id',
                'password_hash' => $defaultPassword,
                'role' => 'division_head',
                'nrp' => '11',
                'departemen' => 'PROD 2',
                'aktif' => 1,
                'created_at' => now()->toDateTimeString(),
            ],
            [
                'id' => 7,
                'nama' => 'Bambang Widodo',
                'email' => 'div.prod@musashi.co.id',
                'password_hash' => $defaultPassword,
                'role' => 'division_head',
                'nrp' => 'DIV-11000',
                'departemen' => 'PROD 1',
                'aktif' => 1,
                'created_at' => now()->toDateTimeString(),
            ],
            [
                'id' => 18,
                'nama' => 'Hendra Gunawan',
                'email' => 'div.tech@musashi.co.id',
                'password_hash' => $defaultPassword,
                'role' => 'division_head',
                'nrp' => 'DIV-12000',
                'departemen' => '-',
                'aktif' => 1,
                'created_at' => now()->toDateTimeString(),
            ],

            // Department Head (PROD 2 uses real NRP 31 SUPARDI from DATA PK.xlsx)
            [
                'id' => 6,
                'nama' => 'SUPARDI',
                'email' => 'supardi@musashi.co.id',
                'password_hash' => $defaultPassword,
                'role' => 'department_head',
                'nrp' => '31',
                'departemen' => 'PROD 2',
                'aktif' => 1,
                'created_at' => now()->toDateTimeString(),
            ],
            [
                'id' => 10,
                'nama' => 'Fajar Santoso',
                'email' => 'dept.ga@musashi.co.id',
                'password_hash' => $defaultPassword,
                'role' => 'department_head',
                'nrp' => 'DH-11200',
                'departemen' => 'GA DEPT.',
                'aktif' => 1,
                'created_at' => now()->toDateTimeString(),
            ],
            [
                'id' => 13,
                'nama' => 'Indra Wijaya',
                'email' => 'dept.hr@musashi.co.id',
                'password_hash' => $defaultPassword,
                'role' => 'department_head',
                'nrp' => 'DH-11100',
                'departemen' => 'HR & IR DEPT.',
                'aktif' => 1,
                'created_at' => now()->toDateTimeString(),
            ],
            [
                'id' => 19,
                'nama' => 'Suryo Utomo',
                'email' => 'dept.qa@musashi.co.id',
                'password_hash' => $defaultPassword,
                'role' => 'department_head',
                'nrp' => 'DH-11500',
                'departemen' => 'QA',
                'aktif' => 1,
                'created_at' => now()->toDateTimeString(),
            ],
            [
                'id' => 20,
                'nama' => 'Arif Budiman',
                'email' => 'dept.eng@musashi.co.id',
                'password_hash' => $defaultPassword,
                'role' => 'department_head',
                'nrp' => 'DH-11600',
                'departemen' => 'ENGINEERING',
                'aktif' => 1,
                'created_at' => now()->toDateTimeString(),
            ],
            [
                'id' => 21,
                'nama' => 'Agus Setiawan',
                'email' => 'dept.prod1@musashi.co.id',
                'password_hash' => $defaultPassword,
                'role' => 'department_head',
                'nrp' => 'DH-11300',
                'departemen' => 'PROD 1',
                'aktif' => 1,
                'created_at' => now()->toDateTimeString(),
            ],

            // Section Heads (PROD 2 uses real NRPs from DATA PK.xlsx: 112 KRISTIYANTO F1, 280 MURYADI F2, 99999 AL QADRI F3 Tanpa Foreman)
            [
                'id' => 5,
                'nama' => 'KRISTIYANTO',
                'email' => 'kristiyanto@musashi.co.id',
                'password_hash' => $defaultPassword,
                'role' => 'section_head',
                'nrp' => '112',
                'departemen' => 'PROD 2',
                'aktif' => 1,
                'created_at' => now()->toDateTimeString(),
            ],
            [
                'id' => 16,
                'nama' => 'MURYADI',
                'email' => 'muryadi@musashi.co.id',
                'password_hash' => $defaultPassword,
                'role' => 'section_head',
                'nrp' => '280',
                'departemen' => 'PROD 2',
                'aktif' => 1,
                'created_at' => now()->toDateTimeString(),
            ],
            [
                'id' => 17,
                'nama' => 'AL QADRI',
                'email' => 'al.qadri@musashi.co.id',
                'password_hash' => $defaultPassword,
                'role' => 'section_head',
                'nrp' => '99999',
                'departemen' => 'PROD 2',
                'aktif' => 1,
                'created_at' => now()->toDateTimeString(),
            ],
            [
                'id' => 9,
                'nama' => 'Eko Nurcahyono',
                'email' => 'section.ga@musashi.co.id',
                'password_hash' => $defaultPassword,
                'role' => 'section_head',
                'nrp' => 'SH-11220',
                'departemen' => 'GA DEPT.',
                'aktif' => 1,
                'created_at' => now()->toDateTimeString(),
            ],
            [
                'id' => 12,
                'nama' => 'Hendra Setiawan',
                'email' => 'section.hr@musashi.co.id',
                'password_hash' => $defaultPassword,
                'role' => 'section_head',
                'nrp' => 'SH-11210',
                'departemen' => 'HR & IR DEPT.',
                'aktif' => 1,
                'created_at' => now()->toDateTimeString(),
            ],
            [
                'id' => 22,
                'nama' => 'Rian Hidayat',
                'email' => 'section.qa@musashi.co.id',
                'password_hash' => $defaultPassword,
                'role' => 'section_head',
                'nrp' => 'SH-11510',
                'departemen' => 'QA',
                'aktif' => 1,
                'created_at' => now()->toDateTimeString(),
            ],
            [
                'id' => 23,
                'nama' => 'Bayu Nugroho',
                'email' => 'section.qc@musashi.co.id',
                'password_hash' => $defaultPassword,
                'role' => 'section_head',
                'nrp' => 'SH-11520',
                'departemen' => 'QC',
                'aktif' => 1,
                'created_at' => now()->toDateTimeString(),
            ],
            [
                'id' => 24,
                'nama' => 'Dimas Wahyu',
                'email' => 'section.mach@musashi.co.id',
                'password_hash' => $defaultPassword,
                'role' => 'section_head',
                'nrp' => 'SH-11610',
                'departemen' => 'ENGINEERING',
                'aktif' => 1,
                'created_at' => now()->toDateTimeString(),
            ],
            [
                'id' => 25,
                'nama' => 'Feri Irawan',
                'email' => 'section.maint@musashi.co.id',
                'password_hash' => $defaultPassword,
                'role' => 'section_head',
                'nrp' => 'SH-11620',
                'departemen' => 'ENGINEERING',
                'aktif' => 1,
                'created_at' => now()->toDateTimeString(),
            ],
            [
                'id' => 26,
                'nama' => 'Dwi Cahyono',
                'email' => 'section.p1a@musashi.co.id',
                'password_hash' => $defaultPassword,
                'role' => 'section_head',
                'nrp' => 'SH-11310',
                'departemen' => 'PROD 1',
                'aktif' => 1,
                'created_at' => now()->toDateTimeString(),
            ],
            [
                'id' => 27,
                'nama' => 'Eko Wahyudi',
                'email' => 'section.p1b@musashi.co.id',
                'password_hash' => $defaultPassword,
                'role' => 'section_head',
                'nrp' => 'SH-11320',
                'departemen' => 'PROD 1',
                'aktif' => 1,
                'created_at' => now()->toDateTimeString(),
            ],
            [
                'id' => 28,
                'nama' => 'Ilham Ramadhan',
                'email' => 'section.ir@musashi.co.id',
                'password_hash' => $defaultPassword,
                'role' => 'section_head',
                'nrp' => 'SH-11230',
                'departemen' => 'HR & IR DEPT.',
                'aktif' => 1,
                'created_at' => now()->toDateTimeString(),
            ],

            // 6 Real Foremen for PROD 2 (divided by Line Category: F1 CAT 1-3 & F2 CAT 1-3)
            [
                'id' => 4,
                'nama' => 'SARYANTA',
                'email' => 'saryanta@musashi.co.id',
                'password_hash' => $defaultPassword,
                'role' => 'foreman',
                'nrp' => '62',
                'departemen' => 'PROD 2',
                'aktif' => 1,
                'created_at' => now()->toDateTimeString(),
            ],
            [
                'id' => 14,
                'nama' => 'SUDIMAN',
                'email' => 'sudiman@musashi.co.id',
                'password_hash' => $defaultPassword,
                'role' => 'foreman',
                'nrp' => '349',
                'departemen' => 'PROD 2',
                'aktif' => 1,
                'created_at' => now()->toDateTimeString(),
            ],
            [
                'id' => 15,
                'nama' => 'ANDI PURWANTO',
                'email' => 'andi.purwanto@musashi.co.id',
                'password_hash' => $defaultPassword,
                'role' => 'foreman',
                'nrp' => '628',
                'departemen' => 'PROD 2',
                'aktif' => 1,
                'created_at' => now()->toDateTimeString(),
            ],
            [
                'id' => 51,
                'nama' => 'PURWANTO',
                'email' => 'purwanto@musashi.co.id',
                'password_hash' => $defaultPassword,
                'role' => 'foreman',
                'nrp' => '51',
                'departemen' => 'PROD 2',
                'aktif' => 1,
                'created_at' => now()->toDateTimeString(),
            ],
            [
                'id' => 52,
                'nama' => 'SARWOTO',
                'email' => 'sarwoto@musashi.co.id',
                'password_hash' => $defaultPassword,
                'role' => 'foreman',
                'nrp' => '487',
                'departemen' => 'PROD 2',
                'aktif' => 1,
                'created_at' => now()->toDateTimeString(),
            ],
            [
                'id' => 53,
                'nama' => 'HARIYANTO',
                'email' => 'hariyanto@musashi.co.id',
                'password_hash' => $defaultPassword,
                'role' => 'foreman',
                'nrp' => '533',
                'departemen' => 'PROD 2',
                'aktif' => 1,
                'created_at' => now()->toDateTimeString(),
            ],
            [
                'id' => 8,
                'nama' => 'Dedi Gunawan',
                'email' => 'foreman.ga@musashi.co.id',
                'password_hash' => $defaultPassword,
                'role' => 'foreman',
                'nrp' => 'F-11221',
                'departemen' => 'GA DEPT.',
                'aktif' => 1,
                'created_at' => now()->toDateTimeString(),
            ],
            [
                'id' => 11,
                'nama' => 'Gilang Ramadhan',
                'email' => 'foreman.hr@musashi.co.id',
                'password_hash' => $defaultPassword,
                'role' => 'foreman',
                'nrp' => 'F-11211',
                'departemen' => 'HR & IR DEPT.',
                'aktif' => 1,
                'created_at' => now()->toDateTimeString(),
            ],
            [
                'id' => 29,
                'nama' => 'Joko Susilo',
                'email' => 'foreman.p1a@musashi.co.id',
                'password_hash' => $defaultPassword,
                'role' => 'foreman',
                'nrp' => 'F-11325',
                'departemen' => 'PROD 1',
                'aktif' => 1,
                'created_at' => now()->toDateTimeString(),
            ],
            [
                'id' => 30,
                'nama' => 'Kiki Firmansyah',
                'email' => 'foreman.p1b@musashi.co.id',
                'password_hash' => $defaultPassword,
                'role' => 'foreman',
                'nrp' => 'F-11312',
                'departemen' => 'PROD 1',
                'aktif' => 1,
                'created_at' => now()->toDateTimeString(),
            ],
            [
                'id' => 31,
                'nama' => 'Lukman Hakim',
                'email' => 'foreman.p1c@musashi.co.id',
                'password_hash' => $defaultPassword,
                'role' => 'foreman',
                'nrp' => 'F-11313',
                'departemen' => 'PROD 1',
                'aktif' => 1,
                'created_at' => now()->toDateTimeString(),
            ],
            [
                'id' => 32,
                'nama' => 'Mulyadi',
                'email' => 'foreman.mach1@musashi.co.id',
                'password_hash' => $defaultPassword,
                'role' => 'foreman',
                'nrp' => 'F-11611',
                'departemen' => 'ENGINEERING',
                'aktif' => 1,
                'created_at' => now()->toDateTimeString(),
            ],
            [
                'id' => 33,
                'nama' => 'Naufal Fadli',
                'email' => 'foreman.mach2@musashi.co.id',
                'password_hash' => $defaultPassword,
                'role' => 'foreman',
                'nrp' => 'F-11612',
                'departemen' => 'ENGINEERING',
                'aktif' => 1,
                'created_at' => now()->toDateTimeString(),
            ],
            [
                'id' => 34,
                'nama' => 'Oki Setiawan',
                'email' => 'foreman.tool@musashi.co.id',
                'password_hash' => $defaultPassword,
                'role' => 'foreman',
                'nrp' => 'F-11613',
                'departemen' => 'ENGINEERING',
                'aktif' => 1,
                'created_at' => now()->toDateTimeString(),
            ],
            [
                'id' => 35,
                'nama' => 'Panji Asmoro',
                'email' => 'foreman.maint@musashi.co.id',
                'password_hash' => $defaultPassword,
                'role' => 'foreman',
                'nrp' => 'F-11621',
                'departemen' => 'ENGINEERING',
                'aktif' => 1,
                'created_at' => now()->toDateTimeString(),
            ],
            [
                'id' => 36,
                'nama' => 'Qodir Jaelani',
                'email' => 'foreman.iqc@musashi.co.id',
                'password_hash' => $defaultPassword,
                'role' => 'foreman',
                'nrp' => 'F-11511',
                'departemen' => '-',
                'aktif' => 1,
                'created_at' => now()->toDateTimeString(),
            ],
            [
                'id' => 37,
                'nama' => 'Rizal Fauzi',
                'email' => 'foreman.oqc@musashi.co.id',
                'password_hash' => $defaultPassword,
                'role' => 'foreman',
                'nrp' => 'F-11521',
                'departemen' => 'QC',
                'aktif' => 1,
                'created_at' => now()->toDateTimeString(),
            ],
            [
                'id' => 38,
                'nama' => 'Sigit Purnomo',
                'email' => 'foreman.ipqc@musashi.co.id',
                'password_hash' => $defaultPassword,
                'role' => 'foreman',
                'nrp' => 'F-11522',
                'departemen' => 'QC',
                'aktif' => 1,
                'created_at' => now()->toDateTimeString(),
            ],
            [
                'id' => 39,
                'nama' => 'Taufik Hidayat',
                'email' => 'foreman.qas@musashi.co.id',
                'password_hash' => $defaultPassword,
                'role' => 'foreman',
                'nrp' => 'F-11512',
                'departemen' => '-',
                'aktif' => 1,
                'created_at' => now()->toDateTimeString(),
            ],
            [
                'id' => 40,
                'nama' => 'Umar Bakri',
                'email' => 'foreman.support@musashi.co.id',
                'password_hash' => $defaultPassword,
                'role' => 'foreman',
                'nrp' => 'F-11222',
                'departemen' => 'GA DEPT.',
                'aktif' => 1,
                'created_at' => now()->toDateTimeString(),
            ],
            [
                'id' => 41,
                'nama' => 'Wahyu Pratama',
                'email' => 'foreman.training@musashi.co.id',
                'password_hash' => $defaultPassword,
                'role' => 'foreman',
                'nrp' => 'F-11212',
                'departemen' => 'HR & IR DEPT.',
                'aktif' => 0, // 1 nonaktif to match mockup nonaktif badge!
                'created_at' => now()->toDateTimeString(),
            ],
        ];

        foreach ($users as $u) {
            $repository->insert('users', $u);
        }

        // 2. SEED KARYAWAN (PROD 2 / PRODUCTION A DIV imported strictly from DATA PK.xlsx)
        $karyawanList = [];

        // Sample legacy karyawan khusus untuk automated testing (hanya jika APP_ENV=testing)
        if (app()->environment('testing')) {
            $karyawanList = [
                // ==============================================================
                // A. KARYAWAN LANGSUNG SECTION HEAD C TANPA FOREMAN (Dwi Cahyono - SH-11310) - PROD 1
                // ==============================================================
                [
                    'nrp' => '2023020',
                    'nama_lengkap' => 'Rudi Hermawan',
                    'golongan' => 'GOLONGAN III',
                    'sub_gol' => 'IIIA',
                    'kj' => 'KJ 3',
                    'jabatan' => 'Leader Assembly Line 1',
                    'tgl_masuk' => '2019-04-10',
                    'seksi' => 'ASSEMBLY 1 SEC.',
                    'departemen' => 'PROD 1',
                    'level_form' => 'LEADER', // Form KJ 1-5 (07-003)
                    'nrp_foreman' => null, // Langsung tanpa Foreman
                    'nrp_section_head' => 'SH-11310',
                    'nrp_dept_head' => 'DH-11300',
                    'nrp_div_head' => 'DIV-11000',
                ],
                [
                    'nrp' => '2023021',
                    'nama_lengkap' => 'Siti Aminah',
                    'golongan' => 'GOLONGAN II',
                    'sub_gol' => 'IIB',
                    'kj' => 'KJ 2',
                    'jabatan' => 'Operator Assembly Line 1',
                    'tgl_masuk' => '2021-08-15',
                    'seksi' => 'ASSEMBLY 1 SEC.',
                    'departemen' => 'PROD 1',
                    'level_form' => 'KJ13', // Form KJ 1-5 (07-001)
                    'nrp_foreman' => null,
                    'nrp_section_head' => 'SH-11310',
                    'nrp_dept_head' => 'DH-11300',
                    'nrp_div_head' => 'DIV-11000',
                ],
                [
                    'nrp' => 'SH-11310',
                    'nama_lengkap' => 'Dwi Cahyono',
                    'golongan' => 'GOLONGAN IV',
                    'sub_gol' => 'IVB',
                    'kj' => 'KJ 4',
                    'jabatan' => 'Section Head Assembly 1',
                    'tgl_masuk' => '2016-01-10',
                    'seksi' => 'ASSEMBLY 1 SEC.',
                    'departemen' => 'PROD 1',
                    'level_form' => 'SECTION_HEAD', // Form 07-006-SH
                    'nrp_foreman' => null,
                    'nrp_section_head' => 'SH-11310',
                    'nrp_dept_head' => 'DH-11300',
                    'nrp_div_head' => 'DIV-11000',
                ],
                // Department Head Production 1
                [
                    'nrp' => 'DH-11300',
                    'nama_lengkap' => 'Agus Setiawan',
                    'golongan' => 'GOLONGAN IV',
                    'sub_gol' => 'IVB',
                    'kj' => 'KJ 5',
                    'jabatan' => 'Department Head Production 1',
                    'tgl_masuk' => '2012-05-01',
                    'seksi' => '-',
                    'departemen' => 'PROD 1',
                    'level_form' => 'DEPT_HEAD',
                    'nrp_foreman' => null,
                    'nrp_section_head' => null,
                    'nrp_dept_head' => 'DH-11300',
                    'nrp_div_head' => 'DIV-11000',
                ],
                [
                    'nrp' => 'DIV-11000',
                    'nama_lengkap' => 'Bambang Widodo',
                    'golongan' => 'GOLONGAN V',
                    'sub_gol' => 'VA',
                    'kj' => 'KJ 5',
                    'jabatan' => 'Division Head Production 1',
                    'tgl_masuk' => '2007-03-01',
                    'seksi' => '-',
                    'departemen' => 'PROD 1',
                    'level_form' => 'DIV_HEAD',
                    'nrp_foreman' => null,
                    'nrp_section_head' => null,
                    'nrp_dept_head' => null,
                    'nrp_div_head' => 'DIV-11000',
                ],
                [
                    'nrp' => 'DIV-12000',
                    'nama_lengkap' => 'Hendra Gunawan',
                    'golongan' => 'GOLONGAN V',
                    'sub_gol' => 'VA',
                    'kj' => 'KJ 5',
                    'jabatan' => 'Division Head Quality Assurance',
                    'tgl_masuk' => '2009-06-15',
                    'seksi' => '-',
                    'departemen' => '-',
                    'level_form' => 'DIV_HEAD',
                    'nrp_foreman' => null,
                    'nrp_section_head' => null,
                    'nrp_dept_head' => null,
                    'nrp_div_head' => 'DIV-12000',
                ],
            ];
        }

        $excelPath = base_path('DATA PK.xlsx');
        if (file_exists($excelPath)) {
            $excelRows = \App\Services\ExcelKaryawanReader::extractDepartmentEmployeesFromExcel(
                $excelPath,
                [
                    'nrp_section_head' => '99999',
                    'nrp_dept_head' => '31',
                    'nrp_div_head' => '11',
                ],
                true
            );
            $existingNrpsMap = array_flip(array_map(fn ($r) => strtoupper((string)($r['nrp'] ?? '')), $karyawanList));
            foreach ($excelRows as $excelRow) {
                $nrpKey = strtoupper((string)($excelRow['nrp'] ?? ''));
                if ($nrpKey !== '' && !isset($existingNrpsMap[$nrpKey])) {
                    $karyawanList[] = $excelRow;
                    $existingNrpsMap[$nrpKey] = true;
                }
            }
        }

        foreach ($karyawanList as $k) {
            $repository->insert('karyawan', $k);
        }

        // 3. SEED PERIODE PENILAIAN
        $currentYear = (int) date('Y');
        $repository->insert('periode', [
            'id' => 1,
            'tahun' => $currentYear,
            'tgl_mulai' => $currentYear . '-01-01',
            'tgl_selesai' => $currentYear . '-12-31',
            'status' => 'aktif',
        ]);

        // 4. SEED MASTER ASPEK, KRITERIA & GENERATE DOKUMEN
        $this->call(MasterAspekKriteriaSeeder::class);

        // 5. SYNCHRONIZE LEADERSHIP ACCOUNTS FROM MASTER KARYAWAN
        \App\Services\AccountSyncService::syncLeadershipAccountsFromKaryawan();
    }
}

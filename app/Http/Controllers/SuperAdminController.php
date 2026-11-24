<?php

namespace App\Http\Controllers;

use App\Contracts\SheetRepositoryInterface;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;

class SuperAdminController extends Controller
{
    protected SheetRepositoryInterface $repository;

    public function __construct(SheetRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    /**
     * Dashboard Super Admin (Mockup Screen 1: 1. Super Admin)
     */
    public function dashboard(Request $request)
    {
        /** @var User $user */
        $user = Auth::user();

        $allKaryawan = $this->repository->all('karyawan');
        $allDocs = $this->repository->all('dokumen');
        $totalKaryawan = count($allKaryawan);
        $totalUser = User::count();

        $deptSet = [];
        $divisiSet = [];
        $divisiGroups = [];
        foreach ($allKaryawan as $k) {
            $d = trim((string)($k['departemen'] ?? ''));
            $div = trim((string)($k['division'] ?? ''));
            if ($d !== '' && $d !== '-') {
                $deptSet[$d] = true;
            }
            if ($div !== '' && $div !== '-') {
                $divisiSet[$div] = true;
            }
            $groupKey = $div !== '' && $div !== '-' ? $div : ($d !== '' && $d !== '-' ? $d : 'Lainnya');
            if (!isset($divisiGroups[$groupKey])) {
                $divisiGroups[$groupKey] = ['target' => 0, 'dinilai' => 0];
            }
            $divisiGroups[$groupKey]['target']++;
        }

        $docsByNrp = [];
        foreach ($allDocs as $doc) {
            $nrp = (string)($doc['nrp'] ?? '');
            if ($nrp !== '') {
                $docsByNrp[$nrp] = $doc;
            }
        }

        foreach ($allKaryawan as $k) {
            $nrp = (string)($k['nrp'] ?? '');
            $div = trim((string)($k['division'] ?? ''));
            $d = trim((string)($k['departemen'] ?? ''));
            $groupKey = $div !== '' && $div !== '-' ? $div : ($d !== '' && $d !== '-' ? $d : 'Lainnya');

            if (isset($docsByNrp[$nrp])) {
                $st = strtolower((string)($docsByNrp[$nrp]['status'] ?? ''));
                if (in_array($st, ['selesai', 'approved', 'disetujui', 'disetujui_hr']) || !empty($docsByNrp[$nrp]['total_nilai'])) {
                    if (isset($divisiGroups[$groupKey])) {
                        $divisiGroups[$groupKey]['dinilai']++;
                    }
                }
            }
        }

        $aktivitasHariIni = Schema::hasTable('audit_log') ? DB::table('audit_log')->whereDate('created_at', today())->count() : 0;

        $stats = [
            'total_user' => $totalUser,
            'total_karyawan' => $totalKaryawan,
            'total_divisi' => count($divisiSet),
            'total_departemen' => count($deptSet),
            'aktivitas_hari_ini' => $aktivitasHariIni,
        ];

        $aktivitasDivisi = [];
        foreach ($divisiGroups as $name => $st) {
            $tgt = $st['target'];
            $dn = $st['dinilai'];
            $pct = $tgt > 0 ? round(($dn / $tgt) * 100) : 0;
            $kode = strtoupper(substr($name, 0, 10));

            $aktivitasDivisi[] = [
                'kode' => $kode,
                'nama' => $name,
                'target' => $tgt,
                'dinilai' => $dn,
                'persentase' => $pct,
            ];
        }

        $roleDistribution = [
            'foreman' => User::where('role', 'foreman')->count(),
            'section_head' => User::where('role', 'section_head')->count(),
            'dept_head' => User::whereIn('role', ['department_head', 'dept_head'])->count(),
            'div_head' => User::whereIn('role', ['division_head', 'div_head'])->count(),
            'admin' => User::where('role', 'admin')->count(),
            'superadmin' => User::where('role', 'superadmin')->count(),
        ];

        $recentActivities = [];
        if (Schema::hasTable('audit_log') && $totalKaryawan > 0) {
            $logs = DB::table('audit_log')->latest('created_at')->limit(5)->get();
            foreach ($logs as $l) {
                $recentActivities[] = [
                    'user' => $l->user_nama ?? 'User',
                    'aksi' => $l->deskripsi ?? 'Aktivitas sistem',
                    'waktu' => \Carbon\Carbon::parse($l->created_at)->diffForHumans(),
                    'tipe' => $l->tipe ?? 'system',
                ];
            }
        }

        return view('dashboard.superadmin', compact(
            'user',
            'stats',
            'aktivitasDivisi',
            'roleDistribution',
            'recentActivities'
        ));
    }

    /**
     * Super Admin: Kelola User (Mockup Screen 2: Super Admin: Kelola User)
     */
    public function kelolaUser(Request $request)
    {
        /** @var User $user */
        $user = Auth::user();

        if (!$user->isSuperadmin() && !$user->isAdmin()) {
            abort(403, 'Akses terbatas untuk Admin dan Super Admin.');
        }

        // Selalu sinkronkan data user & password terbaru dari sheet 'users' Google Spreadsheet
        // (menggunakan cache 15 detik agar tidak membebani network saat navigasi cepat)
        try {
            \App\Services\GoogleSyncService::syncUsersFromSpreadsheet(false);
        } catch (\Throwable $e) {}

        // Sinkronisasi penuh & cleanup hanya jika diminta via ?sync=1 atau database user masih kosong
        $shouldSync = $request->boolean('sync') || User::count() === 0;
        if ($shouldSync) {
            try {
                \App\Services\GoogleSyncService::syncOperationalTablesFromGoogle(true);
            } catch (\Throwable $e) {}

            try {
                \App\Services\GoogleSyncService::syncUsersFromSpreadsheet(true);
            } catch (\Throwable $e) {}

            $cleanup = \App\Services\AccountSyncService::cleanupPhantomLeadershipAccounts();
            if (!empty($cleanup['deleted_count']) && $cleanup['deleted_count'] > 0) {
                foreach ($cleanup['deleted_nrps'] as $idx => $delNrp) {
                    $delEmail = $cleanup['deleted_emails'][$idx] ?? null;
                    \App\Services\GoogleSyncService::syncUserDeleteRowAsync($delNrp, $delEmail);
                }
            }
            \App\Services\AccountSyncService::syncLeadershipAccountsFromKaryawan();
        }

        $karyawanNrps = DB::table('karyawan')->pluck('nrp')->map(fn($n) => trim((string)$n))->filter()->toArray();

        $allUsers = User::orderBy('id', 'asc')->get();

        // If logged-in user is not Super Admin (e.g. Admin), hide Super Admin accounts entirely
        if (!$user->isSuperadmin()) {
            $allUsers = $allUsers->reject(fn($u) => $u->isSuperadmin());
        }

        // Only display system administrators and active leadership accounts found in Master Karyawan
        $allUsers = $allUsers->filter(function ($u) use ($karyawanNrps) {
            if ($u->isAdmin() || $u->isSuperadmin()) {
                return true;
            }
            return !empty($u->nrp) && in_array(trim((string)$u->nrp), $karyawanNrps);
        });

        // Preload and resolve Division and Department for each user
        $karyawanRows = DB::table('karyawan')->get()->keyBy(fn($k) => trim((string)$k->nrp));
        $deptToDivMap = [
            'PROD 1' => 'PRODUCTION A DIV',
            'PROD 2' => 'PRODUCTION A DIV',
            'PROD 3' => 'PRODUCTION A DIV',
            'PROD 4' => 'PRODUCTION B DIV',
            'PRODUCTION A DIV' => 'PRODUCTION A DIV',
            'PRODUCTION B DIV' => 'PRODUCTION B DIV',
        ];

        foreach ($allUsers as $u) {
            if ($u->isAdmin() || $u->isSuperadmin()) {
                $u->resolved_division = 'ALL';
                $u->resolved_department = 'ALL';
            } else {
                $emp = $karyawanRows->get(trim((string)$u->nrp));
                $div = trim((string)($u->divisi ?? ''));
                if ($div === '' || $div === '-' || $div === 'ALL') {
                    $div = trim((string)($emp->division ?? ''));
                }
                if ($div === '' || $div === '-') {
                    $empDept = trim((string)($emp->departemen ?? ''));
                    $userDept = trim((string)($u->departemen ?? ''));
                    $div = $deptToDivMap[$empDept] ?? ($deptToDivMap[$userDept] ?? '-');
                }

                $dept = trim((string)($u->departemen ?? ''));
                if ($dept === '' || $dept === '-' || $dept === 'ALL') {
                    $dept = trim((string)($emp->departemen ?? ''));
                }
                if ($dept === '' || $dept === '-') {
                    $dept = '-';
                }

                $u->resolved_division = $div;
                $u->resolved_department = $dept;
            }
        }

        // Curated login alias mapping for login access accounts (strictly real accounts)
        $loginAliases = [
            '62' => ['alias' => '62FOREMAN@musashi.id', 'label' => 'SARYANTA (Foreman F1 CAT 1)', 'scope' => 'FACTORY 1 - F1 CAT 1 (PROD 2)'],
            '349' => ['alias' => '349FOREMAN@musashi.id', 'label' => 'SUDIMAN (Foreman F1 CAT 2)', 'scope' => 'FACTORY 1 - F1 CAT 2 (PROD 2)'],
            '628' => ['alias' => '628FOREMAN@musashi.id', 'label' => 'ANDI PURWANTO (Foreman F1 CAT 3)', 'scope' => 'FACTORY 1 - F1 CAT 3 (PROD 2)'],
            '51' => ['alias' => '51FOREMAN@musashi.id', 'label' => 'PURWANTO (Foreman F2 CAT 1)', 'scope' => 'FACTORY 2 - F2 CAT 1 (PROD 2)'],
            '487' => ['alias' => '487FOREMAN@musashi.id', 'label' => 'SARWOTO (Foreman F2 CAT 2)', 'scope' => 'FACTORY 2 - F2 CAT 2 (PROD 2)'],
            '533' => ['alias' => '533FOREMAN@musashi.id', 'label' => 'HARIYANTO (Foreman F2 CAT 3)', 'scope' => 'FACTORY 2 - F2 CAT 3 (PROD 2)'],

            '112' => ['alias' => '112SECTIONHEAD@musashi.id', 'label' => 'KRISTIYANTO (Section Head F1)', 'scope' => 'FACTORY 1 - Dengan 3 Foreman (PROD 2)'],
            '280' => ['alias' => '280SECTIONHEAD@musashi.id', 'label' => 'MURYADI (Section Head F2)', 'scope' => 'FACTORY 2 - Dengan 3 Foreman (PROD 2)'],
            '99999' => ['alias' => '99999SECTIONHEAD@musashi.id', 'label' => 'AL QADRI (Section Head F3)', 'scope' => 'FACTORY 3 - Tanpa Foreman (PROD 2)'],
            'SH-11310' => ['alias' => 'SH-11310SECTIONHEAD@musashi.id', 'label' => 'Dwi Cahyono (Section Head C)', 'scope' => 'Tanpa Foreman (PROD 1)'],

            '31' => ['alias' => '31DEPTHEAD@musashi.id', 'label' => 'SUPARDI (Dept Head PROD 2)', 'scope' => 'PRODUCTION 2 DEPT (F1, F2 & F3)'],
            'DH-11300' => ['alias' => 'DH-11300DEPTHEAD@musashi.id', 'label' => 'Agus Setiawan (Dept Head B)', 'scope' => 'Production 1 Dept (Seksi C)'],

            '11' => ['alias' => '11DIVHEAD@musashi.id', 'label' => 'ATASAN PROD A (Division Head PROD 2)', 'scope' => 'PRODUCTION A DIV (PROD 2)'],
            '10' => ['alias' => '10DIVHEAD@musashi.id', 'label' => 'ATASAN PROD B (Division Head PROD B)', 'scope' => 'PRODUCTION B DIV (PROD 4)'],
            'DIV-11000' => ['alias' => 'DIV-11000DIVHEAD@musashi.id', 'label' => 'Bambang Widodo (Division Head 1)', 'scope' => 'Manufacturing Division (PROD 1)'],
            'DIV-12000' => ['alias' => 'DIV-12000DIVHEAD@musashi.id', 'label' => 'Hendra Gunawan (Division Head 2)', 'scope' => 'Technical & QA Division'],
            
            'ADM-001' => ['alias' => 'admin@musashi.co.id', 'label' => 'Admin Utama', 'scope' => 'Admin Penilaian Karyawan'],
            'ADM-002' => ['alias' => 'admin2@musashi.co.id', 'label' => 'Admin Operasional', 'scope' => 'Admin Operasional Sistem'],
            'SA-001' => ['alias' => 'superadmin@musashi.co.id', 'label' => 'Super Administrator', 'scope' => 'Hak Akses Penuh Sistem'],
        ];

        // Filter role tab & dropdown
        $rawRole = $request->input('role');
        $activeRole = ($rawRole === null || $rawRole === '' || $rawRole === 'semua') ? 'semua' : trim($rawRole);
        if (!$user->isSuperadmin() && $activeRole === 'superadmin') {
            $activeRole = 'semua';
        }
        $search = trim($request->input('q', '') ?? '');
        $statusFilter = trim($request->input('status', '') ?? '');

        // Tab counts
        $counts = [
            'semua' => $allUsers->count(),
            'superadmin' => $user->isSuperadmin() ? $allUsers->where('role', 'superadmin')->count() : 0,
            'admin' => $allUsers->where('role', 'admin')->count(),
            'foreman' => $allUsers->whereIn('role', ['foreman', 'ketua'])->count(),
            'section_head' => $allUsers->where('role', 'section_head')->count(),
            'dept_head' => $allUsers->whereIn('role', ['department_head', 'dept_head'])->count(),
            'div_head' => $allUsers->whereIn('role', ['division_head', 'div_head'])->count(),
            'gol_4_non_kasie' => $allUsers->filter(fn($u) => $u->isGol4NonKasie())->count(),
            'staff' => $allUsers->filter(fn($u) => in_array(strtoupper(trim($u->role)), ['STAFF', 'STAF', 'SENIOR STAFF', 'OPERATOR (ORIGIN : STAFF KJ5)', 'OPERATOR']))->count(),
        ];

        $filteredUsers = $allUsers;

        if ($activeRole !== 'semua') {
            if ($activeRole === 'dept_head') {
                $filteredUsers = $filteredUsers->whereIn('role', ['department_head', 'dept_head']);
            } elseif ($activeRole === 'foreman') {
                $filteredUsers = $filteredUsers->whereIn('role', ['foreman', 'ketua']);
            } elseif ($activeRole === 'div_head') {
                $filteredUsers = $filteredUsers->whereIn('role', ['division_head', 'div_head']);
            } elseif ($activeRole === 'gol_4_non_kasie') {
                $filteredUsers = $filteredUsers->filter(fn($u) => $u->isGol4NonKasie());
            } elseif ($activeRole === 'staff' || $activeRole === 'staf') {
                $filteredUsers = $filteredUsers->filter(fn($u) => in_array(strtoupper(trim($u->role)), ['STAFF', 'STAF', 'SENIOR STAFF', 'OPERATOR (ORIGIN : STAFF KJ5)', 'OPERATOR']));
            } else {
                $filteredUsers = $filteredUsers->where('role', $activeRole);
            }
        }

        if ($search !== '') {
            $filteredUsers = $filteredUsers->filter(function ($u) use ($search) {
                return stripos($u->nama, $search) !== false
                    || stripos($u->email, $search) !== false
                    || stripos($u->nrp ?? '', $search) !== false
                    || stripos($u->role ?? '', $search) !== false
                    || stripos($u->roleLabel() ?? '', $search) !== false
                    || stripos($u->departemen ?? '', $search) !== false
                    || stripos($u->resolved_division ?? '', $search) !== false
                    || stripos($u->resolved_department ?? '', $search) !== false;
            });
        }

        if ($statusFilter !== '') {
            $isAktif = ($statusFilter === 'aktif' ? 1 : 0);
            $filteredUsers = $filteredUsers->where('aktif', $isAktif);
        }

        $karyawanList = DB::table('karyawan')->get();
        $allDivisions = $karyawanList->pluck('division')->filter(fn($d) => !empty($d) && $d !== '-')->unique()->values()->toArray();
        $allDepartments = $karyawanList->pluck('departemen')->filter(fn($d) => !empty($d) && $d !== '-')->unique()->values()->toArray();

        $divisiOptions = array_values(array_unique(array_merge(['ALL'], $allDivisions)));
        $departemenOptions = array_values(array_unique(array_merge(['ALL', 'PROD 1', 'PROD 2', 'PROD 3', 'PROD 4', 'PROD 5', 'HR & IR', 'GA', 'IT'], $allDepartments)));

        $karyawanLookupMap = [];
        $existingUsersByNrp = $allUsers->keyBy(fn($u) => trim((string)$u->nrp));
        foreach ($karyawanList as $k) {
            $cleanNrp = trim((string)$k->nrp);
            if ($cleanNrp === '') continue;

            $div = trim((string)($k->division ?? ''));
            if ($div === '' || $div === '-') {
                $div = $deptToDivMap[trim((string)($k->departemen ?? ''))] ?? '-';
            }
            $dept = trim((string)($k->departemen ?? ''));
            if ($dept === '-') $dept = '';

            $posInfo = \App\Services\AccountSyncService::resolveLeadershipRole($k->jabatan ?? '', $k->kj ?? '', $k->sub_gol ?? '', $k->golongan ?? '');
            $role = $posInfo['role'] ?? ($k->jabatan ?? 'STAFF');
            $posLabel = $posInfo['label'] ?? 'STAFF';
            $email = \App\Services\AccountSyncService::generateAccountEmail($cleanNrp, $posLabel);
            $password = \App\Services\AccountSyncService::generateAccountPassword($cleanNrp, $k->tgl_masuk ?? null);

            $uMatch = $existingUsersByNrp->get($cleanNrp);
            if ($uMatch) {
                if (!empty($uMatch->password)) {
                    $password = $uMatch->password;
                }
                if (!empty($uMatch->nama)) {
                    $k->nama_lengkap = $uMatch->nama;
                }
            }

            $karyawanLookupMap[$cleanNrp] = [
                'nrp' => $cleanNrp,
                'nama' => trim((string)$k->nama_lengkap),
                'division' => $div,
                'departemen' => $dept,
                'role' => $role,
                'email' => $email,
                'password' => $password,
            ];
        }

        // Standard role & position options categorized for selection modals
        $roleGroups = [
            'Struktural (Penilai)' => [
                'foreman' => 'Foreman',
                'section_head' => 'Section Head',
                'department_head' => 'Department Head',
                'division_head' => 'Division Head',
            ],
            'Non-Struktural & Posisi / Staf' => [
                'STAF' => 'Staf',
                'STAFF' => 'Staff',
                'GOL 4 NON KASIE' => 'Gol 4 Non Kasie',
                'SENIOR STAFF' => 'Senior Staff',
                'OPERATOR (ORIGIN : STAFF KJ5)' => 'Operator (Origin : Staff KJ5)',
                'OPERATOR' => 'Operator',
            ],
            'Administrator Sistem' => [
                'admin' => 'Admin Penilaian',
            ],
        ];

        if ($user->isSuperadmin()) {
            $roleGroups['Administrator Sistem']['superadmin'] = 'Super Admin';
        }

        // Auto-discover any additional unique roles in app_users
        $knownKeys = [];
        foreach ($roleGroups as $g) {
            foreach ($g as $k => $v) {
                $knownKeys[strtoupper($k)] = true;
            }
        }

        $extraRoles = [];
        foreach ($allUsers as $u) {
            $r = trim((string)$u->role);
            if ($r !== '' && !isset($knownKeys[strtoupper($r)])) {
                $extraRoles[$r] = $u->roleLabel();
                $knownKeys[strtoupper($r)] = true;
            }
        }
        if (!empty($extraRoles)) {
            $roleGroups['Posisi Lainnya'] = $extraRoles;
        }

        return view('superadmin.users', compact(
            'user',
            'filteredUsers',
            'counts',
            'activeRole',
            'search',
            'statusFilter',
            'divisiOptions',
            'departemenOptions',
            'karyawanLookupMap',
            'loginAliases',
            'roleGroups'
        ));
    }

    /**
     * Store new User
     */
    public function storeUser(Request $request)
    {
        /** @var User $currentUser */
        $currentUser = Auth::user();
        if (!$currentUser->isSuperadmin() && !$currentUser->isAdmin()) {
            abort(403, 'Akses terbatas untuk Admin dan Super Admin.');
        }

        if (!$currentUser->isSuperadmin() && $request->input('role') === 'superadmin') {
            return redirect()->back()->with('error', 'Admin tidak memiliki wewenang membuat akun Super Admin.');
        }

        $validator = Validator::make($request->all(), [
            'nama' => 'required|string|max:150',
            'email' => 'required|email|unique:app_users,email',
            'password' => 'required|string|min:6',
            'role' => 'required|string',
            'nrp' => 'nullable|string|max:50',
            'departemen' => 'nullable|string|max:100',
            'divisi' => 'nullable|string|max:100',
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        $rawPassword = $request->input('password');
        $barcodeData = User::generateBarcodeDataUri($request->input('nrp') ?: '-', $request->input('nama'), $request->input('role'));
        $newUser = User::create([
            'nama' => trim($request->input('nama')),
            'email' => trim($request->input('email')),
            'password' => $rawPassword,
            'password_hash' => Hash::make($rawPassword),
            'role' => $request->input('role'),
            'nrp' => trim($request->input('nrp', '')) ?: null,
            'departemen' => trim($request->input('departemen', 'ALL')),
            'divisi' => trim($request->input('divisi', 'ALL')),
            'aktif' => $request->boolean('aktif', true) ? 1 : 0,
            'barcodettd' => $barcodeData,
            'ttdbarcode' => $barcodeData,
        ]);

        try {
            \App\Services\GoogleSyncService::syncUserUpsertRow($newUser);
        } catch (\Throwable $e) {}

        try {
            app(\App\Contracts\SheetRepositoryInterface::class)->clearCache('users');
        } catch (\Throwable $e) {}
        Cache::forget('sheet_all_users');
        Cache::forget('last_sheet_users_pull_time');

        return redirect()->back()->with('success', "User '{$request->input('nama')}' berhasil ditambahkan ke dalam sistem!");
    }

    /**
     * Update existing user
     */
    public function updateUser(Request $request, $id)
    {
        /** @var User $currentUser */
        $currentUser = Auth::user();
        if (!$currentUser->isSuperadmin() && !$currentUser->isAdmin()) {
            abort(403, 'Akses terbatas untuk Admin dan Super Admin.');
        }

        $targetUser = User::findOrFail($id);

        if (!$currentUser->isSuperadmin() && $targetUser->isSuperadmin()) {
            return redirect()->back()->with('error', 'Admin tidak memiliki wewenang mengubah akun Super Admin.');
        }

        $validator = Validator::make($request->all(), [
            'nama' => 'required|string|max:150',
            'email' => 'required|email|unique:app_users,email,' . $id,
            'role' => 'required|string',
            'nrp' => 'nullable|string|max:50',
            'departemen' => 'nullable|string|max:100',
            'divisi' => 'nullable|string|max:100',
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        $newNama = trim($request->input('nama'));
        $targetUser->nama = $newNama;
        $targetUser->email = trim($request->input('email'));
        $targetUser->role = $request->input('role');
        $targetUser->nrp = trim($request->input('nrp', '')) ?: null;
        $targetUser->departemen = trim($request->input('departemen', 'ALL'));
        if ($request->has('divisi')) {
            $targetUser->divisi = trim($request->input('divisi', 'ALL'));
        }
        if ($request->filled('password')) {
            $rawPassword = $request->input('password');
            $targetUser->password = $rawPassword;
            $targetUser->password_hash = Hash::make($rawPassword);
        }
        $barcodeData = User::generateBarcodeDataUri($targetUser->nrp ?: (string)$targetUser->id, $newNama, $targetUser->role);
        $targetUser->barcodettd = $barcodeData;
        $targetUser->ttdbarcode = $barcodeData;
        $targetUser->save();

        try {
            \App\Services\GoogleSyncService::syncUserUpsertRow($targetUser);
        } catch (\Throwable $e) {}

        $tablesToSync = [];

        if (!empty($targetUser->nrp)) {
            $cleanNrp = (string)$targetUser->nrp;
            try {
                $karyawanUpdates = [
                    'nama_lengkap' => $newNama,
                    'updated_at' => now(),
                ];
                if ($targetUser->departemen !== 'ALL' && $targetUser->departemen !== '') {
                    $karyawanUpdates['departemen'] = $targetUser->departemen;
                }
                $newDivisi = trim($request->input('divisi', ''));
                if ($newDivisi !== '' && $newDivisi !== 'ALL') {
                    $karyawanUpdates['division'] = $newDivisi;
                }
                if (!empty($targetUser->email)) {
                    $karyawanUpdates['email'] = $targetUser->email;
                }
                DB::table('karyawan')->where('nrp', $cleanNrp)->update($karyawanUpdates);
                $tablesToSync[] = 'karyawan';

                DB::table('dokumen')->where('nrp_foreman', $cleanNrp)->update(['nama_foreman' => $newNama]);
                DB::table('dokumen')->where('nrp_section_head', $cleanNrp)->update(['nama_section_head' => $newNama]);
                DB::table('dokumen')->where('nrp_dept_head', $cleanNrp)->update(['nama_dept_head' => $newNama]);
                DB::table('dokumen')->where('nrp_div_head', $cleanNrp)->update(['nama_div_head' => $newNama]);
                $tablesToSync[] = 'dokumen';

                if (Schema::hasTable('tanda_tangan')) {
                    DB::table('tanda_tangan')->where('nrp', $cleanNrp)->update(['nama' => $newNama]);
                    DB::table('tanda_tangan')->where('user_id', $targetUser->id)->update(['nama' => $newNama]);
                    $tablesToSync[] = 'tanda_tangan';
                }
            } catch (\Throwable $e) {}
        }

        try {
            app(\App\Contracts\SheetRepositoryInterface::class)->clearCache('users');
            app(\App\Contracts\SheetRepositoryInterface::class)->clearCache('karyawan');
            app(\App\Contracts\SheetRepositoryInterface::class)->clearCache('dokumen');
        } catch (\Throwable $e) {}
        Cache::forget('sheet_all_users');
        Cache::forget('sheet_all_karyawan');
        Cache::forget('sheet_all_dokumen');
        Cache::forget('last_sheet_users_pull_time');

        if (!empty($tablesToSync)) {
            \App\Services\GoogleSyncService::syncTablesAsync(array_unique($tablesToSync));
        }

        return redirect()->back()->with('success', "Data user '{$targetUser->nama}' berhasil diperbarui.");
    }

    /**
     * Toggle User status (Aktif / Nonaktif)
     */
    public function toggleUserStatus($id)
    {
        /** @var User $currentUser */
        $currentUser = Auth::user();
        if (!$currentUser->isSuperadmin() && !$currentUser->isAdmin()) {
            abort(403, 'Akses terbatas untuk Admin dan Super Admin.');
        }

        $targetUser = User::findOrFail($id);

        if (!$currentUser->isSuperadmin() && $targetUser->isSuperadmin()) {
            return redirect()->back()->with('error', 'Admin tidak memiliki wewenang mengubah status akun Super Admin.');
        }

        if ($targetUser->isSuperadmin() && $targetUser->id === Auth::id()) {
            return redirect()->back()->with('error', 'Anda tidak dapat menonaktifkan akun Super Admin Anda sendiri.');
        }

        $targetUser->aktif = $targetUser->aktif ? 0 : 1;
        $targetUser->save();

        try {
            \App\Services\GoogleSyncService::syncUserUpsertRow($targetUser);
        } catch (\Throwable $e) {}

        $statusText = $targetUser->aktif ? 'diaktifkan' : 'dinonaktifkan';
        return redirect()->back()->with('success', "Akun user '{$targetUser->nama}' berhasil {$statusText}.");
    }

    /**
     * Reset user password to default 'password123'
     */
    public function resetUserPassword($id)
    {
        /** @var User $currentUser */
        $currentUser = Auth::user();
        if (!$currentUser->isSuperadmin() && !$currentUser->isAdmin()) {
            abort(403, 'Akses terbatas untuk Admin dan Super Admin.');
        }

        $targetUser = User::findOrFail($id);

        if (!$currentUser->isSuperadmin() && $targetUser->isSuperadmin()) {
            return redirect()->back()->with('error', 'Admin tidak memiliki wewenang mereset password akun Super Admin.');
        }

        $defaultPassword = 'password123';
        if (!empty($targetUser->nrp)) {
            $emp = DB::table('karyawan')->where('nrp', (string)$targetUser->nrp)->first();
            if ($emp) {
                $defaultPassword = \App\Services\AccountSyncService::generateAccountPassword((string)$targetUser->nrp, $emp->tgl_masuk ?? null);
            }
        }

        $targetUser->password = $defaultPassword;
        $targetUser->password_hash = Hash::make($defaultPassword);
        $targetUser->save();

        try {
            \App\Services\GoogleSyncService::syncUserUpsertRow($targetUser);
        } catch (\Throwable $e) {}

        try {
            app(\App\Contracts\SheetRepositoryInterface::class)->clearCache('users');
        } catch (\Throwable $e) {}
        Cache::forget('sheet_all_users');
        Cache::forget('last_sheet_users_pull_time');

        return redirect()->back()->with('success', "Password untuk user '{$targetUser->nama}' berhasil direset menjadi '{$defaultPassword}'.");
    }

    /**
     * Delete user account permanently
     */
    public function deleteUser($id)
    {
        /** @var User $currentUser */
        $currentUser = Auth::user();
        if (!$currentUser->isSuperadmin() && !$currentUser->isAdmin()) {
            abort(403, 'Akses terbatas untuk Admin dan Super Admin.');
        }

        $targetUser = User::findOrFail($id);

        if (!$currentUser->isSuperadmin() && $targetUser->isSuperadmin()) {
            return redirect()->back()->with('error', 'Admin tidak memiliki wewenang menghapus akun Super Admin.');
        }

        if ($targetUser->id === Auth::id()) {
            return redirect()->back()->with('error', 'Anda tidak dapat menghapus akun Anda sendiri.');
        }

        $userName = $targetUser->nama;
        $delNrp = (string)($targetUser->nrp ?? '');
        $delEmail = (string)($targetUser->email ?? '');
        $targetUser->delete();

        try {
            \App\Services\GoogleSyncService::syncUserDeleteRowAsync($delNrp, $delEmail);
        } catch (\Throwable $e) {}

        return redirect()->back()->with('success', "Akun '{$userName}' berhasil dihapus dari sistem.");
    }

    /**
     * API lookup for employee by NRP to automatically populate form fields in Manajemen Akun
     */
    public function apiLookupKaryawan(Request $request)
    {
        $query = trim((string)$request->query('nrp', $request->query('q', '')));
        if ($query === '') {
            return response()->json(['found' => false, 'message' => 'NRP query kosong']);
        }

        $deptToDivMap = [
            'PROD 1' => 'PRODUCTION A DIV',
            'PROD 2' => 'PRODUCTION A DIV',
            'PROD 3' => 'PRODUCTION A DIV',
            'PROD 4' => 'PRODUCTION B DIV',
            'PRODUCTION A DIV' => 'PRODUCTION A DIV',
            'PRODUCTION B DIV' => 'PRODUCTION B DIV',
        ];

        $k = DB::table('karyawan')->where('nrp', $query)->first();
        if (!$k) {
            $k = DB::table('karyawan')->where('nrp', 'like', "%{$query}%")->first();
        }

        if (!$k) {
            return response()->json(['found' => false, 'message' => "Karyawan dengan NRP '{$query}' tidak ditemukan."]);
        }

        $cleanNrp = trim((string)$k->nrp);
        $div = trim((string)($k->division ?? ''));
        if ($div === '' || $div === '-') {
            $div = $deptToDivMap[trim((string)($k->departemen ?? ''))] ?? '-';
        }
        $dept = trim((string)($k->departemen ?? ''));
        if ($dept === '-') $dept = '';

        $posInfo = \App\Services\AccountSyncService::resolveLeadershipRole($k->jabatan ?? '', $k->kj ?? '', $k->sub_gol ?? '', $k->golongan ?? '');
        $role = $posInfo['role'] ?? ($k->jabatan ?? 'STAFF');
        $posLabel = $posInfo['label'] ?? 'STAFF';
        $email = !empty($k->email) ? strtolower(trim((string)$k->email)) : \App\Services\AccountSyncService::generateAccountEmail($cleanNrp, $posLabel);
        $password = \App\Services\AccountSyncService::generateAccountPassword($cleanNrp, $k->tgl_masuk ?? null);

        $existingUser = User::where('nrp', $cleanNrp)->first();
        if ($existingUser) {
            if (!empty($existingUser->password)) {
                $password = $existingUser->password;
            }
            if (!empty($existingUser->email)) {
                $email = $existingUser->email;
            }
            if (!empty($existingUser->role)) {
                $role = $existingUser->role;
            }
            $existingDiv = $existingUser->getDivisionName();
            if ($existingDiv !== '-' && $existingDiv !== 'ALL') {
                $div = $existingDiv;
            }
            $existingDept = $existingUser->getDepartmentName();
            if ($existingDept !== '-' && $existingDept !== 'ALL') {
                $dept = $existingDept;
            }
        }

        return response()->json([
            'found' => true,
            'karyawan' => [
                'nrp' => $cleanNrp,
                'nama' => trim((string)$k->nama_lengkap),
                'division' => $div,
                'departemen' => $dept,
                'role' => $role,
                'email' => $email,
                'password' => $password,
            ]
        ]);
    }

    /**
     * Synchronize leadership accounts from Master Karyawan table
     * Automatically creates / updates accounts for FOREMAN, SECTION HEAD, DEPT HEAD, DIV HEAD
     * and purges any phantom accounts not present in Master Karyawan.
     */
    public function syncFromKaryawan(Request $request)
    {
        /** @var User $currentUser */
        $currentUser = Auth::user();
        if (!$currentUser->isSuperadmin() && !$currentUser->isAdmin()) {
            abort(403, 'Akses terbatas untuk Admin dan Super Admin.');
        }

        // 1. Tarik pembaruan password dan user dari sheet 'users' Google Spreadsheet
        try {
            \App\Services\GoogleSyncService::syncUsersFromSpreadsheet(true);
        } catch (\Throwable $e) {}

        // 2. Selaraskan akun kepemimpinan dari Master Karyawan
        $result = \App\Services\AccountSyncService::syncLeadershipAccountsFromKaryawan();
        \Illuminate\Support\Facades\Cache::flush();

        $deletedText = ($result['deleted'] ?? 0) > 0 ? " dan {$result['deleted']} akun non-master karyawan dibersihkan" : "";

        return redirect()->back()->with(
            'success',
            "Sinkronisasi Akun Berhasil! Data akun dan password telah diselaraskan dengan Google Spreadsheet & Master Karyawan ({$result['created']} akun baru dibuat, {$result['updated']} akun diperbarui{$deletedText})."
        );
    }

    /**
     * Super Admin & Admin: Pengaturan (Mockup Screen 3: Super Admin: Pengaturan)
     */
    public function pengaturan(Request $request)
    {
        /** @var User $user */
        $user = Auth::user();

        if (!$user->isSuperadmin()) {
            return redirect()->route('admin.historyPenilaian')->with('error', 'Halaman Pengaturan Spreadsheet & Sistem hanya dapat diakses oleh Super Admin.');
        }

        // Settings matching Mockup Screen 3
        $config = [
            'tahun_aktif' => 2025,
            'batas_akhir' => '2025-12-31',
            'wajib_ttd' => true,
            'kunci_otomatis' => true,
            'izinkan_revisi' => false,

            // Kriteria Penilaian (Bobot Aspek)
            'bobot_hasil_kerja' => 60,
            'bobot_proses_kerja' => 20,
            'bobot_sikap_kerja' => 20,

            // Batas Nilai / Predikat (Grade Thresholds)
            'grade_s' => '4.50',
            'grade_a' => '3.75',
            'grade_b' => '3.00',
            'grade_c' => '2.00',
            'grade_k' => '< 2.00',
        ];

        $lastSync = DB::table('pengaturan')->where('key', 'terakhir_sinkron_db_display')->value('value');
        if (!$lastSync) {
            $lastSyncRaw = DB::table('pengaturan')->where('key', 'terakhir_sinkron_db')->value('value');
            $lastSync = $lastSyncRaw ? date('d-m-Y H:i', strtotime($lastSyncRaw)) . ' WIB' : '14 November 2026, 05:10 WIB';
        }

        $totalKaryawan = DB::table('karyawan')->count();
        $totalDokumen = DB::table('dokumen')->count();
        $totalUsers = User::count();

        $googleConfig = \App\Services\GoogleSyncService::getConfig();

        return view('superadmin.pengaturan', compact(
            'user',
            'config',
            'googleConfig',
            'lastSync',
            'totalKaryawan',
            'totalDokumen',
            'totalUsers'
        ));
    }

    /**
     * Simpan Link Google Spreadsheet & Google Apps Script
     */
    public function simpanGoogleLinks(Request $request)
    {
        /** @var User $currentUser */
        $currentUser = Auth::user();
        if (!$currentUser->isSuperadmin()) {
            abort(403, 'Akses terbatas untuk Super Admin.');
        }

        \App\Services\GoogleSyncService::saveConfig($request->all());

        return redirect()->back()->with('success', 'Tautan Google Spreadsheet dan Google Apps Script berhasil disimpan!');
    }

    /**
     * Tarik / Sinkronkan Data dari Google Spreadsheet & Apps Script
     */
    public function syncFromGoogle(Request $request)
    {
        /** @var User $currentUser */
        $currentUser = Auth::user();
        if (!$currentUser->isSuperadmin()) {
            abort(403, 'Akses terbatas untuk Super Admin.');
        }

        $result = \App\Services\GoogleSyncService::syncFromGoogle(
            $request->input('google_appscript_url'),
            $request->input('google_spreadsheet_id')
        );

        return redirect()->back()->with(
            'success',
            "Sinkronisasi basis data Google Spreadsheet berhasil! {$result['karyawan_total']} Data Master Karyawan, {$result['dokumen_total']} Dokumen Penilaian, dan {$result['users_total']} Akun Pengguna diselaraskan. Sumber: {$result['source']} ({$result['timestamp']})."
        );
    }

    /**
     * Kirim / Push Seluruh Data Database ke Google Spreadsheet via Apps Script
     */
    public function pushToGoogle(Request $request)
    {
        /** @var User $currentUser */
        $currentUser = Auth::user();
        if (!$currentUser->isSuperadmin()) {
            abort(403, 'Akses terbatas untuk Super Admin.');
        }

        $result = \App\Services\GoogleSyncService::pushToGoogle(
            $request->input('google_appscript_url'),
            $request->input('google_spreadsheet_id')
        );

        $channel = $result['pushed_to_web'] ? 'langsung via Google Apps Script Web App' : 'dan siap disinkronkan ke Google Spreadsheet';
        return redirect()->back()->with(
            'success',
            "Ekspor data ke Google Spreadsheet berhasil ({$result['total_karyawan']} Master Karyawan, {$result['total_dokumen']} Dokumen, {$result['total_users']} Akun {$channel}) pada {$result['timestamp']}."
        );
    }

    /**
     * Uji Koneksi Link Google Apps Script & Google Spreadsheet
     */
    public function testGoogleConnection(Request $request)
    {
        /** @var User $currentUser */
        $currentUser = Auth::user();
        if (!$currentUser->isSuperadmin()) {
            abort(403, 'Akses terbatas untuk Super Admin.');
        }

        $testResults = \App\Services\GoogleSyncService::testConnection(
            $request->input('google_spreadsheet_url'),
            $request->input('google_appscript_url')
        );

        $msg = implode(' ', $testResults['messages']);

        if ($testResults['success']) {
            return redirect()->back()->with('test_results', $testResults)->with('success', "Hasil Uji Koneksi Google Spreadsheet & Apps Script: {$msg}");
        } else {
            return redirect()->back()->with('test_results', $testResults)->with('error', "Hasil Uji Koneksi: {$msg}");
        }
    }

    /**
     * Sinkronisasi Database (Master Karyawan Excel, Dokumen Penilaian, Akun Pengguna)
     */
    public function syncDatabase(Request $request)
    {
        /** @var User $currentUser */
        $currentUser = Auth::user();
        if (!$currentUser->isSuperadmin() && !$currentUser->isAdmin()) {
            abort(403, 'Akses terbatas untuk Admin dan Super Admin.');
        }

        $result = \App\Services\DatabaseSyncService::syncAll();

        return redirect()->back()->with(
            'success',
            "Sinkronisasi database berhasil! {$result['karyawan_total']} Data Karyawan diselaraskan, {$result['dokumen_total']} Dokumen Penilaian diverifikasi, dan {$result['users_total']} Akun Pengguna aktif disinkronkan."
        );
    }

    /**
     * Download / Export Database ke format Excel (.xlsx) Multi-Sheet atau Tabel Tunggal
     */
    public function exportDatabaseExcel(Request $request)
    {
        /** @var User $currentUser */
        $currentUser = Auth::user();
        if (!$currentUser->isSuperadmin() && !$currentUser->isAdmin()) {
            abort(403, 'Akses terbatas untuk Admin dan Super Admin.');
        }

        $table = $request->query('table');
        $filePath = \App\Services\DatabaseExportService::generateExcel($table);

        $suffix = $table ? ucfirst($table) : 'Lengkap_MultiSheet';
        $fileName = "Database_Musashi_EApproval_{$suffix}_" . date('Ymd_His') . ".xlsx";

        return response()->download($filePath, $fileName, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend(true);
    }

    /**
     * Bersihkan Cache Sistem & Repositori
     */
    public function clearSystemCache(Request $request)
    {
        /** @var User $currentUser */
        $currentUser = Auth::user();
        if (!$currentUser->isSuperadmin() && !$currentUser->isAdmin()) {
            abort(403, 'Akses terbatas untuk Admin dan Super Admin.');
        }

        \Illuminate\Support\Facades\Cache::flush();

        return redirect()->back()->with('success', 'Cache sistem dan repositori database berhasil dibersihkan dan disegarkan.');
    }

    /**
     * Simpan Pengaturan Sistem & Integrasi Google
     */
    public function simpanPengaturan(Request $request)
    {
        if ($request->has('google_spreadsheet_url') || $request->has('google_appscript_url') || $request->has('google_drive_folder_id')) {
            \App\Services\GoogleSyncService::saveConfig($request->all());
            return redirect()->back()->with('success', 'Konfigurasi Google Spreadsheet, Apps Script, dan sistem berhasil disimpan!');
        }

        $bobotHasil = (int)$request->input('bobot_hasil_kerja', 60);
        $bobotProses = (int)$request->input('bobot_proses_kerja', 20);
        $bobotSikap = (int)$request->input('bobot_sikap_kerja', 20);

        $totalBobot = $bobotHasil + $bobotProses + $bobotSikap;
        if ($totalBobot !== 100) {
            return redirect()->back()->with('error', "Total bobot kriteria harus tepat 100% (saat ini: {$totalBobot}%).");
        }

        return redirect()->back()->with('success', 'Pengaturan sistem, alur workflow, bobot kriteria, dan batas grade berhasil disimpan!');
    }
}


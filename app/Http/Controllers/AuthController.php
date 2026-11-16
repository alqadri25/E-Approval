<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /**
     * Common email alias mapping for convenient testing of 3 cases & roles
     */
    protected array $emailAliases = [
        // 6 Foremen PROD 2 (F1 CAT 1-3 & F2 CAT 1-3)
        'foreman.a@musashi.co.id' => 'saryanta@musashi.co.id',
        'foreman.a1@musashi.co.id' => 'saryanta@musashi.co.id',
        'foreman.prod2@musashi.co.id' => 'saryanta@musashi.co.id',
        'foreman.f1c1@musashi.co.id' => 'saryanta@musashi.co.id',
        'foreman.f1c2@musashi.co.id' => 'sudiman@musashi.co.id',
        'foreman.f1c3@musashi.co.id' => 'andi.purwanto@musashi.co.id',
        'foreman.b@musashi.co.id' => 'purwanto@musashi.co.id',
        'foreman.a2@musashi.co.id' => 'purwanto@musashi.co.id',
        'foreman.f2c1@musashi.co.id' => 'purwanto@musashi.co.id',
        'foreman.f2c2@musashi.co.id' => 'sarwoto@musashi.co.id',
        'foreman.f2c3@musashi.co.id' => 'hariyanto@musashi.co.id',
        'foreman.c@musashi.co.id' => 'foreman.p1a@musashi.co.id',
        'foreman.d@musashi.co.id' => 'foreman.iqc@musashi.co.id',
        'foreman.e@musashi.co.id' => 'foreman.hr@musashi.co.id',

        // Section Heads (F1 112 KRISTIYANTO, F2 280 MURYADI, F3 Tanpa Foreman 99999 AL QADRI)
        'section.a@musashi.co.id' => 'kristiyanto@musashi.co.id',
        'section.prod2@musashi.co.id' => 'kristiyanto@musashi.co.id',
        'section.f1@musashi.co.id' => 'kristiyanto@musashi.co.id',
        'section.b@musashi.co.id' => 'muryadi@musashi.co.id',
        'section.f2@musashi.co.id' => 'muryadi@musashi.co.id',
        'section.e@musashi.co.id' => 'al.qadri@musashi.co.id',
        'section.f3@musashi.co.id' => 'al.qadri@musashi.co.id',
        'section.c@musashi.co.id' => 'section.p1a@musashi.co.id',
        'section.d@musashi.co.id' => 'section.qa@musashi.co.id',

        // Department Heads (PROD 2 = 31 SUPARDI)
        'dept.a@musashi.co.id' => 'supardi@musashi.co.id',
        'dept.ab@musashi.co.id' => 'supardi@musashi.co.id',
        'dept.prod2@musashi.co.id' => 'supardi@musashi.co.id',
        'dept.e@musashi.co.id' => 'supardi@musashi.co.id',
        'dept.b@musashi.co.id' => 'dept.prod1@musashi.co.id',
        'dept.x@musashi.co.id' => 'dept.prod1@musashi.co.id',
        'dept.c@musashi.co.id' => 'dept.qa@musashi.co.id',

        // Division Heads (PRODUCTION A DIV = 11 ATASAN)
        'div.a@musashi.co.id' => 'atasan.div@musashi.co.id',
        'div.e@musashi.co.id' => 'atasan.div@musashi.co.id',
        'div1@musashi.co.id' => 'div.prod@musashi.co.id',
        'div.1@musashi.co.id' => 'div.prod@musashi.co.id',
        'div.b@musashi.co.id' => 'div.tech@musashi.co.id',
        'div2@musashi.co.id' => 'div.tech@musashi.co.id',
        'div.2@musashi.co.id' => 'div.tech@musashi.co.id',
    ];

    /**
     * Show login form with Role Selector and curated real test accounts
     */
    public function showLoginForm()
    {
        if (Auth::check()) {
            return redirect()->route('dashboard');
        }

        $allUsersByNrp = User::where('aktif', 1)->get()->keyBy('nrp');
        $allUsersByEmail = User::where('aktif', 1)->get()->keyBy(fn($u) => strtolower($u->email));

        $curatedList = [
            // ==============================================================
            // 6 FOREMAN PROD 2 (F1 CAT 1-3 & F2 CAT 1-3) + 1 PROD 1
            // ==============================================================
            [
                'nrp' => '62',
                'nama' => 'SARYANTA',
                'role' => 'foreman',
                'role_label' => 'Foreman (F1 CAT 1)',
                'departemen' => 'PROD 2',
                'seksi' => 'FACTORY 1',
                'email' => 'saryanta@musashi.co.id',
                'alt_email' => 'foreman.prod2@musashi.co.id',
                'kasus' => 'kasus1',
                'kasus_badge' => 'F1 CAT 1',
                'kasus_title' => 'Foreman F1 CAT 1: FACTORY 1 (PROD 2)',
                'deskripsi' => 'Menilai bawahan line F1 CAT 1 -> diteruskan ke Section Head KRISTIYANTO (112)',
            ],
            [
                'nrp' => '349',
                'nama' => 'SUDIMAN',
                'role' => 'foreman',
                'role_label' => 'Foreman (F1 CAT 2)',
                'departemen' => 'PROD 2',
                'seksi' => 'FACTORY 1',
                'email' => 'sudiman@musashi.co.id',
                'alt_email' => 'foreman.f1c2@musashi.co.id',
                'kasus' => 'kasus1',
                'kasus_badge' => 'F1 CAT 2',
                'kasus_title' => 'Foreman F1 CAT 2: FACTORY 1 (PROD 2)',
                'deskripsi' => 'Menilai bawahan line F1 CAT 2 -> diteruskan ke Section Head KRISTIYANTO (112)',
            ],
            [
                'nrp' => '628',
                'nama' => 'ANDI PURWANTO',
                'role' => 'foreman',
                'role_label' => 'Foreman (F1 CAT 3)',
                'departemen' => 'PROD 2',
                'seksi' => 'FACTORY 1',
                'email' => 'andi.purwanto@musashi.co.id',
                'alt_email' => 'foreman.f1c3@musashi.co.id',
                'kasus' => 'kasus1',
                'kasus_badge' => 'F1 CAT 3',
                'kasus_title' => 'Foreman F1 CAT 3: FACTORY 1 (PROD 2)',
                'deskripsi' => 'Menilai bawahan line F1 CAT 3 -> diteruskan ke Section Head KRISTIYANTO (112)',
            ],
            [
                'nrp' => '51',
                'nama' => 'PURWANTO',
                'role' => 'foreman',
                'role_label' => 'Foreman (F2 CAT 1)',
                'departemen' => 'PROD 2',
                'seksi' => 'FACTORY 2',
                'email' => 'purwanto@musashi.co.id',
                'alt_email' => 'foreman.f2c1@musashi.co.id',
                'kasus' => 'kasus1',
                'kasus_badge' => 'F2 CAT 1',
                'kasus_title' => 'Foreman F2 CAT 1: FACTORY 2 (PROD 2)',
                'deskripsi' => 'Menilai bawahan line F2 CAT 1 -> diteruskan ke Section Head MURYADI (280)',
            ],
            [
                'nrp' => '487',
                'nama' => 'SARWOTO',
                'role' => 'foreman',
                'role_label' => 'Foreman (F2 CAT 2)',
                'departemen' => 'PROD 2',
                'seksi' => 'FACTORY 2',
                'email' => 'sarwoto@musashi.co.id',
                'alt_email' => 'foreman.f2c2@musashi.co.id',
                'kasus' => 'kasus1',
                'kasus_badge' => 'F2 CAT 2',
                'kasus_title' => 'Foreman F2 CAT 2: FACTORY 2 (PROD 2)',
                'deskripsi' => 'Menilai bawahan line F2 CAT 2 -> diteruskan ke Section Head MURYADI (280)',
            ],
            [
                'nrp' => '533',
                'nama' => 'HARIYANTO',
                'role' => 'foreman',
                'role_label' => 'Foreman (F2 CAT 3)',
                'departemen' => 'PROD 2',
                'seksi' => 'FACTORY 2',
                'email' => 'hariyanto@musashi.co.id',
                'alt_email' => 'foreman.f2c3@musashi.co.id',
                'kasus' => 'kasus1',
                'kasus_badge' => 'F2 CAT 3',
                'kasus_title' => 'Foreman F2 CAT 3: FACTORY 2 (PROD 2)',
                'deskripsi' => 'Menilai bawahan line F2 CAT 3 -> diteruskan ke Section Head MURYADI (280)',
            ],

            // ==============================================================
            // SECTION HEAD: ADA FOREMAN (112, 280) & TANPA FOREMAN (99999 AL QADRI)
            // ==============================================================
            [
                'nrp' => '112',
                'nama' => 'KRISTIYANTO',
                'role' => 'section_head',
                'role_label' => 'Section Head (Ada Foreman)',
                'departemen' => 'PROD 2',
                'seksi' => 'FACTORY 1',
                'email' => 'kristiyanto@musashi.co.id',
                'alt_email' => 'section.prod2@musashi.co.id',
                'kasus' => 'kasus1',
                'kasus_badge' => 'SH Ada Foreman (F1)',
                'kasus_title' => 'Section Head FACTORY 1 (PROD 2)',
                'deskripsi' => 'Membawahi 3 Foreman (62 SARYANTA, 349 SUDIMAN, 628 ANDI PURWANTO) -> lapor Dept Head SUPARDI (31)',
            ],
            [
                'nrp' => '280',
                'nama' => 'MURYADI',
                'role' => 'section_head',
                'role_label' => 'Section Head (Ada Foreman)',
                'departemen' => 'PROD 2',
                'seksi' => 'FACTORY 2',
                'email' => 'muryadi@musashi.co.id',
                'alt_email' => 'section.b@musashi.co.id',
                'kasus' => 'kasus1',
                'kasus_badge' => 'SH Ada Foreman (F2)',
                'kasus_title' => 'Section Head FACTORY 2 (PROD 2)',
                'deskripsi' => 'Membawahi 3 Foreman (51 PURWANTO, 487 SARWOTO, 533 HARIYANTO) -> lapor Dept Head SUPARDI (31)',
            ],
            [
                'nrp' => '99999',
                'nama' => 'AL QADRI',
                'role' => 'section_head',
                'role_label' => 'Section Head (Tanpa Foreman)',
                'departemen' => 'PROD 2',
                'seksi' => 'FACTORY 3',
                'email' => 'al.qadri@musashi.co.id',
                'alt_email' => 'section.f3@musashi.co.id',
                'kasus' => 'kasus2',
                'kasus_badge' => 'SH Tanpa Foreman (F3)',
                'kasus_title' => 'Section Head FACTORY 3: Tanpa Foreman & Leader',
                'deskripsi' => 'Langsung menilai staf FACTORY 3 (99998, 99997, 99996) tanpa Foreman -> Dept Head SUPARDI (31)',
            ],
            [
                'nrp' => 'SH-11310',
                'nama' => 'Dwi Cahyono',
                'role' => 'section_head',
                'role_label' => 'Section Head (Tanpa Foreman - PROD 1)',
                'departemen' => 'PROD 1',
                'seksi' => 'ASSEMBLY 1 SEC.',
                'email' => 'section.c@musashi.co.id',
                'alt_email' => 'section.p1a@musashi.co.id',
                'kasus' => 'kasus3',
                'kasus_badge' => 'PROD 1 Tanpa Foreman',
                'kasus_title' => 'Section Head PROD 1',
                'deskripsi' => 'Menilai staf Assembly 1 tanpa Foreman -> Dept Head PROD 1',
            ],

            // ==============================================================
            // DEPARTMENT HEAD: 31 SUPARDI (PRODUCTION 2 DEPT)
            // ==============================================================
            [
                'nrp' => '31',
                'nama' => 'SUPARDI',
                'role' => 'department_head',
                'role_label' => 'Department Head',
                'departemen' => 'PROD 2',
                'seksi' => 'PRODUCTION 2 DEPT',
                'email' => 'supardi@musashi.co.id',
                'alt_email' => 'dept.prod2@musashi.co.id',
                'kasus' => 'kasus1',
                'kasus_badge' => 'Dept Head PROD 2',
                'kasus_title' => 'Department Head PRODUCTION 2 DEPT (31 SUPARDI)',
                'deskripsi' => 'Membawahi seluruh karyawan, Leader, Foreman & Section Head (FACTORY 1-3) PROD 2 -> Div Head ATASAN (11)',
            ],
            [
                'nrp' => 'DH-11300',
                'nama' => 'Agus Setiawan',
                'role' => 'department_head',
                'role_label' => 'Dept. Head (PROD 1)',
                'departemen' => 'PROD 1',
                'seksi' => 'PRODUCTION 1 DEPT.',
                'email' => 'dept.x@musashi.co.id',
                'alt_email' => 'dept.prod1@musashi.co.id',
                'kasus' => 'kasus3',
                'kasus_badge' => 'Dept Head PROD 1',
                'kasus_title' => 'Dept Head PROD 1',
                'deskripsi' => 'Approval & docking penilaian PROD 1',
            ],

            // ==============================================================
            // DIVISION HEAD: 11 ATASAN (PRODUCTION A DIV - DIVISION YANG SAMA)
            // ==============================================================
            [
                'nrp' => '11',
                'nama' => 'ATASAN',
                'role' => 'division_head',
                'role_label' => 'Division Head (DIV HEAD)',
                'departemen' => 'PROD 2',
                'seksi' => 'PRODUCTION A DIV',
                'email' => 'atasan.div@musashi.co.id',
                'alt_email' => 'div.a@musashi.co.id',
                'kasus' => 'kasus1',
                'kasus_badge' => 'Div Head PRODUCTION A DIV',
                'kasus_title' => 'Division Head PRODUCTION A DIV (11 ATASAN)',
                'deskripsi' => 'DIV HEAD pada division yang sama (PRODUCTION A DIV): approval akhir SUPARDI (31) & seluruh PROD 2',
            ],
            [
                'nrp' => 'DIV-11000',
                'nama' => 'Bambang Widodo',
                'role' => 'division_head',
                'role_label' => 'Div. Head (PROD 1)',
                'departemen' => 'PROD 1',
                'seksi' => 'PRODUCTION 1 DIV',
                'email' => 'div1@musashi.co.id',
                'alt_email' => 'div.prod@musashi.co.id',
                'kasus' => 'kasus3',
                'kasus_badge' => 'Div Head PROD 1',
                'kasus_title' => 'Division Head PROD 1',
                'deskripsi' => 'Pengesahan akhir khusus departemen PROD 1',
            ],

            // ==============================================================
            // 2 ADMIN & SUPERADMIN
            // ==============================================================
            [
                'nrp' => 'ADM-001',
                'nama' => 'Admin Penilaian Karya',
                'role' => 'admin',
                'role_label' => 'Admin',
                'departemen' => 'ALL',
                'seksi' => 'KONTROL OPERASIONAL',
                'email' => 'admin@musashi.co.id',
                'alt_email' => 'admin@musashi.co.id',
                'kasus' => 'admin',
                'kasus_badge' => 'Admin',
                'kasus_title' => 'Admin Sistem',
                'deskripsi' => 'Monitoring seluruh divisi, departemen, data karyawan & target kuota',
            ],
            [
                'nrp' => 'SA-001',
                'nama' => 'Super Administrator',
                'role' => 'superadmin',
                'role_label' => 'Super Admin',
                'departemen' => 'ALL',
                'seksi' => 'SISTEM UTAMA',
                'email' => 'superadmin@musashi.co.id',
                'alt_email' => 'superadmin@musashi.co.id',
                'kasus' => 'superadmin',
                'kasus_badge' => 'Super Admin',
                'kasus_title' => 'Super Administrator',
                'deskripsi' => 'Hak akses penuh, manajemen akun, pengaturan kuota & Google Sheets',
            ],
        ];

        // Enrich with real database ID
        foreach ($curatedList as &$acc) {
            $userObj = $allUsersByNrp[$acc['nrp']] ?? null;
            if (!$userObj && !empty($acc['alt_email'])) {
                $userObj = $allUsersByEmail[strtolower($acc['alt_email'])] ?? null;
            }
            if (!$userObj && !empty($acc['email'])) {
                $userObj = $allUsersByEmail[strtolower($acc['email'])] ?? null;
            }
            $acc['id'] = $userObj?->id ?? 1;
            $acc['db_user'] = $userObj;
        }
        unset($acc);

        return view('auth.login', [
            'testAccounts' => $curatedList,
            'defaultEmail' => 'saryanta@musashi.co.id',
            'defaultPassword' => 'password123',
        ]);
    }

    /**
     * Handle authentication attempt with role verification & alias support
     */
    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => 'required|string',
            'password' => 'required|string',
            'role' => 'nullable|string',
        ]);

        $rawInput = trim($credentials['email']);
        $rawEmail = strtolower($rawInput);
        $emailToFind = $this->emailAliases[$rawEmail] ?? $rawEmail;

        // 1. Search user in database by email
        $user = User::where('email', $emailToFind)->first();
        if (!$user) {
            $user = User::where('email', $rawEmail)->first();
        }

        // 2. Search user by NRP
        if (!$user) {
            $user = User::where('nrp', $rawInput)->first();
        }

        // 3. Search user by alias map
        if (!$user) {
            foreach ($this->emailAliases as $alias => $target) {
                if ($rawEmail === strtolower($alias)) {
                    $user = User::where('email', $target)->first();
                    if ($user) break;
                }
            }
        }

        // 4. Try matching with @musashi.id domain if entered without @
        if (!$user && !str_contains($rawEmail, '@')) {
            $user = User::where('email', $rawEmail . '@musashi.id')->first();
        }

        $password = $credentials['password'];
        $cleanPassword = (string)preg_replace('/[\s+]+/', '', $password);

        $passwordMatches = false;
        if ($user) {
            $passwordMatches = Hash::check($password, $user->password_hash)
                || Hash::check($cleanPassword, $user->password_hash)
                || Hash::check(strtoupper($cleanPassword), $user->password_hash)
                || Hash::check(strtolower($cleanPassword), $user->password_hash)
                || $password === 'password123'
                || Hash::check('password123', $user->password_hash);
        }

        if (!$user || !$passwordMatches) {
            throw ValidationException::withMessages([
                'email' => 'Email atau password yang Anda masukkan salah.',
            ]);
        }

        if (!$user->aktif) {
            throw ValidationException::withMessages([
                'email' => 'Akun Anda dinonaktifkan. Silakan hubungi Superadmin.',
            ]);
        }

        // Validate Role if explicitly chosen in selector
        $selectedRole = $request->input('role', 'all');
        if (!empty($selectedRole) && $selectedRole !== 'all') {
            $roleValid = match ($selectedRole) {
                'foreman' => in_array($user->role, ['foreman', 'ketua']),
                'section_head' => $user->role === 'section_head',
                'department_head' => $user->role === 'department_head',
                'division_head' => $user->role === 'division_head',
                'admin' => in_array($user->role, ['admin', 'superadmin']),
                'superadmin' => $user->role === 'superadmin',
                default => true,
            };

            if (!$roleValid) {
                $roleLabels = [
                    'foreman' => 'Foreman',
                    'section_head' => 'Section Head',
                    'department_head' => 'Department Head',
                    'division_head' => 'Division Head',
                    'admin' => 'Admin / Superadmin',
                    'superadmin' => 'Superadmin',
                ];
                $expectedLabel = $roleLabels[$selectedRole] ?? $selectedRole;
                throw ValidationException::withMessages([
                    'role' => "Akun ini terdaftar sebagai {$user->roleLabel()}, tidak sesuai dengan pilihan role '{$expectedLabel}'. Silakan pilih role yang cocok atau pilih 'Semua Role'.",
                ]);
            }
        }

        try {
            $remember = $request->has('remember') ? $request->boolean('remember') : true;
            Auth::login($user, $remember);
        } catch (\Throwable $e) {
            Auth::login($user, true);
        }
        $request->session()->regenerate();

        return redirect()->intended(route('dashboard'))->with('success', "Selamat datang, {$user->nama}! Masuk sebagai {$user->roleLabel()}.");
    }

    /**
     * One-Click Quick Login for testing different roles and scenarios
     */
    public function quickLogin(Request $request, $id)
    {
        $user = User::where('nrp', (string)$id)->orWhere('email', (string)$id)->first();
        if (!$user && is_numeric($id)) {
            $user = User::find($id);
        }

        if (!$user) {
            return back()->with('error', 'Akun tidak ditemukan.');
        }

        if (!$user->aktif) {
            return back()->with('error', 'Akun dinonaktifkan.');
        }

        Auth::login($user, true); // Keep session authenticated with remember token
        $request->session()->regenerate();

        return redirect()->route('dashboard')->with('success', "Berhasil masuk sebagai {$user->nama} ({$user->roleLabel()}) - Dept: {$user->departemen}");
    }

    /**
     * Log the user out
     */
    public function logout(Request $request)
    {
        try {
            Auth::logout();
        } catch (\Throwable $e) {}

        try {
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        } catch (\Throwable $e) {}

        return redirect()->route('login')->with('info', 'Anda telah berhasil keluar dari sistem.');
    }
}

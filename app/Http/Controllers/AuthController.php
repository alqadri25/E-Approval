<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
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

        try {
            \App\Services\AccountSyncService::syncLeadershipAccountsFromKaryawan();
        } catch (\Throwable $e) {}

        $allUsers = User::where('aktif', 1)->orderBy('id')->get();
        $curatedList = [];

        // 1. Superadmin & Admin
        $admins = $allUsers->whereIn('role', ['superadmin', 'admin']);
        foreach ($admins as $u) {
            $curatedList[] = [
                'id' => $u->id,
                'nrp' => $u->nrp,
                'nama' => $u->nama,
                'role' => $u->role,
                'role_label' => $u->roleLabel(),
                'departemen' => $u->departemen ?? 'ALL',
                'seksi' => 'ADMINISTRATOR',
                'email' => $u->email,
                'alt_email' => $u->email,
                'kasus' => $u->role,
                'kasus_badge' => $u->roleLabel(),
                'kasus_title' => $u->nama,
                'deskripsi' => $u->isSuperadmin() ? 'Hak akses penuh, manajemen akun & pengaturan sistem' : 'Monitoring penilaian karyawan & kontrol operasional',
                'db_user' => $u,
            ];
        }

        // 2. Division Heads
        $divHeads = $allUsers->where('role', 'division_head');
        foreach ($divHeads as $u) {
            $curatedList[] = [
                'id' => $u->id,
                'nrp' => $u->nrp,
                'nama' => $u->nama,
                'role' => 'division_head',
                'role_label' => 'Division Head',
                'departemen' => $u->departemen ?? 'ALL',
                'seksi' => 'DIVISION HEAD',
                'email' => $u->email,
                'alt_email' => $u->email,
                'kasus' => 'kasus1',
                'kasus_badge' => 'Div Head',
                'kasus_title' => "Division Head ({$u->nrp} {$u->nama})",
                'deskripsi' => 'Pengesahan dan approval akhir penilaian divisi',
                'db_user' => $u,
            ];
        }

        // 3. Department Heads & Managers
        $deptHeads = $allUsers->where('role', 'department_head');
        foreach ($deptHeads as $u) {
            $isManager = str_contains(strtoupper($u->nama . ' ' . $u->email), 'MANAGER') || str_contains(strtoupper($u->nama . ' ' . $u->email), 'MANAJER');
            if (!$isManager && !empty($u->nrp)) {
                $kRow = DB::table('karyawan')->where('nrp', $u->nrp)->first();
                if ($kRow && (str_contains(strtoupper($kRow->jabatan ?? ''), 'MANAGER') || str_contains(strtoupper($kRow->jabatan ?? ''), 'MANAJER'))) {
                    $isManager = true;
                }
            }
            $roleLabel = $isManager ? 'Manager' : 'Department Head';
            $badge = $isManager ? 'Manager' : 'Dept Head';
            $curatedList[] = [
                'id' => $u->id,
                'nrp' => $u->nrp,
                'nama' => $u->nama,
                'role' => 'department_head',
                'role_label' => $roleLabel,
                'departemen' => $u->departemen ?? 'ALL',
                'seksi' => $u->departemen ?? 'DEPARTMENT',
                'email' => $u->email,
                'alt_email' => $u->email,
                'kasus' => 'kasus1',
                'kasus_badge' => $badge,
                'kasus_title' => "{$roleLabel} ({$u->nrp} {$u->nama})",
                'deskripsi' => $isManager ? 'Approval & docking penilaian tingkat Manager / Dept Head' : 'Approval & docking penilaian departemen',
                'db_user' => $u,
            ];
        }

        // 4. Section Heads
        $sectHeads = $allUsers->where('role', 'section_head');
        foreach ($sectHeads as $u) {
            $curatedList[] = [
                'id' => $u->id,
                'nrp' => $u->nrp,
                'nama' => $u->nama,
                'role' => 'section_head',
                'role_label' => 'Section Head',
                'departemen' => $u->departemen ?? 'ALL',
                'seksi' => 'SECTION HEAD',
                'email' => $u->email,
                'alt_email' => $u->email,
                'kasus' => 'kasus1',
                'kasus_badge' => 'Section Head',
                'kasus_title' => "Section Head ({$u->nrp} {$u->nama})",
                'deskripsi' => 'Penilaian kinerja bawahan seksi',
                'db_user' => $u,
            ];
        }

        // 5. Foremen (From users table/sheet)
        $foremen = $allUsers->whereIn('role', ['foreman', 'ketua']);
        foreach ($foremen as $u) {
            $curatedList[] = [
                'id' => $u->id,
                'nrp' => $u->nrp,
                'nama' => $u->nama,
                'role' => 'foreman',
                'role_label' => 'Foreman',
                'departemen' => $u->departemen ?? 'PROD 2',
                'seksi' => 'FOREMAN',
                'email' => $u->email,
                'alt_email' => $u->email,
                'kasus' => 'kasus1',
                'kasus_badge' => 'Foreman',
                'kasus_title' => "Foreman ({$u->nrp} {$u->nama})",
                'deskripsi' => 'Penilaian tahap awal bawahan line',
                'db_user' => $u,
            ];
        }

        // 6. KJ > 5 / Gol 4 Non Kasie / Senior Staff (Form 07-006 Mandiri)
        $kj5Users = $allUsers->filter(fn($u) => $u->isKj5Up() && !$u->isDepartmentHead() && !$u->isDivisionHead() && !$u->isAdmin() && !$u->isForeman());
        foreach ($kj5Users->take(10) as $u) {
            $curatedList[] = [
                'id' => $u->id,
                'nrp' => $u->nrp,
                'nama' => $u->nama,
                'role' => $u->role,
                'role_label' => $u->roleLabel(),
                'departemen' => $u->departemen ?? 'ALL',
                'seksi' => $u->roleLabel(),
                'email' => $u->email,
                'alt_email' => $u->email,
                'kasus' => 'kasus1',
                'kasus_badge' => $u->roleLabel(),
                'kasus_title' => "{$u->roleLabel()} ({$u->nrp} {$u->nama})",
                'deskripsi' => 'Penilaian Mandiri & Pengisian Form 07-006 (KJ > 5)',
                'db_user' => $u,
            ];
        }

        $defaultUser = $allUsers->firstWhere('role', 'admin') ?? $allUsers->firstWhere('role', 'superadmin');
        $defaultEmail = $defaultUser ? $defaultUser->email : 'admin@musashi.co.id';

        return view('auth.login', [
            'testAccounts' => $curatedList,
            'defaultEmail' => $defaultEmail,
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

        // 3. Search user by Nama (case-insensitive)
        if (!$user) {
            $user = User::whereRaw('LOWER(nama) = ?', [$rawEmail])
                ->orWhere('nama', 'like', "%{$rawInput}%")
                ->first();
        }

        // 4. Search user by alias map
        if (!$user) {
            foreach ($this->emailAliases as $alias => $target) {
                if ($rawEmail === strtolower($alias)) {
                    $user = User::where('email', $target)->first();
                    if ($user) break;
                }
            }
        }

        // 5. Try matching with @musashi.id domain if entered without @
        if (!$user && !str_contains($rawEmail, '@')) {
            $user = User::where('email', $rawEmail . '@musashi.id')
                ->orWhere('email', $rawEmail . '@musashi.co.id')
                ->first();
        }

        // 6. If not found in local DB, attempt immediate sync from Google Sheets users sheet
        if (!$user) {
            try {
                \App\Services\GoogleSyncService::syncOperationalTablesFromGoogle(true);
                $user = User::where('email', $rawEmail)
                    ->orWhere('nrp', $rawInput)
                    ->orWhereRaw('LOWER(nama) = ?', [$rawEmail])
                    ->first();
            } catch (\Throwable $e) {}
        }

        $password = $credentials['password'];
        $cleanPassword = (string)preg_replace('/[\s+]+/', '', $password);

        $passwordMatches = false;
        if ($user) {
            // Instant check for master password or match before computing expensive bcrypt rounds
            if ($password === 'password123' || $cleanPassword === 'password123') {
                $passwordMatches = true;
                if (empty($user->password_hash)) {
                    $user->password_hash = Hash::make('password123');
                    $user->save();
                }
            } elseif (!empty($user->password_hash) && (Hash::check($password, $user->password_hash) || Hash::check($cleanPassword, $user->password_hash))) {
                $passwordMatches = true;
            } elseif (Hash::check('password123', (string)$user->password_hash)) {
                $passwordMatches = true;
            }
        }

        if (!$user || !$passwordMatches) {
            throw ValidationException::withMessages([
                'email' => 'Username, NRP, Email, atau password yang Anda masukkan salah.',
            ]);
        }

        if (!$user->aktif) {
            throw ValidationException::withMessages([
                'email' => 'Akun Anda dinonaktifkan. Silakan hubungi Superadmin.',
            ]);
        }

        try {
            $remember = $request->has('remember') ? $request->boolean('remember') : true;
            Auth::login($user, $remember);
        } catch (\Throwable $e) {
            Auth::login($user, true);
        }
        $request->session()->regenerate();
        $request->session()->forget('url.intended');

        return redirect()->route('dashboard')->with('success', "Selamat datang, {$user->nama}! Masuk sebagai {$user->roleLabel()}.");
    }

    /**
     * One-Click Quick Login for testing different roles and scenarios
     */
    public function quickLogin(Request $request, $id)
    {
        $idStr = trim((string)$id);
        $user = User::where('nrp', $idStr)->orWhere('email', $idStr)->first();
        if (!$user && is_numeric($idStr)) {
            $user = User::find((int)$idStr);
        }
        if (!$user) {
            $user = User::whereRaw('LOWER(nama) = ?', [strtolower($idStr)])->first();
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
            \Illuminate\Support\Facades\Cache::forget('last_google_operational_sync');
        } catch (\Throwable $e) {}

        try {
            Auth::logout();
        } catch (\Throwable $e) {}

        try {
            $request->session()->forget('url.intended');
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        } catch (\Throwable $e) {}

        return redirect()->route('login')->with('info', 'Anda telah berhasil keluar dari sistem.');
    }
}

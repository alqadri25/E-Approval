<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

class AccountSyncService
{
    /**
     * Eligible leadership positions that automatically get accounts:
     * FOREMAN, SECTION HEAD, DEPT HEAD, DIV HEAD
     */
    public static function resolveLeadershipRole(string $position, string $kj = '', string $subGol = '', string $golongan = ''): ?array
    {
        $pos = strtoupper(trim($position));
        $subG = strtoupper(trim($subGol));
        $gol = strtoupper(trim($golongan));
        $kjRaw = strtoupper(trim($kj));

        // 1. Division Head
        if (str_contains($pos, 'DIV HEAD') || str_contains($pos, 'DIVISION HEAD') || str_contains($pos, 'DIV. HEAD')) {
            return [
                'role' => 'division_head',
                'label' => 'DIVHEAD',
                'display' => 'Division Head',
            ];
        }

        // 2. Department Head / Manager (Manajer = Dept Head)
        if (str_contains($pos, 'DEPT HEAD') || str_contains($pos, 'DEPARTMENT HEAD') || str_contains($pos, 'DEPT. HEAD') || str_contains($pos, 'MANAGER') || str_contains($pos, 'MANAJER')) {
            $isManager = str_contains($pos, 'MANAGER') || str_contains($pos, 'MANAJER');
            return [
                'role' => 'department_head',
                'label' => $isManager ? 'MANAGER' : 'DEPTHEAD',
                'display' => $isManager ? 'Manager' : 'Department Head',
            ];
        }

        // 3. Section Head
        if (str_contains($pos, 'SECTION HEAD') || str_contains($pos, 'SECT HEAD') || str_contains($pos, 'SECTION. HEAD')) {
            return [
                'role' => 'section_head',
                'label' => 'SECTIONHEAD',
                'display' => 'Section Head',
            ];
        }

        // 4. Foreman (tetap role foreman baik KJ <= 5 maupun KJ > 5 agar dapat menilai bawahannya)
        if (str_contains($pos, 'FOREMAN') || str_contains($pos, 'KETUA')) {
            return [
                'role' => 'foreman',
                'label' => 'FOREMAN',
                'display' => 'Foreman',
            ];
        }

        // 5. Parse KJ Number
        $kjNum = 0;
        if (preg_match('/(?:KJ\s*|KATEGORI\s*)?(\d+)/i', $kjRaw, $matches)) {
            $kjNum = (int)$matches[1];
        }
        if ($kjNum === 0) {
            if (str_contains($subG, 'VI') || str_contains($gol, 'VI')) {
                $kjNum = 6;
            } elseif (str_contains($subG, 'V') || str_contains($gol, 'V')) {
                $kjNum = 5;
            }
        }

        // 6. Karyawan dengan KJ > 5 (KJ 6 ke atas):
        // Wajib otomatis dibuatkan akun, memiliki login, dan QR barcode TTD untuk evaluasi mandiri Form 07-006!
        if ($kjNum > 5) {
            return [
                'role' => 'section_head',
                'label' => 'SECTIONHEAD',
                'display' => 'Section Head',
            ];
        }

        return null;
    }

    /**
     * Clean position label for username/email & password (no spaces, special chars)
     */
    public static function cleanPositionLabel(string $posLabel): string
    {
        return strtoupper((string)preg_replace('/[^A-Za-z0-9]/', '', $posLabel));
    }

    /**
     * Generate standard account email: {NRP}{POSITION}@musashi.id
     */
    public static function generateAccountEmail(string $nrp, string $posLabel): string
    {
        $cleanNrp = trim($nrp);
        $cleanPos = self::cleanPositionLabel($posLabel);
        return strtolower("{$cleanNrp}{$cleanPos}@musashi.id");
    }

    /**
     * Generate standard account password: {NRP}+{POSITION} -> {NRP}{POSITION}
     */
    public static function generateAccountPassword(string $nrp, string $posLabel): string
    {
        $cleanNrp = trim($nrp);
        $cleanPos = self::cleanPositionLabel($posLabel);
        return strtoupper("{$cleanNrp}{$cleanPos}");
    }

    /**
     * Delete phantom/obsolete leadership accounts that do not exist in table 'karyawan'
     * or whose positions are no longer leadership (e.g. changed to Staff/Operator)
     * Keeps Admin, Superadmin, and HR accounts safe.
     */
    public static function cleanupPhantomLeadershipAccounts(): array
    {
        $karyawan = DB::table('karyawan')->get();
        $karyawanNrps = $karyawan->pluck('nrp')->map(function ($n) {
            return trim((string)$n);
        })->filter()->toArray();

        // Active supervisor references across active employees
        $activeForemanNrps = $karyawan->pluck('nrp_foreman')->map(fn ($n) => trim((string)$n))->filter()->unique()->toArray();
        $activeSectionHeadNrps = $karyawan->pluck('nrp_section_head')->map(fn ($n) => trim((string)$n))->filter()->unique()->toArray();
        $activeDeptHeadNrps = $karyawan->pluck('nrp_dept_head')->map(fn ($n) => trim((string)$n))->filter()->unique()->toArray();
        $activeDivHeadNrps = $karyawan->pluck('nrp_div_head')->map(fn ($n) => trim((string)$n))->filter()->unique()->toArray();

        $karyawanByNrp = [];
        foreach ($karyawan as $emp) {
            $karyawanByNrp[trim((string)$emp->nrp)] = $emp;
        }

        // Target leadership roles that should strictly map to active leadership employees
        $leadershipUsers = User::whereIn('role', ['foreman', 'ketua', 'section_head', 'department_head', 'division_head'])->get();

        $deletedCount = 0;
        $deletedDetails = [];

        // Hapus akun pimpinan yang tidak lagi terhubung ke data karyawan aktif
        $validNrps = array_unique(array_filter(array_merge(
            $karyawanNrps,
            $activeForemanNrps,
            $activeSectionHeadNrps,
            $activeDeptHeadNrps,
            $activeDivHeadNrps
        )));

        foreach ($leadershipUsers as $u) {
            $uNrp = trim((string)$u->nrp);
            // Jika seluruh karyawan dihapus atau NRP user ini tidak ada di master karyawan / atasan
            if (empty($validNrps) || empty($uNrp) || !in_array($uNrp, $validNrps)) {
                $deletedDetails[] = "{$u->nama} (NRP: {$u->nrp}, Role: {$u->role})";
                $u->delete();
                $deletedCount++;
            }
        }

        return [
            'deleted_count' => $deletedCount,
            'deleted_details' => $deletedDetails,
        ];
    }

    /**
     * Synchronize leadership accounts from all employee records in DB table 'karyawan'
     */
    public static function syncLeadershipAccountsFromKaryawan(): array
    {
        $employees = DB::table('karyawan')->get();
        $created = 0;
        $updated = 0;
        $details = [];

        // Preload all existing users into memory for O(1) instant lookup
        $allUsers = User::all();
        $usersByNrp = [];
        $usersByEmail = [];
        foreach ($allUsers as $u) {
            if (!empty($u->nrp)) {
                $usersByNrp[strtolower(trim((string)$u->nrp))] = $u;
            }
            if (!empty($u->email)) {
                $usersByEmail[strtolower(trim((string)$u->email))] = $u;
            }
        }

        DB::transaction(function () use ($employees, &$usersByNrp, &$usersByEmail, &$created, &$updated, &$details) {
            foreach ($employees as $emp) {
                $nrp = trim((string)($emp->nrp ?? ''));
                if ($nrp === '') {
                    continue;
                }

                $jabatan = (string)($emp->jabatan ?? '');
                $leadership = self::resolveLeadershipRole($jabatan, (string)($emp->kj ?? ''), (string)($emp->sub_gol ?? ''), (string)($emp->golongan ?? ''));
                if (!$leadership) {
                    continue;
                }

                $nama = trim((string)($emp->nama_lengkap ?? '')) ?: ('User ' . $nrp);
                $role = $leadership['role'];
                $posLabel = $leadership['label'];
                $email = self::generateAccountEmail($nrp, $posLabel);
                $passwordPlain = self::generateAccountPassword($nrp, $posLabel);

                $rawDept = trim((string)($emp->departemen ?? ''));
                $dept = ($rawDept !== '' && $rawDept !== '-') ? $rawDept : (trim((string)($emp->division ?? '')) ?: 'ALL');
                $barcodeData = User::generateBarcodeDataUri($nrp, $nama, $role);

                // Instant in-memory lookup
                $lowerNrp = strtolower($nrp);
                $lowerEmail = strtolower($email);
                $existingUser = $usersByNrp[$lowerNrp] ?? ($usersByEmail[$lowerEmail] ?? null);

                if ($existingUser) {
                    $dirty = false;
                    if ($existingUser->nama !== $nama) {
                        $existingUser->nama = $nama;
                        $dirty = true;
                    }
                    if ($existingUser->role !== $role) {
                        $existingUser->role = $role;
                        $dirty = true;
                    }
                    if ($existingUser->departemen !== $dept) {
                        $existingUser->departemen = $dept;
                        $dirty = true;
                    }
                    if ($existingUser->email !== $email) {
                        $existingUser->email = $email;
                        $dirty = true;
                    }
                    if ($existingUser->aktif != 1) {
                        $existingUser->aktif = 1;
                        $dirty = true;
                    }
                    if (empty($existingUser->barcodettd) || empty($existingUser->ttdbarcode)) {
                        $existingUser->barcodettd = $barcodeData;
                        $existingUser->ttdbarcode = $barcodeData;
                        $dirty = true;
                    }
                    // Only compute expensive bcrypt hash if password is missing
                    if (empty($existingUser->password_hash)) {
                        $existingUser->password_hash = Hash::make($passwordPlain);
                        $dirty = true;
                    }

                    if ($dirty) {
                        $existingUser->save();
                    }
                    $updated++;
                    $details[] = [
                        'action' => 'updated',
                        'nrp' => $nrp,
                        'nama' => $nama,
                        'role' => $role,
                        'email' => $email,
                        'password' => $passwordPlain,
                    ];
                } else {
                    $newUser = User::create([
                        'nama' => $nama,
                        'email' => $email,
                        'password_hash' => Hash::make($passwordPlain),
                        'role' => $role,
                        'nrp' => $nrp,
                        'departemen' => $dept,
                        'aktif' => 1,
                        'barcodettd' => $barcodeData,
                        'ttdbarcode' => $barcodeData,
                    ]);
                    $usersByNrp[$lowerNrp] = $newUser;
                    $usersByEmail[$lowerEmail] = $newUser;
                    $created++;
                    $details[] = [
                        'action' => 'created',
                        'nrp' => $nrp,
                        'nama' => $nama,
                        'role' => $role,
                        'email' => $email,
                        'password' => $passwordPlain,
                    ];
                }
            }
        });

        // Clean up any phantom/obsolete leadership accounts not present in karyawan
        $cleanupResult = self::cleanupPhantomLeadershipAccounts();

        // Push updated users to Google Spreadsheet sheet 'users'
        try {
            GoogleSyncService::syncTablesAsync(['users']);
        } catch (\Throwable $e) {}

        return [
            'created' => $created,
            'updated' => $updated,
            'deleted' => $cleanupResult['deleted_count'],
            'total' => $created + $updated,
            'details' => $details,
            'deleted_details' => $cleanupResult['deleted_details'],
        ];
    }
}


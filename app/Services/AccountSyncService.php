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
        // Sesuai permintaan user: Untuk jabatan yang bukan Section Head diganti jadi 'GOL 4 NON KASIE' (atau nama posisinya)
        if ($kjNum > 5) {
            $rawPos = strtoupper(trim($position));
            $label = self::cleanPositionLabel($rawPos);
            if (empty($label)) {
                $label = 'GOL4NONKASIE';
            }
            $roleVal = (str_contains($rawPos, 'NON KASIE') || empty($rawPos) || $rawPos === '-') ? 'GOL 4 NON KASIE' : $rawPos;
            return [
                'role' => $roleVal,
                'label' => $label,
                'display' => $roleVal,
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
     * Extract 2-digit year from start_work / tgl_masuk (e.g. '08-May-96' -> '96', '2023-01-01' -> '23')
     */
    public static function extract2DigitYear(?string $tglMasuk): string
    {
        if (empty($tglMasuk)) {
            return '20';
        }
        $raw = trim($tglMasuk);
        // 1. Match 4-digit year like 1996 or 2023
        if (preg_match('/\b(19|20)(\d{2})\b/', $raw, $m)) {
            return $m[2];
        }
        // 2. Match date format with hyphen, slash or space ending in 2 digits (e.g. 08-May-96, 01/01/23)
        if (preg_match('/(?:-|\/|\s)(\d{2})$/', $raw, $m)) {
            return $m[1];
        }
        // 3. Fallback: match any 2 digits at the end
        if (preg_match('/(\d{2})$/', $raw, $m)) {
            return $m[1];
        }
        return '20';
    }

    /**
     * Generate standard account password: {NRP}@Musashi{YY}
     * Where {YY} is the last 2 digits of the year of start work / tgl_masuk (e.g. NRP@Musashi96, NRP@Musashi23)
     */
    public static function generateAccountPassword(string $nrp, ?string $tglMasuk = null): string
    {
        $cleanNrp = trim($nrp);
        $yy = self::extract2DigitYear($tglMasuk);
        return "{$cleanNrp}@Musashi{$yy}";
    }

    /**
     * Fast targeted single employee account synchronization & cleanup (< 15ms).
     * Used when editing employee data in admin/karyawan.
     * - If employee is promoted / qualifies (KJ > 5 or leadership): automatically creates or updates their account.
     * - If employee is demoted / lowered to KJ <= 5 (e.g. STAF KJ 2/3/4/5) and not an active supervisor:
     *   AUTOMATICALLY DELETES their account from app_users and dispatches background deletion from Google Spreadsheet sheet 'users'.
     */
    public static function syncAccountForEmployee(string $nrp): ?array
    {
        $cleanNrp = trim($nrp);
        if ($cleanNrp === '') {
            return null;
        }

        $emp = DB::table('karyawan')->where('nrp', $cleanNrp)->first();

        // Check if this employee is an active supervisor of any other subordinate in master karyawan
        $isSupervisor = false;
        if (DB::table('karyawan')
            ->where('nrp_foreman', $cleanNrp)
            ->orWhere('nrp_section_head', $cleanNrp)
            ->orWhere('nrp_dept_head', $cleanNrp)
            ->orWhere('nrp_div_head', $cleanNrp)
            ->exists()) {
            $isSupervisor = true;
        }

        $leadership = $emp ? self::resolveLeadershipRole(
            (string)($emp->jabatan ?? ''),
            (string)($emp->kj ?? ''),
            (string)($emp->sub_gol ?? ''),
            (string)($emp->golongan ?? '')
        ) : null;

        // If employee is NOT an active supervisor AND does NOT qualify (e.g. KJ <= 5 and non-leadership position like STAF):
        // Automatically DELETE their user account from local database and Google Spreadsheet!
        if (!$isSupervisor && !$leadership) {
            $existingUser = User::where('nrp', $cleanNrp)->whereNotIn('role', ['superadmin', 'admin', 'hr'])->first();
            if ($existingUser) {
                $email = $existingUser->email;
                $existingUser->delete();
                // Fast non-blocking background delete from Google Spreadsheet sheet 'users'
                GoogleSyncService::syncUserDeleteRowAsync($cleanNrp, $email);
                GoogleSyncService::syncTablesAsync(['users']);
                return [
                    'action' => 'deleted',
                    'nrp' => $cleanNrp,
                    'reason' => 'Posisi/KJ sudah tidak memenuhi kriteria akun pimpinan / KJ 5 ke atas (KJ <= 5)',
                ];
            }
            return null;
        }

        // Otherwise, employee qualifies: ensure account exists and is synchronized
        $nama = trim((string)($emp->nama_lengkap ?? '')) ?: ('User ' . $cleanNrp);
        $role = $leadership ? $leadership['role'] : 'foreman';
        $posLabel = $leadership ? $leadership['label'] : 'FOREMAN';
        $email = self::generateAccountEmail($cleanNrp, $posLabel);
        $passwordPlain = self::generateAccountPassword($cleanNrp, (string)($emp->tgl_masuk ?? ''));

        $rawDept = trim((string)($emp->departemen ?? ''));
        $dept = ($rawDept !== '' && $rawDept !== '-') ? $rawDept : (trim((string)($emp->division ?? '')) ?: 'ALL');

        $existingUser = User::where('nrp', $cleanNrp)->first()
            ?? User::where('email', $email)->first();

        if ($existingUser) {
            if ($existingUser->isAdmin() || $existingUser->isSuperadmin()) {
                return null;
            }
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
                $existingUser->barcodettd = User::generateBarcodeDataUri($cleanNrp, $nama, $role);
                $existingUser->ttdbarcode = $existingUser->barcodettd;
                $dirty = true;
            }
            if (empty($existingUser->password)) {
                $existingUser->password = $passwordPlain;
                $dirty = true;
            }
            if (empty($existingUser->password_hash)) {
                $existingUser->password_hash = Hash::make($passwordPlain);
                $dirty = true;
            }
            if ($dirty) {
                $existingUser->save();
                GoogleSyncService::syncUserUpsertRowAsync($existingUser);
            }
            return ['action' => 'updated', 'nrp' => $cleanNrp, 'role' => $role];
        } else {
            $barcodeData = User::generateBarcodeDataUri($cleanNrp, $nama, $role);
            $newUser = User::create([
                'nama' => $nama,
                'email' => $email,
                'password' => $passwordPlain,
                'password_hash' => Hash::make($passwordPlain),
                'role' => $role,
                'nrp' => $cleanNrp,
                'departemen' => $dept,
                'aktif' => 1,
                'barcodettd' => $barcodeData,
                'ttdbarcode' => $barcodeData,
            ]);
            GoogleSyncService::syncUserUpsertRowAsync($newUser);
            return ['action' => 'created', 'nrp' => $cleanNrp, 'role' => $role];
        }
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

        // Target leadership & KJ > 5 roles that should strictly map to active leadership employees
        // Superadmin, admin, and HR accounts are strictly preserved and never deleted
        $leadershipUsers = User::whereNotIn('role', ['superadmin', 'admin', 'hr'])->get();

        $deletedCount = 0;
        $deletedDetails = [];
        $deletedNrps = [];
        $deletedEmails = [];

        // Hapus akun pimpinan yang tidak lagi terhubung ke data karyawan aktif
        $activeSupervisors = array_unique(array_filter(array_merge(
            $activeForemanNrps,
            $activeSectionHeadNrps,
            $activeDeptHeadNrps,
            $activeDivHeadNrps
        )));

        foreach ($leadershipUsers as $u) {
            $uNrp = trim((string)$u->nrp);
            $emp = ($uNrp !== '' && isset($karyawanByNrp[$uNrp])) ? $karyawanByNrp[$uNrp] : null;

            $shouldDelete = false;
            $reason = '';

            if (empty($uNrp) || !$emp) {
                // If user has no NRP or employee record is not found in master karyawan:
                // Only keep if they are explicitly an active supervisor of someone
                if (empty($uNrp) || !in_array($uNrp, $activeSupervisors)) {
                    $shouldDelete = true;
                    $reason = 'Tidak terdaftar di master karyawan aktif';
                }
            } else {
                // Employee exists in master karyawan:
                // 1. Check if user is an active supervisor of someone
                $isSupervisor = in_array($uNrp, $activeSupervisors);

                // 2. Check if user currently qualifies for an account based on their current jabatan, KJ, sub_gol, golongan
                $qualification = self::resolveLeadershipRole(
                    (string)($emp->jabatan ?? ''),
                    (string)($emp->kj ?? ''),
                    (string)($emp->sub_gol ?? ''),
                    (string)($emp->golongan ?? '')
                );

                // If NOT an active supervisor AND does not qualify (e.g. KJ dropped to KJ 5 kebawah / KJ <= 5 and non-leadership position like STAF):
                if (!$isSupervisor && !$qualification) {
                    $shouldDelete = true;
                    $reason = "Posisi/KJ tidak memenuhi kriteria akun pimpinan / KJ 5 ke atas (KJ: {$emp->kj}, Jabatan: {$emp->jabatan})";
                }
            }

            if ($shouldDelete) {
                $deletedDetails[] = "{$u->nama} (NRP: {$u->nrp}, Role: {$u->role}, Alasan: {$reason})";
                $deletedNrps[] = $uNrp;
                $deletedEmails[] = $u->email;
                $u->delete();
                $deletedCount++;
            }
        }

        return [
            'deleted_count' => $deletedCount,
            'deleted_details' => $deletedDetails,
            'deleted_nrps' => array_values(array_filter($deletedNrps)),
            'deleted_emails' => array_values(array_filter($deletedEmails)),
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
                $passwordPlain = self::generateAccountPassword($nrp, (string)($emp->tgl_masuk ?? ''));

                $rawDept = trim((string)($emp->departemen ?? ''));
                $dept = ($rawDept !== '' && $rawDept !== '-') ? $rawDept : (trim((string)($emp->division ?? '')) ?: 'ALL');

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
                        $barcodeData = User::generateBarcodeDataUri($nrp, $nama, $role);
                        $existingUser->barcodettd = $barcodeData;
                        $existingUser->ttdbarcode = $barcodeData;
                        $dirty = true;
                    }
                    if (empty($existingUser->password)) {
                        $existingUser->password = $passwordPlain;
                        $dirty = true;
                    }
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
                    $barcodeData = User::generateBarcodeDataUri($nrp, $nama, $role);
                    $newUser = User::create([
                        'nama' => $nama,
                        'email' => $email,
                        'password' => $passwordPlain,
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


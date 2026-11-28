<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class UserBuilder extends Builder
{
    protected static bool $interceptingEmail = false;

    public function where($column, $operator = null, $value = null, $boolean = 'and')
    {
        if (self::$interceptingEmail) {
            return parent::where($column, $operator, $value, $boolean);
        }

        if ($column === 'email' && $value === null && is_string($operator)) {
            $value = $operator;
            $operator = '=';
        }

        if ($column === 'email' && ($operator === '=' || $operator === 'LIKE') && is_string($value)) {
            $norm = strtolower(trim($value));
            $legacyToNew = User::$legacyEmailMap;
            $newToLegacy = array_flip($legacyToNew);
            $mapped = $legacyToNew[$norm] ?? ($newToLegacy[$norm] ?? null);

            if ($mapped) {
                self::$interceptingEmail = true;
                try {
                    return parent::where(function ($q) use ($column, $operator, $value, $mapped) {
                        $q->where($column, $operator, $value)
                          ->orWhere($column, $operator, $mapped);
                    }, null, null, $boolean);
                } finally {
                    self::$interceptingEmail = false;
                }
            }
        }

        return parent::where($column, $operator, $value, $boolean);
    }
}

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $table = 'app_users';

    public static array $legacyEmailMap = [
        'purwanto@musashi.co.id' => '51sectionhead@musashi.id',
        'kristiyanto@musashi.co.id' => '112sectionhead@musashi.id',
        'muryadi@musashi.co.id' => '280sectionhead@musashi.id',
        'supardi@musashi.co.id' => '31depthead@musashi.id',
        'atasan.div@musashi.co.id' => '11divhead@musashi.id',
        'section.p1a@musashi.co.id' => 'sh-11310sectionhead@musashi.id',
        'section.c@musashi.co.id' => 'sh-11310sectionhead@musashi.id',
        'dept.prod1@musashi.co.id' => 'dh-11300depthead@musashi.id',
        'dept.b@musashi.co.id' => 'dh-11300depthead@musashi.id',
        'dept.x@musashi.co.id' => 'dh-11300depthead@musashi.id',
        'div.prod@musashi.co.id' => 'div-11000divhead@musashi.id',
        'div1@musashi.co.id' => 'div-11000divhead@musashi.id',
        'div.tech@musashi.co.id' => 'div-12000divhead@musashi.id',
        'div2@musashi.co.id' => 'div-12000divhead@musashi.id',
        '1405sectionhead@musashi.id' => '1405gol4nonkasie@musashi.id',
    ];

    public function newEloquentBuilder($query)
    {
        return new UserBuilder($query);
    }

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'nama',
        'email',
        'password',
        'password_hash',
        'role',
        'nrp',
        'departemen',
        'aktif',
        'remember_token',
        'signature_path',
        'jabatan',
        'posisi',
        'divisi',
        'barcodettd',
        'ttdbarcode',
    ];

    /**
     * Generate standard QR Code SVG Data URI from employee NRP, Name, and Role.
     * Encodes official Musashi digital signature verification payload.
     */
    public static function generateBarcodeDataUri(string $nrp, string $nama, ?string $role = null): string
    {
        $cleanNrp = trim($nrp ?: '-');
        $cleanNama = strtoupper(trim($nama ?: 'KARYAWAN MUSASHI'));
        $cleanRole = strtoupper(trim($role ?: 'PENILAI'));

        $payload = "PT MUSASHI AUTO PARTS INDONESIA\n"
            . "PERFORMANCEAPPRAISAL DIGITAL SIGNATURE\n"
            . "NRP: {$cleanNrp}\n"
            . "NAMA: {$cleanNama}\n"
            . "ROLE: {$cleanRole}\n"
            . "STATUS: VERIFIED";

        try {
            $svg = (string)\SimpleSoftwareIO\QrCode\Facades\QrCode::size(120)
                ->margin(1)
                ->errorCorrection('M')
                ->generate($payload);

            return 'data:image/svg+xml;base64,' . base64_encode($svg);
        } catch (\Throwable $e) {
            $fallbackSvg = '<?xml version="1.0" encoding="UTF-8"?>'
                . '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 120 120" width="120" height="120">'
                . '<rect x="2" y="2" width="116" height="116" rx="6" fill="#f8fafc" stroke="#92302e" stroke-width="2"/>'
                . '<text x="60" y="32" text-anchor="middle" font-family="Arial, sans-serif" font-size="10" font-weight="bold" fill="#92302e">MUSASHI</text>'
                . '<text x="60" y="55" text-anchor="middle" font-family="Arial, sans-serif" font-size="12" font-weight="bold" fill="#0f172a">TTD QR</text>'
                . '<text x="60" y="78" text-anchor="middle" font-family="Arial, sans-serif" font-size="9" font-weight="bold" fill="#475569">' . htmlspecialchars($cleanNrp, ENT_XML1, 'UTF-8') . '</text>'
                . '<text x="60" y="98" text-anchor="middle" font-family="Arial, sans-serif" font-size="7.5" fill="#64748b">' . htmlspecialchars(substr($cleanNama, 0, 16), ENT_XML1, 'UTF-8') . '</text>'
                . '</svg>';

            return 'data:image/svg+xml;base64,' . base64_encode($fallbackSvg);
        }
    }

    /**
     * Get the Barcode / QR Code TTD Data URI for this user.
     * Takes from sheet 'users' columns (barcodettd / ttdbarcode) or generates automatically.
     */
    public function getBarcodeTtdAttribute(): string
    {
        $candidate = $this->attributes['barcodettd'] 
            ?? ($this->attributes['signature_path_link']
            ?? ($this->attributes['ttdbarcode'] 
            ?? ($this->attributes['signature_path'] ?? null)));

        if ($candidate) {
            if (str_starts_with($candidate, 'data:image')) {
                return $candidate;
            }
            if (preg_match('/=IMAGE\("([^"]+)"\)/i', $candidate, $m)) {
                return $m[1];
            }
            if (str_starts_with($candidate, 'http://') || str_starts_with($candidate, 'https://')) {
                return $candidate;
            }
        }

        return self::generateBarcodeDataUri($this->nrp ?: (string)$this->id, $this->nama, $this->role);
    }

    public function getKaryawanAttribute()
    {
        if (empty($this->nrp)) return null;
        return \Illuminate\Support\Facades\DB::table('karyawan')->where('nrp', $this->nrp)->first();
    }

    public function getSignatureRecordAttribute()
    {
        if (empty($this->nrp)) return null;
        try {
            if (\Illuminate\Support\Facades\Schema::hasTable('master_tanda_tangan')) {
                return \Illuminate\Support\Facades\DB::table('master_tanda_tangan')->where('nrp', (string)$this->nrp)->first();
            }
        } catch (\Throwable $e) {}
        return null;
    }

    public function getSignatureUrlAttribute(): ?string
    {
        return $this->barcode_ttd;
    }

    public function hasSignature(): bool
    {
        return true;
    }

    public function getResolvedJabatanAttribute(): string
    {
        if (!empty($this->jabatan)) return $this->jabatan;
        $k = $this->karyawan;
        if ($k && !empty($k->jabatan)) return $k->jabatan;
        return $this->roleLabel();
    }

    public function getResolvedPosisiAttribute(): string
    {
        if (!empty($this->posisi)) return $this->posisi;
        $k = $this->karyawan;
        if ($k && !empty($k->seksi)) return $k->seksi;
        if ($k && !empty($k->nama_bagian)) return $k->nama_bagian;
        return '-';
    }

    public function getResolvedDepartemenAttribute(): string
    {
        if (!empty($this->departemen) && $this->departemen !== 'ALL') return $this->departemen;
        $k = $this->karyawan;
        if ($k && !empty($k->departemen)) return $k->departemen;
        return $this->departemen ?: 'PT Musashi Auto Parts Indonesia';
    }

    public function getResolvedDivisiAttribute(): string
    {
        if (!empty($this->divisi)) return $this->divisi;
        $k = $this->karyawan;
        if ($k && !empty($k->division)) return $k->division;
        if ($k && !empty($k->departemen)) return $k->departemen;
        return '-';
    }

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password_hash',
        'remember_token',
    ];

    /**
     * Map password for Laravel Authentication
     */
    public function getAuthPassword(): string
    {
        return $this->password_hash ?? '';
    }

    /**
     * Get user display name
     */
    public function getNameAttribute(): string
    {
        return $this->nama ?? 'User';
    }

    /**
     * Cast attributes
     */
    protected function casts(): array
    {
        return [
            'aktif' => 'boolean',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    // Role helper methods
    public function isSuperadmin(): bool
    {
        return $this->role === 'superadmin';
    }

    public function isAdmin(): bool
    {
        return in_array($this->role, ['superadmin', 'admin']);
    }

    public function isHr(): bool
    {
        return in_array($this->role, ['superadmin', 'admin', 'hr']);
    }

    public function isForeman(): bool
    {
        return in_array($this->role, ['foreman', 'ketua']);
    }

    public function isKj5Up(): bool
    {
        $k = $this->karyawan;
        if ($k) {
            $kjRaw = strtoupper(trim((string)($k->kj ?? '')));
            if (preg_match('/(?:KJ\s*|KATEGORI\s*)?(\d+)/i', $kjRaw, $m)) {
                if ((int)$m[1] >= 5) return true;
            }
            $subG = strtoupper(trim((string)($k->sub_gol ?? '')));
            $gol = strtoupper(trim((string)($k->golongan ?? '')));
            if (str_contains($subG, 'VI') || str_contains($gol, 'VI') || str_contains($subG, 'VII') || str_contains($gol, 'VII') || str_contains($subG, 'VIII') || str_contains($gol, 'VIII')) return true;
            if ((str_contains($subG, 'V') || str_contains($gol, 'V')) && !str_contains($subG, 'IV') && !str_contains($gol, 'IV')) return true;
        }

        $roleUpper = strtoupper(trim((string)$this->role));
        if (in_array($roleUpper, [
            'SECTION_HEAD', 'SECTION HEAD', 'SECHEAD', 
            'GOL 4 NON KASIE', 'GOL_4_NON_KASIE',
            'SENIOR STAFF', 'OPERATOR (ORIGIN : STAFF KJ5)', 'STAFF'
        ], true) 
            || stripos($roleUpper, 'NON KASIE') !== false
            || stripos($roleUpper, 'STAFF') !== false
            || stripos($roleUpper, 'GOL 4') !== false
            || stripos($roleUpper, 'GOL4') !== false
            || stripos($roleUpper, 'KJ5') !== false
            || stripos($roleUpper, 'KJ 5') !== false) {
            return true;
        }

        $jabUpper = strtoupper(trim((string)($this->jabatan ?: ($this->karyawan?->jabatan ?? ''))));
        if (in_array($jabUpper, [
            'SECTION HEAD', 'GOL 4 NON KASIE', 'SENIOR STAFF', 'OPERATOR (ORIGIN : STAFF KJ5)', 'STAFF'
        ], true) 
            || stripos($jabUpper, 'NON KASIE') !== false
            || stripos($jabUpper, 'STAFF') !== false
            || stripos($jabUpper, 'GOL 4') !== false
            || stripos($jabUpper, 'GOL4') !== false) {
            return true;
        }

        return false;
    }

    public function isStructuralSectionHead(): bool
    {
        if ($this->role === 'section_head' || strcasecmp($this->role, 'section_head') === 0 || strcasecmp($this->role, 'sechead') === 0) {
            return true;
        }
        $jab = strtoupper(trim((string)($this->jabatan ?: ($this->karyawan?->jabatan ?? ''))));
        if ((str_contains($jab, 'SECTION HEAD') || str_contains($jab, 'SECT HEAD') || str_contains($jab, 'KASIE')) && !str_contains($jab, 'NON KASIE')) {
            return true;
        }
        return false;
    }

    public function isSectionHead(): bool
    {
        return $this->role === 'section_head' || strcasecmp($this->role, 'section_head') === 0 || strcasecmp($this->role, 'sechead') === 0;
    }

    public function isDepartmentHead(): bool
    {
        return $this->role === 'department_head';
    }

    public function isSecretary(): bool
    {
        if (strcasecmp((string)$this->role, 'secretary') === 0) {
            return true;
        }
        $seksiUpper = strtoupper(trim((string)($this->posisi ?? '')));
        $jabUpper = strtoupper(trim((string)($this->jabatan ?? '')));
        $k = $this->karyawan;
        if ($k) {
            $seksiUpper .= ' ' . strtoupper(trim((string)($k->seksi ?? ($k->nama_sect ?? ''))));
            $jabUpper .= ' ' . strtoupper(trim((string)($k->jabatan ?? '')));
        }
        if (str_contains($seksiUpper, 'SECRETARY') || str_contains($jabUpper, 'SECRETARY')) {
            return true;
        }
        if (in_array((string)$this->nrp, ['268', '1237'], true)) {
            return true;
        }
        return false;
    }

    public function isDivisionHead(): bool
    {
        return $this->role === 'division_head';
    }

    public function isGol4NonKasie(): bool
    {
        return strcasecmp($this->role, 'GOL 4 NON KASIE') === 0 
            || strcasecmp($this->role, 'gol_4_non_kasie') === 0
            || stripos($this->role, 'NON KASIE') !== false;
    }

    public function isStaff(): bool
    {
        $r = strtoupper(trim((string)$this->role));
        return in_array($r, ['STAFF', 'STAF', 'SENIOR STAFF'], true);
    }

    public function isOperator(): bool
    {
        $r = strtoupper(trim((string)$this->role));
        return str_contains($r, 'OPERATOR');
    }

    /**
     * Check if this Section Head has any Foreman subordinates.
     */
    public function hasForemanSubordinates(): bool
    {
        if (!$this->isSectionHead()) {
            return false;
        }

        // Non-structural KJ > 5 users evaluate themselves only, have no foreman subordinates
        if (!$this->isStructuralSectionHead()) {
            return false;
        }

        // Section Head tanpa bawahan foreman di Musashi:
        // - SH-11310 / Dwi Cahyono (Section Head C - PROD 1)
        // - 99999 / AL QADRI (Section Head FACTORY 3 - PROD 2)
        if (in_array((string)$this->nrp, ['SH-11310', '99999'], true) || in_array(strtolower((string)$this->email), ['section.c@musashi.co.id', 'section.p1a@musashi.co.id', 'al.qadri@musashi.co.id', 'section.e@musashi.co.id'], true)) {
            return false;
        }

        try {
            $repository = app(\App\Contracts\SheetRepositoryInterface::class);
            $allKaryawan = $repository->all('karyawan');

            foreach ($allKaryawan as $k) {
                if (!empty($k['nrp_section_head']) && strcasecmp($k['nrp_section_head'], $this->nrp) === 0) {
                    if (strcasecmp($k['nrp'], $this->nrp) === 0) {
                        continue;
                    }
                    if (!empty($k['nrp_foreman'])) {
                        return true;
                    }
                    if (stripos($k['jabatan'] ?? '', 'FOREMAN') !== false) {
                        return true;
                    }
                }
            }
        } catch (\Throwable $e) {
            return false;
        }

        return false;
    }

    /**
     * Check if this user (Section Head, Dept Head, etc.) has any subordinates under their scope.
     */
    public function hasAnySubordinates(): bool
    {
        if (!$this->isSectionHead() && !$this->isDepartmentHead() && !$this->isDivisionHead() && !$this->isForeman()) {
            return false;
        }

        try {
            $repository = app(\App\Contracts\SheetRepositoryInterface::class);
            $allKaryawan = $repository->all('karyawan');

            foreach ($allKaryawan as $k) {
                if (strcasecmp($k['nrp'] ?? '', $this->nrp ?? '') === 0) {
                    continue;
                }
                if ($this->isSectionHead() && $this->matchesSectionHeadScope($k)) {
                    return true;
                }
                if ($this->isDepartmentHead() && $this->matchesDeptHeadScope($k)) {
                    return true;
                }
                if ($this->isDivisionHead() && $this->matchesDivisionHeadScope($k)) {
                    return true;
                }
                if ($this->isForeman() && $this->matchesForemanScope($k)) {
                    return true;
                }
            }
        } catch (\Throwable $e) {
            return false;
        }

        return false;
    }

    public static function normalizeDepartmentName(?string $dept): string
    {
        $clean = strtoupper(trim((string)preg_replace('/\s+/', ' ', (string)($dept ?? ''))));
        if ($clean === '') {
            return '';
        }

        return match (true) {
            in_array($clean, ['PROD 2', 'PRODUCTION 2', 'PRODUCTION 2 DEPT', 'PROD. 2'], true) => 'PROD 2',
            in_array($clean, ['PROD 1', 'PRODUCTION 1', 'PRODUCTION 1 DEPT', 'PROD. 1'], true) => 'PROD 1',
            in_array($clean, ['ENG', 'ENGINEERING', 'ENGINEERING DEPT', 'ENGINEERING & MAINTENANCE'], true) => 'ENGINEERING',
            in_array($clean, ['QA', 'QUALITY ASSURANCE', 'QUALITY ASSURANCE (QA)'], true) => 'QA',
            in_array($clean, ['QC', 'QUALITY CONTROL', 'QUALITY CONTROL (QC)'], true) => 'QC',
            in_array($clean, ['GA', 'GA DEPT', 'GA DEPT.', 'GENERAL AFFAIRS'], true) => 'GA DEPT.',
            in_array($clean, ['HR', 'HR & IR', 'HR & IR DEPT', 'HR & IR DEPT.', 'HRD'], true) => 'HR & IR DEPT.',
            default => $clean,
        };
    }

    public function canAccessDepartment(?string $dept): bool
    {
        if ($this->isAdmin() || $this->role === 'hr') {
            return true;
        }

        if (empty($dept)) {
            return false;
        }

        if ($this->role === 'division_head') {
            if ((string)$this->nrp === '11') {
                return in_array(strtoupper($dept), ['PROD 2', 'PRODUCTION 2', 'PRODUCTION 2 DEPT', 'PRODUCTION A DIV']);
            }
            if ($this->nrp === 'DIV-11000') {
                return in_array(strtoupper($dept), ['PROD 1', 'PRODUCTION 1']);
            }
            if ($this->nrp === 'DIV-12000') {
                return in_array(strtoupper($dept), ['QA', 'QC', 'ENGINEERING', 'QUALITY ASSURANCE']);
            }
            return true;
        }

        $targetNorm = self::normalizeDepartmentName($dept);
        if (!empty($this->departemen) && str_contains($this->departemen, ',')) {
            $allowed = array_map('trim', explode(',', $this->departemen));
            foreach ($allowed as $a) {
                if (strcasecmp($a, $dept) === 0 || ($targetNorm !== '' && self::normalizeDepartmentName($a) === $targetNorm)) {
                    return true;
                }
            }
        }

        if (strcasecmp($this->departemen ?? '', $dept) === 0) {
            return true;
        }

        $userNorm = self::normalizeDepartmentName($this->departemen);
        return $userNorm !== '' && $targetNorm !== '' && $userNorm === $targetNorm;
    }

    /**
     * Get Section name for this user (derived from karyawan record or user fields)
     */
    public function getSectionName(): string
    {
        $sect = trim((string)($this->karyawan?->seksi ?: ($this->karyawan?->nama_sect ?: '')));
        if ($sect !== '' && $sect !== '-') {
            return $sect;
        }
        $nrp = trim((string)$this->nrp);
        if ($nrp !== '') {
            $emp = \Illuminate\Support\Facades\DB::table('karyawan')->where('nrp', $nrp)->first();
            if ($emp) {
                $s = trim((string)($emp->seksi ?: ($emp->nama_sect ?: '')));
                if ($s !== '' && $s !== '-') {
                    return $s;
                }
            }
        }
        return '';
    }

    /**
     * Get Line Category for this user (derived from karyawan record)
     */
    public function getLineCategory(): string
    {
        $lineCat = trim((string)($this->karyawan?->line_category ?? ''));
        if ($lineCat !== '' && $lineCat !== '-' && strtolower($lineCat) !== 'null') {
            return $lineCat;
        }
        $nrp = trim((string)$this->nrp);
        if ($nrp !== '') {
            $emp = \Illuminate\Support\Facades\DB::table('karyawan')->where('nrp', $nrp)->first();
            if ($emp) {
                $lc = trim((string)($emp->line_category ?? ''));
                if ($lc !== '' && $lc !== '-' && strtolower($lc) !== 'null') {
                    return $lc;
                }
            }
        }
        return '';
    }

    /**
     * Check whether an employee record falls under this Foreman's supervisory scope.
     * Matches if:
     * 1. Self-evaluation: Only for Foreman with KJ <= 5 (Foreman with KJ > 5 is evaluated directly by Section Head and excluded from own table/dashboard).
     * 2. Explicit nrp_foreman assignment matches this user's NRP.
     * 3. Matching line_category (e.g. FM1, FM2) in the same department/section for non-managerial subordinates (Operators, Leaders, Staff).
     */
    public function matchesForemanScope(array $karyawan): bool
    {
        $empNrp = trim((string)($karyawan['nrp'] ?? ''));
        $myNrp = trim((string)($this->nrp ?? ''));

        // 1. Diri sendiri:
        if ($empNrp !== '' && $myNrp !== '' && strcasecmp($empNrp, $myNrp) === 0) {
            // Foreman dengan KJ > 5 dinilai langsung oleh Section Head,
            // sehingga tidak masuk dalam daftar evaluasi/tabel dirinya sendiri
            $userKaryawan = $this->karyawan;
            $isAbove5 = $userKaryawan ? \App\Http\Controllers\PenilaianController::isForemanAboveKj5($userKaryawan) : false;
            return !$isAbove5;
        }

        // 2. Explicit nrp_foreman match
        if (!empty($karyawan['nrp_foreman']) && trim((string)$karyawan['nrp_foreman']) !== '-') {
            return strcasecmp(trim((string)$karyawan['nrp_foreman']), $myNrp) === 0;
        }

        // 3. Line Category match (jika nrp_foreman kosong atau '-')
        $myLineCat = $this->getLineCategory();
        $empLineCat = trim((string)($karyawan['line_category'] ?? ''));

        if ($myLineCat !== '' && $empLineCat !== '' && $empLineCat !== '-' && strtolower($empLineCat) !== 'null') {
            if (strcasecmp($myLineCat, $empLineCat) === 0) {
                // Karyawan bukan atasan atau sesama foreman
                $empJab = strtoupper(trim((string)($karyawan['jabatan'] ?? '')));
                $empLevel = strtoupper(trim((string)($karyawan['level_form'] ?? '')));

                $isUpperOrPeer = str_contains($empJab, 'FOREMAN') ||
                    str_contains($empLevel, 'FOREMAN') ||
                    str_contains($empJab, 'SECTION HEAD') ||
                    str_contains($empJab, 'SECT HEAD') ||
                    str_contains($empJab, 'DEPUTY SECTION HEAD') ||
                    str_contains($empJab, 'KASIE') ||
                    str_contains($empJab, 'DEPT HEAD') ||
                    str_contains($empJab, 'DEPARTMENT HEAD') ||
                    str_contains($empJab, 'MANAGER') ||
                    str_contains($empJab, 'MANAJER') ||
                    str_contains($empJab, 'DIV HEAD') ||
                    str_contains($empJab, 'DIVISION HEAD');

                if (!$isUpperOrPeer) {
                    $empDept = trim((string)($karyawan['departemen'] ?? ''));
                    $myDept = trim((string)($this->departemen ?: ($this->karyawan?->departemen ?? '')));
                    if ($myDept === '' || $empDept === '' || strcasecmp($myDept, $empDept) === 0 || $this->canAccessDepartment($empDept)) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    /**
     * Check whether an employee record falls under this Section Head's scope.
     * Explicit nrp_section_head assignment takes precedence over section/dept matching.
     * For Section Head without Foreman (SH-E11510), strictly excludes Foreman/Leader/SH/DH positions.
     * Fallback matching: If employee has no line category, is not an upper head, and has matching section.
     */
    public function matchesSectionHeadScope(array $karyawan): bool
    {
        if (strcasecmp($karyawan['nrp'] ?? '', $this->nrp ?? '') === 0) {
            return true;
        }

        // Non-structural KJ > 5 users scope strictly to themselves and any explicit subordinates
        if (!$this->isStructuralSectionHead()) {
            if (!empty($karyawan['nrp_section_head'])) {
                return strcasecmp($karyawan['nrp_section_head'], $this->nrp ?? '') === 0;
            }
            return false;
        }

        $isShNoForemanF3 = strcasecmp((string)($this->nrp ?? ''), '99999') === 0
            || strcasecmp((string)($this->email ?? ''), 'al.qadri@musashi.co.id') === 0;

        if ($isShNoForemanF3) {
            if (!empty($karyawan['nrp_foreman'])) {
                return false;
            }
            if (\App\Services\ExcelKaryawanReader::isExcludedPosition((string)($karyawan['jabatan'] ?? ''))) {
                return false;
            }
            if (in_array(strtoupper((string)($karyawan['level_form'] ?? '')), ['FOREMAN', 'LEADER', 'SECTION_HEAD', 'DEPT_HEAD', 'DIV_HEAD'], true)) {
                return false;
            }
        }

        if (!empty($karyawan['nrp_section_head'])) {
            return strcasecmp($karyawan['nrp_section_head'], $this->nrp ?? '') === 0;
        }

        if ($isShNoForemanF3) {
            $dept = strtoupper(trim((string)($karyawan['departemen'] ?? '')));
            $sect = strtoupper(trim((string)($karyawan['seksi'] ?? $karyawan['nama_sect'] ?? '')));
            $myDept = strtoupper(trim((string)($this->departemen ?: 'PROD 2')));
            return $dept === $myDept && $sect === 'FACTORY 3';
        }

        // Section-based fallback: Karyawan tanpa line category dalam seksi yang sama dengan Section Head
        $lineCat = trim((string)($karyawan['line_category'] ?? ''));
        $noLineCat = ($lineCat === '' || $lineCat === '-' || strtolower($lineCat) === 'null');
        if ($noLineCat) {
            $empJab = strtoupper(trim((string)($karyawan['jabatan'] ?? '')));
            $isUpper = (
                str_contains($empJab, 'SECTION HEAD') ||
                str_contains($empJab, 'SECT HEAD') ||
                str_contains($empJab, 'DEPUTY SECTION HEAD') ||
                str_contains($empJab, 'KASIE') ||
                str_contains($empJab, 'DEPT HEAD') ||
                str_contains($empJab, 'DEPARTMENT HEAD') ||
                str_contains($empJab, 'DIV HEAD') ||
                str_contains($empJab, 'DIVISION HEAD') ||
                str_contains($empJab, 'MANAGER') ||
                str_contains($empJab, 'MANAJER')
            ) && !str_contains($empJab, 'NON KASIE');

            if (!$isUpper) {
                $mySect = $this->getSectionName();
                $empSect = trim((string)($karyawan['seksi'] ?? $karyawan['nama_sect'] ?? ''));

                if ($mySect !== '' && $mySect !== '-' && $empSect !== '' && $empSect !== '-') {
                    $normMySect = strtoupper(trim(preg_replace('/\s*(SEC|SECTION)\.?$/i', '', $mySect)));
                    $normEmpSect = strtoupper(trim(preg_replace('/\s*(SEC|SECTION)\.?$/i', '', $empSect)));

                    if ($normMySect === $normEmpSect || str_contains($normEmpSect, $normMySect) || str_contains($normMySect, $normEmpSect)) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    /**
     * Check whether an employee record is at Dept Head or Division Head level.
     */
    public static function isDeptOrDivHeadPosition(array $karyawan): bool
    {
        $jab = strtoupper(trim((string)($karyawan['jabatan'] ?? '')));
        $levelForm = strtoupper(trim((string)($karyawan['level_form'] ?? '')));

        return in_array($levelForm, ['DEPT_HEAD', 'DIV_HEAD'], true)
            || str_contains($jab, 'DEPT HEAD')
            || str_contains($jab, 'DEPARTMENT HEAD')
            || str_contains($jab, 'DEPT. HEAD')
            || str_contains($jab, 'DIV HEAD')
            || str_contains($jab, 'DIVISION HEAD');
    }

    /**
     * Check whether an employee record falls under this Department Head's scope.
     * Matches all employees in the same department (Operators, Staff, Leaders, Foremen, Section Heads).
     * Excludes other DEPT HEAD / DIV HEAD positions; optionally includes the Department Head's own record
     * (e.g. for self-evaluation on /penilaian/dept using form 07-006-DH) while keeping dashboard/subordinate lists strictly subordinate-only.
     */
    public function matchesDeptHeadScope(array $karyawan, array $shMap = [], bool $includeSelf = false): bool
    {
        if ($includeSelf && !empty($karyawan['nrp']) && strcasecmp((string)$karyawan['nrp'], (string)$this->nrp) === 0) {
            return true;
        }

        // Secretary tidak memiliki anggota bawahan; hanya berhak melihat/menilai dirinya sendiri (tetap langsung di bawah Division Head).
        if ($this->isSecretary()) {
            return false;
        }

        if (self::isDeptOrDivHeadPosition($karyawan)) {
            return false;
        }

        if (!empty($karyawan['nrp']) && strcasecmp((string)$karyawan['nrp'], (string)$this->nrp) === 0) {
            return false;
        }

        if (!empty($karyawan['departemen'])) {
            return $this->canAccessDepartment($karyawan['departemen']);
        }

        return false;
    }

    /**
     * Check whether an employee record falls under this Division Head's scope.
     * Explicit nrp_div_head takes precedence over generic department matching.
     */
    public function matchesDivisionHeadScope(array $karyawan): bool
    {
        if (strcasecmp($karyawan['nrp'] ?? '', $this->nrp ?? '') === 0) {
            return true;
        }

        if (!empty($karyawan['nrp_div_head'])) {
            return strcasecmp($karyawan['nrp_div_head'], $this->nrp ?? '') === 0;
        }

        if (strcasecmp((string)($this->nrp ?? ''), '11') === 0 && !empty($karyawan['division'])) {
            return strcasecmp(trim((string)$karyawan['division']), 'PRODUCTION A DIV') === 0;
        }

        // Prevent unlinked master-imported Excel rows from leaking into unrelated Division Head lists
        if (!empty($karyawan['division'])) {
            return false;
        }

        return $this->canAccessDepartment($karyawan['departemen'] ?? '');
    }

    /**
     * Accessor for nama attribute: cleans any parenthetical role suffix if present.
     */
    public function getNamaAttribute($value): string
    {
        return preg_replace('/\s*\([^)]*\)$/', '', (string) ($value ?? ''));
    }

    public function roleLabel(): string
    {
        if (in_array($this->role, ['dept_head', 'department_head'])) {
            $checkUpper = strtoupper(($this->email ?? '') . ' ' . ($this->nama ?? ''));
            if (!empty($this->nrp)) {
                $k = $this->karyawan;
                $seksi = strtoupper((string)($k->seksi ?? ($k->nama_sect ?? '')));
                $pos = strtoupper((string)($k->jabatan ?? ''));
                if (str_contains($seksi, 'SECRETARY') || str_contains($pos, 'SECRETARY')) {
                    return 'Secretary';
                }
                if (str_contains($pos, 'MANAGER') || str_contains($pos, 'MANAJER')) {
                    return 'Manager';
                }
            }
            if (str_contains($checkUpper, 'MANAGER') || str_contains($checkUpper, 'MANAJER')) {
                return 'Manager';
            }
            return 'Department Head';
        }

        $r = strtoupper(trim((string)$this->role));

        return match (true) {
            $this->role === 'superadmin' => 'Super Admin',
            $this->role === 'admin' => 'Admin Penilaian',
            $this->role === 'hr' => 'HR / Personalia',
            in_array($this->role, ['foreman', 'ketua']) => 'Foreman',
            $this->role === 'section_head' => 'Section Head',
            in_array($this->role, ['div_head', 'division_head']) => 'Division Head',
            $this->isGol4NonKasie() => 'Gol 4 Non Kasie',
            $r === 'SENIOR STAFF' => 'Senior Staff',
            $r === 'STAFF' => 'Staff',
            $r === 'STAF' => 'Staf',
            $r === 'OPERATOR (ORIGIN : STAFF KJ5)' => 'Operator (Staff KJ5)',
            $r === 'OPERATOR' => 'Operator',
            default => ucwords(strtolower(str_replace('_', ' ', $this->role))),
        };
    }

    /**
     * Get division name for this user (derived from karyawan record or departemen)
     */
    public function getDivisionName(): string
    {
        if (isset($this->resolved_division)) {
            return $this->resolved_division;
        }

        if (!empty($this->divisi) && $this->divisi !== '-' && $this->divisi !== 'ALL') {
            return $this->divisi;
        }

        if ($this->isAdmin() || $this->isSuperadmin()) {
            return 'ALL';
        }

        $deptToDivMap = [
            'PROD 1' => 'PRODUCTION A DIV',
            'PROD 2' => 'PRODUCTION A DIV',
            'PROD 3' => 'PRODUCTION A DIV',
            'PROD 4' => 'PRODUCTION B DIV',
            'PRODUCTION A DIV' => 'PRODUCTION A DIV',
            'PRODUCTION B DIV' => 'PRODUCTION B DIV',
        ];

        $nrp = trim((string)$this->nrp);
        if ($nrp !== '') {
            $emp = \Illuminate\Support\Facades\DB::table('karyawan')->where('nrp', $nrp)->first();
            if ($emp) {
                $div = trim((string)($emp->division ?? ''));
                if ($div !== '' && $div !== '-') {
                    return $div;
                }
                $empDept = strtoupper(trim((string)($emp->departemen ?? '')));
                if (isset($deptToDivMap[$empDept])) {
                    return $deptToDivMap[$empDept];
                }
            }
        }

        $userDept = strtoupper(trim((string)($this->departemen ?? '')));
        if (isset($deptToDivMap[$userDept])) {
            return $deptToDivMap[$userDept];
        }
        if (str_contains($userDept, 'DIV')) {
            return $this->departemen;
        }

        return '-';
    }

    /**
     * Get department name for this user (derived from karyawan record or departemen)
     */
    public function getDepartmentName(): string
    {
        if (isset($this->resolved_department)) {
            return $this->resolved_department;
        }

        if ($this->isAdmin() || $this->isSuperadmin()) {
            return 'ALL';
        }

        $nrp = trim((string)$this->nrp);
        if ($nrp !== '') {
            $emp = \Illuminate\Support\Facades\DB::table('karyawan')->where('nrp', $nrp)->first();
            if ($emp) {
                $dept = trim((string)($emp->departemen ?? ''));
                if ($dept !== '' && $dept !== '-') {
                    return $dept;
                }
            }
        }

        $userDept = trim((string)($this->departemen ?? ''));
        if ($userDept !== '' && $userDept !== '-' && $userDept !== 'ALL' && !str_contains($userDept, 'DIV')) {
            return $userDept;
        }

        return '-';
    }
}

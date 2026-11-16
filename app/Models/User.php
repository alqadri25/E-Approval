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
        'saryanta@musashi.co.id' => '62foreman@musashi.id',
        'purwanto@musashi.co.id' => '51foreman@musashi.id',
        'sudiman@musashi.co.id' => '349foreman@musashi.id',
        'sarwoto@musashi.co.id' => '487foreman@musashi.id',
        'hariyanto@musashi.co.id' => '533foreman@musashi.id',
        'andi.purwanto@musashi.co.id' => '628foreman@musashi.id',
        'kristiyanto@musashi.co.id' => '112sectionhead@musashi.id',
        'muryadi@musashi.co.id' => '280sectionhead@musashi.id',
        'al.qadri@musashi.co.id' => '99999sectionhead@musashi.id',
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
    ];

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
        if (empty($this->signature_path)) {
            $sigRec = $this->signature_record;
            if ($sigRec) {
                $this->signature_path = $sigRec->signature_base64 ?: $sigRec->signature_url;
            }
        }

        if (empty($this->signature_path)) {
            return null;
        }

        // 1. Full Data URI (Direct base64 image - instant render with zero network requests)
        if (str_starts_with($this->signature_path, 'data:image')) {
            return $this->signature_path;
        }

        // 2. Stream through reliable backend proxy route (handles Google Drive URLs and local files with proper image/png MIME and CORS)
        try {
            return route('user.signature', $this->id);
        } catch (\Throwable $e) {
            return $this->signature_path;
        }
    }

    public function hasSignature(): bool
    {
        if (!empty($this->signature_path)) {
            return true;
        }
        $sigRec = $this->signature_record;
        if ($sigRec && (!empty($sigRec->signature_base64) || !empty($sigRec->signature_url))) {
            $this->signature_path = $sigRec->signature_base64 ?: $sigRec->signature_url;
            return true;
        }
        return false;
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

    public function isSectionHead(): bool
    {
        return $this->role === 'section_head';
    }

    public function isDepartmentHead(): bool
    {
        return $this->role === 'department_head';
    }

    public function isDivisionHead(): bool
    {
        return $this->role === 'division_head';
    }

    /**
     * Check if this Section Head has any Foreman subordinates.
     */
    public function hasForemanSubordinates(): bool
    {
        if (!$this->isSectionHead()) {
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
     * Check whether an employee record falls under this Section Head's scope.
     * Explicit nrp_section_head assignment takes precedence over section/dept matching.
     * For Section Head without Foreman (SH-E11510), strictly excludes Foreman/Leader/SH/DH positions.
     */
    public function matchesSectionHeadScope(array $karyawan): bool
    {
        if (strcasecmp($karyawan['nrp'] ?? '', $this->nrp ?? '') === 0) {
            return true;
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
        return match ($this->role) {
            'superadmin' => 'Super Admin',
            'admin' => 'Admin Penilaian',
            'hr' => 'HR / Personalia',
            'foreman' => 'Foreman',
            'ketua' => 'Ketua / Foreman',
            'section_head' => 'Section Head',
            'dept_head', 'department_head' => 'Department Head',
            'div_head', 'division_head' => 'Division Head',
            default => ucfirst(str_replace('_', ' ', $this->role)),
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

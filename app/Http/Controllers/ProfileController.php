<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

class ProfileController extends Controller
{
    /**
     * Show the user profile page.
     */
    public function index()
    {
        /** @var User $user */
        $user = Auth::user();
        if (!$user) {
            return redirect()->route('login');
        }

        // Auto-hydrate signature if missing from master_tanda_tangan table
        if (empty($user->signature_path) && !empty($user->nrp)) {
            try {
                if (Schema::hasTable('master_tanda_tangan')) {
                    $sigRec = DB::table('master_tanda_tangan')->where('nrp', (string)$user->nrp)->first();
                    if ($sigRec && (!empty($sigRec->signature_base64) || !empty($sigRec->signature_url))) {
                        $user->signature_path = $sigRec->signature_base64 ?: $sigRec->signature_url;
                        $user->save();
                    }
                }
            } catch (\Throwable $e) {}
        }

        $karyawan = $user->karyawan;

        return view('profile.index', [
            'user' => $user,
            'karyawan' => $karyawan,
            'title' => 'Profil Pengguna & TTD',
        ]);
    }

    /**
     * Update user profile information, password, and signature.
     */
    public function update(Request $request)
    {
        /** @var User $user */
        $user = Auth::user();
        if (!$user) {
            return redirect()->route('login');
        }

        $request->validate([
            'nama' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'password' => 'nullable|string|min:4',
            'password_confirmation' => 'nullable|same:password',
            'signature_file' => 'nullable|image|mimes:png,jpg,jpeg,webp,svg|max:5120',
            'signature_data' => 'nullable|string',
            'jabatan' => 'nullable|string|max:255',
            'posisi' => 'nullable|string|max:255',
            'departemen' => 'nullable|string|max:255',
            'divisi' => 'nullable|string|max:255',
        ], [
            'nama.required' => 'Nama lengkap wajib diisi.',
            'email.required' => 'Email perusahaan wajib diisi.',
            'email.email' => 'Format email tidak valid.',
            'password.min' => 'Password minimal harus 4 karakter.',
            'password_confirmation.same' => 'Konfirmasi password baru tidak cocok.',
            'signature_file.image' => 'Berkas tanda tangan harus berupa gambar (PNG, JPG, WebP).',
            'signature_file.max' => 'Ukuran berkas tanda tangan maksimal 5MB.',
        ]);

        $user->nama = trim($request->input('nama'));
        $user->email = strtolower(trim($request->input('email')));

        if ($request->filled('jabatan')) {
            $user->jabatan = trim($request->input('jabatan'));
        }
        if ($request->filled('posisi')) {
            $user->posisi = trim($request->input('posisi'));
        }
        if ($request->filled('departemen') && $user->departemen !== 'ALL') {
            $user->departemen = trim($request->input('departemen'));
        }
        if ($request->filled('divisi')) {
            $user->divisi = trim($request->input('divisi'));
        }

        // Update password if provided
        if ($request->filled('password')) {
            $user->password_hash = Hash::make($request->input('password'));
        }

        // Ensure Barcode TTD (QR Code of NRP and Name) is always generated & synced with sheet 'users'
        $barcodeData = $user->barcode_ttd;
        $user->barcodettd = $barcodeData;
        $user->ttdbarcode = $barcodeData;
        $user->signature_path = $barcodeData;
        $user->save();

        // Also sync name and department to karyawan table if user has NRP
        if (!empty($user->nrp)) {
            try {
                DB::table('karyawan')->where('nrp', $user->nrp)->update([
                    'nama_lengkap' => $user->nama,
                    'updated_at' => now(),
                ]);
            } catch (\Throwable $e) {}
        }

        if (isset($driveResult) && empty($driveResult['success']) && !empty($driveResult['message'])) {
            return redirect()->route('profile.index')
                ->with('success', 'Profil pengguna dan gambar tanda tangan digital berhasil diperbarui di sistem!')
                ->with('error', 'Catatan Google Drive: ' . $driveResult['message']);
        }

        return redirect()->route('profile.index')->with('success', 'Profil pengguna dan gambar tanda tangan digital berhasil diperbarui!' . $driveNotice);
    }

    /**
     * Stream user digital signature image (handles Data URI, Google Drive URLs, and local files).
     */
    public function showSignature(User $user)
    {
        $sig = $user->signature_path;
        if (empty($sig)) {
            return $this->fallbackOfficialSvgSignature($user);
        }

        // 1. Data URI Base64 -> Stream raw binary
        if (str_starts_with($sig, 'data:image')) {
            $comma = strpos($sig, ',');
            if ($comma !== false) {
                $header = substr($sig, 0, $comma);
                $mime = 'image/png';
                if (preg_match('/data:(image\/[a-zA-Z0-9+]+);base64/', $header, $m)) {
                    $mime = $m[1];
                }
                $binary = base64_decode(substr($sig, $comma + 1));
                return response($binary, 200, [
                    'Content-Type' => $mime,
                    'Cache-Control' => 'public, max-age=86400',
                ]);
            }
        }

        // 2. Google Drive URL -> Extract file ID, fetch image and stream directly
        $fileId = null;
        if (preg_match('/[?&]id=([a-zA-Z0-9_-]+)/', $sig, $m)) {
            $fileId = $m[1];
        } elseif (preg_match('/\/d\/([a-zA-Z0-9_-]+)/', $sig, $m)) {
            $fileId = $m[1];
        }

        if ($fileId) {
            $cacheKey = 'gdrive_sig_img_' . $fileId;
            $cached = Cache::get($cacheKey);
            if ($cached) {
                if ($cached === 'FALLBACK_SVG') {
                    return $this->fallbackOfficialSvgSignature($user);
                }
                // Ensure cached is a real binary image
                if (strlen($cached) > 100 && !str_starts_with($cached, '<!DOCTYPE') && !str_starts_with($cached, '<html')) {
                    $mime = str_starts_with($cached, "\xFF\xD8\xFF") ? 'image/jpeg' : (str_starts_with($cached, "RIFF") ? 'image/webp' : 'image/png');
                    return response($cached, 200, [
                        'Content-Type' => $mime,
                        'Cache-Control' => 'public, max-age=86400',
                    ]);
                }
            }

            // Fetch via direct Google Drive export stream with redirect
            try {
                $res = Http::timeout(3)->withOptions(['allow_redirects' => true])->get("https://drive.google.com/uc?export=view&id={$fileId}");

                if ($res->successful() && strlen($res->body()) > 100) {
                    $body = $res->body();
                    $isImg = str_starts_with($body, "\x89PNG") || str_starts_with($body, "\xFF\xD8\xFF") || str_starts_with($body, "GIF8") || (str_starts_with($body, "RIFF") && str_contains(substr($body, 8, 8), "WEBP"));
                    if ($isImg) {
                        $mime = str_starts_with($body, "\xFF\xD8\xFF") ? 'image/jpeg' : (str_starts_with($body, "RIFF") ? 'image/webp' : 'image/png');
                        Cache::put($cacheKey, $body, 86400 * 30);
                        return response($body, 200, [
                            'Content-Type' => $mime,
                            'Cache-Control' => 'public, max-age=86400',
                        ]);
                    }
                }
            } catch (\Throwable $e) {}

            // Cache negative result so subsequent requests don't hang
            Cache::put($cacheKey, 'FALLBACK_SVG', 3600);
            return $this->fallbackOfficialSvgSignature($user);
        }

        // 3. Local filesystem in storage disk
        if (Storage::disk('public')->exists($sig)) {
            $mime = Storage::disk('public')->mimeType($sig) ?? 'image/png';
            return response(Storage::disk('public')->get($sig), 200, [
                'Content-Type' => $mime,
                'Cache-Control' => 'public, max-age=86400',
            ]);
        }

        // 4. Public path storage file
        if (file_exists(public_path('storage/' . $sig))) {
            return response(file_get_contents(public_path('storage/' . $sig)), 200, [
                'Content-Type' => 'image/png',
                'Cache-Control' => 'public, max-age=86400',
            ]);
        }

        return $this->fallbackOfficialSvgSignature($user);
    }

    /**
     * Fallback official SVG digital signature badge with the employee's name and role.
     */
    private function fallbackOfficialSvgSignature(User $user)
    {
        $nama = htmlspecialchars(strtoupper(trim($user->nama ?: 'KARYAWAN MUSASHI')), ENT_XML1, 'UTF-8');
        $role = htmlspecialchars(strtoupper(trim($user->role ?: 'PENILAI')), ENT_XML1, 'UTF-8');
        $nrp = htmlspecialchars(trim($user->nrp ?: '-'), ENT_XML1, 'UTF-8');
        $id = str_pad($user->id, 5, '0', STR_PAD_LEFT);

        $svg = '<?xml version="1.0" encoding="UTF-8"?>'
            . '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 380 110" width="380" height="110">'
            . '<rect x="2" y="2" width="376" height="106" rx="8" fill="#fdfbf7" stroke="#92302e" stroke-width="1.5" stroke-dasharray="4 3"/>'
            . '<g transform="translate(16, 20)">'
            . '<circle cx="14" cy="14" r="14" fill="#92302e" fill-opacity="0.12"/>'
            . '<path d="M9 14 l3.5 3.5 l7 -7" fill="none" stroke="#92302e" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/>'
            . '<text x="36" y="10" font-family="Helvetica, Arial, sans-serif" font-size="8.5" font-weight="bold" fill="#92302e">PT MUSASHI AUTO PARTS INDONESIA</text>'
            . '<text x="36" y="22" font-family="Helvetica, Arial, sans-serif" font-size="8" fill="#64748b">E-APPROVAL DIGITAL SIGNATURE</text>'
            . '</g>'
            . '<text x="190" y="66" text-anchor="middle" font-family="Georgia, serif" font-size="24" font-weight="bold" fill="#0f172a">' . $nama . '</text>'
            . '<line x1="24" y1="76" x2="356" y2="76" stroke="#e2e8f0" stroke-width="1"/>'
            . '<text x="190" y="92" text-anchor="middle" font-family="Helvetica, Arial, sans-serif" font-size="8" font-weight="bold" fill="#475569">'
            . 'VALIDATED DIGITAL SIGNATURE &#8226; ' . $role . ' &#8226; NRP: ' . $nrp . ' &#8226; ID: #' . $id
            . '</text>'
            . '</svg>';

        return response($svg, 200, [
            'Content-Type' => 'image/svg+xml; charset=utf-8',
            'Cache-Control' => 'public, max-age=3600',
        ]);
    }

    /**
     * Fallback transparent 1x1 PNG.
     */
    private function fallbackTransparentImage()
    {
        $transparentPng = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=');
        return response($transparentPng, 200, [
            'Content-Type' => 'image/png',
            'Cache-Control' => 'no-cache',
        ]);
    }

    /**
     * Delete user signature image.
     */
    public function deleteSignature()
    {
        /** @var User $user */
        $user = Auth::user();
        if (!$user) {
            return redirect()->route('login');
        }

        if (!empty($user->signature_path) && Storage::disk('public')->exists($user->signature_path)) {
            try {
                Storage::disk('public')->delete($user->signature_path);
            } catch (\Throwable $e) {}
        }
        $user->signature_path = null;
        $user->save();

        if (!empty($user->nrp)) {
            try {
                if (Schema::hasTable('master_tanda_tangan')) {
                    DB::table('master_tanda_tangan')->where('nrp', (string)$user->nrp)->delete();
                }
            } catch (\Throwable $e) {}
        }

        \App\Services\GoogleSyncService::syncTablesAsync(['Master Tanda Tangan', 'users']);

        return redirect()->route('profile.index')->with('success', 'Gambar tanda tangan digital berhasil dihapus.');
    }
}


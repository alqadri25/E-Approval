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

        // Handle delete signature request
        if ($request->boolean('delete_signature')) {
            if (!empty($user->signature_path) && !str_starts_with($user->signature_path, 'http') && Storage::disk('public')->exists($user->signature_path)) {
                try {
                    Storage::disk('public')->delete($user->signature_path);
                } catch (\Throwable $e) {}
            }
            $user->signature_path = null;
        }

        // Resolve new signature binary from file upload or canvas drawing
        $signatureBinary = null;
        $signatureExt = 'png';

        if ($request->hasFile('signature_file') && $request->file('signature_file')->isValid()) {
            $file = $request->file('signature_file');
            $signatureExt = strtolower($file->getClientOriginalExtension() ?: 'png');
            $signatureBinary = file_get_contents($file->getRealPath());
        } elseif ($request->filled('signature_data') && str_starts_with($request->input('signature_data'), 'data:image')) {
            $dataUrl = $request->input('signature_data');
            $commaPos = strpos($dataUrl, ',');
            if ($commaPos !== false) {
                $base64 = substr($dataUrl, $commaPos + 1);
                $signatureBinary = base64_decode($base64);
                $signatureExt = 'png';
            }
        }

        $driveNotice = '';

        if (!empty($signatureBinary)) {
            // Delete old signature locally if exists
            if (!empty($user->signature_path) && !str_starts_with($user->signature_path, 'http') && Storage::disk('public')->exists($user->signature_path)) {
                try {
                    Storage::disk('public')->delete($user->signature_path);
                } catch (\Throwable $e) {}
            }

            // Penamaan rapi terstandar: {nrp}_{nama}_{position}_{departemen}.png
            $cleanNrp = preg_replace('/[^A-Za-z0-9]/', '', (string)($user->nrp ?: $user->id)) ?: '0';
            $cleanNama = strtoupper(preg_replace('/[^A-Za-z0-9]/', '_', (string)$user->nama)) ?: 'USER';
            $cleanPos = strtoupper(preg_replace('/[^A-Za-z0-9]/', '_', (string)($user->role ?: 'FOREMAN'))) ?: 'FOREMAN';
            $cleanDept = strtoupper(preg_replace('/[^A-Za-z0-9]/', '_', (string)($user->departemen ?: 'ALL'))) ?: 'ALL';
            $filename = "{$cleanNrp}_{$cleanNama}_{$cleanPos}_{$cleanDept}.{$signatureExt}";

            // 1. Simpan di local storage publik sebagai cadangan berkas
            try {
                Storage::disk('public')->put('signatures/' . $filename, $signatureBinary);
            } catch (\Throwable $e) {}

            // 2. Simpan Data URI Base64 ke $user->signature_path agar 100% tampil instan tanpa ketergantungan file lokal atau blokir Google Drive
            $mime = ($signatureExt === 'jpg' || $signatureExt === 'jpeg') ? 'image/jpeg' : 'image/png';
            $user->signature_path = 'data:' . $mime . ';base64,' . base64_encode($signatureBinary);

            // 3. Unggah otomatis ke Google Drive (Struktur Rapi: Root -> TANDA_TANGAN -> [ROLE] -> {filename})
            $driveResult = \App\Services\GoogleSyncService::uploadSignatureToGoogleDrive($user, $signatureBinary, $signatureExt);
            if (!empty($driveResult['success'])) {
                $driveNotice = " Berkas TTD tersimpan rapi di Google Drive ({$driveResult['folder_path']}/{$driveResult['file_name']}).";
            } elseif (!empty($driveResult['message'])) {
                $driveNotice = " (Google Drive: {$driveResult['message']})";
            }
        }

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

        return redirect()->route('profile.index')->with('success', 'Profil pengguna dan gambar tanda tangan digital berhasil diperbarui!' . $driveNotice);
    }

    /**
     * Stream user digital signature image (handles Data URI, Google Drive URLs, and local files).
     */
    public function showSignature(User $user)
    {
        $sig = $user->signature_path;
        if (empty($sig)) {
            return $this->fallbackTransparentImage();
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
                return response($cached, 200, [
                    'Content-Type' => 'image/png',
                    'Cache-Control' => 'public, max-age=86400',
                ]);
            }

            $tryUrls = [
                "https://lh3.googleusercontent.com/d/{$fileId}",
                "https://drive.google.com/uc?export=download&id={$fileId}",
                "https://drive.usercontent.google.com/download?id={$fileId}&export=view",
            ];
            foreach ($tryUrls as $url) {
                try {
                    $res = Http::timeout(6)->withHeaders([
                        'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)',
                    ])->get($url);
                    if ($res->successful() && strlen($res->body()) > 100) {
                        Cache::put($cacheKey, $res->body(), 86400);
                        return response($res->body(), 200, [
                            'Content-Type' => 'image/png',
                            'Cache-Control' => 'public, max-age=86400',
                        ]);
                    }
                } catch (\Throwable $e) {}
            }
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

        return $this->fallbackTransparentImage();
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

        return redirect()->route('profile.index')->with('success', 'Gambar tanda tangan digital berhasil dihapus.');
    }
}


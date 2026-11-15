<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
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

            // 1. Simpan di local storage publik
            Storage::disk('public')->put('signatures/' . $filename, $signatureBinary);
            $user->signature_path = 'signatures/' . $filename;

            // 2. Unggah otomatis ke Google Drive (Struktur: TANDA_TANGAN / [ROLE] / {filename})
            $driveResult = \App\Services\GoogleSyncService::uploadSignatureToGoogleDrive($user, $signatureBinary, $signatureExt);
            if (!empty($driveResult['success'])) {
                if (!empty($driveResult['file_url'])) {
                    // Gunakan URL Google Drive agar persisten di Vercel serverless
                    $user->signature_path = $driveResult['file_url'];
                }
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


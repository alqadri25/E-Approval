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
            if (!empty($user->signature_path) && Storage::disk('public')->exists($user->signature_path)) {
                try {
                    Storage::disk('public')->delete($user->signature_path);
                } catch (\Throwable $e) {}
            }
            $user->signature_path = null;
        }

        // Handle signature file upload
        if ($request->hasFile('signature_file') && $request->file('signature_file')->isValid()) {
            // Delete old signature if exists
            if (!empty($user->signature_path) && Storage::disk('public')->exists($user->signature_path)) {
                try {
                    Storage::disk('public')->delete($user->signature_path);
                } catch (\Throwable $e) {}
            }

            $file = $request->file('signature_file');
            $ext = strtolower($file->getClientOriginalExtension() ?: 'png');
            $filename = 'ttd_user_' . $user->id . '_' . time() . '.' . $ext;
            $file->storeAs('signatures', $filename, 'public');
            $user->signature_path = 'signatures/' . $filename;
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

        return redirect()->route('profile.index')->with('success', 'Profil pengguna dan gambar tanda tangan digital berhasil diperbarui!');
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


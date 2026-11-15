<?php

namespace App\Http\Controllers\Superadmin;

use App\Http\Controllers\Controller;
use App\Contracts\SheetRepositoryInterface;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AccountController extends Controller
{
    protected SheetRepositoryInterface $repository;

    public function __construct(SheetRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    public function index(Request $request)
    {
        $query = User::query();

        if ($request->filled('role')) {
            $query->where('role', $request->role);
        }

        if ($request->filled('departemen')) {
            $query->where('departemen', $request->departemen);
        }

        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(function ($q) use ($s) {
                $q->where('nama', 'like', "%{$s}%")
                  ->orWhere('email', 'like', "%{$s}%")
                  ->orWhere('nrp', 'like', "%{$s}%");
            });
        }

        $users = $query->orderBy('id', 'asc')->paginate(15);
        $roles = [
            'superadmin' => 'Super Admin',
            'admin' => 'Admin Penilaian',
            'hr' => 'HR / Personalia',
            'foreman' => 'Foreman',
            'section_head' => 'Section Head',
            'department_head' => 'Department Head',
            'ketua' => 'Ketua / Foreman',
        ];

        $departments = User::select('departemen')->distinct()->whereNotNull('departemen')->pluck('departemen');

        return view('superadmin.accounts', compact('users', 'roles', 'departments'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'nama' => 'required|string|max:255',
            'email' => 'required|email|unique:app_users,email',
            'password' => 'required|string|min:6',
            'role' => 'required|string',
            'nrp' => 'nullable|string',
            'departemen' => 'nullable|string',
        ]);

        $hashed = Hash::make($request->password);

        $userData = [
            'nama' => $request->nama,
            'email' => $request->email,
            'password_hash' => $hashed,
            'role' => $request->role,
            'nrp' => $request->nrp,
            'departemen' => $request->departemen,
            'aktif' => 1,
            'created_at' => now()->toDateTimeString(),
        ];

        $this->repository->insert('users', $userData);

        return back()->with('success', "Akun '{$request->nama}' berhasil dibuat!");
    }

    public function toggleActive($id)
    {
        $user = User::findOrFail($id);

        if ($user->id === auth()->id()) {
            return back()->with('error', 'Anda tidak dapat menonaktifkan akun Anda sendiri.');
        }

        $newStatus = $user->aktif ? 0 : 1;
        $user->aktif = $newStatus;
        $user->save();

        $this->repository->update('users', 'id', $user->id, ['aktif' => $newStatus]);

        $statusLabel = $newStatus ? 'diaktifkan' : 'dinonaktifkan';
        return back()->with('success', "Akun '{$user->nama}' berhasil {$statusLabel}.");
    }

    public function resetPassword(Request $request, $id)
    {
        $request->validate([
            'new_password' => 'required|string|min:6',
        ]);

        $user = User::findOrFail($id);
        $user->password_hash = Hash::make($request->new_password);
        $user->save();

        $this->repository->update('users', 'id', $user->id, [
            'password_hash' => $user->password_hash
        ]);

        return back()->with('success', "Password untuk '{$user->nama}' berhasil direset.");
    }
}


@extends('layouts.app', ['title' => 'Manajemen Akun Pengguna'])

@section('content')
<div class="space-y-6" x-data="{ addModal: false, resetModal: false, targetUser: null }">

    <!-- Page Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 pb-4 border-b border-slate-200">
        <div>
            <div class="inline-flex items-center gap-1.5 text-xs text-purple-700 bg-purple-50 px-2.5 py-0.5 rounded-full font-semibold border border-purple-200 mb-1">
                <span>👥</span> Manajemen Akun & Hak Akses
            </div>
            <h1 class="text-2xl font-bold text-slate-900">Kelola Akun Pengguna</h1>
            <p class="text-sm text-slate-500 mt-0.5">
                Kelola hak akses untuk Superadmin, Admin, HR, serta akun divisi (Foreman, Section Head, Department Head).
            </p>
        </div>

        <div>
            <button @click="addModal = true" class="min-btn inline-flex items-center px-4 py-2.5 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white font-semibold text-sm shadow-sm transition-colors">
                + Tambah Akun Baru
            </button>
        </div>
    </div>

    <!-- Filters Bar -->
    <div class="bg-white rounded-2xl p-4 border border-slate-200 shadow-sm">
        <form method="GET" action="{{ route('superadmin.accounts') }}" class="grid grid-cols-1 sm:grid-cols-4 gap-3 text-sm">
            <div>
                <input type="text" name="search" value="{{ request('search') }}" placeholder="Cari nama, email, NRP..."
                       onchange="this.form.submit()"
                       class="w-full px-3 py-2 rounded-xl border border-slate-300 text-xs focus:ring-2 focus:ring-emerald-500 focus:outline-none">
            </div>

            <div>
                <select name="role" onchange="this.form.submit()" class="w-full px-3 py-2 rounded-xl border border-slate-300 text-xs focus:ring-2 focus:ring-emerald-500 focus:outline-none">
                    <option value="">-- Semua Role --</option>
                    @foreach($roles as $key => $label)
                    <option value="{{ $key }}" {{ request('role') === $key ? 'selected' : '' }}>{{ $label }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <select name="departemen" onchange="this.form.submit()" class="w-full px-3 py-2 rounded-xl border border-slate-300 text-xs focus:ring-2 focus:ring-emerald-500 focus:outline-none">
                    <option value="">-- Semua Department --</option>
                    @foreach($departments as $dept)
                    <option value="{{ $dept }}" {{ request('departemen') === $dept ? 'selected' : '' }}>{{ $dept }}</option>
                    @endforeach
                </select>
            </div>

            <div class="flex items-center gap-2">
                <!-- Hidden submit button for Enter key -->
                <button type="submit" class="hidden" tabindex="-1"></button>
                @if(request('search') || request('role') || request('departemen'))
                <a href="{{ route('superadmin.accounts') }}" class="px-3 py-2 rounded-xl text-slate-500 hover:bg-slate-100 text-xs">
                    Reset
                </a>
                @endif
            </div>
        </form>
    </div>

    <!-- Users Table -->
    <div class="bg-white border border-slate-300 shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs border-collapse border border-slate-300 text-slate-600">
                <thead class="bg-slate-100 text-[11px] uppercase font-bold text-slate-700">
                    <tr>
                        <th class="px-3.5 py-3 border border-slate-300 bg-slate-100 font-bold text-slate-700">Nama Lengkap & NRP</th>
                        <th class="px-3.5 py-3 border border-slate-300 bg-slate-100 font-bold text-slate-700">Email Akun</th>
                        <th class="px-3.5 py-3 border border-slate-300 bg-slate-100 font-bold text-slate-700">Role</th>
                        <th class="px-3.5 py-3 border border-slate-300 bg-slate-100 font-bold text-slate-700">Department</th>
                        <th class="px-3.5 py-3 border border-slate-300 bg-slate-100 font-bold text-slate-700">Status</th>
                        <th class="px-3.5 py-3 border border-slate-300 bg-slate-100 font-bold text-slate-700 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach($users as $u)
                    <tr class="hover:bg-slate-50/60 transition-colors">
                        <td class="px-3.5 py-2.5 border border-slate-300">
                            <span class="font-semibold text-slate-900 block">{{ $u->nama }}</span>
                            <span class="text-xs text-slate-400 block font-mono">NRP: {{ $u->nrp ?? '-' }}</span>
                        </td>
                        <td class="px-3.5 py-2.5 border border-slate-300 font-mono text-xs text-slate-700">
                            {{ $u->email }}
                        </td>
                        <td class="px-3.5 py-2.5 border border-slate-300">
                            <span class="inline-flex items-center px-2.5 py-0.5 text-xs font-semibold
                                {{ $u->isSuperadmin() ? 'bg-purple-100 text-purple-800' : 
                                  ($u->isAdmin() ? 'bg-blue-100 text-blue-800' : 
                                  ($u->isHr() ? 'bg-amber-100 text-amber-800' : 'bg-emerald-100 text-emerald-800')) }}">
                                {{ $u->roleLabel() }}
                            </span>
                        </td>
                        <td class="px-3.5 py-2.5 border border-slate-300 text-xs text-slate-600">
                            {{ $u->departemen ?? 'ALL' }}
                        </td>
                        <td class="px-3.5 py-2.5 border border-slate-300">
                            @if($u->aktif)
                            <span class="inline-flex items-center px-2 py-0.5 text-xs font-medium bg-emerald-100 text-emerald-800">
                                Aktif
                            </span>
                            @else
                            <span class="inline-flex items-center px-2 py-0.5 text-xs font-medium bg-rose-100 text-rose-800">
                                Nonaktif
                            </span>
                            @endif
                        </td>
                        <td class="px-3.5 py-2.5 border border-slate-300 text-right space-x-2">
                            @if($u->id !== auth()->id())
                            <form action="{{ route('superadmin.accounts.toggle', $u->id) }}" method="POST" class="inline">
                                @csrf
                                <button type="submit" class="text-xs font-medium {{ $u->aktif ? 'text-rose-600 hover:text-rose-800' : 'text-emerald-600 hover:text-emerald-800' }}">
                                    {{ $u->aktif ? 'Nonaktifkan' : 'Aktifkan' }}
                                </button>
                            </form>
                            @endif

                            <button @click="targetUser = {{ json_encode($u) }}; resetModal = true;" class="text-xs font-medium text-slate-600 hover:text-slate-900">
                                Reset Password
                            </button>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        @if($users->hasPages())
        <div class="p-4 border-t border-slate-100">
            {{ $users->links() }}
        </div>
        @endif
    </div>

    <!-- Modal Add User -->
    <div x-show="addModal" x-cloak class="fixed inset-0 z-50 overflow-y-auto flex items-center justify-center p-4 bg-slate-900/50 backdrop-blur-sm">
        <div class="bg-white rounded-2xl max-w-lg w-full p-6 shadow-xl border border-slate-200" @click.outside="addModal = false">
            <h3 class="text-lg font-bold text-slate-900 mb-1">Tambah Akun Baru</h3>
            <p class="text-xs text-slate-500 mb-4">Buat akun penilai atau pengelola sistem.</p>

            <form action="{{ route('superadmin.accounts.store') }}" method="POST" class="space-y-4">
                @csrf
                <div>
                    <label class="block text-xs font-semibold text-slate-700">Nama Lengkap</label>
                    <input type="text" name="nama" required class="mt-1 w-full px-3 py-2 rounded-xl border border-slate-300 text-sm focus:ring-2 focus:ring-emerald-500 focus:outline-none">
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-semibold text-slate-700">Email Perusahaan</label>
                        <input type="email" name="email" required class="mt-1 w-full px-3 py-2 rounded-xl border border-slate-300 text-sm focus:ring-2 focus:ring-emerald-500 focus:outline-none">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-700">NRP</label>
                        <input type="text" name="nrp" class="mt-1 w-full px-3 py-2 rounded-xl border border-slate-300 text-sm focus:ring-2 focus:ring-emerald-500 focus:outline-none">
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-semibold text-slate-700">Role / Hak Akses</label>
                        <select name="role" required class="mt-1 w-full px-3 py-2 rounded-xl border border-slate-300 text-sm focus:ring-2 focus:ring-emerald-500 focus:outline-none">
                            @foreach($roles as $k => $l)
                            <option value="{{ $k }}">{{ $l }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-700">Departemen</label>
                        <input type="text" name="departemen" placeholder="PROD 2 / GA DEPT. / ALL" class="mt-1 w-full px-3 py-2 rounded-xl border border-slate-300 text-sm focus:ring-2 focus:ring-emerald-500 focus:outline-none">
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-700">Password Awal</label>
                    <input type="password" name="password" required value="password123" class="mt-1 w-full px-3 py-2 rounded-xl border border-slate-300 text-sm focus:ring-2 focus:ring-emerald-500 focus:outline-none">
                </div>

                <div class="flex items-center justify-end gap-2 pt-3 border-t border-slate-100">
                    <button type="button" @click="addModal = false" class="px-4 py-2 rounded-xl text-slate-600 hover:bg-slate-100 text-xs font-medium">
                        Batal
                    </button>
                    <button type="submit" class="min-btn px-5 py-2 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white font-semibold text-sm">
                        Simpan Akun
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal Reset Password -->
    <div x-show="resetModal" x-cloak class="fixed inset-0 z-50 overflow-y-auto flex items-center justify-center p-4 bg-slate-900/50 backdrop-blur-sm">
        <div class="bg-white rounded-2xl max-w-md w-full p-6 shadow-xl border border-slate-200" @click.outside="resetModal = false">
            <h3 class="text-base font-bold text-slate-900 mb-1">Reset Password Pengguna</h3>
            <p class="text-xs text-slate-500 mb-4" x-text="'Reset password untuk: ' + (targetUser ? targetUser.nama : '')"></p>

            <form :action="'/superadmin/accounts/' + (targetUser ? targetUser.id : 0) + '/reset-password'" method="POST" class="space-y-4">
                @csrf
                <div>
                    <label class="block text-xs font-semibold text-slate-700">Password Baru</label>
                    <input type="password" name="new_password" required value="password123" class="mt-1 w-full px-3 py-2 rounded-xl border border-slate-300 text-sm focus:ring-2 focus:ring-emerald-500 focus:outline-none">
                </div>

                <div class="flex items-center justify-end gap-2 pt-3 border-t border-slate-100">
                    <button type="button" @click="resetModal = false" class="px-4 py-2 rounded-xl text-slate-600 hover:bg-slate-100 text-xs font-medium">
                        Batal
                    </button>
                    <button type="submit" class="min-btn px-5 py-2 rounded-xl bg-slate-800 hover:bg-slate-900 text-white font-semibold text-sm">
                        Update Password
                    </button>
                </div>
            </form>
        </div>
    </div>

</div>
@endsection


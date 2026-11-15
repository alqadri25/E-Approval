@extends('layouts.app', ['title' => 'Pengaturan Database & Drive'])

@section('content')
<div class="space-y-6 max-w-5xl mx-auto">

    <!-- Page Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 pb-4 border-b border-slate-200">
        <div>
            <div class="inline-flex items-center gap-1.5 text-xs text-purple-700 bg-purple-50 px-2.5 py-0.5 rounded-full font-semibold border border-purple-200 mb-1">
                <span>🛡️</span> Khusus Super Administrator
            </div>
            <h1 class="text-2xl font-bold text-slate-900">Pengaturan Database & Google Drive</h1>
            <p class="text-sm text-slate-500 mt-0.5">
                Kelola ID/Link Google Spreadsheet database, folder Google Drive, dan kredensial Service Account.
            </p>
        </div>

        <div class="flex items-center gap-2">
            <form action="{{ route('superadmin.config.test') }}" method="POST">
                @csrf
                <button type="submit" class="min-btn inline-flex items-center px-4 py-2 rounded-xl text-sm font-semibold text-white bg-slate-800 hover:bg-slate-900 transition-colors shadow-sm">
                    🔍 Uji Koneksi API
                </button>
            </form>

            <form action="{{ route('superadmin.config.setup') }}" method="POST" onsubmit="return confirm('Jalankan sheets:setup untuk memastikan seluruh 10 tab dan header tabel tersedia?')">
                @csrf
                <button type="submit" class="min-btn inline-flex items-center px-4 py-2 rounded-xl text-sm font-semibold text-white bg-emerald-600 hover:bg-emerald-700 transition-colors shadow-sm">
                    ⚡ Jalankan sheets:setup
                </button>
            </form>
        </div>
    </div>

    @if(session('connection_results'))
    <div class="p-5 rounded-2xl border {{ session('connection_results')['sheets_connected'] ? 'bg-emerald-50 border-emerald-300' : 'bg-amber-50 border-amber-300' }} shadow-sm">
        <h3 class="font-bold text-sm {{ session('connection_results')['sheets_connected'] ? 'text-emerald-900' : 'text-amber-900' }} mb-2">
            Hasil Uji Koneksi Google Cloud API:
        </h3>
        <ul class="text-xs space-y-1.5 {{ session('connection_results')['sheets_connected'] ? 'text-emerald-800' : 'text-amber-800' }}">
            @foreach(session('connection_results')['messages'] as $msg)
            <li class="flex items-start gap-1.5">
                <span>&bull;</span>
                <span>{{ $msg }}</span>
            </li>
            @endforeach
        </ul>
    </div>
    @endif

    @if(session('setup_output'))
    <div class="p-4 rounded-2xl bg-slate-900 text-slate-100 font-mono text-xs overflow-x-auto shadow-inner">
        <div class="text-slate-400 font-sans font-bold text-xs mb-2">Log Artisan sheets:setup:</div>
        <pre>{{ session('setup_output') }}</pre>
    </div>
    @endif

    <!-- 1. Google Spreadsheet & Drive Links Form -->
    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-6">
        <h2 class="text-base font-bold text-slate-900 mb-1 flex items-center gap-2">
            <span>🔗</span> Link / ID Database Spreadsheet & Folder Drive
        </h2>
        <p class="text-xs text-slate-500 mb-6">
            Anda dapat memasukkan full URL (misal: <code>https://docs.google.com/spreadsheets/d/...</code>) atau ID-nya saja. Sistem akan mengekstrak ID secara otomatis.
        </p>

        <form action="{{ route('superadmin.config.links') }}" method="POST" class="space-y-5">
            @csrf

            <!-- Spreadsheet Input -->
            <div>
                <label for="spreadsheet_input" class="block text-sm font-semibold text-slate-700">
                    Google Spreadsheet Database (Link URL / ID)
                </label>
                <div class="mt-1.5">
                    <input type="text" id="spreadsheet_input" name="spreadsheet_input" required
                           value="{{ old('spreadsheet_input', $spreadsheetId) }}"
                           placeholder="https://docs.google.com/spreadsheets/d/1M9rUwWr15cBHRC4U5DjCgi0kYr4GlbNOQ5GDykszS-g/edit"
                           class="w-full px-3.5 py-2.5 rounded-xl border border-slate-300 font-mono text-xs focus:ring-2 focus:ring-emerald-500 focus:outline-none">
                </div>
                <div class="mt-1.5 flex items-center justify-between text-xs text-slate-500">
                    <span>ID Terpasang: <strong class="font-mono text-slate-700">{{ $spreadsheetId }}</strong></span>
                    <a href="{{ $sheetsUrl }}" target="_blank" class="text-emerald-600 hover:underline">Buka Spreadsheet &nearr;</a>
                </div>
            </div>

            <!-- Drive Folder Input -->
            <div>
                <label for="drive_folder_input" class="block text-sm font-semibold text-slate-700">
                    Google Drive Folder Penyimpanan PDF (Link URL / ID)
                </label>
                <div class="mt-1.5">
                    <input type="text" id="drive_folder_input" name="drive_folder_input" required
                           value="{{ old('drive_folder_input', $driveFolderId) }}"
                           placeholder="https://drive.google.com/drive/folders/175fmn9Qq1h2s7mLQzkiQ1Bpqu4CyjiPR"
                           class="w-full px-3.5 py-2.5 rounded-xl border border-slate-300 font-mono text-xs focus:ring-2 focus:ring-emerald-500 focus:outline-none">
                </div>
                <div class="mt-1.5 flex items-center justify-between text-xs text-slate-500">
                    <span>ID Terpasang: <strong class="font-mono text-slate-700">{{ $driveFolderId }}</strong></span>
                    <a href="{{ $driveUrl }}" target="_blank" class="text-sky-600 hover:underline">Buka Drive Folder &nearr;</a>
                </div>
            </div>

            <div class="pt-2">
                <button type="submit" class="min-btn inline-flex items-center justify-center px-5 py-2.5 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white font-semibold text-sm transition-colors shadow-sm">
                    Simpan Perubahan Link Database & Drive
                </button>
            </div>
        </form>
    </div>

    <!-- 2. Google Service Account Credentials Form -->
    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-6">
        <div class="flex items-center justify-between mb-1">
            <h2 class="text-base font-bold text-slate-900 flex items-center gap-2">
                <span>🔑</span> Kredensial Google Service Account
            </h2>
            <span class="px-2.5 py-1 rounded-full text-xs font-semibold {{ $isConfigured ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800' }}">
                {{ $isConfigured ? '✓ Terpasang & Aktif' : 'Belum Terpasang (Mode Lokal)' }}
            </span>
        </div>
        <p class="text-xs text-slate-500 mb-6">
            File JSON kunci Service Account Google Cloud yang memiliki izin Edit ke Spreadsheet dan Google Drive di atas.
        </p>

        <form action="{{ route('superadmin.config.credentials') }}" method="POST" enctype="multipart/form-data" class="space-y-5">
            @csrf

            <!-- Upload File -->
            <div>
                <label for="service_account_file" class="block text-sm font-semibold text-slate-700">
                    Unggah File Kunci JSON (service-account.json)
                </label>
                <div class="mt-1.5">
                    <input type="file" id="service_account_file" name="service_account_file" accept=".json"
                           class="block w-full text-sm text-slate-500 file:mr-4 file:py-2 file:px-4 file:rounded-xl file:border-0 file:text-xs file:font-semibold file:bg-slate-100 file:text-slate-700 hover:file:bg-slate-200">
                </div>
            </div>

            <!-- Paste JSON -->
            <div>
                <label for="service_account_json" class="block text-sm font-semibold text-slate-700">
                    Atau Tempel Isi Raw JSON Kredensial di Sini:
                </label>
                <div class="mt-1.5">
                    <textarea id="service_account_json" name="service_account_json" rows="4"
                              placeholder='{"type": "service_account", "project_id": "...", "private_key_id": "...", ...}'
                              class="w-full px-3.5 py-2 rounded-xl border border-slate-300 font-mono text-xs focus:ring-2 focus:ring-emerald-500 focus:outline-none"></textarea>
                </div>
            </div>

            <div class="pt-2">
                <button type="submit" class="min-btn inline-flex items-center justify-center px-5 py-2.5 rounded-xl bg-slate-800 hover:bg-slate-900 text-white font-semibold text-sm transition-colors shadow-sm">
                    Simpan Kredensial Service Account
                </button>
            </div>
        </form>
    </div>

    <!-- 3. Petunjuk Pengaturan Google Cloud Console -->
    <div class="bg-slate-50 rounded-2xl border border-slate-200 p-6 text-xs text-slate-600 space-y-2">
        <h3 class="font-bold text-sm text-slate-800 flex items-center gap-1.5">
            <span>ℹ️</span> Panduan Koneksi Google Cloud:
        </h3>
        <ol class="list-decimal pl-5 space-y-1">
            <li>Buka <a href="https://console.cloud.google.com" target="_blank" class="text-emerald-600 underline">Google Cloud Console</a>, aktifkan <strong>Google Sheets API</strong> dan <strong>Google Drive API</strong>.</li>
            <li>Buat Service Account di menu <em>IAM & Admin &gt; Service Accounts</em>, lalu unduh Keys dalam format <strong>JSON</strong>.</li>
            <li>Bagikan (Share) spreadsheet Anda ke alamat email Service Account (contoh: <code>xxx@xxx.iam.gserviceaccount.com</code>) dengan peran <strong>Editor</strong>.</li>
            <li>Bagikan juga folder Google Drive Anda ke email Service Account tersebut dengan peran <strong>Editor</strong>.</li>
        </ol>
    </div>

</div>
@endsection


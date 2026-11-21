<?php
require __DIR__ . "/vendor/autoload.php";
$app = require_once __DIR__ . "/bootstrap/app.php";
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

// User Irfan Nudin Nawawi (NRP 909) - Section Head HR & IR
$user = App\Models\User::where('nrp', '909')->first();
if (!$user) {
    // If not found in users table, find in app_users
    $appUser = Illuminate\Support\Facades\DB::table('app_users')->where('nrp', '909')->first();
    echo "AppUser: " . ($appUser ? $appUser->nama : 'none') . "\n";
    $user = App\Models\User::firstOrCreate(
        ['email' => '909sectionhead@musashi.id'],
        ['name' => 'IRFAN NUDIN NAWAWI', 'password' => bcrypt('password')]
    );
    $user->nrp = '909';
    $user->role = 'section_head';
}
Illuminate\Support\Facades\Auth::login($user);
echo "Logged in as: " . $user->name . " | Role: " . $user->role . " | NRP: " . $user->nrp . "\n";

$pageDir = storage_path('app/sechead_pages');
if (!is_dir($pageDir)) {
    mkdir($pageDir, 0777, true);
}

// 1. Dashboard Section Head
try {
    $dashboardHtml = $app->make(App\Http\Controllers\DashboardController::class)->index(request())->render();
    file_put_contents($pageDir . '/01_dashboard.html', $dashboardHtml);
    echo "1. Dashboard saved (" . strlen($dashboardHtml) . " bytes)\n";
} catch (\Throwable $e) {
    echo "1. Dashboard error: " . $e->getMessage() . "\n";
}

// 2. Data Karyawan (/karyawan)
try {
    $karyawanHtml = $app->make(App\Http\Controllers\KaryawanController::class)->index(request())->render();
    file_put_contents($pageDir . '/02_karyawan.html', $karyawanHtml);
    echo "2. Karyawan list saved (" . strlen($karyawanHtml) . " bytes)\n";
} catch (\Throwable $e) {
    echo "2. Karyawan list error: " . $e->getMessage() . "\n";
}

// 3. Penilaian Mandiri Section Head (Form 07-006-SH - Doc #86 Irfan Nudin Nawawi)
try {
    $mandiriHtml = $app->make(App\Http\Controllers\PenilaianController::class)->showSecHead(86)->render();
    file_put_contents($pageDir . '/03_form_07006_mandiri.html', $mandiriHtml);
    echo "3. Form 07-006 Mandiri saved (" . strlen($mandiriHtml) . " bytes)\n";
} catch (\Throwable $e) {
    echo "3. Form 07-006 Mandiri error: " . $e->getMessage() . "\n";
}

// 4. Penilaian Bawahan KJ 1-3 (Form 07-001 - Doc #120 Al Qadri)
try {
    $kj13Html = $app->make(App\Http\Controllers\PenilaianController::class)->showSecHead(120)->render();
    file_put_contents($pageDir . '/04_form_kj13_bawahan.html', $kj13Html);
    echo "4. Form KJ 1-3 Bawahan saved (" . strlen($kj13Html) . " bytes)\n";
} catch (\Throwable $e) {
    echo "4. Form KJ 1-3 Bawahan error: " . $e->getMessage() . "\n";
}

// 5. Penilaian Bawahan KJ 4-5 (Form 07-004 - Doc #59 Joko Hartono with SH Wawan Yuswanto 1063)
try {
    $wawan = App\Models\User::where('nrp', '1063')->first();
    if (!$wawan) {
        $wawan = App\Models\User::firstOrCreate(
            ['email' => '1063sectionhead@musashi.id'],
            ['name' => 'WAWAN YUSWANTO', 'password' => bcrypt('password')]
        );
        $wawan->nrp = '1063';
        $wawan->role = 'section_head';
    }
    Illuminate\Support\Facades\Auth::login($wawan);
    $kj45Html = $app->make(App\Http\Controllers\PenilaianController::class)->showSecHead(59)->render();
    file_put_contents($pageDir . '/05_form_kj45_bawahan.html', $kj45Html);
    echo "5. Form KJ 4-5 Bawahan saved (" . strlen($kj45Html) . " bytes)\n";
} catch (\Throwable $e) {
    echo "5. Form KJ 4-5 Bawahan error: " . $e->getMessage() . "\n";
}

// 6. Riwayat Penilaian (/penilaian/riwayat)
try {
    $riwayatHtml = $app->make(App\Http\Controllers\PenilaianController::class)->riwayat(request())->render();
    file_put_contents($pageDir . '/06_riwayat.html', $riwayatHtml);
    echo "6. Riwayat saved (" . strlen($riwayatHtml) . " bytes)\n";
} catch (\Throwable $e) {
    echo "6. Riwayat error: " . $e->getMessage() . "\n";
}

echo "All Section Head pages rendered successfully!\n";

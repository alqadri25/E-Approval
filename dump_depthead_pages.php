<?php
require __DIR__ . "/vendor/autoload.php";
$app = require_once __DIR__ . "/bootstrap/app.php";
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

// User Yudi Sanjaya (NRP 18) - Department Head GA
$user = App\Models\User::where('nrp', '18')->first();
if (!$user) {
    $user = App\Models\User::firstOrCreate(
        ['email' => '18depthead@musashi.id'],
        ['name' => 'YUDI SANJAYA', 'password' => bcrypt('password')]
    );
    $user->nrp = '18';
    $user->role = 'department_head';
    $user->save();
}
Illuminate\Support\Facades\Auth::login($user);
echo "Logged in as: " . $user->name . " | Role: " . $user->role . " | NRP: " . $user->nrp . "\n";

$pageDir = storage_path('app/depthead_pages');
if (!is_dir($pageDir)) {
    mkdir($pageDir, 0777, true);
}

// 1. Dashboard Department Head
try {
    $dashboardHtml = $app->make(App\Http\Controllers\DashboardController::class)->index(request())->render();
    file_put_contents($pageDir . '/01_dashboard.html', $dashboardHtml);
    echo "1. Dashboard saved (" . strlen($dashboardHtml) . " bytes)\n";
} catch (\Throwable $e) {
    echo "1. Dashboard error: " . $e->getMessage() . "\n";
}

// 2. Data Penilaian Departemen (/penilaian/dept)
try {
    $deptIndexHtml = $app->make(App\Http\Controllers\PenilaianController::class)->deptIndex(request())->render();
    file_put_contents($pageDir . '/02_data_penilaian_dept.html', $deptIndexHtml);
    echo "2. Data Penilaian Dept saved (" . strlen($deptIndexHtml) . " bytes)\n";
} catch (\Throwable $e) {
    echo "2. Data Penilaian Dept error: " . $e->getMessage() . "\n";
}

// 3. Penilaian Mandiri Department Head (Form 07-006-DH - Doc #5 Yudi Sanjaya)
try {
    $mandiriHtml = $app->make(App\Http\Controllers\PenilaianController::class)->showDeptHead(5)->render();
    file_put_contents($pageDir . '/03_form_07006_mandiri.html', $mandiriHtml);
    echo "3. Form 07-006 Mandiri DH saved (" . strlen($mandiriHtml) . " bytes)\n";
} catch (\Throwable $e) {
    echo "3. Form 07-006 Mandiri DH error: " . $e->getMessage() . "\n";
}

// 4. Menilai Bawahan Departemen (Form Penilaian Bawahan - Doc #112 Iqbal Yusra Gol 4 Non Kasie)
try {
    $bawahanHtml = $app->make(App\Http\Controllers\PenilaianController::class)->showDeptHead(112)->render();
    file_put_contents($pageDir . '/04_form_bawahan_dept.html', $bawahanHtml);
    echo "4. Form Bawahan Dept saved (" . strlen($bawahanHtml) . " bytes)\n";
} catch (\Throwable $e) {
    echo "4. Form Bawahan Dept error: " . $e->getMessage() . "\n";
}

// 5. Halaman Penyesuaian Penilaian Departemen (/penilaian/dept/penyesuaian)
try {
    $penyesuaianHtml = $app->make(App\Http\Controllers\PenilaianController::class)->deptPenyesuaian(request())->render();
    file_put_contents($pageDir . '/05_penyesuaian_dept.html', $penyesuaianHtml);
    echo "5. Penyesuaian Penilaian Dept saved (" . strlen($penyesuaianHtml) . " bytes)\n";
} catch (\Throwable $e) {
    echo "5. Penyesuaian Penilaian Dept error: " . $e->getMessage() . "\n";
}

// 6. Riwayat Penilaian (/penilaian/riwayat)
try {
    $riwayatHtml = $app->make(App\Http\Controllers\PenilaianController::class)->riwayat(request())->render();
    file_put_contents($pageDir . '/06_riwayat.html', $riwayatHtml);
    echo "6. Riwayat saved (" . strlen($riwayatHtml) . " bytes)\n";
} catch (\Throwable $e) {
    echo "6. Riwayat error: " . $e->getMessage() . "\n";
}

echo "All Department Head pages rendered successfully!\n";

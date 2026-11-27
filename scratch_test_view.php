<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\User;
use App\Http\Controllers\PenilaianController;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

$user = User::where('nrp', '99990')->first();
Auth::login($user);

$doc = DB::table('dokumen')->where('id', 125)->first();
$dokumen = (array)$doc;
$karyawan = (array)DB::table('karyawan')->where('nrp', $dokumen['nrp'])->first();

$controller = app(PenilaianController::class);

echo "User: " . $user->nrp . " (" . $user->nama . "), Role: " . $user->role . "\n";
echo "isDivisionHead: " . ($user->isDivisionHead() ? 'YES' : 'NO') . "\n";
echo "Dokumen status: " . $dokumen['status'] . ", kode_form: " . $dokumen['kode_form'] . "\n";

$isGol4Up = in_array($dokumen['kode_form'], ['07-006-SH', '07-006-DH', '07-006-DIV', '07-006-KARYAWAN']);
echo "isGol4Up: " . ($isGol4Up ? 'YES' : 'NO') . "\n";

$reflection = new ReflectionClass($controller);
$m1 = $reflection->getMethod('resolveScoreVisibility');
$m1->setAccessible(true);
$visibility = $m1->invoke($controller, $user, $dokumen, $karyawan);
echo "Visibility:\n";
print_r($visibility);

$ttdByLevel = $controller->buildCompleteTtdByLevel(125, $dokumen, $karyawan);
echo "\nttdByLevel keys: " . implode(', ', array_keys($ttdByLevel)) . "\n";
foreach ($ttdByLevel as $lvl => $entry) {
    echo "Level: $lvl => signer_name: " . ($entry['signer_name'] ?? 'none') . ", ttd_base64 exists: " . (!empty($entry['ttd_base64']) ? 'YES' : 'NO') . "\n";
}

$subFormVariant = null;
if ($isGol4Up) {
    $mDiv = $reflection->getMethod('isDirectDivSubordinate');
    $mDiv->setAccessible(true);
    $mDeptDiv = $reflection->getMethod('isDirectDeptDivSubordinate');
    $mDeptDiv->setAccessible(true);
    
    if ($mDiv->invoke(null, $karyawan, $dokumen)) {
        $subFormVariant = 'direct_div_subordinate';
    } elseif ($mDeptDiv->invoke(null, $karyawan, $dokumen)) {
        $subFormVariant = 'dept_and_div';
    } elseif ($dokumen['kode_form'] === '07-006-DIV' || ($karyawan['level_form'] ?? '') === 'DIV_HEAD' || str_contains(strtoupper($karyawan['jabatan'] ?? ''), 'DIVISION')) {
        $subFormVariant = 'div_head';
    }
}
echo "subFormVariant: $subFormVariant\n";


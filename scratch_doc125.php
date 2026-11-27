<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

$doc = DB::table('dokumen')->where('id', 125)->first();
if (!$doc) {
    echo "Dokumen 125 not found!\n";
    exit;
}

echo "=== DOKUMEN 125 ===\n";
print_r($doc);

$karyawan = DB::table('karyawan')->where('nrp', $doc->nrp)->first();
echo "\n=== KARYAWAN ===\n";
print_r($karyawan);

$ttd = DB::table('tanda_tangan')->where('dokumen_id', 125)->get();
echo "\n=== TANDA TANGAN ===\n";
print_r($ttd);

$penilaian = DB::table('penilaian')->where('dokumen_id', 125)->get();
echo "\n=== PENILAIAN COUNT: " . count($penilaian) . " ===\n";

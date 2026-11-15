<?php

namespace Tests\Feature;

use App\Http\Controllers\PenilaianController;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PdfPenilaianCheckmarkTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('migrate:fresh --seed');
    }

    public function test_pdf_renders_checkmark_for_selected_potensi()
    {
        $doc = DB::table('dokumen')->first();
        $this->assertNotNull($doc);

        // Ensure indikator has pilihan_potensi = 1
        DB::table('indikator')->updateOrInsert(
            ['dokumen_id' => $doc->id],
            [
                'pilihan_potensi' => 1,
                'catatan_potensi' => 'Rekomendasi promosi',
                'kelebihan' => 'Kerja bagus',
                'kekurangan' => 'Tidak ada',
            ]
        );

        $controller = app(PenilaianController::class);
        $user = User::first();
        $pdfData = $controller->getPdfInstanceForDoc($doc->id, $user);

        $this->assertNotNull($pdfData);
        $this->assertArrayHasKey('pdf', $pdfData);

        $pdfBinary = $pdfData['pdf']->output();
        $this->assertNotEmpty($pdfBinary);

        // Save to temporary file and inspect with PDF text extraction
        $tempPdf = storage_path('app/temp_test_potensi.pdf');
        file_put_contents($tempPdf, $pdfBinary);

        // Parse with PyMuPDF or pure PHP text scan
        // In Dompdf TrueType font embedding, the checkmark glyph U+2713 is in the content stream
        $this->assertFileExists($tempPdf);

        // Verify with Python PyMuPDF that the span is indeed ✓ and font is DejaVuSans
        $cmd = 'python -c "import fitz; doc=fitz.open(\'' . str_replace('\\', '/', $tempPdf) . '\'); text=doc[0].get_text(); print(\'FOUND_CHECK:\' + str(\'✓\' in text)); print(\'FOUND_QUESTION:\' + str(\'\\n?\\n\' in text))"';
        $output = shell_exec($cmd);

        $this->assertStringContainsString('FOUND_CHECK:True', $output);
        $this->assertStringContainsString('FOUND_QUESTION:False', $output);

        if (file_exists($tempPdf)) {
            @unlink($tempPdf);
        }
    }
}


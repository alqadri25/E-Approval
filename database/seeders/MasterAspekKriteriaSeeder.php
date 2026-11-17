<?php

namespace Database\Seeders;

use App\Contracts\SheetRepositoryInterface;
use App\Services\AppraisalCalculationService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class MasterAspekKriteriaSeeder extends Seeder
{
    public function run(): void
    {
        $repository = app(SheetRepositoryInterface::class);
        $calcService = app(AppraisalCalculationService::class);

        $formCodes = ['07-001', '07-002', '07-003', '07-004', '07-006-SH', '07-006-DH', '07-006-DIV'];

        // 1. POPULATE MASTER ASPEK
        $aspekRows = [];
        $aspekId = 1;

        foreach ($formCodes as $code) {
            $structure = $calcService->getFormStructure($code);
            $order = 1;

            foreach ($structure['groups'] as $groupKey => $group) {
                foreach ($group['aspects'] as $aspectCode => $aspectName) {
                    $aspekRows[] = [
                        'id' => $aspekId++,
                        'kode_form' => $code,
                        'grup' => $group['name'],
                        'aspek_kode' => $aspectCode,
                        'nama' => $aspectName,
                        'bobot_grup' => $group['weight'],
                        'urutan' => $order++,
                    ];
                }
            }
        }

        foreach ($aspekRows as $row) {
            $repository->insert('master_aspek', $row);
        }

        // 2. POPULATE MASTER KRITERIA (1 to 5 descriptors)
        $kriteriaTemplates = [
            'HK_COST' => [
                5 => 'Menghemat biaya lebih dari atau menghasilkan keuntungan diatas 25% dari masa sebelumnya atau dari target yang ditentukan',
                4 => 'Menghemat biaya atau menghasilkan keuntungan diatas 10% dari masa sebelumnya atau dari target yang ditentukan',
                3 => 'Mencapai target dengan biaya atau keuntungan yang sama dengan sebelumnya atau sesuai dengan target yang sudah ditentukan',
                2 => 'Biaya yang dikeluarkan lebih besar atau keuntungan yang diperoleh lebih rendah 10% dari sebelumnya atau dari taget yang ditentukan',
                1 => 'Biaya yang dikeluarkan lebih besar atau keuntungan yang diperoleh lebih rendah 20% dari sebelumnya atau dari taget yang ditentukan',
            ],
            'HK_DELIVERY' => [
                5 => 'Menyelesaikan lebih cepat dari waktu yang ditetapkan, Delivery tepat waktu dan ada improvement terhadap proses.',
                4 => 'Selalu menepati waktu sesuai rencana dan memenuhi unsur 5S',
                3 => 'Terkadang menyelesai-kan lebih lambat dari waktu yang ditetapkan.',
                2 => 'Sering kali menyelesai-kan lebih lambat dari waktu yang ditetapkan.',
                1 => 'Tidak menyelesaikan',
            ],
            'HK_IMPROVEMENT' => [
                5 => 'Selalu melakukan perbaikan terhadap kualitas kerja secara efektif dan efisien, serta menciptakan sesuatu hal yang baru untuk mencapai target dan perkembangan perusahaan dengan penuh kesadaran, tanpa harus diperintahkan oleh atasan.',
                4 => 'Melakukan perbaikan terhadap kualitas kerja secara efektif dan efisien, serta menciptakan sesuatu hal yang baru untuk mencapai target dan perkembangan perusahaan meski harus melalui instruksi atasan/rekan kerja.',
                3 => 'Dapat melakukan perbaikan terhadap kualitas kerja secara efektif dan efisien, tetapi belum dapat menciptakan sesuatu hal yang baru untuk mencapai target dan perkembangan perusahaan sehingga masih harus diarahkan oleh atasan.',
                2 => 'Berusaha melakukan perbaikan terhadap kualitas kerja meskipun belum efektif dan  efisien.',
                1 => 'Tidak melakukan perbaikan terhadap kualitas kerja  ataupun menciptakan sesuatu hal yang baru sebagai upaya mencapai target perusahaan.',
            ],
            'HK_KUALITAS' => [
                5 => 'Mampu melebihi standar mutu yang ditetapkan',
                4 => 'Memenuhi standart mutu 101-109% dari target yang ditetapkan',
                3 => 'Memenuhi standart mutu 100% dari target yang ditetapkan',
                2 => 'Memenuhi standart mutu 85% -99% dari target yang ditetapkan',
                1 => 'Memenuhi standart mutu dibawah 84% dari target yang ditetapkan',
            ],
            'HK_KUANTITAS' => [
                5 => 'Kuantitas hasil kerja melebihi 100% dari target yang diberikan dan dapat memberikan nilai tambah bagi proses berikutnya dalam kondisi normal',
                4 => 'Menghasilkan 100% dari target yang ditetapkan  meskipun mengalami hambatan',
                3 => 'Menghasilkan 100% dari target yang ditetapkan',
                2 => 'Menghasilkan 80% - 99% dari target yang ditetapkan',
                1 => 'Menghasilkan dibawah 80% dari target yang ditetapkan',
            ],
            'MM_DELEGASI' => [
                5 => 'Mampu untuk melakukan pendelegasian dan dapat memaksimalkan potensi yang ada dengan memberikan dorongan kepada bawahan atau orang lain terhadap tanggung jawab pekerjaan',
                4 => 'Mampu mengoptimalkan potensi sumberdaya yang ada untuk kelancaran tugas yang diberikan kepada bawahan atau orang lain',
                3 => 'Mampu memilih dan membagikan tugas kepada bawahan atau orang lain sesuai dengan kemampuan bawahan/orang lain tersebut',
                2 => 'Mampu memilih dan membagikan tugas kepada bawahan atau orang lain tetapi belum dapat memberikan sesuai dengan kemampuan bawahan/orang lain tersebut',
                1 => 'Tidak dapat membagikan tugas kepada bawahan atau orang lain sesuai dengan kemampuan masing-masing bawahan atau orang lain yang diberikan pekerjaan',
            ],
            'MM_KEPEMIMPINAN' => [
                5 => 'Mampu mengarahkan, membimbing, memotivasi dan mengelola bawahan secara bertanggung jawab dan konsisten dalam mencapai target sesuai visi dan misi perusahaan',
                4 => 'Mampu mengarahkan, membimbing, memotivasi dan mengelola bawahan secara bertanggung jawab, tetapi kurang menjaga konsistensi dalam mencapai target sesuai visi dan misi perusahaan',
                3 => 'Cukup mampu menggerakkan, membimbing dan memotivasi bawahan sebatas kemampuan yang dimiliki',
                2 => 'Kurang dapat menggerakkan dan membimbing bawahan, sehingga terkadang target perusahaan seringkali tidak tercapai',
                1 => 'Tidak dapat menggerakkan bawahan, sehingga target yang ditetapkan perusahaan seringkali tidak tercapai',
            ],
            'MM_KEPUTUSAN' => [
                5 => 'Mampu mengambil keputusan secara cepat, tepat dan tegas, dengan menggunakan informasi/data yang relevan, berani mengambil resiko, dapat mengantisipasi dampak dari keputusanya dan konsisten dengan keputusan yang diambil (tanggung jawab)',
                4 => 'Mampu mengambil keputusan secara cepat, tepat dan tegas, dengan menggunakan informasi/data yang relevan, berani mengambil resiko, tetapi belum dapat mengantisipasi dampak dari keputusan yang diambilnya',
                3 => 'Menunjukkan kemauan untuk mengambil keputusan secara tepat dengan menggunakan informasi/data yang relevan, meskipun masih membutuhkan supervisi',
                2 => 'Kurang dapat mengambil keputusan secara tepat karena tidak menggunakan informasi/data yang relevan',
                1 => 'Tidak dapat mengambil keputusan secara cepat, tepat dan tegas karena selalu menggantungkan/mengandalkan atasannya',
            ],
            'MM_PENGEMBANGAN' => [
                5 => 'Mampu melakukan monitoring perkembangan bawahannya & Memberikan bimbingan serta konseling secara berkala sehingga tercipta kinerja bawahan meningkat secara signifikan dan memberikan apresiasi kepada bawahan yang memiliki prestasi meningkat secara berkesinambungan.',
                4 => 'Mampu menunjukkan keterlibatan yang intensif dalam implementasi pengembangan bawahan, seperti memberikan apresiasi kepada bawahan, konseling, motivasi dan melakukan PDCA sehingga hasil kerja bawahan meningkat',
                3 => 'Mampu mengenali kelebihan dan kelemahan bawahan, kemudian membuat program pengembangan secara terencana dan mengimplementasikan tetapi bawahan belum dapat merasakan dampak terhadap bimbingan dan konseling tersebut',
                2 => 'Mampu mengenali kelebihan dan kelemahan bawahan, tetapi sebatas orang-orang tertentu ( tidak seluruh bawahan ) dan memberikan umpan balik kepada bawahan, meski belum konsisten',
                1 => 'Tidak melakukan monitoring dan pengembangan bawahan, pengembangan berjalan sesuai dengan inisiatif bawahan',
            ],
            'PK_ACTION' => [
                5 => 'Secara konsisten senantiasa mampu mengantisipasi setiap perkembangan dan cepat tanggap dalam melakukan perbaikan dengan tepat dan efisien',
                4 => 'Sudah mampu melakukan tidndakan perbaikan yang tepat dan efisien',
                3 => 'Sudah melakukan tindakan perbaikan yang tepat berdasarkan identifikasi masalah yang dijumpai',
                2 => 'Sudah berusaha melakukan tindakan perbaikan, tetapi tidak efektif/tepat dan usahanya masih belum memadai',
                1 => 'Tidak melakukan tindakan perbaikan, meskipun sudah diketahui ada masalah',
            ],
            'PK_ADMINISTRASI' => [
                5 => 'Mampu membuat sistem administrasi yang lebih baik pada bagiannya sendiri dan memberikan manfaat kelompok kerjanya serta bagian yang terkait',
                4 => 'Mampu membuat sistem administrasi yang lebih baik dibagiannya serndiri dan memberikan manfaat bagi kelompok kerjanya',
                3 => 'Kemampuan administrasi yang rapi dan dilaksanakan sesuai dengan sistem keadministrasian',
                2 => 'Menunjukkan usaha untuk tertib menjalankan sistem kearsipan, namun sesekali masih terdapat ketidakdisiplinan sehingga menimbulkan masalah.',
                1 => 'Tidak menunjukkan keperdulian terhadap sistem kearsipan, sehingga seringkali menimbulkan masalah',
            ],
            'PK_CHECK' => [
                5 => 'Secara konsisten senantiasa menerapkan self check secara lengkap dan mendalam yang dilakukan pada setiap tahapan proses kerja',
                4 => 'Sudah mulai melakukan proakrif check atas hasil kerja sebagai pelengkap dari mekanisme kerja yang telah ditetapkan',
                3 => 'Senantiasa melakukan cek hasil kerja sesuai dengan mekanisme yang telah ditetapkan (check by system)',
                2 => 'Sudah mulai melakukan cek hasil kerja, tetapi kualitas belum memadai',
                1 => 'Tidak melakukan cek terhadap hasil kerja sehingga masalah yang ada tidak teridentifikasi',
            ],
            'PK_DO' => [
                5 => 'Cara kerja sesuai dengan activity plan yang dibuat, efektif, efisien dan mampu mengambil keputusan dengan tepat pada situasi kritis tanpa supervisi',
                4 => 'Cara kerja sesuai dengan activity plan yang dibuat dan mampu mengupayakan alternatif solusi/pemecahan masalah dengan supervisi minimal dari atasan',
                3 => 'Cara kerja cukup sesuai activity plan yang dibuat dengan supervisi sewajarnya',
                2 => 'Cara kerja mengacu kepada activity plan yang dibuat, tetapi seringkali tidak efektif dan tidak efisien terutama dalam hal pengaturan waktu dan prioritas pekerjaan',
                1 => 'Cara kerja tidak mengacu kepada activity plan yang dibuat.',
            ],
            'PK_PENGUASAAN' => [
                5 => 'Sangat menguasai teknis pekerjaan; Mampu melaksanakan pekerjaan  yang menjadi tanggung jawabnya;Hampir tidak mendapat bimbingan dari atasan.',
                4 => 'Menguasai teknis pekerjaan; Mampu melaksanakan pekerjaan  yang menjadi tanggung jawabnya; Mendapat sedikit bimbingan dari atasan.',
                3 => 'Cukup menguasai teknis pekerjaan; Cukup mampu melaksanakan pekerjaan  yang menjadi tanggung jawabnya; Masih memerlukan bimbingan dari atasan.',
                2 => 'Kurang menguasai teknis pekerjaan; Kurang mampu melaksanakan pekerjaan yang menjadi tanggung jawabnya; Memerlukan banyak bimbingan dari atasan.',
                1 => 'Tidak menguasai teknis pekerjaan; Tidak mampu melaksanakan pekerjaan yang menjadi tanggung jawabnya; Jika tidak mendapat bimbingan dari atasan, tidak dapat menyelesaikan pekerjaan',
            ],
            'PK_PLAN' => [
                5 => 'Sudah mampu menyusun activity plan secara komprehensif dan antisipatif dengan mempertimbangkan  kepentingan perusahaan pada saat yang akan datang',
                4 => 'Sudah mampu menyusun  activity plan dengan sistematis, logis dan aplikatif',
                3 => 'Sudah mulai menyusun activity plan namun dengan berbagai keterbatasan  dan seringkali tidak sistematis',
                2 => 'Belum mampun mandiri dalam menyusun activity plan dan masih mememerlukan panduan secara intensif',
                1 => 'Tidak dapat menyusun activiry plan dan masih perlu bimbingan',
            ],
            'PK_PROSEDUR' => [
                5 => 'Mampu mengusulkan perbaikan terhadap SOP yang ada, perbaikan yang diusulkan dapat direalisasikan dan berpengaruh positif terhadap kelompok kerjanya dan bagian lain.',
                4 => 'Dapat mengidentifikasi bahwa SOP yang dilakukan ada kelemahan dan mengkomunikasikan pada atasan tetapi belum mengetahui cara pemecahannya.',
                3 => 'Memahami SOP dan dapat menerapkan dengan tepat.',
                2 => 'Konsisten dalam menerapkan SOP tetapi memiliki keterbatasan dalam memahami/mengimplementasikannya sehingga pernah menimbulkan masalah.',
                1 => 'Tidak konsisten dalam menerapkan SOP, sehingga seringkali menimbulkan masalah.',
            ],
            'SK_ADAPTASI' => [
                5 => 'Mudah dan cepat untuk menyesuaikan diri dalam situasi baru/berubah-ubah dan secara konsisten mempertahankan efektivias kerja meskipun dalam situasi di bawah tekanan',
                4 => 'Mudah dan cepat untuk menyesuaikan diri dalam situasi baru/berubah-ubah tetapi belum konsisten dalam mempertahankan efektivias kerja yang beradai di bawah tekanan',
                3 => 'Cukup mampu menyesuaikan diri dalam situasi baru/berubah-ubah, tetapi sesekali perlu bimbingan/motivasi dari atasan',
                2 => 'Dapat menyesuaikan diri dalam situasi baru/berubah-ubah, tetapi harus melalui bimbingan/motivasi dari atasan',
                1 => 'Kurang dapat menyesuaikan diri dalam situasi baru/berubah-ubah sehingga kualitas kerja kurang memadai',
            ],
            'SK_DISIPLIN' => [
                5 => 'Dapat menjadi model bagi rekan kerja',
                4 => 'Tidak pernah melanggar peraturan perusahaan yang berlaku di perusahaan',
                3 => 'Taat melaksanakan peraturan perusahaan meskipun pernah lalai, dengan alasan yang dapat diterima',
                2 => 'Dalam enam (6) bulan review pernah melakukan kesalahan dan menerima teguran/surat teguran',
                1 => 'Tidak taat melaksanakan peraturan perusahaan dan sering melakukan kesalahan',
            ],
            'SK_INISIATIF' => [
                5 => 'Memiliki semangat untuk selalu mencapai hasil terbaik disertai pemikiran kedepan bersedia untuk melakukan tugas lain di luar tugas rutinnya dengan kesadaran sendiri untuk kemajuan kelompok kerjanya dan bagian yang terkait serta mampu mewujudkannya tanpa perintah atasan.',
                4 => 'Memiliki semangat untuk selalu mencapai hasil terbaik disertai pemikiran kedepan bersedia untuk melakukan tugas lain di luar tugas rutinnya untuk kemajuan kelompok kerjanya meski terkadang perlu didorong oleh atasan.',
                3 => 'Cukup memiliki semangat untuk selalu mencapai hasil terbaik bersedia untuk melakukan tugas lain sepanjang terkait dengan tanggung jawab pekerjaannya, tetapi  kurang diikuti oleh pemikiran untuk kemajuan kedepan',
                2 => 'Masih memerlukan dorongan dari luar dirinya untuk terus menerus berusaha mencapai hasil yang terbaik tetapi menunjukkan kesediaan untuk mengerjakan tugas lain di luar tugas rutin yang diperintahkan oleh atasan',
                1 => 'Tidak memiliki dorongan untuk mencapai hasil kerja yang terbaik dan Tidak tanggap terhadap pekerjaan lain di luar tugas rutinnya serta cepat puas dengan hasil kerja yang biasa-biasa saja',
            ],
            'SK_KOMUNIKASI' => [
                5 => 'Mampu menyampaikan ide/gagasan/fikiran dengan jelas, detail dan mudah membina hubungan kerja sama dengan orang lain, serta memberikan tanggapan yang positif terhadap bantuan / pendapat yang dipahami oleh rekan kerja, atasan maupun orang lain yang terkait',
                4 => 'Terkadang mampu menyampaikan ide/gagasan/fikiran dengan jelas, detail, Bersedia dan mampu membina hubungan kerja sama dan menghargai bantuan orang lain serta mudah dipahami oleh rekan kerja, atasan maupun orang yang terkait dengan aktivitas kerjanya.',
                3 => 'Cukup mampu menyampaikan ide/gagasan /fikiran yang sederhana,Bersedia untuk bekerja sama,  dan Memberikan bantuan saat dibutuhkan kepada rekan kerja, atasan maupun orang lain yang terkait dengan aktivitas kerjanya',
                2 => 'Cukup mampu menyampaikan ide/gagasan /fikiran yang sederhana, Bersedia untuk bekerja sama dan Memberikan bantuan meski harus melalui perintah, kepada rekan kerja saja',
                1 => 'Tidak dapat menyampaikan ide/gagasan/fikiran kepada rekan kerja, Menunjukkan keengganan dan Terkadang sampai menimbulkan perpecahan dalam kelompok kerjanya.',
            ],
            'SK_TANGGUNG_JAWAB' => [
                5 => 'Mampu bertanggung jawab terhadap pencapaian target kerja, efisiensi biaya, serta permasalahan yang muncul dalam departemennya dan menyelesaikan permasalahan dengan cepat, tepat, tanpa banyak mengeluh dan konsisten',
                4 => 'Mampu bertanggung jawab terhadap pencapaian target kerja, efisiensi biaya, serta permasalahan yang muncul dalam departemennya dan berusaha mencari solusi terhadap permasalahan yang muncul.',
                3 => 'Mampu bertanggung jawab terhadap pencapaian target kerja dan menunjukkan usaha dalam mencari solusi terhadap permasalahan yang muncul.',
                2 => 'Kurang bertanggung jawab terhadap pencapaian target kerja, efisiensi biaya dan kurang menunjukkan antusiasme dalam mencari solusi terhadap permasalahan yang muncul.',
                1 => 'Tidak bertanggung jawab terhadap pencapaian target kerja, efisiensi biaya dan tidak menunjukkan antusiasme dalam mencari solusi terhadap permasalahan yang muncul.',
            ],
        ];

        $kriteriaId = 1;
        foreach ($kriteriaTemplates as $aspekCode => $scoreMap) {
            foreach ($scoreMap as $score => $desc) {
                $repository->insert('master_kriteria', [
                    'id' => $kriteriaId++,
                    'aspek_kode' => $aspekCode,
                    'skor' => $score,
                    'deskripsi' => $desc,
                ]);
            }
        }

        // 3. POPULATE KUOTA DEPARTEMEN SESUAI TARGET EXCEL PERUSAHAAN (PROD 2: 171 Org, PROD 1: 2 Org, ENGINEERING: 6 Org)
        $kuotaRows = [
            // PROD 2 (171 Karyawan Non-07-006: KJ 2=64, KJ 3=82, KJ 4=21, KJ 5=4 [mencakup KJ 5+ / Foreman KJ 6])
            [
                'departemen' => 'PROD 2',
                'kj' => 'KJ 2',
                'kuota_s' => 0,
                'kuota_a' => 1,
                'kuota_b' => 16,
                'kuota_c' => 47,
                'kuota_k' => 0,
                'total_karyawan' => 64,
                'created_at' => now(),
            ],
            [
                'departemen' => 'PROD 2',
                'kj' => 'KJ 3',
                'kuota_s' => 0,
                'kuota_a' => 1,
                'kuota_b' => 20,
                'kuota_c' => 61,
                'kuota_k' => 0,
                'total_karyawan' => 82,
                'created_at' => now(),
            ],
            [
                'departemen' => 'PROD 2',
                'kj' => 'KJ 4',
                'kuota_s' => 0,
                'kuota_a' => 0,
                'kuota_b' => 5,
                'kuota_c' => 16,
                'kuota_k' => 0,
                'total_karyawan' => 21,
                'created_at' => now(),
            ],
            [
                'departemen' => 'PROD 2',
                'kj' => 'KJ 5',
                'kuota_s' => 1,
                'kuota_a' => 2,
                'kuota_b' => 2,
                'kuota_c' => 1,
                'kuota_k' => 0,
                'total_karyawan' => 6,
                'created_at' => now(),
            ],
        ];

        foreach ($kuotaRows as $kRow) {
            \Illuminate\Support\Facades\DB::table('kuota_departemen')->updateOrInsert(
                ['departemen' => $kRow['departemen'], 'kj' => $kRow['kj']],
                $kRow
            );
        }

        // 4. GENERATE ACTIVE PENILAIAN DOCUMENTS FOR SEEDED EMPLOYEES
        $karyawanList = $repository->all('karyawan');
        $activePeriod = $repository->find('periode', 'status', 'aktif') ?? ['id' => 1, 'tahun' => 2025];

        $docId = 1;
        foreach ($karyawanList as $k) {
            $kodeForm = match ($k['level_form'] ?? '') {
                'KJ13' => '07-001',
                'KJ45' => '07-002',
                'LEADER' => '07-003',
                'FOREMAN' => '07-004',
                'SECTION_HEAD' => '07-006-SH',
                'DEPT_HEAD' => '07-006-DH',
                'DIV_HEAD' => '07-006-DIV',
                default => \App\Http\Controllers\PenilaianController::determineKodeForm($k),
            };

            $existing = $repository->find('dokumen', 'nrp', $k['nrp']);
            if (!$existing) {
                // Determine sample status & scores for rich demonstration
                $status = 'menunggu_foreman';
                $totalNilai = null;
                $gradeOriginal = null;
                $dockingFinal = null;

                // 1. Dokumen Selesai (Division Head PROD 1)
                if ($k['nrp'] === 'DIV-11000') {
                    $status = 'selesai';
                    $totalNilai = 4.50;// Grade S (Division Head)
                    $gradeOriginal = $calcService->scoreToGrade($totalNilai);
                    $dockingFinal = $gradeOriginal;

                // 2. Evaluasi Mandiri Section Head (Menunggu Section Head / Belum Dinilai)
                } elseif ($kodeForm === '07-006-SH' && !in_array($k['nrp'], ['SH-11310'])) {
                    $status = 'menunggu_section_head';
                    $totalNilai = null;
                    $gradeOriginal = null;
                    $dockingFinal = null;

                } elseif ($k['nrp'] === 'SH-11310') {
                    $status = 'menunggu_dept_head';
                    $totalNilai = 4.25;
                    $gradeOriginal = $calcService->scoreToGrade($totalNilai);
                    $dockingFinal = $gradeOriginal;

                // 3. Evaluasi Mandiri Foreman (Menunggu Foreman / Belum Dinilai)
                } elseif ($kodeForm === '07-004' && strtoupper($k['departemen'] ?? '') === 'PROD 2') {
                    $status = 'menunggu_foreman';
                    $totalNilai = null;
                    $gradeOriginal = null;
                    $dockingFinal = null;

                // 4. Kasus Tanpa Foreman (Section Head C PROD 1 & Section Head FACTORY 3 99999 AL QADRI)
                } elseif (in_array($k['nrp'], ['2023020', '2023021', '2023022']) || (empty($k['nrp_foreman']) && !empty($k['nrp_section_head']))) {
                    $status = 'menunggu_section_head';
                    $totalNilai = null;
                    $gradeOriginal = null;
                    $dockingFinal = null;

                } elseif ($kodeForm === '07-006-DH' || in_array($k['nrp'], ['31', 'DH-11300'])) {
                    $status = 'menunggu_dept_head';
                    $totalNilai = null;
                    $gradeOriginal = null;
                    $dockingFinal = null;
                } elseif ($kodeForm === '07-006-DIV' || in_array($k['nrp'], ['11', 'DIV-12000'])) {
                    $status = 'menunggu_div_head';
                    $totalNilai = null;
                    $gradeOriginal = null;
                    $dockingFinal = null;

                // 5. Bawahan dengan Foreman (Menunggu Foreman)
                } else {
                    $status = 'menunggu_foreman';
                    $totalNilai = null;
                    $gradeOriginal = null;
                    $dockingFinal = null;
                }

                $isKasus2Direct = strtoupper($k['departemen'] ?? '') === 'PROD 2'
                    || $kodeForm === '07-006-SH'
                    || $kodeForm === '07-006-DH'
                    || $kodeForm === '07-006-DIV'
                    || empty($k['nrp_foreman'])
                    || in_array($k['nrp'], ['2023020', '2023021', '2023022', 'DH-11300', 'DIV-11000', 'DIV-12000']);

                $docRecord = [
                    'id' => $docId++,
                    'periode_id' => $activePeriod['id'],
                    'nrp' => $k['nrp'],
                    'kode_form' => $kodeForm,
                    'status' => $status,
                    'total_nilai' => $totalNilai,
                    'grade_original' => $gradeOriginal,
                    'docking_final' => $dockingFinal,
                    'drive_file_id' => null,
                    'drive_url' => null,
                    'hash_dokumen' => $status === 'selesai' ? hash('sha256', $k['nrp'] . 'completed') : null,
                    'kode_verifikasi' => 'PK-' . ($activePeriod['tahun'] ?? 2025) . '-' . $k['nrp'] . '-' . strtoupper(Str::random(6)),
                    'created_at' => now()->toDateTimeString(),
                ];
                $repository->insert('dokumen', $docRecord);

                // Pre-seed aspect scores in 'nilai' table (Kecuali Kasus 2: dibiarkan kosong agar Section Head B dapat mengisi sendiri)
                if (!$isKasus2Direct && in_array($status, ['selesai', 'menunggu_section_head', 'menunggu_dept_head'])) {
                    $formStruct = $calcService->getFormStructure($kodeForm);
                    foreach ($formStruct['groups'] as $g) {
                        foreach ($g['aspects'] as $aspekCode => $aspekName) {
                            $baseVal = match($gradeOriginal) {
                                'A' => 4.0,
                                'S' => 5.0,
                                'C' => 2.5,
                                default => 3.5,
                            };
                            $repository->insert('nilai', [
                                'dokumen_id' => $docRecord['id'],
                                'level' => 'foreman',
                                'aspek_kode' => $aspekCode,
                                'skor' => $baseVal,
                                'created_at' => now(),
                            ]);
                            if (in_array($status, ['selesai', 'menunggu_dept_head'])) {
                                $repository->insert('nilai', [
                                    'dokumen_id' => $docRecord['id'],
                                    'level' => 'section_head',
                                    'aspek_kode' => $aspekCode,
                                    'skor' => $baseVal,
                                    'created_at' => now(),
                                ]);
                            }
                            if ($status === 'selesai') {
                                $repository->insert('nilai', [
                                    'dokumen_id' => $docRecord['id'],
                                    'level' => 'dept_head',
                                    'aspek_kode' => $aspekCode,
                                    'skor' => $baseVal,
                                    'created_at' => now(),
                                ]);
                            }
                        }
                    }

                    // Seed indicator
                    $repository->insert('indikator', [
                        'dokumen_id' => $docRecord['id'],
                        'pilihan_potensi' => 2,
                        'kelebihan' => 'Disiplin dan bertanggung jawab penuh dalam mencapai target.',
                        'kekurangan' => 'Dapat lebih proaktif dalam perbaikan (Kaizen/Improvement).',
                        'created_at' => now(),
                    ]);

                    // Seed digital signature for Foreman
                    $foremanSignerId = \Illuminate\Support\Facades\DB::table('app_users')
                        ->where('nrp', $k['nrp_foreman'] ?? '')
                        ->value('id') ?? 1;
                    $repository->insert('tanda_tangan', [
                        'dokumen_id' => $docRecord['id'],
                        'level' => 'foreman',
                        'user_id' => $foremanSignerId,
                        'tgl_ttd' => now()->subDay()->toDateTimeString(),
                        'ip' => '127.0.0.1',
                        'user_agent' => 'Mozilla/5.0 Seeder',
                        'hash_snapshot' => hash('sha256', 'foreman-' . $docRecord['id']),
                        'ttd_image' => null,
                        'created_at' => now()->subDay()->toDateTimeString(),
                    ]);

                    if (in_array($status, ['selesai', 'menunggu_dept_head'])) {
                        $secSignerId = \Illuminate\Support\Facades\DB::table('app_users')
                            ->where('nrp', $k['nrp_section_head'] ?? '')
                            ->value('id') ?? 1;
                        $repository->insert('tanda_tangan', [
                            'dokumen_id' => $docRecord['id'],
                            'level' => 'section_head',
                            'user_id' => $secSignerId,
                            'tgl_ttd' => now()->toDateTimeString(),
                            'ip' => '127.0.0.1',
                            'user_agent' => 'Mozilla/5.0 Seeder',
                            'hash_snapshot' => hash('sha256', 'section-' . $docRecord['id']),
                            'ttd_image' => null,
                            'created_at' => now()->toDateTimeString(),
                        ]);
                    }

                    if ($status === 'selesai') {
                        $deptSignerId = \Illuminate\Support\Facades\DB::table('app_users')
                            ->where('nrp', $k['nrp_dept_head'] ?? '')
                            ->value('id') ?? 1;
                        $repository->insert('tanda_tangan', [
                            'dokumen_id' => $docRecord['id'],
                            'level' => 'dept_head',
                            'user_id' => $deptSignerId,
                            'tgl_ttd' => now()->toDateTimeString(),
                            'ip' => '127.0.0.1',
                            'user_agent' => 'Mozilla/5.0 Seeder',
                            'hash_snapshot' => hash('sha256', 'dept-' . $docRecord['id']),
                            'ttd_image' => null,
                            'created_at' => now()->toDateTimeString(),
                        ]);
                    }
                }
            }
        }
    }
}


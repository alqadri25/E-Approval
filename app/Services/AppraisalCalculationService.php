<?php

namespace App\Services;

class AppraisalCalculationService
{
    /**
     * Calculate group subtotal, averages, weighted scores, and total final score.
     */
    public function calculate(string $kodeForm, array $scores): array
    {
        $structure = $this->getFormStructure($kodeForm);
        $result = [
            'groups' => [],
            'grand_total' => 0.00,
        ];

        $grandTotal = 0.0;

        foreach ($structure['groups'] as $groupKey => $groupDef) {
            $aspects = $groupDef['aspects'];
            $divisor = count($aspects);
            $weight = $groupDef['weight']; // e.g. 60, 20, 40

            $sum = 0.0;
            $aspectScores = [];

            foreach ($aspects as $code => $name) {
                $val = isset($scores[$code]) ? floatval($scores[$code]) : 0.0;
                $val = max(0.0, min(5.0, $val)); // clamp between 0 and 5
                $aspectScores[$code] = $val;
                $sum += $val;
            }

            // Average (Total Nilai dibagi divisor)
            $avg = $divisor > 0 ? ($sum / $divisor) : 0.0;

            // Weighted final score for group (Rata-rata x weight%)
            $weightedScore = $avg * ($weight / 100.0);

            // Musashi Rounding rule: 2 decimal places (<= 0.5 ke bawah, > 0.5 ke atas)
            $roundedScore = round($weightedScore, 2);

            $result['groups'][$groupKey] = [
                'name' => $groupDef['name'],
                'weight' => $weight,
                'divisor' => $divisor,
                'aspects' => $aspectScores,
                'total_sum' => round($sum, 2),
                'average' => round($avg, 2),
                'weighted_score' => $roundedScore,
            ];

            $grandTotal += $roundedScore;
        }

        $result['grand_total'] = round($grandTotal, 2);
        return $result;
    }

    /**
     * Get structure definitions and aspects for each form code
     */
    public function getFormStructure(string $kodeForm): array
    {
        try {
            $custom = \Illuminate\Support\Facades\DB::table('pengaturan')
                ->where('key', 'form_structure_' . $kodeForm)
                ->value('value');
            if ($custom) {
                $decoded = json_decode($custom, true);
                if (is_array($decoded) && !empty($decoded['groups'])) {
                    return $decoded;
                }
            }
        } catch (\Throwable $e) {
            // fallback to default
        }

        return $this->getDefaultFormStructure($kodeForm);
    }

    public function getDefaultFormStructure(string $kodeForm): array
    {
        return match ($kodeForm) {
            '07-001' => [
                'name' => 'Form Penilaian Karya KJ 1-3',
                'level_form' => 'KJ13',
                'groups' => [
                    'hasil_kerja' => [
                        'name' => 'HASIL KERJA (60%)',
                        'weight' => 60,
                        'aspects' => [
                            'HK_KUALITAS' => 'Kualitas',
                            'HK_KUANTITAS' => 'Kuantitas',
                            'HK_DELIVERY' => 'Delivery',
                            'HK_IMPROVEMENT' => 'Improvement',
                        ]
                    ],
                    'proses_kerja' => [
                        'name' => 'PROSES KERJA (20%)',
                        'weight' => 20,
                        'aspects' => [
                            'PK_PENGUASAAN' => 'Penguasaan Pekerjaan',
                            'PK_ADMINISTRASI' => 'Kerapihan Administrasi',
                            'PK_PROSEDUR' => 'Taat Prosedur Kerja',
                        ]
                    ],
                    'sikap_kerja' => [
                        'name' => 'SIKAP KERJA (20%)',
                        'weight' => 20,
                        'aspects' => [
                            'SK_KOMUNIKASI' => 'Komunikasi & Kerja sama',
                            'SK_INISIATIF' => 'Dorongan Berprestasi / Inisiatif',
                            'SK_DISIPLIN' => 'Disiplin',
                        ]
                    ],
                ]
            ],

            '07-002' => [
                'name' => 'Form Penilaian Karya KJ 4-5 Non Jabatan',
                'level_form' => 'KJ45',
                'groups' => [
                    'hasil_kerja' => [
                        'name' => 'HASIL KERJA (60%)',
                        'weight' => 60,
                        'aspects' => [
                            'HK_KUALITAS' => 'Kualitas',
                            'HK_KUANTITAS' => 'Kuantitas',
                            'HK_DELIVERY' => 'Delivery',
                            'HK_IMPROVEMENT' => 'Improvement',
                        ]
                    ],
                    'proses_kerja' => [
                        'name' => 'PROSES KERJA (20%)',
                        'weight' => 20,
                        'aspects' => [
                            'PK_PENGUASAAN' => 'Penguasaan Pekerjaan',
                            'PK_ADMINISTRASI' => 'Kerapihan Administrasi',
                            'PK_PROSEDUR' => 'Taat Prosedur Kerja',
                        ]
                    ],
                    'sikap_kerja' => [
                        'name' => 'SIKAP KERJA (20%)',
                        'weight' => 20,
                        'aspects' => [
                            'SK_KOMUNIKASI' => 'Komunikasi & Kerja sama',
                            'SK_INISIATIF' => 'Dorongan Berprestasi / Inisiatif',
                            'SK_DISIPLIN' => 'Disiplin',
                            'SK_TANGGUNG_JAWAB' => 'Tanggung Jawab',
                        ]
                    ],
                ]
            ],

            '07-003' => [
                'name' => 'Form Penilaian Karya Leader',
                'level_form' => 'LEADER',
                'groups' => [
                    'hasil_kerja' => [
                        'name' => 'HASIL KERJA (40%)',
                        'weight' => 40,
                        'aspects' => [
                            'HK_KUALITAS' => 'Kualitas',
                            'HK_KUANTITAS' => 'Kuantitas',
                            'HK_DELIVERY' => 'Delivery',
                            'HK_IMPROVEMENT' => 'Improvement',
                            'HK_COST' => 'Cost',
                        ]
                    ],
                    'proses_kerja' => [
                        'name' => 'PROSES KERJA (20%)',
                        'weight' => 20,
                        'aspects' => [
                            'PK_PLAN' => 'Plan',
                            'PK_DO' => 'Do',
                            'PK_CHECK' => 'Check',
                            'PK_ACTION' => 'Action',
                        ]
                    ],
                    'man_management' => [
                        'name' => 'MAN MANAGEMENT (20%)',
                        'weight' => 20,
                        'aspects' => [
                            'MM_KEPEMIMPINAN' => 'Kepemimpinan',
                            'MM_KEPUTUSAN' => 'Pengambilan Keputusan',
                            'MM_DELEGASI' => 'Pendelegasian Tugas',
                            'MM_PENGEMBANGAN' => 'Pengembangan Bawahan (Counching & Counseling)',
                        ]
                    ],
                    'sikap_kerja' => [
                        'name' => 'SIKAP KERJA (20%)',
                        'weight' => 20,
                        'aspects' => [
                            'SK_KOMUNIKASI' => 'Komunikasi & Kerjasama',
                            'SK_ADAPTASI' => 'Adaptasi',
                            'SK_TANGGUNG_JAWAB' => 'Tanggung Jawab',
                        ]
                    ],
                ]
            ],

            '07-004' => [
                'name' => 'Form Penilaian Karya Foreman',
                'level_form' => 'FOREMAN',
                'groups' => [
                    'hasil_kerja' => [
                        'name' => 'HASIL KERJA (40%)',
                        'weight' => 40,
                        'aspects' => [
                            'HK_KUALITAS' => 'Kualitas',
                            'HK_KUANTITAS' => 'Kuantitas',
                            'HK_DELIVERY' => 'Delivery',
                            'HK_IMPROVEMENT' => 'Improvement',
                            'HK_COST' => 'Cost',
                        ]
                    ],
                    'proses_kerja' => [
                        'name' => 'PROSES KERJA (20%)',
                        'weight' => 20,
                        'aspects' => [
                            'PK_PLAN' => 'Plan',
                            'PK_DO' => 'Do',
                            'PK_CHECK' => 'Check',
                            'PK_ACTION' => 'Action',
                        ]
                    ],
                    'man_management' => [
                        'name' => 'MAN MANAGEMENT (20%)',
                        'weight' => 20,
                        'aspects' => [
                            'MM_KEPEMIMPINAN' => 'Kepemimpinan',
                            'MM_KEPUTUSAN' => 'Pengambilan Keputusan',
                            'MM_DELEGASI' => 'Pendelegasian Tugas',
                            'MM_PENGEMBANGAN' => 'Pengembangan Bawahan (Counching & Counseling)',
                        ]
                    ],
                    'sikap_kerja' => [
                        'name' => 'SIKAP KERJA (20%)',
                        'weight' => 20,
                        'aspects' => [
                            'SK_KOMUNIKASI' => 'Komunikasi & Kerjasama',
                            'SK_ADAPTASI' => 'Adaptasi',
                            'SK_TANGGUNG_JAWAB' => 'Tanggung Jawab',
                        ]
                    ],
                ]
            ],

            '07-006-SH' => [
                'name' => 'Form Penilaian Karya Section Head (Gol. 4 Up)',
                'level_form' => 'SECTION_HEAD',
                'groups' => [
                    'hasil_kerja' => [
                        'name' => 'HASIL KERJA (55%) / 仕事の結果 (55%)',
                        'weight' => 55,
                        'aspects' => [
                            'HK_KUALITAS' => 'Kualitas / 質',
                            'HK_KUANTITAS' => 'Kuantitas / 量',
                            'HK_DELIVERY' => 'Delivery / 期間',
                            'HK_IMPROVEMENT' => 'Improvement / 改善',
                            'HK_COST' => 'Cost / コスト',
                        ]
                    ],
                    'proses_kerja' => [
                        'name' => 'PROSES KERJA (15%) / 仕事の方法 (15%)',
                        'weight' => 15,
                        'aspects' => [
                            'PK_PLAN' => 'Plan (Perencanaan)',
                            'PK_DO' => 'Do (Pelaksanaan)',
                            'PK_CHECK' => 'Check (Pemeriksaan)',
                            'PK_ACTION' => 'Action (Tindakan Lanjut)',
                        ]
                    ],
                    'man_management' => [
                        'name' => 'MAN MANAGEMENT (15%) / 人材管理 (15%)',
                        'weight' => 15,
                        'aspects' => [
                            'MM_KEPEMIMPINAN' => 'Kepemimpinan / リーダーシップ',
                            'MM_KEPUTUSAN' => 'Pengambilan Keputusan / 決まる能力',
                            'MM_DELEGASI' => 'Pendelegasian Tugas / 部下の管理',
                            'MM_PENGEMBANGAN' => 'Pengembangan Bawahan / 部下の発展の能力',
                        ]
                    ],
                    'sikap_kerja' => [
                        'name' => 'SIKAP KERJA (15%) / 仕事に関する性格 (15%)',
                        'weight' => 15,
                        'aspects' => [
                            'SK_KOMUNIKASI' => 'Komunikasi & Kerja Sama / コミュニケーション',
                            'SK_ADAPTASI' => 'Adaptasi / 順応',
                            'SK_TANGGUNG_JAWAB' => 'Tanggung Jawab / 責任',
                        ]
                    ],
                ]
            ],

            '07-006-DH' => [
                'name' => 'Form Penilaian Karya Department Head (Gol. 4 Up)',
                'level_form' => 'DEPT_HEAD',
                'groups' => [
                    'hasil_kerja' => [
                        'name' => 'HASIL KERJA (55%) / 仕事の結果 (55%)',
                        'weight' => 55,
                        'aspects' => [
                            'HK_KUALITAS' => 'Kualitas / 質',
                            'HK_KUANTITAS' => 'Kuantitas / 量',
                            'HK_DELIVERY' => 'Delivery / 期間',
                            'HK_IMPROVEMENT' => 'Improvement / 改善',
                            'HK_COST' => 'Cost / コスト',
                        ]
                    ],
                    'proses_kerja' => [
                        'name' => 'PROSES KERJA (15%) / 仕事の方法 (15%)',
                        'weight' => 15,
                        'aspects' => [
                            'PK_PLAN' => 'Plan (Perencanaan)',
                            'PK_DO' => 'Do (Pelaksanaan)',
                            'PK_CHECK' => 'Check (Pemeriksaan)',
                            'PK_ACTION' => 'Action (Tindakan Lanjut)',
                        ]
                    ],
                    'man_management' => [
                        'name' => 'MAN MANAGEMENT (15%) / 人材管理 (15%)',
                        'weight' => 15,
                        'aspects' => [
                            'MM_KEPEMIMPINAN' => 'Kepemimpinan / リーダーシップ',
                            'MM_KEPUTUSAN' => 'Pengambilan Keputusan / 決まる能力',
                            'MM_DELEGASI' => 'Pendelegasian Tugas / 部下の管理',
                            'MM_PENGEMBANGAN' => 'Pengembangan Bawahan / 部下の発展の能力',
                        ]
                    ],
                    'sikap_kerja' => [
                        'name' => 'SIKAP KERJA (15%) / 仕事に関する性格 (15%)',
                        'weight' => 15,
                        'aspects' => [
                            'SK_KOMUNIKASI' => 'Komunikasi & Kerja Sama / コミュニケーション',
                            'SK_ADAPTASI' => 'Adaptasi / 順応',
                            'SK_TANGGUNG_JAWAB' => 'Tanggung Jawab / 責任',
                        ]
                    ],
                ]
            ],

            '07-006-DIV' => [
                'name' => 'Form Penilaian Karya Division Head (Gol. 4 Up)',
                'level_form' => 'DIV_HEAD',
                'groups' => [
                    'hasil_kerja' => [
                        'name' => 'HASIL KERJA (55%) / 仕事の結果 (55%)',
                        'weight' => 55,
                        'aspects' => [
                            'HK_KUALITAS' => 'Kualitas / 質',
                            'HK_KUANTITAS' => 'Kuantitas / 量',
                            'HK_DELIVERY' => 'Delivery / 期間',
                            'HK_IMPROVEMENT' => 'Improvement / 改善',
                            'HK_COST' => 'Cost / コスト',
                        ]
                    ],
                    'proses_kerja' => [
                        'name' => 'PROSES KERJA (15%) / 仕事の方法 (15%)',
                        'weight' => 15,
                        'aspects' => [
                            'PK_PLAN' => 'Plan (Perencanaan)',
                            'PK_DO' => 'Do (Pelaksanaan)',
                            'PK_CHECK' => 'Check (Pemeriksaan)',
                            'PK_ACTION' => 'Action (Tindakan Lanjut)',
                        ]
                    ],
                    'man_management' => [
                        'name' => 'MAN MANAGEMENT (15%) / 人材管理 (15%)',
                        'weight' => 15,
                        'aspects' => [
                            'MM_KEPEMIMPINAN' => 'Kepemimpinan / リーダーシップ',
                            'MM_KEPUTUSAN' => 'Pengambilan Keputusan / 決まる能力',
                            'MM_DELEGASI' => 'Pendelegasian Tugas / 部下の管理',
                            'MM_PENGEMBANGAN' => 'Pengembangan Bawahan / 部下の発展の能力',
                        ]
                    ],
                    'sikap_kerja' => [
                        'name' => 'SIKAP KERJA (15%) / 仕事に関する性格 (15%)',
                        'weight' => 15,
                        'aspects' => [
                            'SK_KOMUNIKASI' => 'Komunikasi & Kerja Sama / コミュニケーション',
                            'SK_ADAPTASI' => 'Adaptasi / 順応',
                            'SK_TANGGUNG_JAWAB' => 'Tanggung Jawab / 責任',
                        ]
                    ],
                ]
            ],

            default => throw new \InvalidArgumentException("Kode form '{$kodeForm}' tidak dikenali."),
        };
    }

    /**
     * Convert Total Score (1-5) into Musashi Grade Letter (S, A, B, C, K)
     */
    public function scoreToGrade(float $score): string
    {
        if ($score > 4.50) return 'S';
        if ($score > 3.75) return 'A';
        if ($score >= 3.00) return 'B';
        if ($score >= 1.00) return 'C';
        return 'K';
    }
}


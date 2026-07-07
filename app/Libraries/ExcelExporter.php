<?php

namespace App\Libraries;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;
use App\Models\JadwalModel;
use App\Models\KelasModel;
use App\Models\GuruModel;
use App\Models\RuanganModel;
use App\Models\TimeslotModel;

class ExcelExporter
{
    protected JadwalModel $jadwalModel;
    protected KelasModel $kelasModel;
    protected GuruModel $guruModel;
    protected RuanganModel $ruanganModel;
    protected TimeslotModel $timeslotModel;

    public function __construct()
    {
        $this->jadwalModel = new JadwalModel();
        $this->kelasModel = new KelasModel();
        $this->guruModel = new GuruModel();
        $this->ruanganModel = new RuanganModel();
        $this->timeslotModel = new TimeslotModel();
    }

    public function exportByKelas(int $kelasId, int $taId, ?int $scheduleLogId = null)
    {
        $kelas = $this->kelasModel->find($kelasId);
        $jadwal = $this->jadwalModel->getByKelas($kelasId, $taId, $scheduleLogId);

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle(substr($kelas['nama'], 0, 31));

        $this->createTimetableSheet($sheet, $jadwal, 'kelas', $kelas['nama']);

        return $this->generateExcel($spreadsheet, 'jadwal_kelas_' . str_replace(' ', '_', $kelas['nama']));
    }

    public function exportByGuru(int $guruId, int $taId, ?int $scheduleLogId = null)
    {
        $guru = $this->getGuruWithUser($guruId);
        $jadwal = $this->jadwalModel->getByGuru($guruId, $taId, $scheduleLogId);

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Jadwal Guru');

        $this->createTimetableSheet($sheet, $jadwal, 'guru', $guru['nama'] ?? 'Guru');

        return $this->generateExcel($spreadsheet, 'jadwal_guru_' . str_replace(' ', '_', $guru['nama'] ?? 'guru'));
    }

    public function exportByRuangan(int $ruanganId, int $taId, ?int $scheduleLogId = null)
    {
        $ruangan = $this->ruanganModel->find($ruanganId);
        $jadwal = $this->jadwalModel->getByRuangan($ruanganId, $taId, $scheduleLogId);

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Jadwal Ruangan');

        $this->createTimetableSheet($sheet, $jadwal, 'ruangan', $ruangan['nama']);

        return $this->generateExcel($spreadsheet, 'jadwal_ruangan_' . str_replace(' ', '_', $ruangan['nama']));
    }

    public function exportAll(int $taId, ?int $scheduleLogId = null)
    {
        $kelasList = $this->kelasModel->where('tahun_ajaran_id', $taId)->findAll();

        $spreadsheet = new Spreadsheet();

        foreach ($kelasList as $i => $kelas) {
            if ($i == 0) {
                $sheet = $spreadsheet->getActiveSheet();
            } else {
                $sheet = $spreadsheet->createSheet();
            }

            $sheet->setTitle(substr($kelas['nama'], 0, 31));

            $jadwal = $this->jadwalModel->getByKelas($kelas['id'], $taId, $scheduleLogId);
            $this->createTimetableSheet($sheet, $jadwal, 'kelas', $kelas['nama']);
        }

        $spreadsheet->setActiveSheetIndex(0);

        return $this->generateExcel($spreadsheet, 'jadwal_semua_kelas');
    }

    private function createTimetableSheet($sheet, array $jadwal, string $viewType, string $title): void
    {
        $db = \Config\Database::connect();
        $hari = $db->table('hari')->orderBy('urutan', 'ASC')->get()->getResultArray();
        $timeslotsByHari = $this->timeslotModel->getGroupedByHari();

        $jadwalIndex = [];
        foreach ($jadwal as $j) {
            $jadwalIndex[(int) $j['hari_id']][(int) $j['timeslot_id']] = $j;
        }

        $lastCol = chr(ord('A') + count($hari));
        $sheet->mergeCells('A1:' . $lastCol . '1');
        $sheet->setCellValue('A1', 'SMART SCHOOL SCHEDULING');
        $sheet->mergeCells('A2:' . $lastCol . '2');
        $sheet->setCellValue('A2', 'Jadwal: ' . $title);

        $row = 4;
        $col = 'A';
        foreach ($hari as $h) {
            $sheet->setCellValue($col . $row, $h['nama']);
            $sheet->getColumnDimension($col)->setWidth(28);
            $col++;
        }
        $this->applyHeaderStyles($sheet, 'A' . $row . ':' . chr(ord('A') + count($hari) - 1) . $row);

        $maxRows = 0;
        foreach ($hari as $h) {
            $maxRows = max($maxRows, count($timeslotsByHari[(int) $h['id']] ?? []));
        }

        $row++;
        for ($i = 0; $i < $maxRows; $i++) {
            $col = 'A';
            foreach ($hari as $h) {
                $hariId = (int) $h['id'];
                $slots = $timeslotsByHari[$hariId] ?? [];
                $slot = $slots[$i] ?? null;

                if (! $slot) {
                    $sheet->setCellValue($col . $row, '');
                    $col++;

                    continue;
                }

                $tipe = $slot['tipe'] ?? 'jp';
                $timeLabel = substr($slot['waktu_mulai'], 0, 5) . '-' . substr($slot['waktu_selesai'], 0, 5);

                if ($tipe === 'kegiatan_khusus') {
                    $text = strtoupper($slot['keterangan'] ?? 'KEGIATAN') . "\n" . $timeLabel;
                    $sheet->setCellValue($col . $row, $text);
                    $sheet->getStyle($col . $row)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFDEE2E6');
                } elseif ($tipe === 'istirahat') {
                    $text = 'ISTIRAHAT' . "\n" . $timeLabel;
                    $sheet->setCellValue($col . $row, $text);
                    $sheet->getStyle($col . $row)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFE9ECEF');
                } else {
                    $cell = $jadwalIndex[$hariId][(int) $slot['id']] ?? null;
                    if ($cell) {
                        $text = $cell['mapel_nama'];
                        if ($viewType === 'kelas') {
                            $text .= "\n" . $cell['guru_nama'];
                            if (($cell['ruangan_tipe'] ?? '') === 'lab') {
                                $text .= "\nLab: " . $cell['ruangan_kode'];
                            }
                        } elseif ($viewType === 'guru') {
                            $text .= "\nKelas: " . $cell['kelas_nama'] . "\nRuang: " . $cell['ruangan_kode'];
                        } elseif ($viewType === 'ruangan') {
                            $text .= "\nKelas: " . $cell['kelas_nama'] . "\n" . $cell['guru_nama'];
                        }
                        $sheet->setCellValue($col . $row, "JP {$slot['jam_ke']}\n{$timeLabel}\n{$text}");
                        $hexColor = str_replace('#', '', $cell['mapel_warna'] ?? '');
                        if (strlen($hexColor) === 6) {
                            $sheet->getStyle($col . $row)->getFill()
                                ->setFillType(Fill::FILL_SOLID)
                                ->getStartColor()->setARGB('FF' . $hexColor);
                            $sheet->getStyle($col . $row)->getFont()->getColor()->setARGB('FFFFFFFF');
                        }
                    } else {
                        $sheet->setCellValue($col . $row, "JP {$slot['jam_ke']}\n{$timeLabel}\n-");
                    }
                }

                $sheet->getStyle($col . $row)->getAlignment()->setWrapText(true);
                $col++;
            }
            $row++;
        }

        $endRow = $row - 1;
        $endCol = chr(ord('A') + count($hari) - 1);
        $sheet->getStyle('A4:' . $endCol . $endRow)->applyFromArray([
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color'       => ['argb' => 'FF000000'],
                ],
            ],
            'alignment' => [
                'vertical' => Alignment::VERTICAL_CENTER,
            ],
        ]);
        $sheet->getStyle('A1:A2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
        $sheet->getStyle('A2')->getFont()->setBold(true);
    }

    private function getGuruWithUser(int $guruId): ?array
    {
        $db = \Config\Database::connect();

        return $db->table('guru')
            ->select('guru.*, users.nama')
            ->join('users', 'users.id = guru.user_id')
            ->where('guru.id', $guruId)
            ->get()
            ->getRowArray();
    }

    private function applyHeaderStyles($sheet, $range): void
    {
        $sheet->getStyle($range)->applyFromArray([
            'font' => [
                'bold'  => true,
                'color' => ['argb' => 'FFFFFFFF'],
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical'   => Alignment::VERTICAL_CENTER,
            ],
            'fill' => [
                'fillType'   => Fill::FILL_SOLID,
                'startColor' => ['argb' => 'FF4F46E5'],
            ],
        ]);
    }

    private function generateExcel($spreadsheet, string $filename)
    {
        $writer = new Xlsx($spreadsheet);

        ob_start();
        $writer->save('php://output');
        $content = ob_get_clean();

        $response = \Config\Services::response();
        $response->setHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $response->setHeader('Content-Disposition', 'attachment; filename="' . $filename . '.xlsx"');
        $response->setHeader('Cache-Control', 'max-age=0');
        $response->setBody($content);

        return $response;
    }
}

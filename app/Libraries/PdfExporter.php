<?php

namespace App\Libraries;

use Dompdf\Dompdf;
use Dompdf\Options;
use App\Models\JadwalModel;
use App\Models\KelasModel;
use App\Models\GuruModel;
use App\Models\RuanganModel;
use App\Models\TimeslotModel;

class PdfExporter
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

        $html = $this->renderTimetableHtml($jadwal, 'kelas', $kelas['nama']);

        return $this->generatePdf($html, 'jadwal_kelas_' . str_replace(' ', '_', $kelas['nama']));
    }

    public function exportByGuru(int $guruId, int $taId, ?int $scheduleLogId = null)
    {
        $guru = $this->getGuruWithUser($guruId);
        $jadwal = $this->jadwalModel->getByGuru($guruId, $taId, $scheduleLogId);
        $totalJp = $this->jadwalModel->countJpByGuru($guruId, $taId, $scheduleLogId);

        $html = $this->renderTimetableHtml($jadwal, 'guru', $guru['nama'] ?? 'Guru', $totalJp);

        return $this->generatePdf($html, 'jadwal_guru_' . str_replace(' ', '_', $guru['nama'] ?? 'guru'));
    }

    public function exportByRuangan(int $ruanganId, int $taId, ?int $scheduleLogId = null)
    {
        $ruangan = $this->ruanganModel->find($ruanganId);
        $jadwal = $this->jadwalModel->getByRuangan($ruanganId, $taId, $scheduleLogId);

        $html = $this->renderTimetableHtml($jadwal, 'ruangan', $ruangan['nama']);

        return $this->generatePdf($html, 'jadwal_ruangan_' . str_replace(' ', '_', $ruangan['nama']));
    }

    public function exportAll(int $taId, ?int $scheduleLogId = null)
    {
        $kelasList = $this->kelasModel->where('tahun_ajaran_id', $taId)->findAll();

        $html = '';
        foreach ($kelasList as $i => $kelas) {
            $jadwal = $this->jadwalModel->getByKelas($kelas['id'], $taId, $scheduleLogId);
            $html .= $this->renderTimetableHtml($jadwal, 'kelas', $kelas['nama']);

            if ($i < count($kelasList) - 1) {
                $html .= '<div style="page-break-before: always;"></div>';
            }
        }

        return $this->generatePdf($html, 'jadwal_semua_kelas');
    }

    private function renderTimetableHtml(array $jadwal, string $viewType, string $title, ?int $totalJp = null): string
    {
        $db = \Config\Database::connect();
        $hari = $db->table('hari')->orderBy('urutan', 'ASC')->get()->getResultArray();
        $timeslotsByHari = $this->timeslotModel->getGroupedByHari();

        $viewHtml = view('components/timetable', [
            'jadwal'          => $jadwal,
            'hari'            => $hari,
            'timeslotsByHari' => $timeslotsByHari,
            'viewType'        => $viewType,
            'title'           => $title,
            'totalJp'         => $totalJp,
            'isExport'        => true,
        ]);

        return '
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="utf-8">
            <title>Jadwal ' . esc($title) . '</title>
            <style>
                body { font-family: sans-serif; font-size: 10px; }
                .timetable-day-header { background-color: #4f46e5; color: #fff; padding: 6px; font-weight: bold; }
                .timetable-break, .timetable-kegiatan { background-color: #e9ecef; padding: 4px; text-align: center; }
                .cell-title { font-weight: bold; font-size: 9px; }
                .cell-subtitle { font-size: 8px; }
                .header-title { text-align: center; font-size: 16px; font-weight: bold; margin-bottom: 5px; }
                .header-subtitle { text-align: center; font-size: 12px; margin-bottom: 15px; }
                .footer { margin-top: 30px; width: 100%; }
                .footer td { border: none !important; text-align: center; width: 33%; }
            </style>
        </head>
        <body>
            <div class="header-title">SMART SCHOOL SCHEDULING</div>
            <div class="header-subtitle">SMK Tunas Teknologi</div>
            ' . $viewHtml . '
            <table class="footer">
                <tr>
                    <td>Mengetahui,<br>Kepala Sekolah<br><br><br><br>___________________</td>
                    <td></td>
                    <td>Dibuat oleh,<br>Kurikulum<br><br><br><br>___________________</td>
                </tr>
            </table>
        </body>
        </html>
        ';
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

    private function generatePdf(string $html, string $filename)
    {
        $options = new Options();
        $options->set('defaultFont', 'sans-serif');
        $options->set('isRemoteEnabled', false);
        $options->set('isHtml5ParserEnabled', true);

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'landscape');
        $dompdf->render();

        $output = $dompdf->output();

        $response = \Config\Services::response();
        $response->setHeader('Content-Type', 'application/pdf');
        $response->setHeader('Content-Disposition', 'attachment; filename="' . $filename . '.pdf"');
        $response->setHeader('Cache-Control', 'max-age=0');
        $response->setBody($output);

        return $response;
    }
}

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

        $blocks = [];
        foreach ($kelasList as $kelas) {
            $jadwal = $this->jadwalModel->getByKelas($kelas['id'], $taId, $scheduleLogId);
            $blocks[] = $this->renderTimetableBlock($jadwal, 'kelas', $kelas['nama']);
        }

        $html = $this->wrapPdfDocument($blocks);

        return $this->generatePdf($html, 'jadwal_semua_kelas');
    }

    private function renderTimetableHtml(array $jadwal, string $viewType, string $title, ?int $totalJp = null): string
    {
        return $this->wrapPdfDocument([
            $this->renderTimetableBlock($jadwal, $viewType, $title, $totalJp),
        ]);
    }

    /**
     * Inner timetable block (header + grid + footer) without full HTML document wrapper.
     */
    private function renderTimetableBlock(array $jadwal, string $viewType, string $title, ?int $totalJp = null): string
    {
        $db = \Config\Database::connect();
        $hari = $db->table('hari')->orderBy('urutan', 'ASC')->get()->getResultArray();
        $timeslotsByHari = $this->timeslotModel->getGroupedByHari();
        $branding = BrandingService::get();
        $schoolName = esc($branding['nama_sekolah']);
        $logoUri = BrandingService::logoDataUri();
        $logoHtml = $logoUri
            ? '<img src="' . $logoUri . '" style="max-height:26px;max-width:70px;vertical-align:middle;margin-right:8px;">'
            : '';

        $viewHtml = view('components/timetable', [
            'jadwal'          => $jadwal,
            'hari'            => $hari,
            'timeslotsByHari' => $timeslotsByHari,
            'viewType'        => $viewType,
            'title'           => $title,
            'totalJp'         => $totalJp,
            'isExport'        => true,
        ]);

        $totalJpBadge = ($viewType === 'guru' && $totalJp !== null)
            ? ' <span style="font-weight:normal;font-size:8px;">(' . (int) $totalJp . ' JP/minggu)</span>'
            : '';

        return '
            <div class="pdf-block">
                <div class="pdf-header">
                    ' . $logoHtml . '<span class="pdf-header-title">SMART SCHOOL SCHEDULING</span>
                    <div class="pdf-header-sub">' . $schoolName . '</div>
                </div>
                <div class="pdf-schedule-title">Jadwal: ' . esc($title) . $totalJpBadge . '</div>
                ' . $viewHtml . '
                <table class="footer">
                    <tr>
                        <td>Mengetahui,<br>Kepala Sekolah<div class="sign-space"></div>___________________</td>
                        <td></td>
                        <td>Dibuat oleh,<br>Kurikulum<div class="sign-space"></div>___________________</td>
                    </tr>
                </table>
            </div>
        ';
    }

    /**
     * @param list<string> $blocks HTML fragments from renderTimetableBlock()
     */
    private function wrapPdfDocument(array $blocks): string
    {
        $body = '';
        foreach ($blocks as $i => $block) {
            $breakClass = $i > 0 ? ' pdf-block-break' : '';
            $body .= '<div class="pdf-section' . $breakClass . '">' . $block . '</div>';
        }

        return '
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="utf-8">
            <title>Jadwal</title>
            ' . $this->getPdfStyles() . '
        </head>
        <body>
            ' . $body . '
        </body>
        </html>
        ';
    }

    private function getPdfStyles(): string
    {
        return '<style>
                @page { margin: 7mm 8mm 8mm 8mm; }
                body {
                    font-family: DejaVu Sans, sans-serif;
                    font-size: 8px;
                    color: #1f2937;
                    margin: 0;
                    padding: 0;
                }
                .pdf-section { margin: 0; padding: 0; }
                .pdf-block-break { page-break-before: always; }
                .pdf-header {
                    text-align: center;
                    margin: 0 0 5px 0;
                    padding: 0 0 5px 0;
                    border-bottom: 1px solid #c7d2fe;
                }
                .pdf-header-title {
                    font-size: 11px;
                    font-weight: bold;
                    margin: 0;
                    line-height: 1.25;
                }
                .pdf-header-sub {
                    font-size: 8px;
                    color: #4b5563;
                    margin: 2px 0 0;
                    line-height: 1.25;
                }
                .pdf-schedule-title {
                    text-align: center;
                    font-size: 10px;
                    font-weight: bold;
                    margin: 4px 0 6px;
                    line-height: 1.25;
                }
                .timetable-wrapper h5,
                .timetable-wrapper .mb-3,
                .timetable-wrapper .badge { display: none !important; }
                .timetable-table {
                    width: 100%;
                    border-collapse: collapse;
                    table-layout: fixed;
                    font-size: 7.5px;
                    page-break-inside: avoid;
                }
                .timetable-table thead th {
                    background-color: #4f46e5;
                    color: #fff;
                    text-align: center;
                    font-weight: bold;
                    padding: 5px 4px;
                    border: 1px solid #3730a3;
                    vertical-align: middle;
                    font-size: 8px;
                    line-height: 1.2;
                }
                .tt-th-jp { width: 24px; }
                .tt-th-time { width: 58px; text-align: left !important; font-size: 7px !important; }
                .timetable-table tbody td {
                    border: 1px solid #d1d5db;
                    vertical-align: middle;
                    padding: 3px 4px;
                }
                .tt-td-jp {
                    width: 24px;
                    text-align: center;
                    background: #f8fafc;
                    font-weight: bold;
                    color: #4f46e5;
                    padding: 4px 2px;
                    font-size: 7.5px;
                }
                .tt-jp-num { display: inline-block; }
                .tt-td-time {
                    width: 58px;
                    font-size: 6.5px;
                    color: #4b5563;
                    white-space: nowrap;
                    padding: 4px 3px;
                    background: #f8fafc;
                }
                .tt-td-cell {
                    padding: 3px;
                    background: #fff;
                }
                .tt-td-merged { height: auto; }
                .tt-td-empty { background: #fafafa; }
                .tt-subject {
                    border-radius: 3px;
                    padding: 4px 5px;
                    color: #fff;
                    box-sizing: border-box;
                    line-height: 1.3;
                }
                .tt-subject--spanned {
                    padding: 5px 5px;
                }
                .tt-blok-badge {
                    display: inline-block;
                    background: rgba(0,0,0,0.22);
                    color: #fff;
                    font-size: 6px;
                    padding: 1px 4px;
                    border-radius: 2px;
                    font-weight: bold;
                    margin: 0 0 2px 0;
                    line-height: 1.2;
                }
                .tt-subject-name {
                    font-weight: bold;
                    font-size: 7px;
                    line-height: 1.3;
                    margin: 0 0 2px 0;
                }
                .tt-subject-sub {
                    font-size: 6px;
                    line-height: 1.3;
                    margin: 0 0 1px 0;
                    opacity: 0.95;
                }
                .tt-subject-room,
                .tt-lab-badge {
                    font-size: 6px;
                    line-height: 1.25;
                    margin: 2px 0 0 0;
                }
                .tt-lab-badge {
                    display: inline-block;
                    background: rgba(0,0,0,0.22);
                    padding: 1px 4px;
                    border-radius: 2px;
                    font-weight: bold;
                }
                .tt-td-break {
                    background: #f1f5f9 !important;
                    text-align: center;
                    padding: 4px 5px !important;
                }
                .tt-td-kegiatan {
                    background: #e0e7ff !important;
                    text-align: center;
                    padding: 4px 5px !important;
                }
                .tt-break-inner,
                .tt-kegiatan-inner {
                    text-align: center;
                    padding: 1px 0;
                    line-height: 1.25;
                }
                .tt-break-label,
                .tt-kegiatan-label {
                    display: block;
                    font-weight: bold;
                    font-size: 6.5px;
                    margin: 0 0 2px 0;
                    color: #475569;
                }
                .tt-kegiatan-label { color: #3730a3; }
                .tt-break-time {
                    display: block;
                    font-size: 6px;
                    color: #64748b;
                    margin: 0;
                }
                .tt-row-break td { background-color: #f8fafc; }
                .tt-row-kegiatan td { background-color: #eef2ff; }
                .tt-icon-break,
                .tt-icon-kegiatan,
                .bi { display: none; }
                .tt-empty-cell { min-height: 0; }
                .tt-action-bar,
                .tt-swap-pick-btn,
                .tt-delete-btn,
                .tt-empty-add-icon { display: none !important; }
                .footer {
                    margin-top: 8px;
                    width: 100%;
                    border-collapse: collapse;
                }
                .footer td {
                    border: none !important;
                    text-align: center;
                    width: 33%;
                    font-size: 8px;
                    padding-top: 2px;
                    line-height: 1.3;
                }
                .footer .sign-space { height: 22px; }
            </style>';
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
        $options->set('defaultFont', 'DejaVu Sans');
        $options->set('isRemoteEnabled', false);
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isFontSubsettingEnabled', true);

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

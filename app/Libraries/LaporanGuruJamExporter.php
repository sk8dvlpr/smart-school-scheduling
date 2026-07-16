<?php

namespace App\Libraries;

use Dompdf\Dompdf;
use Dompdf\Options;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class LaporanGuruJamExporter
{
    /**
     * ponytail: pure grouping helper — tested in JadwalLaporanTest
     *
     * @param list<array<string, mixed>> $rows
     *
     * @return list<array{guru_id: int, nip: string, nama_guru: string, rows: list<array<string, mixed>>, subtotal: int}>
     */
    public static function groupByGuru(array $rows): array
    {
        $grouped = [];
        foreach ($rows as $row) {
            $guruId = (int) $row['guru_id'];
            if (! isset($grouped[$guruId])) {
                $grouped[$guruId] = [
                    'guru_id'    => $guruId,
                    'nip'        => (string) ($row['nip'] ?? ''),
                    'nama_guru'  => (string) ($row['nama_guru'] ?? ''),
                    'rows'       => [],
                    'subtotal'   => 0,
                ];
            }
            $jp = (int) $row['total_jp'];
            $grouped[$guruId]['rows'][] = $row;
            $grouped[$guruId]['subtotal'] += $jp;
        }

        return array_values($grouped);
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @param array<string, mixed>       $meta
     */
    public function toPdf(array $rows, array $meta)
    {
        $grouped = self::groupByGuru($rows);
        $html    = $this->renderPdfHtml($grouped, $meta);

        $options = new Options();
        $options->set('defaultFont', 'sans-serif');
        $options->set('isRemoteEnabled', false);
        $options->set('isHtml5ParserEnabled', true);

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        $response = \Config\Services::response();
        $response->setHeader('Content-Type', 'application/pdf');
        $response->setHeader('Content-Disposition', 'attachment; filename="laporan_jam_mengajar_guru.pdf"');
        $response->setHeader('Cache-Control', 'max-age=0');
        $response->setBody($dompdf->output());

        return $response;
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @param array<string, mixed>       $meta
     */
    public function toExcel(array $rows, array $meta)
    {
        $grouped     = self::groupByGuru($rows);
        $spreadsheet = new Spreadsheet();
        $sheet       = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Rekap Guru');

        $sheet->setCellValue('A1', 'Laporan Jam Mengajar Guru');
        $sheet->setCellValue('A2', 'Tahun Ajaran: ' . ($meta['tahun_ajaran'] ?? '-'));
        $sheet->setCellValue('A3', 'Dicetak: ' . ($meta['tanggal_cetak'] ?? date('d/m/Y H:i')));

        $headers = ['NIP', 'Nama Guru', 'Kode Mapel', 'Nama Mapel', 'JP/Minggu'];
        $col     = 'A';
        foreach ($headers as $header) {
            $sheet->setCellValue($col . '5', $header);
            $col++;
        }
        $sheet->getStyle('A5:E5')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']],
            'fill' => [
                'fillType'   => Fill::FILL_SOLID,
                'startColor' => ['argb' => 'FF4F46E5'],
            ],
        ]);

        $rowNum = 6;
        foreach ($grouped as $group) {
            foreach ($group['rows'] as $item) {
                $sheet->setCellValue('A' . $rowNum, $group['nip']);
                $sheet->setCellValue('B' . $rowNum, $group['nama_guru']);
                $sheet->setCellValue('C' . $rowNum, $item['mapel_kode'] ?? '');
                $sheet->setCellValue('D' . $rowNum, $item['mapel_nama'] ?? '');
                $sheet->setCellValue('E' . $rowNum, (int) $item['total_jp']);
                $rowNum++;
            }
            $sheet->setCellValue('D' . $rowNum, 'Subtotal');
            $sheet->setCellValue('E' . $rowNum, $group['subtotal']);
            $sheet->getStyle('A' . $rowNum . ':E' . $rowNum)->getFont()->setBold(true);
            $rowNum++;
        }

        foreach (range('A', 'E') as $c) {
            $sheet->getColumnDimension($c)->setAutoSize(true);
        }

        if (! empty($meta['detail_per_hari'])) {
            $detailSheet = $spreadsheet->createSheet();
            $detailSheet->setTitle('Detail per Hari');
            $detailSheet->fromArray(['Nama Guru', 'Hari', 'Kode Mapel', 'Nama Mapel', 'JP'], null, 'A1');
            $detailSheet->getStyle('A1:E1')->getFont()->setBold(true);
            $dRow = 2;
            foreach ($meta['detail_per_hari'] as $d) {
                $detailSheet->setCellValue('A' . $dRow, $d['nama_guru'] ?? '');
                $detailSheet->setCellValue('B' . $dRow, $d['hari_nama'] ?? '');
                $detailSheet->setCellValue('C' . $dRow, $d['mapel_kode'] ?? '');
                $detailSheet->setCellValue('D' . $dRow, $d['mapel_nama'] ?? '');
                $detailSheet->setCellValue('E' . $dRow, (int) ($d['total_jp'] ?? 0));
                $dRow++;
            }
        }

        $writer = new Xlsx($spreadsheet);
        ob_start();
        $writer->save('php://output');
        $content = ob_get_clean();

        $response = \Config\Services::response();
        $response->setHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $response->setHeader('Content-Disposition', 'attachment; filename="laporan_jam_mengajar_guru.xlsx"');
        $response->setHeader('Cache-Control', 'max-age=0');
        $response->setBody($content);

        return $response;
    }

    /**
     * @param list<array{guru_id: int, nip: string, nama_guru: string, rows: list<array<string, mixed>>, subtotal: int}> $grouped
     * @param array<string, mixed>                                                                                          $meta
     */
    private function renderPdfHtml(array $grouped, array $meta): string
    {
        $school   = esc($meta['sekolah'] ?? 'SMK');
        $ta       = esc($meta['tahun_ajaran'] ?? '-');
        $printed  = esc($meta['tanggal_cetak'] ?? date('d/m/Y H:i'));
        $logoUri  = $meta['logo_data_uri'] ?? null;
        $logoHtml = is_string($logoUri) && $logoUri !== ''
            ? '<div style="margin-bottom:8px;"><img src="' . $logoUri . '" style="max-height:48px;max-width:120px;"></div>'
            : '';

        $html = '<html><head><style>
            body { font-family: DejaVu Sans, sans-serif; font-size: 11px; }
            h2 { margin: 0 0 4px; }
            .meta { color: #555; margin-bottom: 16px; }
            table { width: 100%; border-collapse: collapse; }
            th, td { border: 1px solid #ccc; padding: 6px 8px; text-align: left; }
            th { background: #4F46E5; color: #fff; }
            .subtotal td { font-weight: bold; background: #f3f4f6; }
            .footer { margin-top: 16px; font-size: 10px; color: #666; }
        </style></head><body>';
        $html .= $logoHtml;
        $html .= '<h2>Laporan Jam Mengajar Guru</h2>';
        $html .= '<div class="meta">' . $school . '<br>Tahun Ajaran: ' . $ta . '<br>Dicetak: ' . $printed . '</div>';
        $html .= '<table><thead><tr>
            <th>NIP</th><th>Nama Guru</th><th>Kode Mapel</th><th>Nama Mapel</th><th>JP/Minggu</th>
        </tr></thead><tbody>';

        foreach ($grouped as $group) {
            foreach ($group['rows'] as $i => $item) {
                $html .= '<tr>';
                if ($i === 0) {
                    $html .= '<td>' . esc($group['nip']) . '</td>';
                    $html .= '<td>' . esc($group['nama_guru']) . '</td>';
                } else {
                    $html .= '<td></td><td></td>';
                }
                $html .= '<td>' . esc($item['mapel_kode'] ?? '') . '</td>';
                $html .= '<td>' . esc($item['mapel_nama'] ?? '') . '</td>';
                $html .= '<td>' . (int) $item['total_jp'] . '</td>';
                $html .= '</tr>';
            }
            $html .= '<tr class="subtotal"><td colspan="4" style="text-align:right">Subtotal</td><td>' . $group['subtotal'] . '</td></tr>';
        }

        $html .= '</tbody></table>';
        $html .= '<p class="footer">Data dari jadwal generate terakhir — untuk perhitungan gaji manual.</p>';
        $html .= '</body></html>';

        return $html;
    }
}

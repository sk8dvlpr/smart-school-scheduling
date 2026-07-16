<?php

namespace App\Controllers\KepalaSekolah;

use App\Controllers\BaseController;
use App\Libraries\LaporanGuruJamExporter;
use App\Models\JadwalModel;
use App\Models\MapelModel;
use App\Models\TahunAjaranModel;

class LaporanController extends BaseController
{
    protected TahunAjaranModel $taModel;
    protected JadwalModel $jadwalModel;
    protected MapelModel $mapelModel;

    public function __construct()
    {
        $this->taModel     = new TahunAjaranModel();
        $this->jadwalModel = new JadwalModel();
        $this->mapelModel  = new MapelModel();
    }

    public function guruJam(): string
    {
        $activeTa = $this->taModel->where('is_active', 1)->first();
        $guruId   = (int) ($this->request->getGet('guru_id') ?? 0);
        $mapelId  = (int) ($this->request->getGet('mapel_id') ?? 0);

        $rows      = [];
        $grouped   = [];
        $hasJadwal = false;

        if ($activeTa) {
            $logId = $this->jadwalModel->resolveApprovedScheduleLogId((int) $activeTa['id']);
            $hasJadwal = $logId !== null && $this->jadwalModel
                ->where('tahun_ajaran_id', $activeTa['id'])
                ->where('schedule_log_id', $logId)
                ->countAllResults() > 0;

            if ($hasJadwal) {
                $rows    = $this->jadwalModel->getJamMengajarReport(
                    (int) $activeTa['id'],
                    $guruId > 0 ? $guruId : null,
                    $mapelId > 0 ? $mapelId : null,
                    $logId
                );
                $grouped = LaporanGuruJamExporter::groupByGuru($rows);
            }
        }

        $db = \Config\Database::connect();

        return view('kepala_sekolah/laporan/guru_jam', [
            'title'       => 'Laporan Jam Mengajar Guru',
            'active_ta'   => $activeTa,
            'has_jadwal'  => $hasJadwal,
            'rows'        => $rows,
            'grouped'     => $grouped,
            'guru_list'   => $db->table('guru')
                ->select('guru.id, users.nama')
                ->join('users', 'users.id = guru.user_id')
                ->where('guru.deleted_at IS NULL')
                ->orderBy('users.nama', 'ASC')
                ->get()
                ->getResultArray(),
            'mapel_list'  => $this->mapelModel->where('deleted_at IS NULL')->orderBy('nama', 'ASC')->findAll(),
            'filter_guru' => $guruId,
            'filter_mapel'=> $mapelId,
        ]);
    }

    public function export()
    {
        $activeTa = $this->taModel->where('is_active', 1)->first();
        if (! $activeTa) {
            return redirect()->back()->with('error', 'Tidak ada Tahun Ajaran aktif.');
        }

        $format  = $this->request->getGet('format') ?? 'pdf';
        $guruId  = (int) ($this->request->getGet('guru_id') ?? 0);
        $mapelId = (int) ($this->request->getGet('mapel_id') ?? 0);

        $logId = $this->jadwalModel->resolveApprovedScheduleLogId((int) $activeTa['id']);
        if ($logId === null) {
            return redirect()->back()->with('error', 'Belum ada jadwal yang disetujui Kepala Sekolah.');
        }

        $rows = $this->jadwalModel->getJamMengajarReport(
            $activeTa['id'],
            $guruId > 0 ? $guruId : null,
            $mapelId > 0 ? $mapelId : null,
            $logId
        );

        if ($rows === []) {
            return redirect()->back()->with('error', 'Tidak ada data jadwal untuk diekspor.');
        }

        $meta = [
            'sekolah'       => \App\Libraries\BrandingService::get()['nama_sekolah'],
            'logo_data_uri' => \App\Libraries\BrandingService::logoDataUri(),
            'tahun_ajaran'  => $activeTa['nama'],
            'tanggal_cetak' => date('d/m/Y H:i'),
        ];

        if ($format === 'excel') {
            $detail = [];
            $guruIds = array_unique(array_column($rows, 'guru_id'));
            foreach ($guruIds as $gid) {
                $perHari = $this->jadwalModel->getJamMengajarPerHari($activeTa['id'], (int) $gid);
                $nama    = '';
                foreach ($rows as $r) {
                    if ((int) $r['guru_id'] === (int) $gid) {
                        $nama = $r['nama_guru'];
                        break;
                    }
                }
                foreach ($perHari as $ph) {
                    $ph['nama_guru'] = $nama;
                    $detail[]        = $ph;
                }
            }
            $meta['detail_per_hari'] = $detail;
        }

        $exporter = new LaporanGuruJamExporter();

        if ($format === 'excel') {
            return $exporter->toExcel($rows, $meta);
        }

        return $exporter->toPdf($rows, $meta);
    }
}

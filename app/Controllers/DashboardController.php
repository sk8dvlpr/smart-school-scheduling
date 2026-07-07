<?php

namespace App\Controllers;

class DashboardController extends BaseController
{
    public function kurikulum(): string
    {
        $db = \Config\Database::connect();
        $activeTa = $db->table('tahun_ajaran')->where('is_active', 1)->get()->getRowArray();
        $activeTaId = (int) ($activeTa['id'] ?? 0);
        $latestLog = null;
        $jadwalPerHari = [0, 0, 0, 0, 0];
        $hasJadwal = false;
        $jadwalHariIni = [];

        if ($activeTaId > 0) {
            $hasJadwal = $db->table('jadwal')->where('tahun_ajaran_id', $activeTaId)->countAllResults() > 0;

            $latestLog = $db->table('schedule_logs')
                ->where('tahun_ajaran_id', $activeTaId)
                ->orderBy('id', 'DESC')
                ->get()
                ->getRow();

            $hariRows = $db->table('hari')->orderBy('urutan', 'ASC')->get()->getResultArray();
            foreach ($hariRows as $idx => $hari) {
                $jadwalPerHari[$idx] = $db->table('jadwal')
                    ->where('tahun_ajaran_id', $activeTaId)
                    ->where('hari_id', $hari['id'])
                    ->countAllResults();
            }

            $guruId = (int) session()->get('guru_id');
            if ($guruId > 0) {
                $todayDow = (int) date('N');
                $hariIni = $db->table('hari')->where('urutan', $todayDow)->get()->getRowArray();
                if ($hariIni) {
                    $jadwalModel = new \App\Models\JadwalModel();
                    $allJadwal = $jadwalModel->getByGuru($guruId, $activeTaId);
                    $jadwalHariIni = array_values(array_filter($allJadwal, fn ($j) => (int) $j['hari_id'] === (int) $hariIni['id']));
                }
            }
        }

        $kelasPerJurusanRows = $db->table('kelas')
            ->select('jurusan.kode, COUNT(kelas.id) AS total')
            ->join('jurusan', 'jurusan.id = kelas.jurusan_id')
            ->where('kelas.deleted_at IS NULL')
            ->groupBy('jurusan.kode')
            ->orderBy('jurusan.kode', 'ASC')
            ->get()
            ->getResultArray();

        $kelasPerJurusanLabels = [];
        $kelasPerJurusanData = [];
        foreach ($kelasPerJurusanRows as $row) {
            $kelasPerJurusanLabels[] = $row['kode'];
            $kelasPerJurusanData[] = (int) $row['total'];
        }

        return view('dashboard/kurikulum', [
            'title'                    => 'Dashboard Kurikulum',
            'total_users'              => $db->table('users')->where('deleted_at IS NULL')->countAllResults(),
            'total_guru'               => $db->table('guru')->where('deleted_at IS NULL')->countAllResults(),
            'total_kelas'              => $db->table('kelas')->where('deleted_at IS NULL')->countAllResults(),
            'total_mapel'              => $db->table('mapel')->where('deleted_at IS NULL')->countAllResults(),
            'logs'                     => $db->table('schedule_logs')->orderBy('started_at', 'DESC')->orderBy('id', 'DESC')->limit(5)->get()->getResult(),
            'active_ta'                => $activeTa,
            'latest_log'               => $latestLog,
            'has_jadwal'               => $hasJadwal,
            'jadwal_per_hari'          => $jadwalPerHari,
            'jadwal_hari_ini'          => $jadwalHariIni,
            'kelas_per_jurusan_labels' => $kelasPerJurusanLabels,
            'kelas_per_jurusan_data'   => $kelasPerJurusanData,
        ]);
    }

    public function kepalaSekolah(): string
    {
        $db = \Config\Database::connect();
        $activeTa = $db->table('tahun_ajaran')->where('is_active', 1)->get()->getRowArray();
        $activeTaId = (int) ($activeTa['id'] ?? 0);
        $jadwalCount = 0;
        $guruTerjadwal = 0;
        $latestLog = null;

        if ($activeTaId > 0) {
            $jadwalCount = $db->table('jadwal')->where('tahun_ajaran_id', $activeTaId)->countAllResults();
            $guruTerjadwal = (new \App\Models\JadwalModel())->countDistinctGuruTerjadwal($activeTaId);

            $latestLog = $db->table('schedule_logs')
                ->where('tahun_ajaran_id', $activeTaId)
                ->where('status', 'completed')
                ->orderBy('id', 'DESC')
                ->get()
                ->getRowArray();
        }

        return view('dashboard/kepala_sekolah', [
            'title'          => 'Dashboard Kepala Sekolah',
            'active_ta'      => $activeTa,
            'jadwal_count'   => $jadwalCount,
            'guru_terjadwal' => $guruTerjadwal,
            'latest_log'     => $latestLog,
            'has_jadwal'     => $jadwalCount > 0,
            'total_kelas'    => $db->table('kelas')->where('deleted_at IS NULL')->countAllResults(),
            'total_guru'     => $db->table('guru')->where('deleted_at IS NULL')->countAllResults(),
        ]);
    }
}

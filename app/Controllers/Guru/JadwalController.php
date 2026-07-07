<?php

namespace App\Controllers\Guru;

use App\Controllers\BaseController;
use App\Models\JadwalModel;
use App\Models\TahunAjaranModel;
use App\Models\TimeslotModel;
use App\Models\UserModel;
use App\Models\HariModel;

class JadwalController extends BaseController
{
    public function index()
    {
        $guruId = (int) session()->get('guru_id');
        if ($guruId <= 0) {
            return redirect()->to('/profile')->with('error', 'Profil guru tidak ditemukan.');
        }

        $taModel  = new TahunAjaranModel();
        $activeTa = $taModel->where('is_active', 1)->first();

        if (! $activeTa) {
            return view('guru/jadwal/index', [
                'title'     => 'Jadwal Mengajar',
                'active_ta' => null,
                'error'     => 'Tidak ada Tahun Ajaran aktif.',
            ]);
        }

        $jadwalModel = new JadwalModel();
        $logId       = $jadwalModel->resolveScheduleLogId((int) $activeTa['id']);
        $publishedLog = (new \App\Libraries\ScheduleHistoryService())->getPublishedLog((int) $activeTa['id']);

        if ($logId === null) {
            return view('guru/jadwal/index', [
                'title'     => 'Jadwal Mengajar',
                'active_ta' => $activeTa,
                'error'     => 'Belum ada jadwal yang dipublish oleh Kurikulum.',
            ]);
        }

        $user = (new UserModel())->find((int) session()->get('user_id'));

        $jadwal      = $jadwalModel->getByGuru($guruId, (int) $activeTa['id'], $logId);
        $totalJp     = $jadwalModel->countJpByGuru($guruId, (int) $activeTa['id'], $logId);

        $hariModel = new HariModel();
        $timeslotModel = new TimeslotModel();

        return view('guru/jadwal/index', [
            'title'           => 'Jadwal Mengajar',
            'user'            => $user,
            'jadwal'          => $jadwal,
            'hari'            => $hariModel->orderBy('urutan', 'ASC')->findAll(),
            'timeslotsByHari' => $timeslotModel->getGroupedByHari(),
            'total_jp'        => $totalJp,
            'active_ta'       => $activeTa,
            'published_log'   => $publishedLog,
        ]);
    }

    public function export(string $format)
    {
        $guruId = (int) session()->get('guru_id');
        if ($guruId <= 0) {
            return redirect()->back()->with('error', 'Profil guru tidak ditemukan.');
        }

        $taModel  = new TahunAjaranModel();
        $activeTa = $taModel->where('is_active', 1)->first();
        if (! $activeTa) {
            return redirect()->back()->with('error', 'Tidak ada Tahun Ajaran aktif.');
        }

        if ($format === 'pdf') {
            $exporter = new \App\Libraries\PdfExporter();
            $logId = (new JadwalModel())->resolveScheduleLogId((int) $activeTa['id']);

            return $exporter->exportByGuru($guruId, (int) $activeTa['id'], $logId);
        }
        if ($format === 'excel') {
            $exporter = new \App\Libraries\ExcelExporter();
            $logId = (new JadwalModel())->resolveScheduleLogId((int) $activeTa['id']);

            return $exporter->exportByGuru($guruId, (int) $activeTa['id'], $logId);
        }

        return redirect()->back()->with('error', 'Format tidak didukung.');
    }
}

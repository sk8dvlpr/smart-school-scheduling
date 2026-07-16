<?php

namespace App\Controllers\Guru;

use App\Controllers\BaseController;
use App\Libraries\ScheduleHistoryService;
use App\Models\JadwalModel;
use App\Models\TahunAjaranModel;
use App\Models\TimeslotModel;
use App\Models\UserModel;
use App\Models\HariModel;

class JadwalController extends BaseController
{
    /**
     * @return \CodeIgniter\HTTP\RedirectResponse|string
     */
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

        $jadwalModel  = new JadwalModel();
        $historySvc   = new ScheduleHistoryService();
        $publishedLog = $historySvc->getPublishedLog((int) $activeTa['id']);
        $logId        = $jadwalModel->resolveApprovedScheduleLogId((int) $activeTa['id']);

        if ($logId === null) {
            $error = 'Belum ada jadwal yang dipublish oleh Kurikulum.';
            if ($publishedLog !== null) {
                $status = $publishedLog['approval_status'] ?? 'pending';
                if ($status === 'pending') {
                    $error = 'Jadwal sudah dipublish, menunggu persetujuan Kepala Sekolah.';
                } elseif ($status === 'rejected') {
                    $error = 'Jadwal ditolak Kepala Sekolah. Menunggu Kurikulum publish ulang.';
                }
            }

            return view('guru/jadwal/index', [
                'title'         => 'Jadwal Mengajar',
                'active_ta'     => $activeTa,
                'error'         => $error,
                'published_log' => $publishedLog,
            ]);
        }

        $user = (new UserModel())->find((int) session()->get('user_id'));

        $jadwal  = $jadwalModel->getByGuru($guruId, (int) $activeTa['id'], $logId);
        $totalJp = $jadwalModel->countJpByGuru($guruId, (int) $activeTa['id'], $logId);

        $hariModel     = new HariModel();
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

    /**
     * @return \CodeIgniter\HTTP\RedirectResponse|\CodeIgniter\HTTP\ResponseInterface
     */
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

        $logId = (new JadwalModel())->resolveApprovedScheduleLogId((int) $activeTa['id']);
        if ($logId === null) {
            return redirect()->back()->with('error', 'Belum ada jadwal yang disetujui Kepala Sekolah.');
        }

        if ($format === 'pdf') {
            $exporter = new \App\Libraries\PdfExporter();

            return $exporter->exportByGuru($guruId, (int) $activeTa['id'], $logId);
        }

        if ($format === 'excel') {
            $exporter = new \App\Libraries\ExcelExporter();

            return $exporter->exportByGuru($guruId, (int) $activeTa['id'], $logId);
        }

        return redirect()->back()->with('error', 'Format export tidak valid.');
    }
}

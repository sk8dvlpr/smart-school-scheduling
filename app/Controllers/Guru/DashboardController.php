<?php

namespace App\Controllers\Guru;

use App\Controllers\BaseController;
use App\Models\JadwalModel;
use App\Models\TahunAjaranModel;
use App\Models\UserModel;
use App\Models\HariModel;

class DashboardController extends BaseController
{
    public function index()
    {
        $guruId = (int) session()->get('guru_id');
        if ($guruId <= 0) {
            return redirect()->to('/profile')->with('error', 'Profil guru tidak ditemukan.');
        }

        $taModel  = new TahunAjaranModel();
        $activeTa = $taModel->where('is_active', 1)->first();

        $jadwalModel = new JadwalModel();
        $jadwal      = [];
        $totalJp     = 0;
        $totalKelas  = 0;
        $jadwalHariIni = [];
        $jadwalBesok = [];

        if ($activeTa) {
            $jadwal = $jadwalModel->getByGuru($guruId, $activeTa['id']);
            $totalKelas = count(array_unique(array_column($jadwal, 'kelas_id')));
            $totalJp = $jadwalModel->countJpByGuru($guruId, $activeTa['id']);

            $hariModel = new HariModel();
            $hariIni = $hariModel->where('urutan', (int) date('N'))->first();
            $besokUrutan = (int) date('N') + 1;
            if ($besokUrutan > 5) {
                $besokUrutan = 1;
            }
            $hariBesok = $hariModel->where('urutan', $besokUrutan)->first();

            if ($hariIni) {
                $hariIniId = (int) $hariIni['id'];
                $jadwalHariIni = array_values(array_filter(
                    $jadwal,
                    fn ($row) => (int) $row['hari_id'] === $hariIniId
                ));
            }

            if ($hariBesok) {
                $besokId = (int) $hariBesok['id'];
                $jadwalBesok = array_values(array_filter(
                    $jadwal,
                    fn ($row) => (int) $row['hari_id'] === $besokId
                ));
            }
        }

        $user = (new UserModel())->find((int) session()->get('user_id'));

        return view('guru/dashboard', [
            'title'           => 'Dashboard Guru',
            'user'            => $user,
            'jadwal'          => $jadwal,
            'jadwal_hari_ini' => $jadwalHariIni,
            'total_jp'        => $totalJp,
            'total_kelas'     => $totalKelas,
            'jadwal_besok'    => $jadwalBesok,
            'active_ta'       => $activeTa,
        ]);
    }
}

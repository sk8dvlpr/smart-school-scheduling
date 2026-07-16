<?php

namespace App\Controllers\Guru;

use App\Controllers\BaseController;
use App\Models\GuruHariBlokirModel;
use App\Models\HariModel;

class HariBlokirController extends BaseController
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

        $blokirModel = new GuruHariBlokirModel();
        $hariModel   = new HariModel();

        $hari = $hariModel->orderBy('urutan', 'ASC')->findAll();
        $blocked = $blokirModel->where('guru_id', $guruId)->findAll();
        $blockedIds = array_map('intval', array_column($blocked, 'hari_id'));

        return view('guru/hari_blokir/index', [
            'title'       => 'Hari Tidak Mengajar',
            'hari'        => $hari,
            'blocked_ids' => $blockedIds,
        ]);
    }

    /**
     * @return \CodeIgniter\HTTP\RedirectResponse
     */
    public function update()
    {
        $guruId = (int) session()->get('guru_id');
        if ($guruId <= 0) {
            return redirect()->to('/profile')->with('error', 'Profil guru tidak ditemukan.');
        }

        $checked = $this->request->getPost('hari_id') ?? [];
        if (! is_array($checked)) {
            $checked = [];
        }

        $blokirModel = new GuruHariBlokirModel();
        $blokirModel->where('guru_id', $guruId)->delete();

        foreach ($checked as $hariId) {
            $blokirModel->insert([
                'guru_id' => $guruId,
                'hari_id' => (int) $hariId,
            ]);
        }

        return redirect()->to('/guru/hari-blokir')->with('success', 'Hari tidak mengajar berhasil disimpan.');
    }
}

<?php

namespace App\Controllers\Guru;

use App\Controllers\BaseController;
use App\Models\GuruPreferensiModel;
use App\Models\HariModel;
use App\Models\TimeslotModel;

class PreferensiController extends BaseController
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

        $prefModel = new GuruPreferensiModel();
        $hariModel = new HariModel();
        $tsModel   = new TimeslotModel();
        $hari      = $hariModel->orderBy('urutan', 'ASC')->findAll();
        $rows      = $prefModel->getByGuru($guruId);

        return view('guru/preferensi/index', [
            'title'           => 'Preferensi Jadwal',
            'hari'            => $hari,
            'timeslotsByHari' => $tsModel->getGroupedByHari(),
            'formState'       => $prefModel->toFormState($rows, $hari),
        ]);
    }

    /**
     * @return \CodeIgniter\HTTP\RedirectResponse
     */
    public function save()
    {
        $guruId = (int) session()->get('guru_id');
        if ($guruId <= 0) {
            return redirect()->to('/profile')->with('error', 'Profil guru tidak ditemukan.');
        }

        $prefModel = new GuruPreferensiModel();
        $dayPost   = $this->request->getPost('day');
        $rows      = $prefModel->fromFormPost(is_array($dayPost) ? $dayPost : []);
        $prefModel->replaceForGuru($guruId, $rows);

        return redirect()->to('/guru/preferensi')->with('success', 'Preferensi jadwal berhasil disimpan.');
    }
}

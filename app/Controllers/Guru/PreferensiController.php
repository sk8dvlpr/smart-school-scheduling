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

        return view('guru/preferensi/index', [
            'title'           => 'Preferensi Jadwal',
            'preferensi'      => $prefModel->getByGuru($guruId),
            'hari'            => $hariModel->orderBy('urutan', 'ASC')->findAll(),
            'timeslotsByHari' => $tsModel->getGroupedByHari(),
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

        $rules = [
            'preferensi' => 'permit_empty',
        ];
        if (! $this->validate($rules)) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        $raw = $this->request->getPost('preferensi');
        $rows = is_array($raw) ? $raw : [];

        (new GuruPreferensiModel())->replaceForGuru($guruId, $rows);

        return redirect()->to('/guru/preferensi')->with('success', 'Preferensi jadwal berhasil disimpan.');
    }
}

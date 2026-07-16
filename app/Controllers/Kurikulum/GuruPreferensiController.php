<?php

namespace App\Controllers\Kurikulum;

use App\Controllers\BaseController;
use App\Models\GuruModel;
use App\Models\GuruPreferensiModel;
use App\Models\HariModel;
use App\Models\TimeslotModel;

class GuruPreferensiController extends BaseController
{
    protected GuruModel $guruModel;
    protected GuruPreferensiModel $prefModel;
    protected HariModel $hariModel;
    protected TimeslotModel $timeslotModel;

    public function __construct()
    {
        $this->guruModel     = new GuruModel();
        $this->prefModel     = new GuruPreferensiModel();
        $this->hariModel     = new HariModel();
        $this->timeslotModel = new TimeslotModel();
    }

    /**
     * @return \CodeIgniter\HTTP\RedirectResponse|string
     */
    public function index(int $guruId)
    {
        $guru = $this->getGuruWithUser($guruId);
        if (! $guru) {
            return redirect()->to('/kurikulum/guru')->with('error', 'Guru tidak ditemukan.');
        }

        $hari = $this->hariModel->orderBy('urutan', 'ASC')->findAll();
        $rows = $this->prefModel->getByGuru($guruId);

        return view('kurikulum/guru/preferensi', [
            'title'           => 'Preferensi Jadwal — ' . $guru['nama'],
            'guru'            => $guru,
            'hari'            => $hari,
            'timeslotsByHari' => $this->timeslotModel->getGroupedByHari(),
            'formState'       => $this->prefModel->toFormState($rows, $hari),
        ]);
    }

    /**
     * @return \CodeIgniter\HTTP\RedirectResponse
     */
    public function update(int $guruId)
    {
        if (! $this->guruModel->find($guruId)) {
            return redirect()->to('/kurikulum/guru')->with('error', 'Guru tidak ditemukan.');
        }

        $dayPost = $this->request->getPost('day');
        $rows    = $this->prefModel->fromFormPost(is_array($dayPost) ? $dayPost : []);
        $this->prefModel->replaceForGuru($guruId, $rows);

        return redirect()->to("/kurikulum/guru/{$guruId}/preferensi")
            ->with('success', 'Preferensi jadwal berhasil disimpan.');
    }

    private function getGuruWithUser(int $guruId): ?array
    {
        $db = \Config\Database::connect();

        return $db->table('guru')
            ->select('guru.*, users.nip, users.nama')
            ->join('users', 'users.id = guru.user_id')
            ->where('guru.id', $guruId)
            ->where('guru.deleted_at IS NULL')
            ->get()
            ->getRowArray();
    }
}

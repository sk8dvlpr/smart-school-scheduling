<?php

namespace App\Controllers\Kurikulum;

use App\Controllers\BaseController;
use App\Models\GuruModel;
use App\Models\GuruHariBlokirModel;
use App\Models\HariModel;

class GuruHariBlokirController extends BaseController
{
    protected GuruModel $guruModel;
    protected GuruHariBlokirModel $blokirModel;
    protected HariModel $hariModel;

    public function __construct()
    {
        $this->guruModel = new GuruModel();
        $this->blokirModel = new GuruHariBlokirModel();
        $this->hariModel = new HariModel();
    }

    public function index(int $guruId)
    {
        $guru = $this->getGuruWithUser($guruId);
        if (! $guru) {
            return redirect()->to('/kurikulum/guru')->with('error', 'Guru tidak ditemukan.');
        }

        $hari = $this->hariModel->orderBy('urutan', 'ASC')->findAll();
        $blocked = $this->blokirModel->where('guru_id', $guruId)->findAll();
        $blockedIds = array_column($blocked, 'hari_id');

        return view('kurikulum/guru/hari_blokir', [
            'title'        => 'Hari Blokir — ' . $guru['nama'],
            'guru'         => $guru,
            'hari'         => $hari,
            'blocked_ids'  => $blockedIds,
        ]);
    }

    public function update(int $guruId)
    {
        if (! $this->guruModel->find($guruId)) {
            return redirect()->to('/kurikulum/guru')->with('error', 'Guru tidak ditemukan.');
        }

        $checked = $this->request->getPost('hari_id') ?? [];
        if (! is_array($checked)) {
            $checked = [];
        }

        $this->blokirModel->where('guru_id', $guruId)->delete();

        foreach ($checked as $hariId) {
            $this->blokirModel->insert([
                'guru_id' => $guruId,
                'hari_id' => (int) $hariId,
            ]);
        }

        return redirect()->to("/kurikulum/guru/$guruId/hari-blokir")->with('success', 'Hari blokir berhasil disimpan.');
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

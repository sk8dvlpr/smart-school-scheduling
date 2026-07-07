<?php

namespace App\Controllers\Kurikulum;

use App\Controllers\BaseController;
use App\Models\GuruModel;
use App\Models\GuruMapelModel;
use App\Models\MapelModel;

class GuruMapelController extends BaseController
{
    protected GuruModel $guruModel;
    protected GuruMapelModel $guruMapelModel;
    protected MapelModel $mapelModel;

    public function __construct()
    {
        $this->guruModel = new GuruModel();
        $this->guruMapelModel = new GuruMapelModel();
        $this->mapelModel = new MapelModel();
    }

    public function index(int $guruId)
    {
        $guru = $this->getGuruWithUser($guruId);
        if (! $guru) {
            return redirect()->to('/kurikulum/guru')->with('error', 'Guru tidak ditemukan.');
        }

        $db = \Config\Database::connect();
        $mapelList = $db->table('guru_mapel')
            ->select('guru_mapel.*, mapel.nama as mapel_nama, mapel.kode as mapel_kode')
            ->join('mapel', 'mapel.id = guru_mapel.mapel_id')
            ->where('guru_mapel.guru_id', $guruId)
            ->orderBy('mapel.nama', 'ASC')
            ->get()
            ->getResultArray();

        $totalCap = array_sum(array_column($mapelList, 'max_jam_per_minggu'));
        $usedMapelIds = array_column($mapelList, 'mapel_id');
        $availableMapel = $this->mapelModel->where('deleted_at IS NULL')->orderBy('nama', 'ASC')->findAll();
        $availableMapel = array_filter($availableMapel, fn ($m) => ! in_array($m['id'], $usedMapelIds, true));

        return view('kurikulum/guru/mapel', [
            'title'           => 'Kompetensi Mapel — ' . $guru['nama'],
            'guru'            => $guru,
            'mapel_list'      => $mapelList,
            'available_mapel' => array_values($availableMapel),
            'total_cap'       => $totalCap,
        ]);
    }

    public function create(int $guruId)
    {
        if (! $this->guruModel->find($guruId)) {
            return redirect()->to('/kurikulum/guru')->with('error', 'Guru tidak ditemukan.');
        }

        $data = $this->request->getPost();
        $data['guru_id'] = $guruId;

        $db = \Config\Database::connect();
        $exists = $db->table('guru_mapel')
            ->where('guru_id', $guruId)
            ->where('mapel_id', $data['mapel_id'])
            ->countAllResults() > 0;

        if ($exists) {
            return redirect()->back()->withInput()->with('error', 'Mapel ini sudah ditambahkan untuk guru tersebut.');
        }

        if (! $this->guruMapelModel->validate($data)) {
            return redirect()->back()->withInput()->with('errors', $this->guruMapelModel->errors());
        }

        $this->guruMapelModel->insert($data);

        return redirect()->to("/kurikulum/guru/$guruId/mapel")->with('success', 'Kompetensi mapel berhasil ditambahkan.');
    }

    public function delete(int $guruId, int $mapelId)
    {
        $row = $this->guruMapelModel
            ->where('guru_id', $guruId)
            ->where('mapel_id', $mapelId)
            ->first();

        if (! $row) {
            return redirect()->to("/kurikulum/guru/$guruId/mapel")->with('error', 'Data tidak ditemukan.');
        }

        $this->guruMapelModel->delete($row['id']);

        return redirect()->to("/kurikulum/guru/$guruId/mapel")->with('success', 'Kompetensi mapel berhasil dihapus.');
    }

    private function getGuruWithUser(int $guruId): ?array
    {
        $db = \Config\Database::connect();

        return $db->table('guru')
            ->select('guru.*, users.nip, users.nama, users.email')
            ->join('users', 'users.id = guru.user_id')
            ->where('guru.id', $guruId)
            ->where('guru.deleted_at IS NULL')
            ->get()
            ->getRowArray();
    }
}

<?php

namespace App\Controllers\Kurikulum;

use App\Controllers\BaseController;
use App\Models\MapelModel;
use App\Models\JurusanModel;

class MapelController extends BaseController
{
    protected MapelModel $mapelModel;
    protected JurusanModel $jurusanModel;

    public function __construct()
    {
        $this->mapelModel = new MapelModel();
        $this->jurusanModel = new JurusanModel();
    }

    public function index(): string
    {
        $db = \Config\Database::connect();
        $mapel = $db->table('mapel')
            ->select('mapel.*, jurusan.nama as nama_jurusan')
            ->join('jurusan', 'jurusan.id = mapel.jurusan_id', 'left')
            ->where('mapel.deleted_at IS NULL')
            ->orderBy('mapel.id', 'DESC')
            ->get()
            ->getResultArray();

        return view('kurikulum/mapel/index', [
            'title'   => 'Manajemen Mata Pelajaran',
            'mapel'   => $mapel,
            'jurusan' => $this->jurusanModel->findAll(),
        ]);
    }

    public function create()
    {
        $data = $this->request->getPost();

        if ($data['tipe'] === 'umum') {
            $data['jurusan_id'] = null;
        } elseif (empty($data['jurusan_id'])) {
            return redirect()->back()->withInput()->with('error', 'Jurusan wajib diisi untuk mata pelajaran Kejuruan.');
        }

        if (! $this->mapelModel->validate($data)) {
            return redirect()->back()->withInput()->with('errors', $this->mapelModel->errors());
        }

        $this->mapelModel->insert($data);

        return redirect()->to('/kurikulum/mapel')->with('success', 'Mata Pelajaran berhasil ditambahkan.');
    }

    public function show(int $id)
    {
        $data = $this->mapelModel->find($id);
        if ($data) {
            return $this->response->setJSON($data);
        }

        return $this->response->setStatusCode(404)->setJSON(['error' => 'Data tidak ditemukan']);
    }

    public function update(int $id)
    {
        $data = $this->request->getPost();
        $data['id'] = $id;

        if ($data['tipe'] === 'umum') {
            $data['jurusan_id'] = null;
        } elseif (empty($data['jurusan_id'])) {
            return redirect()->back()->withInput()->with('error', 'Jurusan wajib diisi untuk mata pelajaran Kejuruan.');
        }

        if (! $this->mapelModel->validate($data)) {
            return redirect()->back()->withInput()->with('errors', $this->mapelModel->errors());
        }

        $this->mapelModel->update($id, $data);

        return redirect()->to('/kurikulum/mapel')->with('success', 'Mata Pelajaran berhasil diperbarui.');
    }

    public function delete(int $id)
    {
        $db = \Config\Database::connect();

        $hasKelasMapel = $db->table('kelas_mapel')->where('mapel_id', $id)->countAllResults() > 0;
        $hasGuruMapel = $db->table('guru_mapel')->where('mapel_id', $id)->countAllResults() > 0;

        if ($hasKelasMapel || $hasGuruMapel) {
            return redirect()->to('/kurikulum/mapel')->with('error', 'Gagal menghapus! Mata pelajaran ini masih digunakan di kurikulum rombel atau kompetensi guru.');
        }

        $this->mapelModel->delete($id);

        return redirect()->to('/kurikulum/mapel')->with('success', 'Mata Pelajaran berhasil dihapus.');
    }
}

<?php

namespace App\Controllers\Kurikulum;

use App\Controllers\BaseController;
use App\Models\RuanganModel;
use App\Models\JurusanModel;

class RuanganController extends BaseController
{
    protected RuanganModel $ruanganModel;
    protected JurusanModel $jurusanModel;

    public function __construct()
    {
        $this->ruanganModel = new RuanganModel();
        $this->jurusanModel = new JurusanModel();
    }

    public function index(): string
    {
        $db = \Config\Database::connect();
        $ruangan = $db->table('ruangan')
            ->select('ruangan.*, jurusan.nama as nama_jurusan')
            ->join('jurusan', 'jurusan.id = ruangan.jurusan_id', 'left')
            ->where('ruangan.deleted_at IS NULL')
            ->orderBy('ruangan.id', 'DESC')
            ->get()
            ->getResultArray();

        return view('kurikulum/ruangan/index', [
            'title'   => 'Manajemen Ruangan',
            'ruangan' => $ruangan,
            'jurusan' => $this->jurusanModel->findAll(),
        ]);
    }

    public function create()
    {
        $data = $this->request->getPost();

        if ($data['tipe'] === 'kelas') {
            $data['jurusan_id'] = null;
        } elseif (empty($data['jurusan_id'])) {
            $data['jurusan_id'] = null;
        }

        if (! $this->ruanganModel->validate($data)) {
            return redirect()->back()->withInput()->with('errors', $this->ruanganModel->errors());
        }

        $this->ruanganModel->insert($data);

        return redirect()->to('/kurikulum/ruangan')->with('success', 'Ruangan berhasil ditambahkan.');
    }

    public function show(int $id)
    {
        $data = $this->ruanganModel->find($id);
        if ($data) {
            return $this->response->setJSON($data);
        }

        return $this->response->setStatusCode(404)->setJSON(['error' => 'Data tidak ditemukan']);
    }

    public function update(int $id)
    {
        $data = $this->request->getPost();
        $data['id'] = $id;

        if ($data['tipe'] === 'kelas') {
            $data['jurusan_id'] = null;
        } elseif (empty($data['jurusan_id'])) {
            $data['jurusan_id'] = null;
        }

        if (! $this->ruanganModel->validate($data)) {
            return redirect()->back()->withInput()->with('errors', $this->ruanganModel->errors());
        }

        $this->ruanganModel->update($id, $data);

        return redirect()->to('/kurikulum/ruangan')->with('success', 'Ruangan berhasil diperbarui.');
    }

    public function delete(int $id)
    {
        $db = \Config\Database::connect();

        $hasKelas = $db->table('kelas')->where('ruangan_id', $id)->where('deleted_at IS NULL')->countAllResults() > 0;
        $hasKelasMapel = $db->table('kelas_mapel')->where('lab_id', $id)->countAllResults() > 0;

        if ($hasKelas || $hasKelasMapel) {
            return redirect()->to('/kurikulum/ruangan')->with('error', 'Gagal menghapus! Ruangan ini sedang digunakan sebagai rombel atau lab.');
        }

        $this->ruanganModel->delete($id);

        return redirect()->to('/kurikulum/ruangan')->with('success', 'Ruangan berhasil dihapus.');
    }
}

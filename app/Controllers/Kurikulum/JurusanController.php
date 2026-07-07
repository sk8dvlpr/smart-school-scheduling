<?php

namespace App\Controllers\Kurikulum;

use App\Controllers\BaseController;
use App\Models\JurusanModel;

class JurusanController extends BaseController
{
    protected JurusanModel $jurusanModel;

    public function __construct()
    {
        $this->jurusanModel = new JurusanModel();
    }

    public function index(): string
    {
        return view('kurikulum/jurusan/index', [
            'title'   => 'Manajemen Jurusan',
            'jurusan' => $this->jurusanModel->findAll(),
        ]);
    }

    public function create()
    {
        $data = $this->request->getPost();

        if (! $this->jurusanModel->validate($data)) {
            return redirect()->back()->withInput()->with('errors', $this->jurusanModel->errors());
        }

        $this->jurusanModel->insert($data);

        return redirect()->to('/kurikulum/jurusan')->with('success', 'Jurusan berhasil ditambahkan.');
    }

    public function show(int $id)
    {
        $data = $this->jurusanModel->find($id);
        if ($data) {
            return $this->response->setJSON($data);
        }

        return $this->response->setStatusCode(404)->setJSON(['error' => 'Data tidak ditemukan']);
    }

    public function update(int $id)
    {
        $data = $this->request->getPost();
        $data['id'] = $id;

        if (! $this->jurusanModel->validate($data)) {
            return redirect()->back()->withInput()->with('errors', $this->jurusanModel->errors());
        }

        $this->jurusanModel->update($id, $data);

        return redirect()->to('/kurikulum/jurusan')->with('success', 'Jurusan berhasil diperbarui.');
    }

    public function delete(int $id)
    {
        $db = \Config\Database::connect();

        $hasMapel = $db->table('mapel')->where('jurusan_id', $id)->where('deleted_at IS NULL')->countAllResults() > 0;
        $hasKelas = $db->table('kelas')->where('jurusan_id', $id)->where('deleted_at IS NULL')->countAllResults() > 0;

        if ($hasMapel || $hasKelas) {
            return redirect()->to('/kurikulum/jurusan')->with('error', 'Gagal menghapus! Jurusan ini sedang digunakan oleh Kelas atau Mata Pelajaran.');
        }

        $this->jurusanModel->delete($id);

        return redirect()->to('/kurikulum/jurusan')->with('success', 'Jurusan berhasil dihapus.');
    }
}

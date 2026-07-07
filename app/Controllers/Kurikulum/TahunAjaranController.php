<?php

namespace App\Controllers\Kurikulum;

use App\Controllers\BaseController;
use App\Models\TahunAjaranModel;

class TahunAjaranController extends BaseController
{
    protected TahunAjaranModel $tahunAjaranModel;

    public function __construct()
    {
        $this->tahunAjaranModel = new TahunAjaranModel();
    }

    public function index(): string
    {
        return view('kurikulum/tahun_ajaran/index', [
            'title'        => 'Manajemen Tahun Ajaran',
            'tahun_ajaran' => $this->tahunAjaranModel->findAll(),
        ]);
    }

    public function create()
    {
        $data = $this->request->getPost();
        $data['is_active'] = isset($data['is_active']) ? 1 : 0;

        if (! $this->tahunAjaranModel->validate($data)) {
            return redirect()->back()->withInput()->with('errors', $this->tahunAjaranModel->errors());
        }

        if ($data['is_active'] == 1) {
            $this->tahunAjaranModel->set(['is_active' => 0])->where('id >', 0)->update();
        }

        $this->tahunAjaranModel->insert($data);

        return redirect()->to('/kurikulum/tahun-ajaran')->with('success', 'Tahun Ajaran berhasil ditambahkan.');
    }

    public function show(int $id)
    {
        $data = $this->tahunAjaranModel->find($id);
        if ($data) {
            return $this->response->setJSON($data);
        }

        return $this->response->setStatusCode(404)->setJSON(['error' => 'Data tidak ditemukan']);
    }

    public function update(int $id)
    {
        $data = $this->request->getPost();
        $data['is_active'] = isset($data['is_active']) ? 1 : 0;

        if (! $this->tahunAjaranModel->validate($data)) {
            return redirect()->back()->withInput()->with('errors', $this->tahunAjaranModel->errors());
        }

        if ($data['is_active'] == 1) {
            $this->tahunAjaranModel->set(['is_active' => 0])->where('id !=', $id)->update();
        }

        $this->tahunAjaranModel->update($id, $data);

        return redirect()->to('/kurikulum/tahun-ajaran')->with('success', 'Tahun Ajaran berhasil diperbarui.');
    }

    public function delete(int $id)
    {
        $this->tahunAjaranModel->delete($id);

        return redirect()->to('/kurikulum/tahun-ajaran')->with('success', 'Tahun Ajaran berhasil dihapus.');
    }
}

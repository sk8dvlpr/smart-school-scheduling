<?php

namespace App\Controllers\Kurikulum;

use App\Controllers\BaseController;
use App\Models\KelasModel;
use App\Models\JurusanModel;
use App\Models\RuanganModel;
use App\Models\TahunAjaranModel;

class KelasController extends BaseController
{
    protected KelasModel $kelasModel;

    public function __construct()
    {
        $this->kelasModel = new KelasModel();
    }

    public function index(): string
    {
        $db = \Config\Database::connect();

        $filterTingkat = trim((string) $this->request->getGet('tingkat'));
        $filterJurusan = (int) $this->request->getGet('jurusan_id');
        $filterTa      = (int) $this->request->getGet('tahun_ajaran_id');

        if (! in_array($filterTingkat, ['X', 'XI', 'XII'], true)) {
            $filterTingkat = '';
        }

        $builder = $db->table('kelas')
            ->select('kelas.*, jurusan.nama as nama_jurusan, ruangan.nama as nama_ruangan,
                      tahun_ajaran.nama as ta_nama, tahun_ajaran.semester,
                      (SELECT COALESCE(SUM(km.jam_per_minggu), 0) FROM kelas_mapel km
                       WHERE km.kelas_id = kelas.id) as total_jp')
            ->join('jurusan', 'jurusan.id = kelas.jurusan_id', 'left')
            ->join('ruangan', 'ruangan.id = kelas.ruangan_id', 'left')
            ->join('tahun_ajaran', 'tahun_ajaran.id = kelas.tahun_ajaran_id', 'left')
            ->where('kelas.deleted_at IS NULL');

        if ($filterTingkat !== '') {
            $builder->where('kelas.tingkat', $filterTingkat);
        }
        if ($filterJurusan > 0) {
            $builder->where('kelas.jurusan_id', $filterJurusan);
        }
        if ($filterTa > 0) {
            $builder->where('kelas.tahun_ajaran_id', $filterTa);
        }

        $kelas = $builder
            ->orderBy('kelas.tahun_ajaran_id', 'DESC')
            ->orderBy('kelas.tingkat', 'ASC')
            ->orderBy('kelas.nama', 'ASC')
            ->get()
            ->getResultArray();

        return view('kurikulum/kelas/index', [
            'title'            => 'Manajemen Rombel',
            'kelas'            => $kelas,
            'jurusan'          => (new JurusanModel())->findAll(),
            'ruangan'          => (new RuanganModel())->where('tipe', 'kelas')->findAll(),
            'ta'               => (new TahunAjaranModel())->orderBy('id', 'DESC')->findAll(),
            'filter_tingkat'   => $filterTingkat,
            'filter_jurusan'   => $filterJurusan,
            'filter_tahun_ajaran' => $filterTa,
        ]);
    }

    public function create()
    {
        $data = $this->request->getPost();

        if (! $this->kelasModel->validate($data)) {
            return redirect()->back()->withInput()->with('errors', $this->kelasModel->errors());
        }

        $db = \Config\Database::connect();
        $exists = $db->table('kelas')
            ->where('nama', $data['nama'])
            ->where('tahun_ajaran_id', $data['tahun_ajaran_id'])
            ->where('deleted_at IS NULL')
            ->countAllResults() > 0;

        if ($exists) {
            return redirect()->back()->withInput()->with('error', 'Nama rombel sudah ada pada tahun ajaran tersebut.');
        }

        $this->kelasModel->insert($data);

        return redirect()->to('/kurikulum/kelas')->with('success', 'Rombel berhasil ditambahkan.');
    }

    public function show(int $id)
    {
        $data = $this->kelasModel->find($id);
        if ($data) {
            return $this->response->setJSON($data);
        }

        return $this->response->setStatusCode(404)->setJSON(['error' => 'Data tidak ditemukan']);
    }

    public function update(int $id)
    {
        $data = $this->request->getPost();

        if (! $this->kelasModel->validate($data)) {
            return redirect()->back()->withInput()->with('errors', $this->kelasModel->errors());
        }

        $db = \Config\Database::connect();
        $exists = $db->table('kelas')
            ->where('nama', $data['nama'])
            ->where('tahun_ajaran_id', $data['tahun_ajaran_id'])
            ->where('id !=', $id)
            ->where('deleted_at IS NULL')
            ->countAllResults() > 0;

        if ($exists) {
            return redirect()->back()->withInput()->with('error', 'Nama rombel sudah ada pada tahun ajaran tersebut.');
        }

        $this->kelasModel->update($id, $data);

        return redirect()->to('/kurikulum/kelas')->with('success', 'Rombel berhasil diperbarui.');
    }

    public function delete(int $id)
    {
        $db = \Config\Database::connect();

        $hasKelasMapel = $db->table('kelas_mapel')->where('kelas_id', $id)->countAllResults() > 0;
        $hasJadwal = $db->table('jadwal')->where('kelas_id', $id)->countAllResults() > 0;

        if ($hasKelasMapel || $hasJadwal) {
            return redirect()->to('/kurikulum/kelas')->with('error', 'Gagal menghapus! Rombel ini memiliki kurikulum mapel atau jadwal aktif.');
        }

        $this->kelasModel->delete($id);

        return redirect()->to('/kurikulum/kelas')->with('success', 'Rombel berhasil dihapus.');
    }
}

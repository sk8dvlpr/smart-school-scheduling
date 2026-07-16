<?php

namespace App\Controllers\Kurikulum;

use App\Controllers\BaseController;
use App\Models\KelasModel;
use App\Models\KelasMapelModel;
use App\Models\MapelModel;
use App\Models\RuanganModel;

class KelasMapelController extends BaseController
{
    protected KelasModel $kelasModel;
    protected KelasMapelModel $kelasMapelModel;
    protected MapelModel $mapelModel;
    protected RuanganModel $ruanganModel;

    public function __construct()
    {
        $this->kelasModel = new KelasModel();
        $this->kelasMapelModel = new KelasMapelModel();
        $this->mapelModel = new MapelModel();
        $this->ruanganModel = new RuanganModel();
    }

    public function index(int $kelasId)
    {
        $kelas = $this->getKelasDetail($kelasId);
        if (! $kelas) {
            return redirect()->to('/kurikulum/kelas')->with('error', 'Rombel tidak ditemukan.');
        }

        $db = \Config\Database::connect();
        $mapelList = $db->table('kelas_mapel')
            ->select('kelas_mapel.*, mapel.nama as mapel_nama, mapel.kode as mapel_kode, mapel.tipe as mapel_tipe,
                      ruangan.nama as lab_nama')
            ->join('mapel', 'mapel.id = kelas_mapel.mapel_id')
            ->join('ruangan', 'ruangan.id = kelas_mapel.lab_id', 'left')
            ->where('kelas_mapel.kelas_id', $kelasId)
            ->orderBy('mapel.nama', 'ASC')
            ->get()
            ->getResultArray();

        $totalJp = array_sum(array_column($mapelList, 'jam_per_minggu'));
        $usedMapelIds = array_column($mapelList, 'mapel_id');

        $availableMapel = $db->table('mapel')
            ->where('deleted_at IS NULL')
            ->groupStart()
                ->where('tipe', 'umum')
                ->orWhere('jurusan_id', $kelas['jurusan_id'])
            ->groupEnd()
            ->orderBy('nama', 'ASC')
            ->get()
            ->getResultArray();
        $availableMapel = array_filter($availableMapel, fn ($m) => ! in_array($m['id'], $usedMapelIds, true));

        $labs = $this->ruanganModel
            ->where('tipe', 'lab')
            ->where('jurusan_id', $kelas['jurusan_id'])
            ->findAll();

        return view('kurikulum/kelas/mapel', [
            'title'           => 'Kurikulum Rombel — ' . $kelas['nama'],
            'kelas'           => $kelas,
            'mapel_list'      => $mapelList,
            'available_mapel' => array_values($availableMapel),
            'labs'            => $labs,
            'total_jp'        => $totalJp,
        ]);
    }

    public function create(int $kelasId)
    {
        $kelas = $this->kelasModel->find($kelasId);
        if (! $kelas) {
            return redirect()->to('/kurikulum/kelas')->with('error', 'Rombel tidak ditemukan.');
        }

        $data = $this->prepareData($kelas, $this->request->getPost());
        if (isset($data['error'])) {
            return redirect()->back()->withInput()->with('error', $data['error']);
        }

        $db = \Config\Database::connect();
        $exists = $db->table('kelas_mapel')
            ->where('kelas_id', $kelasId)
            ->where('mapel_id', $data['mapel_id'])
            ->where('tahun_ajaran_id', $data['tahun_ajaran_id'])
            ->countAllResults() > 0;

        if ($exists) {
            return redirect()->back()->withInput()->with('error', 'Mapel ini sudah ada di kurikulum rombel.');
        }

        if (! $this->kelasMapelModel->validate($data)) {
            return redirect()->back()->withInput()->with('errors', $this->kelasMapelModel->errors());
        }

        $this->kelasMapelModel->insert($data);

        return redirect()->to("/kurikulum/kelas/$kelasId/mapel")->with('success', 'Mapel kurikulum berhasil ditambahkan.');
    }

    public function update(int $kelasId, int $mapelId)
    {
        $kelas = $this->kelasModel->find($kelasId);
        if (! $kelas) {
            return redirect()->to('/kurikulum/kelas')->with('error', 'Rombel tidak ditemukan.');
        }

        $row = $this->kelasMapelModel
            ->where('kelas_id', $kelasId)
            ->where('mapel_id', $mapelId)
            ->first();

        if (! $row) {
            return redirect()->to("/kurikulum/kelas/$kelasId/mapel")->with('error', 'Data tidak ditemukan.');
        }

        $data = $this->prepareData($kelas, $this->request->getPost(), (int) $row['mapel_id']);
        if (isset($data['error'])) {
            return redirect()->back()->withInput()->with('error', $data['error']);
        }

        if (! $this->kelasMapelModel->validate($data)) {
            return redirect()->back()->withInput()->with('errors', $this->kelasMapelModel->errors());
        }

        $this->kelasMapelModel->update($row['id'], $data);

        return redirect()->to("/kurikulum/kelas/$kelasId/mapel")->with('success', 'Mapel kurikulum berhasil diperbarui.');
    }

    public function delete(int $kelasId, int $mapelId)
    {
        $row = $this->kelasMapelModel
            ->where('kelas_id', $kelasId)
            ->where('mapel_id', $mapelId)
            ->first();

        if (! $row) {
            return redirect()->to("/kurikulum/kelas/$kelasId/mapel")->with('error', 'Data tidak ditemukan.');
        }

        $jadwalCount = \Config\Database::connect()->table('jadwal')
            ->where('kelas_mapel_id', $row['id'])
            ->countAllResults();
        if ($jadwalCount > 0) {
            return redirect()->to("/kurikulum/kelas/$kelasId/mapel")
                ->with('error', 'Tidak bisa hapus: mapel ini masih dipakai di jadwal (' . $jadwalCount . ' baris).');
        }

        $this->kelasMapelModel->delete($row['id']);

        return redirect()->to("/kurikulum/kelas/$kelasId/mapel")->with('success', 'Mapel kurikulum berhasil dihapus.');
    }

    private function getKelasDetail(int $kelasId): ?array
    {
        $db = \Config\Database::connect();

        return $db->table('kelas')
            ->select('kelas.*, jurusan.nama as nama_jurusan, tahun_ajaran.nama as ta_nama')
            ->join('jurusan', 'jurusan.id = kelas.jurusan_id', 'left')
            ->join('tahun_ajaran', 'tahun_ajaran.id = kelas.tahun_ajaran_id', 'left')
            ->where('kelas.id', $kelasId)
            ->where('kelas.deleted_at IS NULL')
            ->get()
            ->getRowArray();
    }

    /**
     * @return array<string, mixed>
     */
    private function prepareData(array $kelas, array $post, ?int $fixedMapelId = null): array
    {
        $data = $post;
        $data['kelas_id'] = $kelas['id'];
        $data['tahun_ajaran_id'] = $kelas['tahun_ajaran_id'];
        $data['butuh_lab'] = isset($data['butuh_lab']) ? 1 : 0;

        if ($fixedMapelId !== null) {
            $data['mapel_id'] = $fixedMapelId;
        }

        $mapel = $this->mapelModel->find($data['mapel_id']);
        if (! $mapel) {
            return ['error' => 'Mapel tidak ditemukan.'];
        }

        if ($mapel['tipe'] === 'kejuruan' && (int) $mapel['jurusan_id'] !== (int) $kelas['jurusan_id']) {
            return ['error' => 'Mapel kejuruan harus sesuai jurusan rombel (HC7).'];
        }

        if ($data['butuh_lab']) {
            if (empty($data['lab_id'])) {
                return ['error' => 'Lab utama wajib dipilih jika mapel membutuhkan lab.'];
            }
            $lab = $this->ruanganModel->find($data['lab_id']);
            if (! $lab || ($lab['tipe'] ?? '') !== 'lab' || (int) ($lab['jurusan_id'] ?? 0) !== (int) $kelas['jurusan_id']) {
                return ['error' => 'Lab utama harus lab jurusan rombel ini.'];
            }
        } else {
            $data['lab_id'] = null;
        }

        return $data;
    }
}

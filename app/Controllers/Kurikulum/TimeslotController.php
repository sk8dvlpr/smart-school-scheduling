<?php

namespace App\Controllers\Kurikulum;

use App\Controllers\BaseController;
use App\Models\TimeslotModel;
use App\Models\HariModel;

class TimeslotController extends BaseController
{
    protected TimeslotModel $timeslotModel;
    protected HariModel $hariModel;

    public function __construct()
    {
        $this->timeslotModel = new TimeslotModel();
        $this->hariModel = new HariModel();
    }

    public function index(): string
    {
        $hari = $this->hariModel->orderBy('urutan', 'ASC')->findAll();
        $activeHariId = (int) ($this->request->getGet('hari_id') ?: ($hari[0]['id'] ?? 0));

        $timeslot = [];
        $jpCount = 0;
        if ($activeHariId > 0) {
            $timeslot = $this->timeslotModel
                ->where('hari_id', $activeHariId)
                ->orderBy('jam_ke', 'ASC')
                ->findAll();
            $jpCount = count(array_filter($timeslot, fn ($t) => $t['tipe'] === 'jp'));
        }

        return view('kurikulum/timeslot/index', [
            'title'          => 'Manajemen Timeslot & Jadwal Harian',
            'hari'           => $hari,
            'active_hari_id' => $activeHariId,
            'timeslot'       => $timeslot,
            'jp_count'       => $jpCount,
        ]);
    }

    public function create()
    {
        $data = $this->request->getPost();
        $data = $this->normalizeTimeslot($data);

        if (! $this->timeslotModel->validate($data)) {
            return redirect()->back()->withInput()->with('errors', $this->timeslotModel->errors());
        }

        $this->timeslotModel->insert($data);

        $hariId = $data['hari_id'];

        return redirect()->to("/kurikulum/timeslot?hari_id=$hariId")->with('success', 'Timeslot berhasil ditambahkan.');
    }

    public function show(int $id)
    {
        $data = $this->timeslotModel->find($id);
        if ($data) {
            return $this->response->setJSON($data);
        }

        return $this->response->setStatusCode(404)->setJSON(['error' => 'Data tidak ditemukan']);
    }

    public function update(int $id)
    {
        $data = $this->request->getPost();
        $data = $this->normalizeTimeslot($data);

        if (! $this->timeslotModel->validate($data)) {
            return redirect()->back()->withInput()->with('errors', $this->timeslotModel->errors());
        }

        $this->timeslotModel->update($id, $data);

        $hariId = $data['hari_id'];

        return redirect()->to("/kurikulum/timeslot?hari_id=$hariId")->with('success', 'Timeslot berhasil diperbarui.');
    }

    public function delete(int $id)
    {
        $row = $this->timeslotModel->find($id);
        if (! $row) {
            return redirect()->to('/kurikulum/timeslot')->with('error', 'Data tidak ditemukan.');
        }

        $db = \Config\Database::connect();
        $hasJadwal = $db->table('jadwal')->where('timeslot_id', $id)->countAllResults() > 0;

        if ($hasJadwal) {
            return redirect()->to('/kurikulum/timeslot?hari_id=' . $row['hari_id'])->with('error', 'Gagal menghapus! Timeslot ini sedang digunakan dalam jadwal aktif.');
        }

        $this->timeslotModel->delete($id, true);

        return redirect()->to('/kurikulum/timeslot?hari_id=' . $row['hari_id'])->with('success', 'Timeslot berhasil dihapus secara permanen.');
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function normalizeTimeslot(array $data): array
    {
        $tipe = $data['tipe'] ?? 'jp';
        if (in_array($tipe, ['istirahat', 'kegiatan_khusus'], true)) {
            $data['jam_ke'] = 0;
        }

        return $data;
    }
}

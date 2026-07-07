<?php

namespace App\Controllers\KepalaSekolah;

use App\Controllers\BaseController;
use App\Libraries\ScheduleHistoryService;
use App\Models\TahunAjaranModel;
use App\Models\JadwalModel;
use App\Models\KelasModel;
use App\Models\RuanganModel;
use App\Models\TimeslotModel;

class JadwalController extends BaseController
{
    protected TahunAjaranModel $taModel;
    protected JadwalModel $jadwalModel;
    protected KelasModel $kelasModel;
    protected RuanganModel $ruanganModel;
    protected TimeslotModel $timeslotModel;

    public function __construct()
    {
        $this->taModel       = new TahunAjaranModel();
        $this->jadwalModel   = new JadwalModel();
        $this->kelasModel    = new KelasModel();
        $this->ruanganModel  = new RuanganModel();
        $this->timeslotModel = new TimeslotModel();
    }

    public function index(): string
    {
        $activeTa = $this->taModel->where('is_active', 1)->first();
        if (! $activeTa) {
            return view('kepala_sekolah/jadwal/index', [
                'title'       => 'Lihat Jadwal',
                'active_ta'   => null,
                'has_jadwal'  => false,
                'kelas'       => [],
                'guru'        => [],
                'ruangan'     => [],
            ]);
        }

        $publishedLog = (new ScheduleHistoryService())->getPublishedLog((int) $activeTa['id']);
        $hasJadwal    = $publishedLog !== null;

        return view('kepala_sekolah/jadwal/index', [
            'title'         => 'Lihat Jadwal',
            'active_ta'     => $activeTa,
            'has_jadwal'    => $hasJadwal,
            'published_log' => $publishedLog,
            'kelas'         => $this->kelasModel->where('tahun_ajaran_id', $activeTa['id'])->findAll(),
            'guru'          => $this->getGuruListWithUsers(),
            'ruangan'       => $this->ruanganModel->findAll(),
        ]);
    }

    public function viewByKelas(int $id): string
    {
        $activeTa = $this->taModel->where('is_active', 1)->first();
        if (! $activeTa) {
            return '';
        }

        $logId = $this->jadwalModel->resolveScheduleLogId((int) $activeTa['id']);
        if ($logId === null) {
            return '';
        }

        $ctx = $this->getTimetableContext();

        return view('kurikulum/schedule/view_kelas', [
            'kelas'           => $this->kelasModel->find($id),
            'jadwal'          => $this->jadwalModel->getByKelas($id, (int) $activeTa['id'], $logId),
            'hari'            => $ctx['hari'],
            'timeslotsByHari' => $ctx['timeslotsByHari'],
            'editable'        => false,
            'schedule_log_id' => $logId,
        ]);
    }

    public function viewByGuru(int $id): string
    {
        $activeTa = $this->taModel->where('is_active', 1)->first();
        if (! $activeTa) {
            return '';
        }

        $logId = $this->jadwalModel->resolveScheduleLogId((int) $activeTa['id']);
        if ($logId === null) {
            return '';
        }

        $jadwal  = $this->jadwalModel->getByGuru($id, (int) $activeTa['id'], $logId);
        $totalJp = $this->jadwalModel->countJpByGuru($id, (int) $activeTa['id'], $logId);
        $ctx     = $this->getTimetableContext();

        return view('kurikulum/schedule/view_guru', [
            'guru'            => $this->getGuruWithUser($id),
            'jadwal'          => $jadwal,
            'hari'            => $ctx['hari'],
            'timeslotsByHari' => $ctx['timeslotsByHari'],
            'total_jp'        => $totalJp,
        ]);
    }

    public function viewByRuangan(int $id): string
    {
        $activeTa = $this->taModel->where('is_active', 1)->first();
        if (! $activeTa) {
            return '';
        }

        $logId = $this->jadwalModel->resolveScheduleLogId((int) $activeTa['id']);
        if ($logId === null) {
            return '';
        }

        $jadwal = $this->jadwalModel->getByRuangan($id, (int) $activeTa['id'], $logId);
        $ctx    = $this->getTimetableContext();

        $maxPossibleSlots = 0;
        foreach ($ctx['timeslotsByHari'] as $daySlots) {
            $maxPossibleSlots += count(array_filter($daySlots, fn ($ts) => ($ts['tipe'] ?? '') === 'jp'));
        }
        $usedSlots   = count($jadwal);
        $utilization = $maxPossibleSlots > 0 ? round(($usedSlots / $maxPossibleSlots) * 100, 1) : 0;

        return view('kurikulum/schedule/view_ruangan', [
            'ruangan'         => $this->ruanganModel->find($id),
            'jadwal'          => $jadwal,
            'hari'            => $ctx['hari'],
            'timeslotsByHari' => $ctx['timeslotsByHari'],
            'utilization'     => $utilization,
            'used_slots'      => $usedSlots,
            'max_slots'       => $maxPossibleSlots,
        ]);
    }

    public function export(string $type)
    {
        $activeTa = $this->taModel->where('is_active', 1)->first();
        if (! $activeTa) {
            return redirect()->back()->with('error', 'Tidak ada Tahun Ajaran aktif.');
        }

        $logId = $this->jadwalModel->resolveScheduleLogId((int) $activeTa['id']);
        if ($logId === null) {
            return redirect()->back()->with('error', 'Belum ada jadwal yang dipublish.');
        }

        $parts = explode('-', $type);
        if (count($parts) < 2) {
            return redirect()->back()->with('error', 'Tipe export tidak valid.');
        }

        $format = $parts[0];
        $entity = $parts[1];
        $id     = $parts[2] ?? null;

        if ($format === 'pdf') {
            $exporter = new \App\Libraries\PdfExporter();
        } elseif ($format === 'excel') {
            $exporter = new \App\Libraries\ExcelExporter();
        } else {
            return redirect()->back()->with('error', 'Format atau entitas tidak didukung.');
        }

        if ($entity === 'kelas' && $id) {
            return $exporter->exportByKelas($id, (int) $activeTa['id'], $logId);
        }
        if ($entity === 'guru' && $id) {
            return $exporter->exportByGuru($id, (int) $activeTa['id'], $logId);
        }
        if ($entity === 'ruangan' && $id) {
            return $exporter->exportByRuangan($id, (int) $activeTa['id'], $logId);
        }
        if ($entity === 'all') {
            return $exporter->exportAll((int) $activeTa['id'], $logId);
        }

        return redirect()->back()->with('error', 'Format atau entitas tidak didukung.');
    }

    /**
     * @return array{hari: list<array<string, mixed>>, timeslotsByHari: array<int, list<array<string, mixed>>>}
     */
    private function getTimetableContext(): array
    {
        $db = \Config\Database::connect();

        return [
            'hari'            => $db->table('hari')->orderBy('urutan', 'ASC')->get()->getResultArray(),
            'timeslotsByHari' => $this->timeslotModel->getGroupedByHari(),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function getGuruListWithUsers(): array
    {
        $db = \Config\Database::connect();

        return $db->table('guru')
            ->select('guru.id, users.nama')
            ->join('users', 'users.id = guru.user_id')
            ->where('guru.deleted_at IS NULL')
            ->orderBy('users.nama', 'ASC')
            ->get()
            ->getResultArray();
    }

    private function getGuruWithUser(int $id): ?array
    {
        $db = \Config\Database::connect();

        return $db->table('guru')
            ->select('guru.*, users.nama')
            ->join('users', 'users.id = guru.user_id')
            ->where('guru.id', $id)
            ->where('guru.deleted_at IS NULL')
            ->get()
            ->getRowArray();
    }
}

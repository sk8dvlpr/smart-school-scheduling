<?php

namespace App\Controllers\Kurikulum;

use App\Controllers\BaseController;
use App\Models\TahunAjaranModel;
use App\Models\ScheduleConfigModel;
use App\Models\ScheduleLogModel;
use App\Models\JadwalModel;
use App\Models\KelasModel;
use App\Models\GuruModel;
use App\Models\RuanganModel;
use App\Models\TimeslotModel;
use App\Libraries\ScheduleGenerator;
use App\Libraries\JadwalManualService;
use App\Libraries\ScheduleHistoryService;

class ScheduleController extends BaseController
{
    protected TahunAjaranModel $taModel;
    protected ScheduleConfigModel $configModel;
    protected ScheduleLogModel $logModel;
    protected JadwalModel $jadwalModel;
    protected KelasModel $kelasModel;
    protected GuruModel $guruModel;
    protected RuanganModel $ruanganModel;
    protected TimeslotModel $timeslotModel;

    public function __construct()
    {
        $this->taModel = new TahunAjaranModel();
        $this->configModel = new ScheduleConfigModel();
        $this->logModel = new ScheduleLogModel();
        $this->jadwalModel = new JadwalModel();
        $this->kelasModel = new KelasModel();
        $this->guruModel = new GuruModel();
        $this->ruanganModel = new RuanganModel();
        $this->timeslotModel = new TimeslotModel();
    }

    public function index()
    {
        $activeTa = $this->taModel->where('is_active', 1)->first();
        if (! $activeTa) {
            return view('kurikulum/schedule/index', [
                'title'     => 'Generator Jadwal',
                'error'     => 'Tidak ada Tahun Ajaran aktif.',
                'active_ta' => null,
            ]);
        }

        $this->initDefaultConfig($activeTa['id']);

        $configs = $this->configModel->where('tahun_ajaran_id', $activeTa['id'])->findAll();
        $configMap = [];
        foreach ($configs as $c) {
            $configMap[$c['param_key']] = $c['param_value'];
        }

        $generator = new ScheduleGenerator();
        $validationResults = $generator->validate($activeTa['id']);
        $isValid = count(array_filter($validationResults, fn ($v) => ! $v['status'])) === 0;

        $latestLog = $this->logModel->where('tahun_ajaran_id', $activeTa['id'])
            ->orderBy('id', 'DESC')
            ->first();

        $hasJadwal = $this->jadwalModel->resolveKurikulumLogId($activeTa['id']) !== null;
        $historyLogs = $this->logModel->where('tahun_ajaran_id', $activeTa['id'])
            ->orderBy('id', 'DESC')
            ->findAll(10);

        return view('kurikulum/schedule/index', [
            'title'         => 'Generator Jadwal',
            'active_ta'     => $activeTa,
            'config'        => $configMap,
            'validation'    => $validationResults,
            'is_valid'      => $isValid,
            'latest_log'    => $latestLog,
            'has_jadwal'    => $hasJadwal,
            'history_logs'  => $historyLogs,
            'published_log' => (new ScheduleHistoryService())->getPublishedLog((int) $activeTa['id']),
        ]);
    }

    public function generate()
    {
        if (! $this->request->isAJAX()) {
            return $this->response->setStatusCode(403);
        }

        $activeTa = $this->taModel->where('is_active', 1)->first();
        if (! $activeTa) {
            return $this->response->setJSON(['success' => false, 'message' => 'Tidak ada Tahun Ajaran aktif.']);
        }

        set_time_limit(0);

        $parentLogId   = (int) $this->request->getPost('parent_log_id');
        $generateMode  = $this->request->getPost('generate_mode') === 'history_repair' ? 'history_repair' : 'fresh';
        $parentLogId   = $parentLogId > 0 ? $parentLogId : null;

        $generator = new ScheduleGenerator();
        $result = $generator->generate(
            (int) $activeTa['id'],
            (int) session()->get('user_id'),
            $parentLogId,
            $generateMode
        );

        return $this->response->setJSON($result);
    }

    /**
     * @return \CodeIgniter\HTTP\RedirectResponse
     */
    public function publish(int $logId)
    {
        $activeTa = $this->taModel->where('is_active', 1)->first();
        if (! $activeTa) {
            return redirect()->to('/kurikulum/schedule')->with('error', 'Tidak ada Tahun Ajaran aktif.');
        }

        $service = new ScheduleHistoryService();
        $result  = $service->publish((int) $activeTa['id'], $logId, (int) session()->get('user_id'));

        if (! $result['success']) {
            return redirect()->back()->with('error', $result['message']);
        }

        $flash = redirect()->back()->with('success', $result['message']);
        if (! empty($result['warning'])) {
            $flash = $flash->with('warning', $result['warning']);
        }

        return $flash;
    }

    /**
     * @return \CodeIgniter\HTTP\RedirectResponse|string
     */
    public function historyDetail(int $logId)
    {
        $activeTa = $this->taModel->where('is_active', 1)->first();
        if (! $activeTa) {
            return redirect()->to('/kurikulum/schedule')->with('error', 'Tidak ada Tahun Ajaran aktif.');
        }

        $log = $this->logModel->where('id', $logId)->where('tahun_ajaran_id', $activeTa['id'])->first();
        if (! $log) {
            return redirect()->to('/kurikulum/schedule/logs')->with('error', 'History tidak ditemukan.');
        }

        $report = json_decode($log['result_report'] ?? '{}', true) ?: [];
        $placed = (int) ($report['stats']['placed_units'] ?? 0);
        $total  = (int) ($report['stats']['total_units'] ?? 0);
        $pct    = $total > 0 ? round(($placed / $total) * 100, 1) : 0;

        return view('kurikulum/schedule/history_detail', [
            'title'        => 'Detail History Generate',
            'active_ta'    => $activeTa,
            'log'          => $log,
            'report'       => $report,
            'pct'          => $pct,
            'is_published' => (int) ($activeTa['published_schedule_log_id'] ?? 0) === $logId,
            'suggestions'  => $this->buildRepairSuggestions($report),
        ]);
    }

    public function config()
    {
        $activeTa = $this->taModel->where('is_active', 1)->first();
        if (! $activeTa) {
            return redirect()->to('/kurikulum/schedule')->with('error', 'Tidak ada Tahun Ajaran aktif.');
        }

        $this->initDefaultConfig($activeTa['id']);

        $configs = $this->configModel->where('tahun_ajaran_id', $activeTa['id'])->findAll();
        $configMap = [];
        foreach ($configs as $c) {
            $configMap[$c['param_key']] = $c['param_value'];
        }

        return view('kurikulum/schedule/config', [
            'title'     => 'Konfigurasi Parameter Algoritma',
            'active_ta' => $activeTa,
            'config'    => $configMap,
        ]);
    }

    public function saveConfig()
    {
        $activeTa = $this->taModel->where('is_active', 1)->first();
        if (! $activeTa) {
            return redirect()->to('/kurikulum/schedule')->with('error', 'Tidak ada Tahun Ajaran aktif.');
        }

        $postData = $this->request->getPost();

        foreach ($postData as $key => $val) {
            if (in_array($key, [
                // CSP
                'csp_consistency_method', 'csp_variable_ordering', 'csp_value_ordering',
                'csp_repair_strategy', 'csp_max_attempts',
                // GA
                'population_size', 'max_generations', 'tournament_size', 'crossover_rate',
                'crossover_method', 'mutation_rate', 'mutation_method', 'elitism_ratio',
                'stagnation_limit', 'adaptive_mutation', 'adaptive_mutation_trigger',
                'adaptive_mutation_increment', 'fitness_threshold', 'timeout_seconds',
                // Soft constraint weights (1-10)
                'sc1_teacher_gap', 'sc2_student_gap', 'sc3_subject_distribution',
                'sc4_heavy_morning', 'sc5_light_afternoon',                 'sc6_teacher_load_balance', 'sc7_teacher_preference',
                'sc8_room_transition', 'sc9_teacher_continuity', 'sc10_first_slot_rotation',
                'sc11_lab_load_balance',
                'sc_lab_day_pack',
                'sc_lab_preference',
            ], true)) {
                $existing = $this->configModel->where('tahun_ajaran_id', $activeTa['id'])
                    ->where('param_key', $key)
                    ->first();
                if ($existing) {
                    $this->configModel->where('tahun_ajaran_id', $activeTa['id'])
                        ->where('param_key', $key)
                        ->set(['param_value' => $val])
                        ->update();
                } else {
                    $this->configModel->insert([
                        'tahun_ajaran_id' => $activeTa['id'],
                        'param_key'       => $key,
                        'param_value'     => $val,
                    ]);
                }
            }
        }

        return redirect()->to('/kurikulum/schedule/config')->with('success', 'Konfigurasi berhasil disimpan.');
    }

    public function result()
    {
        $activeTa = $this->taModel->where('is_active', 1)->first();
        if (! $activeTa) {
            return redirect()->to('/kurikulum/schedule')->with('error', 'Tidak ada Tahun Ajaran aktif.');
        }

        $scheduleLogId = $this->resolveKurikulumLogId($activeTa);

        return view('kurikulum/schedule/result', [
            'title'           => 'Hasil Penjadwalan',
            'active_ta'       => $activeTa,
            'schedule_log_id' => $scheduleLogId,
            'kelas'           => $this->kelasModel->where('tahun_ajaran_id', $activeTa['id'])->findAll(),
            'guru'            => $this->getGuruListWithUsers(),
            'ruangan'         => $this->ruanganModel->findAll(),
        ]);
    }

    public function viewByKelas(int $id)
    {
        $activeTa = $this->taModel->where('is_active', 1)->first();
        if (! $activeTa) {
            return '';
        }

        $kelas = $this->kelasModel->find($id);
        if (! $kelas) {
            return '';
        }

        $scheduleLogId = $this->resolveKurikulumLogId($activeTa);
        if ($scheduleLogId === null) {
            return view('kurikulum/schedule/view_kelas', [
                'kelas'           => $kelas,
                'jadwal'          => [],
                'hari'            => [],
                'timeslotsByHari' => [],
                'schedule_log_id' => null,
            ]);
        }

        $ctx = $this->getTimetableContext();

        return view('kurikulum/schedule/view_kelas', [
            'kelas'           => $kelas,
            'jadwal'          => $this->jadwalModel->getByKelas($id, (int) $activeTa['id'], $scheduleLogId),
            'hari'            => $ctx['hari'],
            'timeslotsByHari' => $ctx['timeslotsByHari'],
            'schedule_log_id' => $scheduleLogId,
        ]);
    }

    /**
     * @return \CodeIgniter\HTTP\ResponseInterface
     */
    public function manualOptions()
    {
        if (! $this->request->isAJAX()) {
            return $this->response->setStatusCode(403);
        }

        $activeTa = $this->taModel->where('is_active', 1)->first();
        if (! $activeTa) {
            return $this->response->setJSON(['success' => false, 'message' => 'Tidak ada Tahun Ajaran aktif.']);
        }

        $scheduleLogId = $this->resolveKurikulumLogId($activeTa);
        if ($scheduleLogId === null) {
            return $this->response->setJSON(['success' => false, 'message' => 'Tidak ada history jadwal aktif.']);
        }

        $kelasId    = (int) $this->request->getGet('kelas_id');
        $hariId     = (int) $this->request->getGet('hari_id');
        $timeslotId = (int) $this->request->getGet('timeslot_id');

        if ($kelasId < 1 || $hariId < 1 || $timeslotId < 1) {
            return $this->response->setJSON(['success' => false, 'message' => 'Parameter slot tidak valid.']);
        }

        $service = new JadwalManualService();

        return $this->response->setJSON(
            $service->getOptions((int) $activeTa['id'], $scheduleLogId, $kelasId, $hariId, $timeslotId)
        );
    }

    /**
     * @return \CodeIgniter\HTTP\ResponseInterface
     */
    public function manualPlace()
    {
        if (! $this->request->isAJAX()) {
            return $this->response->setStatusCode(403);
        }

        $activeTa = $this->taModel->where('is_active', 1)->first();
        if (! $activeTa) {
            return $this->response->setJSON(['success' => false, 'message' => 'Tidak ada Tahun Ajaran aktif.']);
        }

        $scheduleLogId = $this->resolveKurikulumLogId($activeTa);
        if ($scheduleLogId === null) {
            return $this->response->setJSON(['success' => false, 'message' => 'Tidak ada history jadwal aktif.']);
        }

        $kelasId      = (int) $this->request->getPost('kelas_id');
        $hariId       = (int) $this->request->getPost('hari_id');
        $timeslotId   = (int) $this->request->getPost('timeslot_id');
        $kelasMapelId = (int) $this->request->getPost('kelas_mapel_id');
        $guruId       = (int) $this->request->getPost('guru_id');

        if ($kelasId < 1 || $hariId < 1 || $timeslotId < 1 || $kelasMapelId < 1 || $guruId < 1) {
            return $this->response->setJSON(['success' => false, 'message' => 'Data penempatan tidak lengkap.']);
        }

        $service = new JadwalManualService();

        return $this->response->setJSON(array_merge(
            $service->place((int) $activeTa['id'], $scheduleLogId, $kelasId, $hariId, $timeslotId, $kelasMapelId, $guruId),
            ['csrf_hash' => csrf_hash()]
        ));
    }

    /**
     * @return \CodeIgniter\HTTP\ResponseInterface
     */
    public function manualDelete(int $id)
    {
        if (! $this->request->isAJAX()) {
            return $this->response->setStatusCode(403);
        }

        $activeTa = $this->taModel->where('is_active', 1)->first();
        if (! $activeTa) {
            return $this->response->setJSON(['success' => false, 'message' => 'Tidak ada Tahun Ajaran aktif.']);
        }

        $scheduleLogId = $this->resolveKurikulumLogId($activeTa);
        if ($scheduleLogId === null) {
            return $this->response->setJSON(['success' => false, 'message' => 'Tidak ada history jadwal aktif.']);
        }

        $kelasId = (int) ($this->request->getPost('kelas_id') ?: $this->request->getGet('kelas_id'));
        if ($kelasId < 1) {
            return $this->response->setJSON(['success' => false, 'message' => 'Rombel tidak valid.']);
        }

        $service = new JadwalManualService();

        return $this->response->setJSON(array_merge(
            $service->delete((int) $activeTa['id'], $scheduleLogId, $id, $kelasId),
            ['csrf_hash' => csrf_hash()]
        ));
    }

    /**
     * @return \CodeIgniter\HTTP\ResponseInterface
     */
    public function manualSwapSlots()
    {
        return $this->manualSwapHandler('swapSlots');
    }

    /**
     * @return \CodeIgniter\HTTP\ResponseInterface
     */
    public function manualSwapMapel()
    {
        return $this->manualSwapHandler('swapMapel');
    }

    /**
     * @return \CodeIgniter\HTTP\ResponseInterface
     */
    public function manualSwapGuru()
    {
        return $this->manualSwapHandler('swapGuru');
    }

    /**
     * @return \CodeIgniter\HTTP\ResponseInterface
     */
    protected function manualSwapHandler(string $method)
    {
        if (! $this->request->isAJAX()) {
            return $this->response->setStatusCode(403);
        }

        $activeTa = $this->taModel->where('is_active', 1)->first();
        if (! $activeTa) {
            return $this->response->setJSON(['success' => false, 'message' => 'Tidak ada Tahun Ajaran aktif.']);
        }

        $scheduleLogId = $this->resolveKurikulumLogId($activeTa);
        if ($scheduleLogId === null) {
            return $this->response->setJSON(['success' => false, 'message' => 'Tidak ada history jadwal aktif.']);
        }

        $jadwalIdA = (int) $this->request->getPost('jadwal_id_a');
        $jadwalIdB = (int) $this->request->getPost('jadwal_id_b');
        $kelasId   = (int) $this->request->getPost('kelas_id');

        if ($jadwalIdA < 1 || $jadwalIdB < 1 || $kelasId < 1) {
            return $this->response->setJSON(['success' => false, 'message' => 'Parameter swap tidak lengkap.']);
        }

        $service = new JadwalManualService();
        $result  = $service->{$method}((int) $activeTa['id'], $scheduleLogId, $jadwalIdA, $jadwalIdB, $kelasId);

        return $this->response->setJSON(array_merge($result, ['csrf_hash' => csrf_hash()]));
    }

    public function viewByGuru(int $id)
    {
        $activeTa = $this->taModel->where('is_active', 1)->first();
        if (! $activeTa) {
            return '';
        }

        $scheduleLogId = $this->resolveKurikulumLogId($activeTa);
        if ($scheduleLogId === null) {
            return '';
        }

        $jadwal = $this->jadwalModel->getByGuru($id, (int) $activeTa['id'], $scheduleLogId);
        $totalJp = 0;
        $processedGroups = [];
        foreach ($jadwal as $j) {
            if ($j['blok_group']) {
                if (! in_array($j['blok_group'], $processedGroups, true)) {
                    $processedGroups[] = $j['blok_group'];
                    $durasi = 0;
                    foreach ($jadwal as $jd) {
                        if ($jd['blok_group'] == $j['blok_group'] && $jd['hari_id'] == $j['hari_id']) {
                            $durasi++;
                        }
                    }
                    $totalJp += $durasi;
                }
            } else {
                $totalJp++;
            }
        }

        $ctx = $this->getTimetableContext();

        return view('kurikulum/schedule/view_guru', [
            'guru'            => $this->getGuruWithUser($id),
            'jadwal'          => $jadwal,
            'hari'            => $ctx['hari'],
            'timeslotsByHari' => $ctx['timeslotsByHari'],
            'total_jp'        => $totalJp,
        ]);
    }

    public function viewByRuangan(int $id)
    {
        $activeTa = $this->taModel->where('is_active', 1)->first();
        if (! $activeTa) {
            return '';
        }

        $scheduleLogId = $this->resolveKurikulumLogId($activeTa);
        if ($scheduleLogId === null) {
            return '';
        }

        $jadwal = $this->jadwalModel->getByRuangan($id, (int) $activeTa['id'], $scheduleLogId);
        $ctx = $this->getTimetableContext();

        $maxPossibleSlots = 0;
        foreach ($ctx['timeslotsByHari'] as $daySlots) {
            $maxPossibleSlots += count(array_filter($daySlots, fn ($ts) => ($ts['tipe'] ?? '') === 'jp'));
        }
        $usedSlots = count($jadwal);
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

        $parts = explode('-', $type);
        if (count($parts) < 2) {
            return redirect()->back()->with('error', 'Tipe export tidak valid.');
        }

        $format = $parts[0];
        $entity = $parts[1];
        $id = $parts[2] ?? null;

        if ($format === 'pdf') {
            $exporter = new \App\Libraries\PdfExporter();
        } elseif ($format === 'excel') {
            $exporter = new \App\Libraries\ExcelExporter();
        } else {
            return redirect()->back()->with('error', 'Format atau entitas tidak didukung.');
        }

        $scheduleLogId = $this->resolveKurikulumLogId($activeTa);

        if ($entity === 'kelas' && $id) {
            return $exporter->exportByKelas((int) $id, (int) $activeTa['id'], $scheduleLogId);
        }
        if ($entity === 'guru' && $id) {
            return $exporter->exportByGuru((int) $id, (int) $activeTa['id'], $scheduleLogId);
        }
        if ($entity === 'ruangan' && $id) {
            return $exporter->exportByRuangan((int) $id, (int) $activeTa['id'], $scheduleLogId);
        }
        if ($entity === 'all') {
            return $exporter->exportAll((int) $activeTa['id'], $scheduleLogId);
        }

        return redirect()->back()->with('error', 'Format atau entitas tidak didukung.');
    }

    public function reset()
    {
        $activeTa = $this->taModel->where('is_active', 1)->first();
        if (! $activeTa) {
            return redirect()->back()->with('error', 'Tidak ada Tahun Ajaran aktif.');
        }

        $generator = new ScheduleGenerator();
        $generator->reset($activeTa['id']);

        return redirect()->to('/kurikulum/schedule')->with('success', 'Semua jadwal tahun ajaran ini berhasil dihapus/direset.');
    }

    public function logs()
    {
        $activeTa = $this->taModel->where('is_active', 1)->first();
        if (! $activeTa) {
            return redirect()->to('/kurikulum/schedule')->with('error', 'Tidak ada Tahun Ajaran aktif.');
        }

        $db = \Config\Database::connect();
        $logs = $db->table('schedule_logs')
            ->select('schedule_logs.*, users.nama as admin_nama')
            ->join('users', 'users.id = schedule_logs.generated_by', 'left')
            ->where('schedule_logs.tahun_ajaran_id', $activeTa['id'])
            ->orderBy('schedule_logs.id', 'DESC')
            ->get()
            ->getResultArray();

        return view('kurikulum/schedule/logs', [
            'title'         => 'Riwayat Generate Jadwal',
            'active_ta'     => $activeTa,
            'logs'          => $logs,
            'published_id'  => (int) ($activeTa['published_schedule_log_id'] ?? 0),
        ]);
    }

    /**
     * @return list<array{reason_label: string, suggested_fix: string, count: int, examples: list<string>}>
     */
    private function buildRepairSuggestions(array $report): array
    {
        $grouped = [];

        foreach ($report['unplaced'] ?? [] as $item) {
            $fix   = trim((string) ($item['suggested_fix'] ?? ''));
            $label = (string) ($item['reason_label'] ?? $item['reason'] ?? 'Tidak diketahui');
            $key   = $label . '|' . $fix;

            if (! isset($grouped[$key])) {
                $grouped[$key] = [
                    'reason_label'  => $label,
                    'suggested_fix' => $fix !== '' ? $fix : 'Periksa master data terkait dan generate ulang.',
                    'count'         => 0,
                    'examples'      => [],
                ];
            }

            $grouped[$key]['count']++;
            $example = trim(($item['kelas_nama'] ?? '') . ' / ' . ($item['mapel_nama'] ?? ''));
            if ($example !== '/' && count($grouped[$key]['examples']) < 3) {
                $grouped[$key]['examples'][] = $example;
            }
        }

        foreach ($report['warnings'] ?? [] as $warning) {
            $text = trim((string) $warning);
            if ($text === '') {
                continue;
            }
            $key = 'warning|' . $text;
            if (! isset($grouped[$key])) {
                $grouped[$key] = [
                    'reason_label'  => 'Peringatan',
                    'suggested_fix' => $text,
                    'count'         => 1,
                    'examples'      => [],
                ];
            }
        }

        usort($grouped, static fn ($a, $b) => $b['count'] <=> $a['count']);

        return array_values($grouped);
    }

    private function resolveKurikulumLogId(array $activeTa): ?int
    {
        $fromRequest = (int) ($this->request->getGet('schedule_log_id') ?: $this->request->getPost('schedule_log_id'));
        if ($fromRequest > 0) {
            return $fromRequest;
        }

        return $this->jadwalModel->resolveKurikulumLogId((int) $activeTa['id']);
    }

    private function initDefaultConfig(int $taId): void
    {
        $defaults = [
            // CSP (Fase 1)
            'csp_consistency_method'      => 'AC-3',
            'csp_variable_ordering'       => 'MRV',
            'csp_value_ordering'          => 'LCV',
            'csp_repair_strategy'         => 'min_conflict',
            'csp_max_attempts'            => 8,
            // GA (Fase 2)
            'population_size'             => 100,
            'max_generations'             => 500,
            'tournament_size'             => 5,
            'crossover_rate'              => 0.8,
            'crossover_method'            => 'order_crossover',
            'mutation_rate'               => 0.08,
            'mutation_method'             => 'swap_with_repair',
            'elitism_ratio'               => 0.1,
            'stagnation_limit'            => 40,
            'adaptive_mutation'           => 1,
            'adaptive_mutation_trigger'   => 20,
            'adaptive_mutation_increment' => 0.02,
            'fitness_threshold'           => 0.95,
            'timeout_seconds'             => 300,
            // Soft constraint weights (skala 1-10)
            'sc1_teacher_gap'             => 9,
            'sc2_student_gap'             => 9,
            'sc3_subject_distribution'    => 7,
            'sc4_heavy_morning'           => 6,
            'sc5_light_afternoon'         => 5,
            'sc6_teacher_load_balance'    => 7,
            'sc7_teacher_preference'      => 5,
            'sc8_room_transition'         => 5,
            'sc9_teacher_continuity'      => 4,
            'sc10_first_slot_rotation'    => 3,
            'sc11_lab_load_balance'       => 6,
            'sc_lab_day_pack'             => 7,
            'sc_lab_preference'           => 5,
        ];

        foreach ($defaults as $key => $val) {
            $exists = $this->configModel->where('tahun_ajaran_id', $taId)
                ->where('param_key', $key)
                ->first();
            if (! $exists) {
                $this->configModel->insert([
                    'tahun_ajaran_id' => $taId,
                    'param_key'       => $key,
                    'param_value'     => $val,
                ]);
            }
        }
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

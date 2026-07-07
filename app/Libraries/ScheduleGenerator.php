<?php

namespace App\Libraries;

use App\Models\JadwalModel;
use App\Models\ScheduleLogModel;
use App\Models\TimeslotModel;

class ScheduleGenerator
{
    public function validate(int $tahunAjaranId): array
    {
        $db = \Config\Database::connect();
        $results = [];

        $ta = $db->table('tahun_ajaran')->where('id', $tahunAjaranId)->get()->getRow();
        $results[] = [
            'rule'    => 'Tahun Ajaran Aktif',
            'status'  => (bool) $ta,
            'message' => $ta ? 'OK' : 'Tahun ajaran tidak valid.',
        ];
        if (! $ta) {
            return $results;
        }

        $kelasTanpaRoom = $db->table('kelas')
            ->where('tahun_ajaran_id', $tahunAjaranId)
            ->where('deleted_at IS NULL')
            ->where('ruangan_id IS NULL')
            ->countAllResults();
        $results[] = [
            'rule'    => 'Homeroom Kelas',
            'status'  => $kelasTanpaRoom === 0,
            'message' => $kelasTanpaRoom === 0
                ? 'Semua kelas memiliki ruangan (Homeroom).'
                : "$kelasTanpaRoom kelas belum di-assign ruangan.",
        ];

        $kelasTanpaMapel = $db->table('kelas')
            ->select('kelas.nama')
            ->join('kelas_mapel', 'kelas_mapel.kelas_id = kelas.id', 'left')
            ->where('kelas.tahun_ajaran_id', $tahunAjaranId)
            ->where('kelas.deleted_at IS NULL')
            ->groupBy('kelas.id')
            ->having('COUNT(kelas_mapel.id) = 0')
            ->get()
            ->getResultArray();
        $results[] = [
            'rule'    => 'Kurikulum Kelas (kelas_mapel)',
            'status'  => count($kelasTanpaMapel) === 0,
            'message' => count($kelasTanpaMapel) === 0
                ? 'Setiap kelas memiliki minimal 1 kelas_mapel.'
                : count($kelasTanpaMapel) . ' kelas belum punya kelas_mapel: '
                    . implode(', ', array_slice(array_column($kelasTanpaMapel, 'nama'), 0, 5))
                    . (count($kelasTanpaMapel) > 5 ? '...' : ''),
        ];

        $timeslotModel = new TimeslotModel();
        $jpSlotsByHari = SchedulingContext::buildJpSlotsByHari($timeslotModel->getGroupedByHari());
        $weeklyCap = SchedulingContext::weeklyJpCapacity($jpSlotsByHari);

        $overCapKelas = $db->table('kelas_mapel')
            ->select('kelas.nama, SUM(kelas_mapel.jam_per_minggu) as total_jp')
            ->join('kelas', 'kelas.id = kelas_mapel.kelas_id')
            ->where('kelas_mapel.tahun_ajaran_id', $tahunAjaranId)
            ->groupBy('kelas_mapel.kelas_id')
            ->having('total_jp >', $weeklyCap)
            ->get()
            ->getResultArray();
        $results[] = [
            'rule'    => 'Beban JP Kelas ≤ Kapasitas Mingguan',
            'status'  => count($overCapKelas) === 0,
            'message' => count($overCapKelas) === 0
                ? "Semua kelas ≤ {$weeklyCap} JP/minggu."
                : count($overCapKelas) . ' kelas melebihi kapasitas mingguan (' . $weeklyCap . ' JP).',
        ];

        $mapelDemand = [];
        $kmRows = $db->table('kelas_mapel')
            ->select('kelas_mapel.mapel_id, kelas.jurusan_id, SUM(kelas_mapel.jam_per_minggu) as demand')
            ->join('kelas', 'kelas.id = kelas_mapel.kelas_id')
            ->where('kelas_mapel.tahun_ajaran_id', $tahunAjaranId)
            ->groupBy('kelas_mapel.mapel_id, kelas.jurusan_id')
            ->get()
            ->getResultArray();

        $mapelMeta = [];
        foreach ($db->table('mapel')->get()->getResultArray() as $m) {
            $mapelMeta[(int) $m['id']] = $m;
        }

        $guruMapelRows = $db->table('guru_mapel')->get()->getResultArray();
        $guruPool = SchedulingContext::buildGuruPool($guruMapelRows, array_values($mapelMeta));

        $mapelSupplyShort = [];
        $aggregatedDemand = [];
        foreach ($kmRows as $row) {
            $mapelId = (int) $row['mapel_id'];
            $aggregatedDemand[$mapelId] = ($aggregatedDemand[$mapelId] ?? 0) + (int) $row['demand'];
        }
        foreach ($aggregatedDemand as $mapelId => $demand) {
            $meta = $mapelMeta[$mapelId] ?? [];
            $supply = 0;
            foreach ($guruPool[$mapelId] ?? [] as $entry) {
                $supply += $entry['max_jam'];
            }
            if ($supply < $demand) {
                $mapelSupplyShort[] = ($meta['kode'] ?? "mapel#$mapelId") . " (butuh $demand, cap $supply)";
            }
        }
        $results[] = [
            'rule'    => 'Kapasitas Guru per Mapel',
            'status'  => count($mapelSupplyShort) === 0,
            'message' => count($mapelSupplyShort) === 0
                ? 'Kapasitas guru_mapel mencukupi kebutuhan per mapel.'
                : 'Mapel kurang kapasitas guru: ' . implode(', ', array_slice($mapelSupplyShort, 0, 5))
                    . (count($mapelSupplyShort) > 5 ? '...' : ''),
        ];

        $kejuruanMismatch = $db->table('kelas_mapel')
            ->select('kelas.nama, mapel.kode')
            ->join('kelas', 'kelas.id = kelas_mapel.kelas_id')
            ->join('mapel', 'mapel.id = kelas_mapel.mapel_id')
            ->where('kelas_mapel.tahun_ajaran_id', $tahunAjaranId)
            ->where('mapel.tipe', 'kejuruan')
            ->where('mapel.jurusan_id != kelas.jurusan_id', null, false)
            ->get()
            ->getResultArray();
        $results[] = [
            'rule'    => 'Kesesuaian Jurusan (HC-7)',
            'status'  => count($kejuruanMismatch) === 0,
            'message' => count($kejuruanMismatch) === 0
                ? 'Semua mapel kejuruan sesuai jurusan kelas.'
                : count($kejuruanMismatch) . ' ketidaksesuaian jurusan ditemukan.',
        ];

        $mapelNeeded = $db->table('kelas_mapel')
            ->distinct()
            ->select('mapel_id')
            ->where('tahun_ajaran_id', $tahunAjaranId)
            ->get()
            ->getResultArray();
        $mapelNoGuru = [];
        foreach ($mapelNeeded as $row) {
            $mid = (int) $row['mapel_id'];
            if (empty($guruPool[$mid])) {
                $mapelNoGuru[] = $mapelMeta[$mid]['kode'] ?? "mapel#$mid";
            }
        }
        $results[] = [
            'rule'    => 'Guru Eligible per Mapel',
            'status'  => count($mapelNoGuru) === 0,
            'message' => count($mapelNoGuru) === 0
                ? 'Setiap mapel yang dibutuhkan punya minimal 1 guru_mapel.'
                : 'Mapel tanpa guru: ' . implode(', ', $mapelNoGuru),
        ];

        $labInvalid = $db->table('kelas_mapel')
            ->select('kelas.nama, mapel.kode')
            ->join('kelas', 'kelas.id = kelas_mapel.kelas_id')
            ->join('mapel', 'mapel.id = kelas_mapel.mapel_id')
            ->join('ruangan', 'ruangan.id = kelas_mapel.lab_id', 'left')
            ->where('kelas_mapel.tahun_ajaran_id', $tahunAjaranId)
            ->where('kelas_mapel.butuh_lab', 1)
            ->groupStart()
                ->where('kelas_mapel.lab_id IS NULL')
                ->orWhere('ruangan.tipe !=', 'lab')
                ->orWhere('ruangan.id IS NULL')
                ->orWhere('ruangan.jurusan_id != kelas.jurusan_id', null, false)
            ->groupEnd()
            ->get()
            ->getResultArray();
        $results[] = [
            'rule'    => 'Lab Tersedia',
            'status'  => count($labInvalid) === 0,
            'message' => count($labInvalid) === 0
                ? 'Semua kelas_mapel butuh_lab memiliki lab utama valid (jurusan sesuai).'
                : count($labInvalid) . ' entri lab tidak valid atau jurusan tidak sesuai.',
        ];

        $labCountByJurusan = [];
        foreach ($db->table('ruangan')
            ->where('tipe', 'lab')
            ->where('jurusan_id IS NOT NULL')
            ->where('deleted_at IS NULL')
            ->get()->getResultArray() as $labRow) {
            $jid = (int) $labRow['jurusan_id'];
            $labCountByJurusan[$jid] = (int) ($labCountByJurusan[$jid] ?? 0) + 1;
        }

        $jpPerJurusan = $db->table('kelas_mapel')
            ->select('kelas.jurusan_id, SUM(kelas_mapel.jam_per_minggu) AS total_jp')
            ->join('kelas', 'kelas.id = kelas_mapel.kelas_id')
            ->where('kelas_mapel.tahun_ajaran_id', $tahunAjaranId)
            ->where('kelas_mapel.butuh_lab', 1)
            ->groupBy('kelas.jurusan_id')
            ->get()
            ->getResultArray();

        $jurusanOverMsgs = [];
        foreach ($jpPerJurusan as $row) {
            $jid = (int) $row['jurusan_id'];
            $labCount = (int) ($labCountByJurusan[$jid] ?? 0);
            $poolCap = $labCount * $weeklyCap;
            $totalJp = (int) $row['total_jp'];
            if ($labCount < 1) {
                $jurusanOverMsgs[] = "Jurusan #{$jid}: tidak ada lab terdaftar";
            } elseif ($totalJp > $poolCap) {
                $jurusanOverMsgs[] = "Jurusan #{$jid}: {$totalJp} JP > kapasitas pool {$poolCap} JP ({$labCount} lab)";
            }
        }
        $results[] = [
            'rule'    => 'Kapasitas Pool Lab per Jurusan (HC-3)',
            'status'  => count($jurusanOverMsgs) === 0,
            'message' => count($jurusanOverMsgs) === 0
                ? "Total JP mapel lab per jurusan ≤ jumlah lab × {$weeklyCap} JP/minggu."
                : 'Pool lab jurusan kelebihan beban: ' . implode('; ', array_slice($jurusanOverMsgs, 0, 4))
                    . (count($jurusanOverMsgs) > 4 ? '...' : ''),
        ];

        $labOverCap = $db->table('kelas_mapel')
            ->select('kelas_mapel.lab_id, ruangan.nama AS lab_nama, SUM(kelas_mapel.jam_per_minggu) AS total_jp')
            ->join('ruangan', 'ruangan.id = kelas_mapel.lab_id')
            ->where('kelas_mapel.tahun_ajaran_id', $tahunAjaranId)
            ->where('kelas_mapel.butuh_lab', 1)
            ->where('kelas_mapel.lab_id IS NOT NULL')
            ->groupBy('kelas_mapel.lab_id')
            ->having('total_jp >', $weeklyCap)
            ->get()
            ->getResultArray();
        $labOverMsgs = [];
        foreach ($labOverCap as $row) {
            $labOverMsgs[] = ($row['lab_nama'] ?? 'Lab#' . $row['lab_id'])
                . ' (' . (int) $row['total_jp'] . " JP preferensi > {$weeklyCap} JP)";
        }
        $results[] = [
            'rule'    => 'Beban Lab Preferensi (peringatan)',
            'status'  => true,
            'message' => count($labOverCap) === 0
                ? 'Tidak ada lab preferensi dengan demand melebihi kapasitas mingguan slot.'
                : 'Peringatan — solver dapat memakai lab jurusan lain: ' . implode('; ', array_slice($labOverMsgs, 0, 4))
                    . (count($labOverMsgs) > 4 ? '...' : ''),
        ];

        $hariRows = $db->table('hari')->orderBy('urutan', 'ASC')->get()->getResultArray();
        $hariTanpaJp = [];
        foreach ($hariRows as $hari) {
            if (count($jpSlotsByHari[(int) $hari['id']] ?? []) < 1) {
                $hariTanpaJp[] = $hari['nama'];
            }
        }
        $results[] = [
            'rule'    => 'Konfigurasi Timeslot per Hari',
            'status'  => count($hariRows) > 0 && count($hariTanpaJp) === 0,
            'message' => (count($hariTanpaJp) === 0 && count($hariRows) > 0)
                ? 'Semua hari memiliki minimal 1 slot JP.'
                : (count($hariTanpaJp) > 0
                    ? 'Hari tanpa slot JP: ' . implode(', ', $hariTanpaJp) . '.'
                    : 'Data Hari atau Timeslot kosong.'),
        ];

        $totalDemand = (int) $db->table('kelas_mapel')
            ->selectSum('jam_per_minggu', 'total')
            ->where('tahun_ajaran_id', $tahunAjaranId)
            ->get()
            ->getRow()->total;

        $totalSupply = (int) $db->table('guru_mapel')
            ->selectSum('max_jam_per_minggu', 'total')
            ->get()
            ->getRow()->total;

        $capacityOk = $totalSupply >= $totalDemand;
        $results[] = [
            'rule'    => 'Kapasitas Guru Mingguan',
            'status'  => $capacityOk,
            'message' => $capacityOk
                ? "Kapasitas guru_mapel mencukupi (butuh {$totalDemand} JP, cap {$totalSupply} JP/minggu)."
                : "Kapasitas guru_mapel tidak cukup: butuh {$totalDemand} JP, cap {$totalSupply} JP/minggu.",
        ];

        return $results;
    }

    /**
     * @return array{success: bool, status: string, summary: string, report?: array, fitness?: string, execution_time?: float, message?: string, schedule_log_id?: int}
     */
    public function generate(
        int $tahunAjaranId,
        int $generatedBy,
        ?int $parentLogId = null,
        string $generateMode = 'fresh'
    ): array {
        $db = \Config\Database::connect();
        $startTime = microtime(true);
        $report = [
            'errors'   => [],
            'warnings' => [],
            'unplaced' => [],
            'stats'    => [],
        ];

        $validationResults = $this->validate($tahunAjaranId);
        $failedRules = array_filter($validationResults, fn ($v) => ! $v['status']);
        if ($failedRules !== []) {
            $messages = array_map(fn ($r) => $r['rule'] . ': ' . $r['message'], $failedRules);

            return [
                'success' => false,
                'status'  => 'failed',
                'summary' => 'Pre-validasi gagal. Perbaiki data master sebelum generate.',
                'message' => implode(' | ', $messages),
                'report'  => ['errors' => $messages, 'validation' => $validationResults],
            ];
        }

        $mode = $generateMode === 'history_repair' && $parentLogId ? 'history_repair' : 'fresh';

        $logModel = new ScheduleLogModel();
        $logId = $logModel->insert([
            'tahun_ajaran_id'        => $tahunAjaranId,
            'status'                 => 'running',
            'generated_by'           => $generatedBy,
            'started_at'             => date('Y-m-d H:i:s'),
            'parent_schedule_log_id' => $parentLogId,
            'generate_mode'          => $mode,
            'label'                  => 'Generate #' . ((int) $db->table('schedule_logs')->where('tahun_ajaran_id', $tahunAjaranId)->countAllResults() + 1),
        ]);

        $finalize = function (string $status, string $summary, array $extra = []) use (
            &$logModel,
            $logId,
            $startTime,
            &$report
        ): array {
            $execTime = round(microtime(true) - $startTime, 2);
            $report['stats']['execution_time'] = $execTime;

            $logModel->update($logId, array_merge([
                'status'          => $status,
                'execution_time'  => $execTime,
                'error_message'   => $summary,
                'result_report'   => json_encode($report, JSON_UNESCAPED_UNICODE),
                'completed_at'    => date('Y-m-d H:i:s'),
            ], $extra));

            $success = in_array($status, ['completed', 'partial'], true);

            return array_merge([
                'success'        => $success,
                'status'         => $status,
                'summary'        => $summary,
                'report'         => $report,
                'execution_time' => $execTime,
                'schedule_log_id' => $logId,
            ], $success ? [] : ['message' => $summary]);
        };

        $ta = $db->table('tahun_ajaran')->where('id', $tahunAjaranId)->get()->getRowArray();
        if (! $ta) {
            $report['errors'][] = 'Tahun ajaran tidak ditemukan atau tidak aktif.';

            return $finalize('failed', 'Tahun ajaran tidak valid.');
        }

        $configData = $db->table('schedule_config')->where('tahun_ajaran_id', $tahunAjaranId)->get()->getResultArray();
        $config = [];
        foreach ($configData as $c) {
            $config[$c['param_key']] = $c['param_value'];
        }

        $guruRows = $db->table('guru')
            ->select('guru.*, users.nama')
            ->join('users', 'users.id = guru.user_id')
            ->where('guru.deleted_at IS NULL')
            ->get()
            ->getResultArray();
        $guruNames = [];
        foreach ($guruRows as $g) {
            $guruNames[(int) $g['id']] = $g['nama'];
        }

        $kelasData = $db->table('kelas')
            ->where('tahun_ajaran_id', $tahunAjaranId)
            ->where('deleted_at IS NULL')
            ->get()
            ->getResultArray();
        $kelasById = [];
        $kelasNames = [];
        $homeroomMap = [];
        foreach ($kelasData as $kelasRow) {
            $kid = (int) $kelasRow['id'];
            $kelasById[$kid] = $kelasRow;
            $kelasNames[$kid] = $kelasRow['nama'];
            $homeroomMap[$kid] = (int) $kelasRow['ruangan_id'];
        }

        $mapelData = $db->table('mapel')->where('deleted_at IS NULL')->get()->getResultArray();
        $mapelNames = [];
        $mapelMeta = [];
        foreach ($mapelData as $m) {
            $mapelNames[(int) $m['id']] = $m['nama'];
            $mapelMeta[(int) $m['id']] = $m;
        }

        $timeslotModel = new TimeslotModel();
        $jpSlotsByHari = SchedulingContext::buildJpSlotsByHari($timeslotModel->getGroupedByHari());
        $hariData = $db->table('hari')->orderBy('urutan', 'ASC')->get()->getResultArray();

        $kelasMapelRows = $db->table('kelas_mapel')
            ->where('tahun_ajaran_id', $tahunAjaranId)
            ->get()
            ->getResultArray();

        $guruMapelRows = $db->table('guru_mapel')->get()->getResultArray();
        $guruPool = SchedulingContext::buildGuruPool($guruMapelRows, $mapelData);
        $guruBlokir = SchedulingContext::buildGuruBlokirIndex(
            $db->table('guru_hari_blokir')->get()->getResultArray()
        );

        $ruanganRows = $db->table('ruangan')
            ->where('deleted_at IS NULL')
            ->where('tipe', 'lab')
            ->get()
            ->getResultArray();
        $labPoolByJurusan = SchedulingContext::buildLabPoolByJurusan($ruanganRows);

        $units = $this->buildPlacementUnits($kelasMapelRows, $kelasById, $mapelMeta);
        $report['stats']['total_units'] = count($units);

        if ($units === []) {
            $report['errors'][] = 'Tidak ada unit JP kelas_mapel yang bisa dijadwalkan.';

            return $finalize('failed', 'Tidak ada unit JP. Periksa data kelas_mapel.');
        }

        $solverConfig = [
            'timeout_seconds'  => max(15, (int) ($config['timeout_seconds'] ?? 300)),
            'csp_max_attempts' => max(1, (int) ($config['csp_max_attempts'] ?? 8)),
        ];

        $engineData = [
            'units'                => $units,
            'jp_slots_by_hari'     => $jpSlotsByHari,
            'hari_data'            => $hariData,
            'guru_pool'            => $guruPool,
            'guru_blokir'          => $guruBlokir,
            'homeroom_map'         => $homeroomMap,
            'lab_pool_by_jurusan'  => $labPoolByJurusan,
            'guru_preferensi'      => $this->loadGuruPreferensi(),
        ];

        $seedAssignments = [];
        $repairReport    = null;
        if ($mode === 'history_repair' && $parentLogId) {
            $repairEngine = new HistoryRepairEngine();
            $repairResult = $repairEngine->repairFromHistory($engineData, $parentLogId, $units);
            $seedAssignments = $repairResult['seed_assignments'];
            $repairReport    = $repairResult['repair_report'];
            $report['stats']['repair'] = $repairReport;
        }

        $cspTimeout = (int) $solverConfig['timeout_seconds'];
        $cspEngine = new CSPEngine(array_merge($engineData, $solverConfig, [
            'seed_assignments' => $seedAssignments,
        ]));

        $cspResult = $cspEngine->solve();
        $assignments = $cspResult['assignments'] ?? [];
        $unplaced = $cspResult['unplaced'] ?? [];
        $report['stats']['csp'] = $cspResult['stats'] ?? [];
        $report['unplaced'] = $this->enrichUnplaced($unplaced, $guruNames, $kelasNames, $mapelNames);

        if ($assignments === []) {
            $summary = sprintf(
                'Gagal menempatkan unit JP (%d dari %d tidak terjadwal).',
                count($unplaced),
                count($units)
            );

            return $finalize('failed', $summary, ['total_conflicts' => count($unplaced)]);
        }

        $placedUnits = array_intersect_key($units, $assignments);
        $gaEngine = new GAEngine(array_merge($engineData, [
            'population_size'            => (int) ($config['population_size'] ?? 100),
            'max_generations'           => (int) ($config['max_generations'] ?? 500),
            'tournament_size'           => (int) ($config['tournament_size'] ?? 5),
            'crossover_rate'            => (float) ($config['crossover_rate'] ?? 0.8),
            'mutation_rate'             => (float) ($config['mutation_rate'] ?? 0.08),
            'fitness_threshold'         => (float) ($config['fitness_threshold'] ?? 0.95),
            'elitism_ratio'             => (float) ($config['elitism_ratio'] ?? 0.1),
            'stagnation_limit'          => (int) ($config['stagnation_limit'] ?? 40),
            'adaptive_mutation'         => (int) ($config['adaptive_mutation'] ?? 1),
            'adaptive_mutation_trigger' => (int) ($config['adaptive_mutation_trigger'] ?? 20),
            'adaptive_mutation_increment' => (float) ($config['adaptive_mutation_increment'] ?? 0.02),
            'timeout_seconds'           => min(180, $cspTimeout),
            'sc1_teacher_gap'           => (float) ($config['sc1_teacher_gap'] ?? 9),
            'sc2_student_gap'           => (float) ($config['sc2_student_gap'] ?? 9),
            'sc3_subject_distribution'  => (float) ($config['sc3_subject_distribution'] ?? 7),
            'sc4_heavy_morning'         => (float) ($config['sc4_heavy_morning'] ?? 6),
            'sc5_light_afternoon'       => (float) ($config['sc5_light_afternoon'] ?? 5),
            'sc6_teacher_load_balance'  => (float) ($config['sc6_teacher_load_balance'] ?? 7),
            'sc7_teacher_preference'    => (float) ($config['sc7_teacher_preference'] ?? 5),
            'sc8_room_transition'       => (float) ($config['sc8_room_transition'] ?? 5),
            'sc9_teacher_continuity'    => (float) ($config['sc9_teacher_continuity'] ?? 4),
            'sc10_first_slot_rotation'  => (float) ($config['sc10_first_slot_rotation'] ?? 3),
            'sc11_lab_load_balance'     => (float) ($config['sc11_lab_load_balance'] ?? 6),
            'sc_lab_preference'         => (float) ($config['sc_lab_preference'] ?? 5),
        ]));

        $gaResult = $gaEngine->optimize($assignments);
        $finalAssignments = $gaResult['assignments'];
        $report['stats']['ga'] = [
            'fitness'     => $gaResult['fitness'],
            'generations' => $gaResult['generations'],
            'violations'  => $gaResult['violations'],
        ];

        try {
            $db->transStart();

            $jadwalBatch = $this->expandAssignmentsToRows(
                $finalAssignments,
                $placedUnits,
                $jpSlotsByHari,
                $homeroomMap,
                $tahunAjaranId,
                (int) $logId
            );

            $jadwalModel = new JadwalModel();
            foreach (array_chunk($jadwalBatch, 100) as $chunk) {
                $jadwalModel->insertBatch($chunk);
            }

            $db->transComplete();

            if (! $db->transStatus()) {
                throw new \RuntimeException('Transaksi penyimpanan jadwal gagal.');
            }

            $report['stats']['placed_units'] = count($finalAssignments);
            $report['stats']['unplaced_units'] = count($unplaced);
            $report['stats']['jadwal_rows'] = count($jadwalBatch);
            $report['fill_report'] = $this->buildFillReport(
                $finalAssignments,
                $placedUnits,
                $jpSlotsByHari,
                $hariData,
                $kelasNames
            );

            $isPartial = count($unplaced) > 0;
            $status = $isPartial ? 'partial' : 'completed';

            if ($isPartial) {
                $summary = sprintf(
                    'Jadwal sebagian berhasil: %d/%d unit ditempatkan, %d belum terjadwal.',
                    count($finalAssignments),
                    count($units),
                    count($unplaced)
                );
            } else {
                $summary = sprintf(
                    'Jadwal berhasil digenerate: %d unit JP, fitness %.4f.',
                    count($units),
                    $gaResult['fitness']
                );
            }

            $result = $finalize($status, $summary, [
                'fitness_score'   => $gaResult['fitness'],
                'generations_run' => $gaResult['generations'],
                'total_conflicts' => count($unplaced),
                'unplaced_report' => json_encode($report['unplaced'], JSON_UNESCAPED_UNICODE),
                'repair_report'   => $repairReport ? json_encode($repairReport, JSON_UNESCAPED_UNICODE) : null,
            ]);
            $result['fitness'] = number_format($gaResult['fitness'], 4);
            $result['schedule_log_id'] = (int) $logId;

            return $result;
        } catch (\Throwable $e) {
            $report['errors'][] = $e->getMessage();

            return $finalize('failed', 'Gagal menyimpan jadwal: ' . $e->getMessage());
        }
    }

    public function reset(int $tahunAjaranId): bool
    {
        $db = \Config\Database::connect();
        $logIds = $db->table('schedule_logs')
            ->select('id')
            ->where('tahun_ajaran_id', $tahunAjaranId)
            ->get()
            ->getResultArray();

        foreach ($logIds as $row) {
            (new JadwalModel())->where('schedule_log_id', (int) $row['id'])->delete();
        }

        (new ScheduleLogModel())->where('tahun_ajaran_id', $tahunAjaranId)->delete();
        $db->table('tahun_ajaran')->where('id', $tahunAjaranId)->update(['published_schedule_log_id' => null]);

        return true;
    }

    /**
     * @param list<array<string, mixed>> $kelasMapelRows
     * @param array<int, array<string, mixed>> $kelasById
     * @param array<int, array<string, mixed>> $mapelMeta
     * @return array<int, array<string, mixed>>
     */
    protected function buildPlacementUnits(array $kelasMapelRows, array $kelasById, array $mapelMeta): array
    {
        $units = [];
        $unitId = 1;

        foreach ($kelasMapelRows as $km) {
            $kelasId = (int) $km['kelas_id'];
            $mapelId = (int) $km['mapel_id'];
            $kelas = $kelasById[$kelasId] ?? null;
            $mapel = $mapelMeta[$mapelId] ?? null;
            if (! $kelas || ! $mapel) {
                continue;
            }

            $jam = (int) ($km['jam_per_minggu'] ?? 0);
            for ($i = 0; $i < $jam; $i++) {
                $units[$unitId] = [
                    'unit_id'          => $unitId,
                    'kelas_mapel_id'   => (int) $km['id'],
                    'kelas_id'         => $kelasId,
                    'mapel_id'         => $mapelId,
                    'jurusan_id'       => (int) $kelas['jurusan_id'],
                    'unit_index'       => $i,
                    'butuh_lab'        => (int) ($km['butuh_lab'] ?? 0),
                    'lab_id'           => $km['lab_id'] ?? null, // preferred lab (kelas_mapel.lab_id)
                    'homeroom_id'      => (int) $kelas['ruangan_id'],
                    'mapel_tipe'       => $mapel['tipe'] ?? 'umum',
                    'mapel_jurusan_id' => isset($mapel['jurusan_id']) ? (int) $mapel['jurusan_id'] : null,
                    'bobot_kognitif'   => (int) ($mapel['bobot_kognitif'] ?? 5),
                ];
                $unitId++;
            }
        }

        return $units;
    }

    /**
     * @param array<int, array{hari_id: int, timeslot_id: int, slot_index: int, guru_id: int}> $assignments
     * @param array<int, array<string, mixed>> $units
     * @param array<int, list<array{id: int, jam_ke: int, slot_index: int}>> $jpSlotsByHari
     */
    protected function expandAssignmentsToRows(
        array $assignments,
        array $units,
        array $jpSlotsByHari,
        array $homeroomMap,
        int $tahunAjaranId,
        int $scheduleLogId
    ): array {
        $jamKeBySlot = [];
        foreach ($jpSlotsByHari as $hariId => $slots) {
            foreach ($slots as $slot) {
                $jamKeBySlot[(int) $hariId][(int) $slot['id']] = (int) $slot['jam_ke'];
            }
        }

        $sorted = [];
        foreach ($assignments as $unitId => $assignment) {
            if (! isset($units[$unitId])) {
                continue;
            }
            $unit = $units[$unitId];
            $hariId = (int) $assignment['hari_id'];
            $sorted[] = [
                'unit_id'    => $unitId,
                'assignment' => $assignment,
                'unit'       => $unit,
                'jam_ke'     => $jamKeBySlot[$hariId][(int) $assignment['timeslot_id']] ?? 0,
            ];
        }

        usort($sorted, function (array $a, array $b): int {
            $cmp = (int) $a['unit']['kelas_id'] <=> (int) $b['unit']['kelas_id'];
            if ($cmp !== 0) {
                return $cmp;
            }
            $cmp = (int) $a['assignment']['hari_id'] <=> (int) $b['assignment']['hari_id'];
            if ($cmp !== 0) {
                return $cmp;
            }
            $cmp = (int) $a['unit']['mapel_id'] <=> (int) $b['unit']['mapel_id'];
            if ($cmp !== 0) {
                return $cmp;
            }

            return $a['jam_ke'] <=> $b['jam_ke'];
        });

        $rows = [];
        $prevKey = null;
        $prevJamKe = null;
        $blokGroup = null;

        foreach ($sorted as $item) {
            $unit = $item['unit'];
            $assignment = $item['assignment'];
            $hariId = (int) $assignment['hari_id'];
            $kelasId = (int) $unit['kelas_id'];
            $mapelId = (int) $unit['mapel_id'];
            $jamKe = (int) $item['jam_ke'];
            $key = "{$kelasId}:{$hariId}:{$mapelId}";

            if ($key === $prevKey && $prevJamKe !== null && $jamKe === $prevJamKe + 1) {
                // extend same blok_group
            } else {
                $blokGroup = bin2hex(random_bytes(8));
            }

            $ruanganId = (int) ($unit['butuh_lab'] ?? 0) === 1
                ? (int) ($assignment['ruangan_id'] ?? 0)
                : (int) ($homeroomMap[$kelasId] ?? 0);

            $rows[] = [
                'tahun_ajaran_id' => $tahunAjaranId,
                'schedule_log_id' => $scheduleLogId,
                'kelas_mapel_id'  => (int) $unit['kelas_mapel_id'],
                'hari_id'         => $hariId,
                'timeslot_id'     => (int) $assignment['timeslot_id'],
                'kelas_id'        => $kelasId,
                'guru_id'         => (int) $assignment['guru_id'],
                'mapel_id'        => $mapelId,
                'ruangan_id'      => $ruanganId,
                'blok_group'      => $blokGroup,
            ];

            $prevKey = $key;
            $prevJamKe = $jamKe;
        }

        return $rows;
    }

    protected function enrichUnplaced(array $unplaced, array $guruNames, array $kelasNames, array $mapelNames): array
    {
        $reasonLabels = [
            'teacher_conflict'    => 'Konflik jadwal guru (HC-1)',
            'lab_conflict'        => 'Konflik jadwal lab (HC-3)',
            'no_slot'             => 'Tidak ada slot tersedia',
            'no_guru_eligible'    => 'Tidak ada guru eligible (HC-4/HC-6/HC-7)',
            'class_conflict'      => 'Konflik slot kelas (HC-2)',
            'class_over_capacity' => 'Kapasitas kelas terlampaui (HC-5)',
            'timeout'             => 'Waktu habis saat penjadwalan',
            'not_attempted'       => 'Belum dicoba',
        ];

        foreach ($unplaced as &$item) {
            $item['guru_nama'] = $guruNames[(int) ($item['guru_id'] ?? 0)] ?? null;
            $item['kelas_nama'] = $kelasNames[(int) ($item['kelas_id'] ?? 0)] ?? null;
            $item['mapel_nama'] = $mapelNames[(int) ($item['mapel_id'] ?? 0)] ?? null;
            $item['reason_label'] = $reasonLabels[$item['reason'] ?? ''] ?? ($item['reason'] ?? 'Tidak diketahui');
            if (empty($item['suggested_fix'])) {
                $item['suggested_fix'] = $this->defaultSuggestedFix($item['reason'] ?? '');
            }
        }
        unset($item);

        return $unplaced;
    }

    protected function defaultSuggestedFix(string $reason): string
    {
        return match ($reason) {
            'teacher_conflict'    => 'Tambah guru cadangan (guru_mapel) untuk mapel ini atau naikkan csp_max_attempts.',
            'lab_conflict'        => 'Banyak kelas berbagi lab yang sama — kurangi JP mapel lab, tambah lab, atau bagi kelas ke lab berbeda.',
            'no_guru_eligible'    => 'Tambah guru_mapel, naikkan max_jam_per_minggu, atau hapus guru_hari_blokir.',
            'class_over_capacity' => 'Total JP kelas melebihi kapasitas mingguan — kurangi jam_per_minggu kelas_mapel.',
            'timeout'             => 'Naikkan timeout_seconds atau kurangi csp_max_attempts.',
            default               => 'Generate ulang dengan parameter solver yang lebih agresif.',
        };
    }

    /**
     * @param array<int, array{hari_id: int, slot_index: int}> $assignments
     * @param array<int, array<string, mixed>> $units
     * @param array<int, list<array{id: int, jam_ke: int, slot_index: int}>> $jpSlotsByHari
     * @param list<array<string, mixed>> $hariData
     * @param array<int, string> $kelasNames
     * @return array<string, array<string, string>>
     */
    protected function buildFillReport(
        array $assignments,
        array $units,
        array $jpSlotsByHari,
        array $hariData,
        array $kelasNames
    ): array {
        $hariById = [];
        foreach ($hariData as $h) {
            $hariById[(int) $h['id']] = $h['kode'] ?? $h['nama'] ?? ('H' . $h['id']);
        }

        $counts = [];
        foreach ($assignments as $unitId => $a) {
            if (! isset($units[$unitId])) {
                continue;
            }
            $kelasId = (int) $units[$unitId]['kelas_id'];
            $hariId = (int) $a['hari_id'];
            $counts[$kelasId][$hariId] = (int) ($counts[$kelasId][$hariId] ?? 0) + 1;
        }

        $report = [];
        foreach ($counts as $kelasId => $days) {
            $nama = $kelasNames[$kelasId] ?? ('Kelas #' . $kelasId);
            $entry = [];
            $total = 0;
            $capTotal = 0;
            foreach ($hariData as $h) {
                $hid = (int) $h['id'];
                $cap = SchedulingContext::dailyJpCapacity($hid, $jpSlotsByHari);
                $placed = (int) ($days[$hid] ?? 0);
                $label = $hariById[$hid] ?? 'H';
                $entry[$label] = "{$placed}/{$cap}";
                $total += $placed;
                $capTotal += $cap;
            }
            $entry['total'] = "{$total}/{$capTotal}";
            $report[$nama] = $entry;
        }

        return $report;
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function loadGuruPreferensi(): array
    {
        $db = \Config\Database::connect();

        return $db->table('guru_preferensi')->get()->getResultArray();
    }
}

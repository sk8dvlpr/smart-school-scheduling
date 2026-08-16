<?php

namespace App\Libraries;

/**
 * CSP solver v3.0.
 *
 * Backtracking + forward checking with MRV variable ordering and LCV value
 * ordering, enforcing HC-1..HC-8 (no mid-day no-gap rule, no per-day JP cap —
 * those became soft constraints, which is what lets a full week always solve).
 *
 * Assignment shape: [unit_id => ['hari_id','timeslot_id','slot_index','guru_id','ruangan_id'?]]
 */
class CSPEngine
{
    /** @var array<int, array<string, mixed>> */
    protected array $units = [];
    /** @var list<array<string, mixed>> */
    protected array $hariData = [];
    /** @var array<int, list<array{id:int,jam_ke:int,slot_index:int}>> */
    protected array $jpSlotsByHari = [];
    /** @var array<int, list<array{guru_id:int,max_jam:int,mapel_tipe:string,mapel_jurusan_id:?int}>> */
    protected array $guruPool = [];
    /** @var array<int, array<int, true>> */
    protected array $guruBlokir = [];
    /** @var array<int, int> */
    protected array $homeroomMap = [];
    /** @var array<int, list<int>> jurusan_id => lab ruangan ids */
    protected array $labPoolByJurusan = [];

    protected float $startTime = 0.0;
    protected int $timeoutSeconds = 300;
    protected int $maxAttempts = 12;

    /** @var list<int> */
    protected array $hariIds = [];
    /** @var array<int, int> mapel_id => eligible guru pool size (static scarcity) */
    protected array $mapelSupply = [];

    // Mutable solver state -----------------------------------------------
    /** @var array<int, array{hari_id:int,timeslot_id:int,slot_index:int,guru_id:int,ruangan_id?:int}> */
    protected array $assignments = [];
    /** @var array<int, array<int, array<int, true>>> */
    protected array $guruSlot = [];
    /** @var array<int, array<int, array<int, true>>> */
    protected array $kelasSlot = [];
    /** @var array<int, array<int, array<int, true>>> */
    protected array $labSlot = [];
    /** @var array<int, array<int, int>> */
    protected array $guruMapelAssigned = [];
    /** @var array<int, array<int, int>> [kelas][hari] => jp count (for LCV spreading) */
    protected array $kelasDayCount = [];
    /** @var array<int, int> kelas_mapel_id => locked guru_id (SC-9) */
    protected array $kelasMapelGuruLock = [];
    /**
     * Master switch for the SC-9 guru lock. Disabled on solve() attempt 1 so
     * the solver can freely pick the best guru per kelas_mapel; re-enabled from
     * attempt 2 onwards (and during repair) to keep solutions stable.
     * Default true keeps the public API (canAssignUnit/candidatesForUnit)
     * behaving exactly like the old always-on lock.
     */
    protected bool $lockGuruEnabled = true;
    /** @var array<int, array{hari_id:int,timeslot_id:int,slot_index:int,guru_id:int,ruangan_id?:int}> */
    protected array $seedAssignments = [];

    /** Jumlah evict sukses oleh repair PASS 2 (di-reset per repairSweepAdvanced). */
    protected int $repairEvictCount = 0;
    /** Jumlah swap/relocate sukses oleh repair PASS 3 (di-reset per repairSweepAdvanced). */
    protected int $repairSwapCount = 0;

    public function __construct(array $data)
    {
        $this->units               = $data['units'] ?? [];
        $this->hariData            = $data['hari_data'] ?? [];
        $this->jpSlotsByHari       = $data['jp_slots_by_hari'] ?? [];
        $this->guruPool            = $data['guru_pool'] ?? [];
        $this->guruBlokir          = $data['guru_blokir'] ?? [];
        $this->homeroomMap         = $data['homeroom_map'] ?? [];
        $this->labPoolByJurusan    = $data['lab_pool_by_jurusan'] ?? [];
        $this->timeoutSeconds      = max(15, (int) ($data['timeout_seconds'] ?? 300));
        $this->maxAttempts         = max(1, (int) ($data['csp_max_attempts'] ?? 12));
        $this->seedAssignments     = $data['seed_assignments'] ?? [];

        foreach ($this->hariData as $h) {
            $this->hariIds[] = (int) $h['id'];
        }

        foreach ($this->guruPool as $mapelId => $entries) {
            $this->mapelSupply[(int) $mapelId] = count($entries);
        }
    }

    /**
     * @return array{assignments: array, unplaced: array, stats: array}
     */
    public function solve(): array
    {
        $this->startTime = microtime(true);

        if ($this->units === []) {
            return [
                'assignments' => [],
                'unplaced'    => [],
                'stats'       => ['placed' => 0, 'unplaced' => 0, 'attempts' => 0, 'elapsed' => 0.0],
            ];
        }

        $grouped   = $this->groupUnitsByKelas();
        $pressures = $this->computeClassPressures($grouped);

        $best = null;
        $bestUnplaced = PHP_INT_MAX;
        $attemptsRun  = 0;
        /** @var array<int, array<int, int>> attempt => [kelas_id => jumlah unplaced] */
        $attemptHistory = [];

        for ($attempt = 1; $attempt <= $this->maxAttempts; $attempt++) {
            if ($this->isTimedOut()) {
                break;
            }
            $attemptsRun = $attempt;
            // Delayed guru lock (SC-9): attempt 1 is fully flexible, attempts 2+
            // lock the guru per kelas_mapel for stable solutions.
            $this->lockGuruEnabled = ($attempt >= 2);
            $this->resetState();
            $this->applySeedAssignments();

            $classOrder = $this->buildClassOrder(array_keys($grouped), $pressures, $attempt, $attemptHistory[$attempt - 1] ?? []);
            foreach ($classOrder as $kelasId) {
                if ($this->isTimedOut()) {
                    break;
                }
                $this->placeClass((int) $kelasId, $grouped[$kelasId], $attempt);
            }

            $unplacedIds = $this->collectUnplacedIds();
            $countUnplaced = count($unplacedIds);

            // Track per-class unplaced counts for smart re-ordering next attempt.
            $kelasUnplaced = [];
            foreach ($unplacedIds as $uid) {
                $kid = (int) $this->units[$uid]['kelas_id'];
                $kelasUnplaced[$kid] = ($kelasUnplaced[$kid] ?? 0) + 1;
            }
            $attemptHistory[$attempt] = $kelasUnplaced;

            if ($countUnplaced < $bestUnplaced) {
                $bestUnplaced = $countUnplaced;
                $best = [
                    'assignments' => $this->assignments,
                    'state'       => $this->snapshotState(),
                    'unplaced'    => $unplacedIds,
                ];
            }

            if ($countUnplaced === 0) {
                break;
            }
        }

        if ($best === null) {
            $best = ['assignments' => [], 'state' => null, 'unplaced' => array_keys($this->units)];
        }

        // Restore best attempt, then run a final greedy repair sweep.
        if ($best['state'] !== null) {
            $this->restoreState($best['state']);
        } else {
            $this->resetState();
        }
        // Repair & GA phase behave like today: guru lock fully enabled.
        $this->lockGuruEnabled = true;
        $this->repairSweepAdvanced($best['unplaced']);

        $unplacedIds = $this->collectUnplacedIds();
        $unplaced    = [];
        foreach ($unplacedIds as $uid) {
            $unplaced[] = $this->buildUnplacedEntry($uid, $this->analyzeFailure($uid));
        }

        return [
            'assignments' => $this->assignments,
            'unplaced'    => $unplaced,
            'stats'       => [
                'placed'   => count($this->assignments),
                'unplaced' => count($unplaced),
                'attempts' => $attemptsRun,
                'elapsed'  => round(microtime(true) - $this->startTime, 3),
            ],
        ];
    }

    // ------------------------------------------------------------------
    // Placement
    // ------------------------------------------------------------------

    protected function placeClass(int $kelasId, array $unitIds, int $attempt): void
    {
        $ordered = $this->orderUnits($unitIds, $attempt);

        // Node budget scales with class size; loose constraints keep depth shallow.
        $budget = max(8000, count($ordered) * 150);
        $snapshot = $this->snapshotState();

        if ($this->backtrack($ordered, 0, $budget)) {
            return;
        }

        // Backtracking exhausted budget — fall back to best-effort greedy so we
        // keep as many placements as possible instead of unwinding the class.
        $this->restoreState($snapshot);
        foreach ($ordered as $unitId) {
            if (isset($this->assignments[$unitId])) {
                continue;
            }
            $cand = $this->firstCandidate($unitId);
            if ($cand !== null) {
                $this->assign($unitId, $cand);
            }
        }
    }

    /**
     * @param list<int> $units
     */
    protected function backtrack(array $units, int $i, int &$budget): bool
    {
        if ($i >= count($units)) {
            return true;
        }
        if (--$budget <= 0 || $this->isTimedOut()) {
            return false;
        }

        $unitId = $units[$i];
        if (isset($this->assignments[$unitId])) {
            return $this->backtrack($units, $i + 1, $budget);
        }

        $candidates = $this->candidateAssignments($unitId, 24);
        foreach ($candidates as $cand) {
            $this->assign($unitId, $cand);
            if ($this->backtrack($units, $i + 1, $budget)) {
                return true;
            }
            $this->unassign($unitId);
            if ($budget <= 0 || $this->isTimedOut()) {
                return false;
            }
        }

        return false;
    }

    /**
     * @param array<int, array{hari_id:int,timeslot_id:int,slot_index:int,guru_id:int}> $existing
     */
    public function canAssignUnit(int $unitId, array $cand, array $existing = []): bool
    {
        if (! isset($this->units[$unitId])) {
            return false;
        }

        $snapshot = $this->snapshotState();
        $this->assignments       = $existing;
        $this->guruSlot          = [];
        $this->kelasSlot         = [];
        $this->labSlot           = [];
        $this->guruMapelAssigned = [];
        $this->kelasDayCount     = [];
        $this->kelasMapelGuruLock = [];

        foreach ($existing as $uid => $a) {
            if (isset($this->units[$uid])) {
                $this->assign((int) $uid, $a);
            }
        }

        $ok = $this->canAssign($unitId, $cand);
        $this->restoreState($snapshot);

        return $ok;
    }

    /**
     * @return list<array{hari_id:int,timeslot_id:int,slot_index:int,guru_id:int}>
     */
    public function candidatesForUnit(int $unitId, int $limit = 24): array
    {
        return $this->candidateAssignments($unitId, $limit);
    }

    /**
     * Total evict-and-reinsert yang berhasil dieksekusi repair PASS 2
     * pada solve() terakhir (0 jika tidak pernah dipanggil / tidak ada evict).
     */
    public function getRepairEvictCount(): int
    {
        return $this->repairEvictCount;
    }

    /**
     * Total relocate/swap yang berhasil dieksekusi repair PASS 3
     * pada solve() terakhir (0 jika tidak pernah dipanggil / tidak ada swap).
     */
    public function getRepairSwapCount(): int
    {
        return $this->repairSwapCount;
    }

    protected function applySeedAssignments(): void
    {
        foreach ($this->seedAssignments as $unitId => $cand) {
            $unitId = (int) $unitId;
            if (! isset($this->units[$unitId]) || isset($this->assignments[$unitId])) {
                continue;
            }
            if ($this->canAssign($unitId, $cand)) {
                $this->assign($unitId, $cand);
            }
        }
    }

    protected function canAssign(int $unitId, array $cand): bool
    {
        $unit     = $this->units[$unitId];
        $kelasId  = (int) $unit['kelas_id'];
        $mapelId  = (int) $unit['mapel_id'];
        $kmId     = (int) $unit['kelas_mapel_id'];
        $butuhLab = (int) ($unit['butuh_lab'] ?? 0) === 1;
        $hariId   = (int) $cand['hari_id'];
        $timeslotId = (int) $cand['timeslot_id'];
        $guruId   = (int) $cand['guru_id'];

        if (isset($this->kelasMapelGuruLock[$kmId]) && $this->kelasMapelGuruLock[$kmId] !== $guruId) {
            return false;
        }
        if (isset($this->kelasSlot[$kelasId][$hariId][$timeslotId])) {
            return false;
        }
        if (isset($this->guruSlot[$guruId][$hariId][$timeslotId])) {
            return false;
        }

        if ($butuhLab) {
            $kmDayLab = SchedulingContext::buildKmDayLabFromAssignments($this->assignments, $this->units, $unitId);
            $explicit = isset($cand['ruangan_id']) ? (int) $cand['ruangan_id'] : null;
            $ruanganId = SchedulingContext::resolveLabForPlacement(
                $kmId,
                $hariId,
                $timeslotId,
                (int) ($unit['lab_id'] ?? 0),
                (int) $unit['jurusan_id'],
                $this->labPoolByJurusan,
                $this->labSlot,
                $kmDayLab,
                $explicit
            );
            if ($ruanganId === null) {
                return false;
            }
            if ($explicit !== null && $explicit > 0 && $explicit !== $ruanganId) {
                return false;
            }
        }

        if (! in_array($guruId, SchedulingContext::eligibleGurus(
            $unit, $hariId, $this->guruPool, $this->guruBlokir, $this->guruMapelAssigned
        ), true)) {
            return false;
        }
        if (SchedulingContext::remainingCap($guruId, $mapelId, $this->guruPool, $this->guruMapelAssigned) < 1) {
            return false;
        }

        return true;
    }

    /**
     * Ordered, feasible (hari, timeslot, guru) candidates for a unit.
     * Non-lab: LCV spread across days (fewest class JP that day first), then earlier slot.
     * Lab (butuh_lab): pack same kelas_mapel onto as few days as possible (km-packing heuristic).
     *
     * $allowBlocked=true HANYA untuk repair pass (PASS 2/3 evict/relocate):
     * okupansi HC-1/HC-2/HC-3 di-skip sehingga kandidat boleh sedang
     * terblokir unit lain (blocker bisa di-evict). Keamanan HC tetap dijamin
     * via canAssign() setelah blocker dilepas. Caller normal (backtrack,
     * firstCandidate, candidatesForUnit) memakai default false — perilaku
     * tidak berubah.
     *
     * @return list<array{hari_id:int,timeslot_id:int,slot_index:int,guru_id:int,ruangan_id?:int}>
     */
    protected function candidateAssignments(int $unitId, int $limit, bool $allowBlocked = false): array
    {
        $unit     = $this->units[$unitId];
        $kelasId  = (int) $unit['kelas_id'];
        $mapelId  = (int) $unit['mapel_id'];
        $kmId     = (int) $unit['kelas_mapel_id'];
        $butuhLab = (int) ($unit['butuh_lab'] ?? 0) === 1;
        $lockedGuru = $this->kelasMapelGuruLock[$kmId] ?? null;
        $kmDayLab = $butuhLab
            ? SchedulingContext::buildKmDayLabFromAssignments($this->assignments, $this->units, $unitId)
            : [];
        // Kandidat repair ($allowBlocked) mengabaikan okupansi lab — blocker
        // lab (HC-3) boleh diusir; kandidat normal memakai labSlot aktual.
        $labSlotSource = $allowBlocked ? [] : $this->labSlot;

        $out = [];
        foreach ($this->hariIds as $hariId) {
            $eligible = SchedulingContext::eligibleGurus(
                $unit,
                $hariId,
                $this->guruPool,
                $this->guruBlokir,
                $this->guruMapelAssigned
            );
            if ($eligible === []) {
                continue;
            }
            if ($lockedGuru !== null) {
                $eligible = in_array($lockedGuru, $eligible, true) ? [$lockedGuru] : [];
                if ($eligible === []) {
                    continue;
                }
            }

            $dayCount = (int) ($this->kelasDayCount[$kelasId][$hariId] ?? 0);

            foreach ($this->jpSlotsByHari[$hariId] ?? [] as $slot) {
                $timeslotId = (int) $slot['id'];
                $slotIndex  = (int) $slot['slot_index'];

                if (! $allowBlocked && isset($this->kelasSlot[$kelasId][$hariId][$timeslotId])) {
                    continue; // HC-2
                }

                $ruanganId = null;
                if ($butuhLab) {
                    $ruanganId = SchedulingContext::resolveLabForPlacement(
                        $kmId,
                        $hariId,
                        $timeslotId,
                        (int) ($unit['lab_id'] ?? 0),
                        (int) $unit['jurusan_id'],
                        $this->labPoolByJurusan,
                        $labSlotSource,
                        $kmDayLab,
                        null
                    );
                    if ($ruanganId === null) {
                        continue; // HC-3 / HC-LAB-DAY / no pool lab
                    }
                }

                // HC-1: pick a free guru, prefer most remaining cap (LCV).
                $bestGuru = null;
                $bestCap  = -1;
                foreach ($eligible as $g) {
                    if (! $allowBlocked && isset($this->guruSlot[$g][$hariId][$timeslotId])) {
                        continue;
                    }
                    $cap = SchedulingContext::remainingCap($g, $mapelId, $this->guruPool, $this->guruMapelAssigned);
                    if ($cap > $bestCap) {
                        $bestCap  = $cap;
                        $bestGuru = $g;
                    }
                }
                if ($bestGuru === null) {
                    continue;
                }

                $score = $butuhLab
                    ? SchedulingContext::cspLabCandidateScore(
                        $unit,
                        $hariId,
                        $slotIndex,
                        $this->assignments,
                        $this->units,
                        $this->labPoolByJurusan,
                        $unitId
                    )
                    : ($dayCount * 100 + $slotIndex);

                $candidate = [
                    'hari_id'     => $hariId,
                    'timeslot_id' => $timeslotId,
                    'slot_index'  => $slotIndex,
                    'guru_id'     => $bestGuru,
                    '_score'      => $score,
                ];
                if ($butuhLab && $ruanganId !== null) {
                    $candidate['ruangan_id'] = $ruanganId;
                }
                $out[] = $candidate;
            }
        }

        usort($out, fn ($a, $b) => $a['_score'] <=> $b['_score']);
        $out = array_slice($out, 0, max(1, $limit));
        foreach ($out as &$c) {
            unset($c['_score']);
        }

        return $out;
    }

    protected function firstCandidate(int $unitId): ?array
    {
        $c = $this->candidateAssignments($unitId, 1);

        return $c[0] ?? null;
    }

    /**
     * Final sweep: try to place any still-unplaced units with a fresh scan.
     *
     * @param list<int> $unplacedIds
     */
    protected function repairSweep(array $unplacedIds): void
    {
        foreach ($unplacedIds as $unitId) {
            if (isset($this->assignments[$unitId]) || $this->isTimedOut()) {
                continue;
            }
            $cand = $this->firstCandidate((int) $unitId);
            if ($cand !== null) {
                $this->assign((int) $unitId, $cand);
            }
        }
    }

    /**
     * Multi-pass min-conflict repair sweep (v3.2):
     *   PASS 1: greedy (repairSweep)
     *   PASS 2: evict-and-reinsert — usir unit blocking slot terbaik, re-place unit korban
     *   PASS 3: relocate — pindahkan blocker ke slot lain untuk membuka slot unit target
     * Semua mutasi lewat assign()/unassign(), semua validasi via canAssign(),
     * kegagalan selalu di-rollback penuh, dan semua loop hormati isTimedOut().
     *
     * @param list<int> $unplacedIds
     */
    protected function repairSweepAdvanced(array $unplacedIds): void
    {
        // Counter repair pass di-reset agar bisa dijadikan bukti eksekusi
        // PASS 2/3 pada solve() terakhir (dipakai unit test + debugging).
        $this->repairEvictCount = 0;
        $this->repairSwapCount  = 0;

        $this->repairSweep($unplacedIds);                    // PASS 1: greedy
        $unplaced = $this->collectUnplacedIds();
        if ($unplaced === [] || $this->isTimedOut()) {
            return;
        }

        $rounds = 0;
        while ($rounds < 2 && $unplaced !== [] && ! $this->isTimedOut()) {
            $rounds++;
            $before = count($unplaced);
            $this->repairEvictAndReinsert($unplaced);        // PASS 2
            $unplaced = $this->collectUnplacedIds();
            if ($unplaced === [] || $this->isTimedOut()) {
                break;
            }
            $this->repairRelocateSwap($unplaced);            // PASS 3
            $unplaced = $this->collectUnplacedIds();
            if (count($unplaced) >= $before) {
                break;                                       // tidak ada progres → stop
            }
        }
    }

    /**
     * PASS 2: untuk setiap unit yang masih stuck, coba "usir" unit yang
     * menempati slot terbaik (evict), tempatkan unit target, lalu re-place
     * unit korban ke slot lain. Korban yang gagal di-re-place dikembalikan
     * ke posisi semula (rollback penuh).
     *
     * Catatan desain: enumerasi memakai rawCandidateSlots() (termasuk slot yang
     * sedang terblokir) — candidateAssignments() hanya menghasilkan kandidat
     * bebas-blokir sehingga tidak bisa dipakai untuk evict.
     *
     * @param list<int> $unplaced
     */
    protected function repairEvictAndReinsert(array $unplaced): void
    {
        $evicted = [];
        $ops     = 0;
        $maxOps  = min(60, count($unplaced) * 3);

        foreach ($unplaced as $unitId) {
            $unitId = (int) $unitId;
            if (isset($this->assignments[$unitId]) || $this->isTimedOut() || $ops >= $maxOps) {
                continue;
            }
            $cand = $this->firstCandidate($unitId);
            if ($cand !== null) {
                $this->assign($unitId, $cand);
                continue;
            }

            foreach ($this->rawCandidateSlots($unitId, 24) as $c) {
                $blockers = $this->blockingUnits($unitId, $c);
                if ($blockers === []) {
                    $this->assign($unitId, $c);
                    break;
                }

                $victim = $this->pickEvictVictim($unitId, $blockers, $evicted);
                if ($victim === null) {
                    continue;
                }

                $victimCand = $this->assignments[$victim];
                $this->unassign($victim);
                $evicted[$victim] = true;
                $ops++;
                if (! $this->canAssign($unitId, $c)) {
                    $this->assign($victim, $victimCand);
                    continue;
                }
                $this->assign($unitId, $c);
                $replacement = $this->firstCandidate($victim);
                if ($replacement !== null && $this->canAssign($victim, $replacement)) {
                    $this->assign($victim, $replacement);
                    $this->repairEvictCount++; // evict sukses: unit terpasang + korban di-re-place
                    break; // sukses: unit terpasang + korban di-re-place
                }
                // Korban tidak bisa di-re-place → rollback penuh, coba kandidat lain.
                $this->unassign($unitId);
                $this->assign($victim, $victimCand);
            }
        }
    }

    /**
     * PASS 3: untuk setiap unit yang masih stuck, coba pindahkan (relocate)
     * satu blocker ke slot lain agar slot target terbuka — bukan swap simultan.
     *
     * @param list<int> $unplaced
     */
    protected function repairRelocateSwap(array $unplaced): void
    {
        $ops    = 0;
        $maxOps = min(60, count($unplaced) * 3);

        foreach ($unplaced as $unitId) {
            $unitId = (int) $unitId;
            if (isset($this->assignments[$unitId]) || $this->isTimedOut() || $ops >= $maxOps) {
                continue;
            }
            foreach ($this->rawCandidateSlots($unitId, 24) as $c) {
                $blockers = $this->blockingUnits($unitId, $c);
                if ($blockers === []) {
                    $this->assign($unitId, $c);
                    break;
                }
                foreach ($blockers as $victim) {
                    $victim = (int) $victim;
                    if ($this->units[$victim]['kelas_mapel_id'] === $this->units[$unitId]['kelas_mapel_id']) {
                        continue;
                    }
                    $victimCand = $this->assignments[$victim];
                    $this->unassign($victim);
                    $ops++;
                    // Validasi ulang setelah blocker dilepas; gagal → rollback.
                    if (! $this->canAssign($unitId, $c)) {
                        $this->assign($victim, $victimCand);
                        continue;
                    }
                    $this->assign($unitId, $c);
                    $placed = false;
                    foreach ($this->candidateAssignments($victim, 24) as $vc) {
                        if ($this->canAssign($victim, $vc)) {
                            $this->assign($victim, $vc);
                            $placed = true;
                            $this->repairSwapCount++; // relocate/swap sukses
                            break;
                        }
                    }
                    if (! $placed) {
                        // Korban tidak punya slot lain → rollback penuh.
                        $this->unassign($unitId);
                        $this->assign($victim, $victimCand);
                    } else {
                        continue 2;
                    }
                }
            }
        }
    }

    /**
     * Enumerasi (hari, timeslot, guru) untuk unit TANPA filter okupansi
     * (HC-1/HC-2/HC-3 dilewati) — kandidat boleh sedang terblokir unit lain.
     * Khusus dipakai repair pass (evict/relocate) agar blocker bisa diusir;
     * keamanan HC tetap dijamin via canAssign() setelah blocker dilepas.
     * Eligibility HC-4/HC-6/HC-7 dan lab pool jurusan tetap dihormati.
     *
     * Thin wrapper di atas candidateAssignments($unitId, $limit, $allowBlocked=true).
     *
     * @return list<array{hari_id:int,timeslot_id:int,slot_index:int,guru_id:int,ruangan_id?:int}>
     */
    protected function rawCandidateSlots(int $unitId, int $limit): array
    {
        return $this->candidateAssignments($unitId, $limit, true);
    }

    /**
     * Unit-unit yang saat ini menempati (hari, timeslot) sama dengan $cand dan
     * memblokir placement via HC-1 (guru sama), HC-2 (kelas sama), atau HC-3
     * (ruangan lab sama).
     *
     * @param array{hari_id:int,timeslot_id:int,slot_index:int,guru_id:int,ruangan_id?:int} $cand
     * @return list<int>
     */
    protected function blockingUnits(int $unitId, array $cand): array
    {
        $unit      = $this->units[$unitId];
        $kelasId   = (int) $unit['kelas_id'];
        $guruId    = (int) $cand['guru_id'];
        $hariId    = (int) $cand['hari_id'];
        $tsId      = (int) $cand['timeslot_id'];
        $ruanganId = (int) ($cand['ruangan_id'] ?? 0);

        $blockers = [];
        foreach ($this->assignments as $otherId => $a) {
            $otherId = (int) $otherId;
            if ($otherId === $unitId) {
                continue;
            }
            if ((int) $a['hari_id'] !== $hariId || (int) $a['timeslot_id'] !== $tsId) {
                continue;
            }
            $blocked = (int) $a['guru_id'] === $guruId; // HC-1
            if (! $blocked && (int) $this->units[$otherId]['kelas_id'] === $kelasId) {
                $blocked = true;                        // HC-2
            }
            if (! $blocked && $ruanganId > 0 && (int) ($a['ruangan_id'] ?? 0) === $ruanganId) {
                $blocked = true;                        // HC-3 (ruangan lab sama)
            }
            if ($blocked) {
                $blockers[] = $otherId;
            }
        }

        return $blockers;
    }

    /**
     * Pilih korban terbaik untuk di-evict di antara blocker:
     * (1) BUKAN unit dari kelas_mapel yang sama dengan unit target (urutan
     *     mengajar satu kelas_mapel dipertahankan — di-evict terakhir),
     * (2) unitScarcity() terkecil (paling mudah di-re-place),
     * (3) belum pernah di-evict di pass ini.
     *
     * @param list<int> $blockers
     * @param array<int, true> $evicted
     */
    protected function pickEvictVictim(int $unitId, array $blockers, array $evicted): ?int
    {
        $targetKm = (int) ($this->units[$unitId]['kelas_mapel_id'] ?? 0);

        $candidates = [];
        foreach ($blockers as $bid) {
            $bid = (int) $bid;
            if (isset($evicted[$bid])) {
                continue;
            }
            $candidates[] = [
                'id'       => $bid,
                'sameKm'   => (int) ($this->units[$bid]['kelas_mapel_id'] ?? 0) === $targetKm,
                'scarcity' => $this->unitScarcity($bid),
            ];
        }

        if ($candidates === []) {
            return null;
        }

        usort($candidates, static function ($a, $b) {
            if ($a['sameKm'] !== $b['sameKm']) {
                return $a['sameKm'] <=> $b['sameKm']; // beda kelas_mapel dulu (false < true)
            }

            return $a['scarcity'] <=> $b['scarcity'];
        });

        return $candidates[0]['id'];
    }

    // ------------------------------------------------------------------
    // State mutation
    // ------------------------------------------------------------------

    protected function assign(int $unitId, array $cand): void
    {
        $unit       = $this->units[$unitId];
        $kelasId    = (int) $unit['kelas_id'];
        $mapelId    = (int) $unit['mapel_id'];
        $kmId       = (int) $unit['kelas_mapel_id'];
        $butuhLab   = (int) ($unit['butuh_lab'] ?? 0) === 1;
        $hariId     = $cand['hari_id'];
        $timeslotId = $cand['timeslot_id'];
        $guruId     = $cand['guru_id'];

        if ($butuhLab) {
            $kmDayLab = SchedulingContext::buildKmDayLabFromAssignments($this->assignments, $this->units, $unitId);
            $explicit = isset($cand['ruangan_id']) ? (int) $cand['ruangan_id'] : null;
            $ruanganId = SchedulingContext::resolveLabForPlacement(
                $kmId,
                (int) $hariId,
                (int) $timeslotId,
                (int) ($unit['lab_id'] ?? 0),
                (int) $unit['jurusan_id'],
                $this->labPoolByJurusan,
                $this->labSlot,
                $kmDayLab,
                $explicit
            );
            if ($ruanganId !== null) {
                $cand['ruangan_id'] = $ruanganId;
                $this->labSlot[$ruanganId][$hariId][$timeslotId] = true;
            }
        }

        $this->assignments[$unitId] = $cand;
        $this->guruSlot[$guruId][$hariId][$timeslotId]   = true;
        $this->kelasSlot[$kelasId][$hariId][$timeslotId] = true;
        $this->guruMapelAssigned[$guruId][$mapelId] = (int) ($this->guruMapelAssigned[$guruId][$mapelId] ?? 0) + 1;
        $this->kelasDayCount[$kelasId][$hariId] = (int) ($this->kelasDayCount[$kelasId][$hariId] ?? 0) + 1;

        if ($this->lockGuruEnabled && ! isset($this->kelasMapelGuruLock[$kmId])) {
            $this->kelasMapelGuruLock[$kmId] = $guruId;
        }
    }

    protected function unassign(int $unitId): void
    {
        if (! isset($this->assignments[$unitId])) {
            return;
        }
        $unit       = $this->units[$unitId];
        $kelasId    = (int) $unit['kelas_id'];
        $mapelId    = (int) $unit['mapel_id'];
        $butuhLab   = (int) ($unit['butuh_lab'] ?? 0) === 1;
        $cand       = $this->assignments[$unitId];
        $hariId     = $cand['hari_id'];
        $timeslotId = $cand['timeslot_id'];
        $guruId     = $cand['guru_id'];

        unset(
            $this->assignments[$unitId],
            $this->guruSlot[$guruId][$hariId][$timeslotId],
            $this->kelasSlot[$kelasId][$hariId][$timeslotId]
        );
        if ($butuhLab && isset($cand['ruangan_id']) && (int) $cand['ruangan_id'] > 0) {
            unset($this->labSlot[(int) $cand['ruangan_id']][$hariId][$timeslotId]);
        }
        $this->guruMapelAssigned[$guruId][$mapelId] = max(0, (int) ($this->guruMapelAssigned[$guruId][$mapelId] ?? 1) - 1);
        $this->kelasDayCount[$kelasId][$hariId] = max(0, (int) ($this->kelasDayCount[$kelasId][$hariId] ?? 1) - 1);
    }

    protected function resetState(): void
    {
        $this->assignments        = [];
        $this->guruSlot           = [];
        $this->kelasSlot          = [];
        $this->labSlot            = [];
        $this->guruMapelAssigned  = [];
        $this->kelasDayCount      = [];
        $this->kelasMapelGuruLock = [];
    }

    protected function snapshotState(): array
    {
        return [
            'assignments'        => $this->assignments,
            'guruSlot'           => $this->guruSlot,
            'kelasSlot'          => $this->kelasSlot,
            'labSlot'            => $this->labSlot,
            'guruMapelAssigned'  => $this->guruMapelAssigned,
            'kelasDayCount'      => $this->kelasDayCount,
            'kelasMapelGuruLock' => $this->kelasMapelGuruLock,
        ];
    }

    protected function restoreState(array $s): void
    {
        $this->assignments        = $s['assignments'];
        $this->guruSlot           = $s['guruSlot'];
        $this->kelasSlot          = $s['kelasSlot'];
        $this->labSlot            = $s['labSlot'];
        $this->guruMapelAssigned  = $s['guruMapelAssigned'];
        $this->kelasDayCount      = $s['kelasDayCount'];
        $this->kelasMapelGuruLock = $s['kelasMapelGuruLock'] ?? [];
    }

    // ------------------------------------------------------------------
    // Ordering heuristics
    // ------------------------------------------------------------------

    /**
     * @return array<int, list<int>> kelas_id => unit_ids
     */
    protected function groupUnitsByKelas(): array
    {
        $grouped = [];
        foreach ($this->units as $unitId => $unit) {
            $grouped[(int) $unit['kelas_id']][] = (int) $unitId;
        }

        return $grouped;
    }

    /**
     * Class pressure = total unit scarcity (lab + limited-guru units first).
     *
     * @param array<int, list<int>> $grouped
     * @return array<int, float>
     */
    protected function computeClassPressures(array $grouped): array
    {
        $pressures = [];
        foreach ($grouped as $kelasId => $unitIds) {
            $p = 0.0;
            foreach ($unitIds as $uid) {
                $p += $this->unitScarcity($uid);
            }
            $pressures[(int) $kelasId] = $p;
        }

        return $pressures;
    }

    protected function unitScarcity(int $unitId): float
    {
        $unit    = $this->units[$unitId];
        $mapelId = (int) $unit['mapel_id'];
        $supply  = max(1, $this->mapelSupply[$mapelId] ?? 1);
        $score   = 1.0 / $supply;
        if ((int) ($unit['butuh_lab'] ?? 0) === 1) {
            $score += 2.0; // labs are the scarcest rooms
        }
        if (($unit['mapel_tipe'] ?? 'umum') === 'kejuruan') {
            $score += 0.5;
        }

        return $score;
    }

    /**
     * @param list<int> $kelasIds
     * @param array<int, float> $pressures
     * @param array<int, int> $lastUnplaced kelas_id => jumlah unplaced di attempt sebelumnya
     * @return list<int>
     */
    protected function buildClassOrder(array $kelasIds, array $pressures, int $attempt, array $lastUnplaced = []): array
    {
        $kelasIds = array_map('intval', $kelasIds);
        if ($attempt === 1) {
            usort($kelasIds, fn ($a, $b) => ($pressures[$b] ?? 0) <=> ($pressures[$a] ?? 0));

            return $kelasIds;
        }

        // Attempt >= 2: prioritise classes that left units unplaced last attempt
        // (so they get a fresh, clean state first), then shuffle the rest.
        $failed = [];
        $rest   = [];
        foreach ($kelasIds as $kid) {
            $unplaced = (int) ($lastUnplaced[$kid] ?? 0);
            if ($unplaced > 0) {
                $failed[] = [
                    'kelas'    => $kid,
                    'unplaced' => $unplaced,
                    'pressure' => (float) ($pressures[$kid] ?? 0),
                ];
            } else {
                $rest[] = $kid;
            }
        }

        if ($failed !== []) {
            usort($failed, static function ($a, $b) {
                if ($b['unplaced'] !== $a['unplaced']) {
                    return $b['unplaced'] <=> $a['unplaced'];
                }

                return $b['pressure'] <=> $a['pressure'];
            });
            shuffle($rest);

            return array_merge(array_column($failed, 'kelas'), $rest);
        }

        // No class failed last attempt — full shuffle (old behaviour).
        shuffle($kelasIds);

        return $kelasIds;
    }

    /**
     * @param list<int> $unitIds
     * @return list<int>
     */
    protected function orderUnits(array $unitIds, int $attempt): array
    {
        $unitIds = array_map('intval', $unitIds);
        usort($unitIds, fn ($a, $b) => $this->unitScarcity($b) <=> $this->unitScarcity($a));

        if ($attempt > 1) {
            // Light perturbation: rotate to vary tie-breaks between attempts.
            $shift = $attempt % max(1, count($unitIds));
            $unitIds = array_merge(array_slice($unitIds, $shift), array_slice($unitIds, 0, $shift));
        }

        return $unitIds;
    }

    // ------------------------------------------------------------------
    // Reporting
    // ------------------------------------------------------------------

    /**
     * @return list<int>
     */
    protected function collectUnplacedIds(): array
    {
        $ids = [];
        foreach ($this->units as $unitId => $unit) {
            if (! isset($this->assignments[$unitId])) {
                $ids[] = (int) $unitId;
            }
        }

        return $ids;
    }

    protected function analyzeFailure(int $unitId): array
    {
        $unit    = $this->units[$unitId];
        $mapelId = (int) $unit['mapel_id'];

        $anyEligible = false;
        $anyClassSlotFree = false;
        $anyLabFree = false;
        $butuhLab = (int) ($unit['butuh_lab'] ?? 0) === 1;
        $kmId     = (int) $unit['kelas_mapel_id'];
        $kelasId  = (int) $unit['kelas_id'];
        $kmDayLab = $butuhLab
            ? SchedulingContext::buildKmDayLabFromAssignments($this->assignments, $this->units)
            : [];

        foreach ($this->hariIds as $hariId) {
            $eligible = SchedulingContext::eligibleGurus(
                $unit,
                $hariId,
                $this->guruPool,
                $this->guruBlokir,
                $this->guruMapelAssigned
            );
            if ($eligible !== []) {
                $anyEligible = true;
            }
            foreach ($this->jpSlotsByHari[$hariId] ?? [] as $slot) {
                $ts = (int) $slot['id'];
                if (! isset($this->kelasSlot[$kelasId][$hariId][$ts])) {
                    $anyClassSlotFree = true;
                }
                if ($butuhLab) {
                    $resolved = SchedulingContext::resolveLabForPlacement(
                        $kmId,
                        $hariId,
                        $ts,
                        (int) ($unit['lab_id'] ?? 0),
                        (int) $unit['jurusan_id'],
                        $this->labPoolByJurusan,
                        $this->labSlot,
                        $kmDayLab,
                        null
                    );
                    if ($resolved !== null) {
                        $anyLabFree = true;
                    }
                }
            }
        }

        if (! $anyEligible) {
            $reason = 'no_guru_eligible';
            $fix    = 'Tambah guru_mapel untuk mapel ini, naikkan max_jam_per_minggu, atau hapus guru_hari_blokir.';
        } elseif ($butuhLab && ! $anyLabFree) {
            $reason = 'lab_conflict';
            $fix    = 'Pool lab jurusan penuh — tambah lab jurusan, kurangi JP mapel lab, atau bagi beban antar lab.';
        } elseif (! $anyClassSlotFree) {
            $reason = 'class_over_capacity';
            $fix    = 'Total JP rombel melebihi kapasitas mingguan — kurangi jam_per_minggu kelas_mapel.';
        } elseif ($this->isTimedOut()) {
            $reason = 'timeout';
            $fix    = 'Naikkan timeout_seconds atau kurangi beban data.';
        } else {
            $reason = 'teacher_conflict';
            $fix    = 'Guru eligible bentrok di slot tersedia — tambah guru cadangan (guru_mapel) atau naikkan csp_max_attempts.';
        }

        return [
            'reason'           => $reason,
            'blocked_slot'     => null,
            'suggested_fix'    => $fix,
            'tried_strategies' => [],
        ];
    }

    protected function buildUnplacedEntry(int $unitId, array $analysis): array
    {
        $unit = $this->units[$unitId] ?? [];

        return [
            'unit_id'          => $unitId,
            'kelas_mapel_id'   => (int) ($unit['kelas_mapel_id'] ?? 0),
            'kelas_id'         => (int) ($unit['kelas_id'] ?? 0),
            'guru_id'          => (int) ($this->assignments[$unitId]['guru_id'] ?? 0),
            'mapel_id'         => (int) ($unit['mapel_id'] ?? 0),
            'reason'           => $analysis['reason'],
            'blocked_slot'     => $analysis['blocked_slot'],
            'suggested_fix'    => $analysis['suggested_fix'],
            'tried_strategies' => $analysis['tried_strategies'],
        ];
    }

    protected function isTimedOut(): bool
    {
        return (microtime(true) - $this->startTime) >= $this->timeoutSeconds;
    }
}

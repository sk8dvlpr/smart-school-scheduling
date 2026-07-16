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
    protected int $maxAttempts = 8;

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
    /** @var array<int, array{hari_id:int,timeslot_id:int,slot_index:int,guru_id:int,ruangan_id?:int}> */
    protected array $seedAssignments = [];

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
        $this->maxAttempts         = max(1, (int) ($data['csp_max_attempts'] ?? 8));
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

        for ($attempt = 1; $attempt <= $this->maxAttempts; $attempt++) {
            if ($this->isTimedOut()) {
                break;
            }
            $attemptsRun = $attempt;
            $this->resetState();
            $this->applySeedAssignments();

            $classOrder = $this->buildClassOrder(array_keys($grouped), $pressures, $attempt);
            foreach ($classOrder as $kelasId) {
                if ($this->isTimedOut()) {
                    break;
                }
                $this->placeClass((int) $kelasId, $grouped[$kelasId], $attempt);
            }

            $unplacedIds = $this->collectUnplacedIds();
            $countUnplaced = count($unplacedIds);

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
        $this->repairSweep($best['unplaced']);

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
        $budget = max(2000, count($ordered) * 40);
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

        $candidates = $this->candidateAssignments($unitId, 12);
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
    public function candidatesForUnit(int $unitId, int $limit = 12): array
    {
        return $this->candidateAssignments($unitId, $limit);
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
     * @return list<array{hari_id:int,timeslot_id:int,slot_index:int,guru_id:int,ruangan_id?:int}>
     */
    protected function candidateAssignments(int $unitId, int $limit): array
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

                if (isset($this->kelasSlot[$kelasId][$hariId][$timeslotId])) {
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
                        $this->labSlot,
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
                    if (isset($this->guruSlot[$g][$hariId][$timeslotId])) {
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

        if (! isset($this->kelasMapelGuruLock[$kmId])) {
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
     * @return list<int>
     */
    protected function buildClassOrder(array $kelasIds, array $pressures, int $attempt): array
    {
        $kelasIds = array_map('intval', $kelasIds);
        if ($attempt === 1) {
            usort($kelasIds, fn ($a, $b) => ($pressures[$b] ?? 0) <=> ($pressures[$a] ?? 0));

            return $kelasIds;
        }

        // Later attempts diversify to escape earlier resource contention.
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

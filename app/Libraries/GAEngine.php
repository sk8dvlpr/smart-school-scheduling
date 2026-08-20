<?php

namespace App\Libraries;

/**
 * GA optimizer v3.1 — optimizes SC-1..SC-12 (SC-7 via guru_preferensi,
 * SC-9 guru lock, SC-12 lab day packing) over a CSP-seeded population.
 *
 * Fitness = 1 / (1 + Σ Wi × Penalty_i), each penalty normalized to ~0..1.
 * Tournament selection, order/uniform crossover, swap-with-repair mutation,
 * elitism, adaptive mutation, and stagnation-based early stopping.
 * Every offspring must satisfy HC-1..HC-8 (via isFeasible) or is rejected.
 */
class GAEngine
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
    /** @var array<int, list<int>> */
    protected array $labPoolByJurusan = [];

    protected int $populationSize = 100;
    protected int $maxGenerations = 500;
    protected int $tournamentSize = 5;
    protected float $crossoverRate = 0.8;
    protected float $mutationRate = 0.08;
    protected float $fitnessThreshold = 0.95;
    protected float $elitismRatio = 0.1;
    protected int $stagnationLimit = 40;
    protected bool $adaptiveMutation = true;
    protected int $adaptiveTrigger = 20;
    protected float $adaptiveIncrement = 0.02;
    protected int $timeoutSeconds = 120;

    /** @var array<int, list<array{hari_id:?int,timeslot_id:?int,tipe:string,bobot:int}>> */
    protected array $guruPreferensi = [];

    /** @var array<string, float> SC weights (scale 1..10) */
    protected array $w = [];

    protected float $currentMutationRate = 0.08;

    /** @var array<int, int> hari_id => max slot_index per day (precomputed) */
    protected array $dailyCap = [];
    protected int $numHari = 1;
    /** @var array<string, float> schedule-hash => fitness (per optimize() run) */
    protected array $fitnessCache = [];

    public function __construct(array $data)
    {
        $this->units         = $data['units'] ?? [];
        $this->hariData      = $data['hari_data'] ?? [];
        $this->jpSlotsByHari = $data['jp_slots_by_hari'] ?? [];
        $this->guruPool      = $data['guru_pool'] ?? [];
        $this->guruBlokir    = $data['guru_blokir'] ?? [];
        $this->homeroomMap         = $data['homeroom_map'] ?? [];
        $this->labPoolByJurusan   = $data['lab_pool_by_jurusan'] ?? [];
        $this->guruPreferensi     = $this->indexPreferensi($data['guru_preferensi'] ?? []);

        $this->populationSize   = max(5, (int) ($data['population_size'] ?? 100));
        $this->maxGenerations   = max(1, (int) ($data['max_generations'] ?? 500));
        $this->tournamentSize   = max(2, (int) ($data['tournament_size'] ?? 5));
        $this->crossoverRate    = (float) ($data['crossover_rate'] ?? 0.8);
        $this->mutationRate      = (float) ($data['mutation_rate'] ?? 0.08);
        $this->fitnessThreshold = (float) ($data['fitness_threshold'] ?? 0.95);
        $this->elitismRatio     = min(0.5, max(0.0, (float) ($data['elitism_ratio'] ?? 0.1)));
        $this->stagnationLimit  = max(5, (int) ($data['stagnation_limit'] ?? 40));
        $this->adaptiveMutation = (int) ($data['adaptive_mutation'] ?? 1) === 1;
        $this->adaptiveTrigger  = max(1, (int) ($data['adaptive_mutation_trigger'] ?? 20));
        $this->adaptiveIncrement = (float) ($data['adaptive_mutation_increment'] ?? 0.02);
        $this->timeoutSeconds   = max(10, (int) ($data['timeout_seconds'] ?? 120));

        $this->w = [
            'sc1'  => (float) ($data['sc1_teacher_gap'] ?? 9),
            'sc2'  => (float) ($data['sc2_student_gap'] ?? 9),
            'sc3'  => (float) ($data['sc3_subject_distribution'] ?? 7),
            'sc4'  => (float) ($data['sc4_heavy_morning'] ?? 6),
            'sc5'  => (float) ($data['sc5_light_afternoon'] ?? 5),
            'sc6'  => (float) ($data['sc6_teacher_load_balance'] ?? 7),
            'sc7'  => (float) ($data['sc7_teacher_preference'] ?? 5),
            'sc8'  => (float) ($data['sc8_room_transition'] ?? 5),
            'sc9'  => (float) ($data['sc9_teacher_continuity'] ?? 4),
            'sc10' => (float) ($data['sc10_first_slot_rotation'] ?? 3),
            'sc11' => (float) ($data['sc11_lab_load_balance'] ?? 6),
            'sc12' => (float) ($data['sc_lab_day_pack'] ?? 7),
            'lab_pref' => (float) ($data['sc_lab_preference'] ?? 5),
        ];

        $this->currentMutationRate = $this->mutationRate;

        foreach ($this->jpSlotsByHari as $hariId => $slots) {
            $this->dailyCap[(int) $hariId] = max(1, count($slots) - 1);
        }
        $this->numHari = max(1, count($this->hariData));
    }

    /**
     * @return array{assignments: array, fitness: float, generations: int, violations: int,
     *               penalty_breakdown: array<string, float>, weighted_sum: float}
     */
    public function optimize(array $initialAssignments): array
    {
        if ($initialAssignments === []) {
            return [
                'assignments'       => [],
                'fitness'           => 0.0,
                'generations'       => 0,
                'violations'        => 0,
                'penalty_breakdown' => [],
                'weighted_sum'      => 0.0,
            ];
        }

        $start = microtime(true);
        $this->currentMutationRate = $this->mutationRate;
        $this->fitnessCache = [];

        $population = [$initialAssignments];
        while (count($population) < $this->populationSize) {
            // Guard timeout: jangan habiskan budget di fase inisialisasi populasi.
            if ((microtime(true) - $start) > $this->timeoutSeconds) {
                break;
            }
            $population[] = $this->mutateSchedule($initialAssignments);
        }

        $best        = $initialAssignments;
        $bestFitness = $this->fitness($best);
        $generations = 0;
        $stagnant    = 0;
        $eliteCount  = max(1, (int) floor($this->populationSize * $this->elitismRatio));

        while ($generations < $this->maxGenerations) {
            if ((microtime(true) - $start) >= $this->timeoutSeconds) {
                break;
            }
            if ($bestFitness >= $this->fitnessThreshold) {
                break;
            }
            if ($stagnant >= $this->stagnationLimit) {
                break;
            }

            // Fitness is expensive: score every chromosome once per generation.
            // fitness() caches internally, so re-scoring an unchanged elite is cheap.
            $fits = [];
            foreach ($population as $i => $c) {
                $fits[$i] = $this->fitness($c);
            }
            array_multisort($fits, SORT_DESC, $population);

            $next = array_slice($population, 0, $eliteCount); // elitism

            while (count($next) < $this->populationSize) {
                $parentA = $this->tournamentSelect($population, $fits);
                $parentB = $this->tournamentSelect($population, $fits);

                $child = (mt_rand() / mt_getrandmax()) < $this->crossoverRate
                    ? $this->crossover($parentA, $parentB)
                    : $parentA;

                if ((mt_rand() / mt_getrandmax()) < $this->currentMutationRate) {
                    $child = $this->mutateSchedule($child);
                }

                $next[] = $this->isFeasible($child) ? $child : $parentA;
            }

            $population = $next;
            $generations++;

            // population[0] is the sorted elite carried into next; its score is fits[0].
            $genBest        = $population[0];
            $genBestFitness = $fits[0];

            if ($genBestFitness > $bestFitness + 1e-9) {
                $best        = $genBest;
                $bestFitness = $genBestFitness;
                $stagnant    = 0;
                $this->currentMutationRate = $this->mutationRate; // reset adaptive
            } else {
                $stagnant++;
                if ($this->adaptiveMutation && $stagnant > 0 && $stagnant % $this->adaptiveTrigger === 0) {
                    $this->currentMutationRate = min(0.5, $this->currentMutationRate + $this->adaptiveIncrement);
                }
            }
        }

        $penalties = $this->penalties($best);
        $weightedSum = 0.0;
        foreach ($this->w as $key => $weight) {
            $weightedSum += $weight * ($penalties[$key] ?? 0.0);
        }
        $violations = 0.0;
        foreach ($penalties as $p) {
            $violations += $p;
        }

        return [
            'assignments'      => $best,
            'fitness'          => round($bestFitness, 6),
            'generations'      => $generations,
            'violations'       => (int) round($violations * 100),
            'penalty_breakdown' => $penalties,
            'weighted_sum'     => round($weightedSum, 4),
        ];
    }

    // ------------------------------------------------------------------
    // Genetic operators
    // ------------------------------------------------------------------

    /**
     * @param list<array> $population
     * @param list<float> $fits fitness aligned with $population indices
     */
    protected function tournamentSelect(array $population, array $fits): array
    {
        $n = count($population);
        $bestIdx = mt_rand(0, $n - 1);
        for ($i = 1; $i < $this->tournamentSize; $i++) {
            $idx = mt_rand(0, $n - 1);
            if (($fits[$idx] ?? 0.0) > ($fits[$bestIdx] ?? 0.0)) {
                $bestIdx = $idx;
            }
        }

        return $population[$bestIdx];
    }

    /**
     * Block crossover: copy a contiguous block of unit assignments from parentB
     * onto a copy of parentA. The whole block is validated once with isFeasible;
     * if rejected, fall back to per-gene swaps with early exit after 5 failures.
     */
    protected function crossover(array $parentA, array $parentB): array
    {
        $child = $parentA;
        $ids   = array_keys($parentA);
        $n     = count($ids);
        if ($n < 2) {
            return $child;
        }

        $len   = max(1, (int) floor($n * 0.3));
        $start = mt_rand(0, max(0, $n - $len));
        $block = array_slice($ids, $start, $len);

        $trial = $child;
        foreach ($block as $unitId) {
            if (isset($parentB[$unitId])) {
                $trial[$unitId] = $parentB[$unitId];
            }
        }
        if ($this->isFeasible($trial)) {
            return $trial;
        }

        $failures = 0;
        foreach ($block as $unitId) {
            if (! isset($parentB[$unitId]) || $failures > 5) {
                continue;
            }
            $trial = $child;
            $trial[$unitId] = $parentB[$unitId];
            if ($this->isFeasible($trial)) {
                $child = $trial;
            } else {
                $failures++;
            }
        }

        return $child;
    }

    /**
     * Swap mutation: randomly swap two units of the same class or same teacher.
     * This avoids trivial class/teacher conflicts and preserves schedule density.
     */
    protected function mutateSchedule(array $schedule): array
    {
        if ($schedule === []) {
            return $schedule;
        }

        $swapCount = mt_rand(1, 3);
        $trial = $schedule;
        $unitIds = array_keys($schedule);

        for ($i = 0; $i < $swapCount; $i++) {
            $u1 = $unitIds[array_rand($unitIds)];
            $swapType = mt_rand(0, 1); // 0 = class swap, 1 = guru swap

            $u2 = null;
            if ($swapType === 0) {
                // Class-based swap
                $kelasId = $this->units[$u1]['kelas_id'] ?? 0;
                $candidates = [];
                foreach ($this->units as $uid => $u) {
                    if (($u['kelas_id'] ?? 0) === $kelasId && $uid !== $u1) {
                        $candidates[] = $uid;
                    }
                }
                if ($candidates !== []) {
                    $u2 = $candidates[array_rand($candidates)];
                }
            } else {
                // Guru-based swap
                $guruId = $trial[$u1]['guru_id'] ?? 0;
                $candidates = [];
                foreach ($trial as $uid => $a) {
                    if (($a['guru_id'] ?? 0) === $guruId && $uid !== $u1) {
                        $candidates[] = $uid;
                    }
                }
                if ($candidates !== []) {
                    $u2 = $candidates[array_rand($candidates)];
                }
            }

            if ($u2 === null) {
                continue;
            }

            // Swap timeslots
            $t1 = [
                'hari_id'     => $trial[$u1]['hari_id'],
                'timeslot_id' => $trial[$u1]['timeslot_id'],
                'slot_index'  => $trial[$u1]['slot_index']
            ];
            $t2 = [
                'hari_id'     => $trial[$u2]['hari_id'],
                'timeslot_id' => $trial[$u2]['timeslot_id'],
                'slot_index'  => $trial[$u2]['slot_index']
            ];

            $trial[$u1]['hari_id']     = $t2['hari_id'];
            $trial[$u1]['timeslot_id'] = $t2['timeslot_id'];
            $trial[$u1]['slot_index']  = $t2['slot_index'];

            $trial[$u2]['hari_id']     = $t1['hari_id'];
            $trial[$u2]['timeslot_id'] = $t1['timeslot_id'];
            $trial[$u2]['slot_index']  = $t1['slot_index'];
        }

        return $this->isFeasible($trial) ? $trial : $schedule;
    }

    // ------------------------------------------------------------------
    // Feasibility (HC-1..HC-8)
    // ------------------------------------------------------------------

    protected function isFeasible(array $schedule): bool
    {
        $guruSlot = [];
        $kelasSlot = [];
        $labSlot = [];
        $guruMapel = [];
        $kmDayLab = [];

        foreach ($schedule as $unitId => $a) {
            if (! isset($this->units[$unitId])) {
                return false;
            }
            $unit       = $this->units[$unitId];
            $hariId     = (int) $a['hari_id'];
            $timeslotId = (int) $a['timeslot_id'];
            $guruId     = (int) $a['guru_id'];
            $kelasId    = (int) $unit['kelas_id'];
            $kmId       = (int) $unit['kelas_mapel_id'];
            $butuhLab   = (int) ($unit['butuh_lab'] ?? 0) === 1;

            if (isset($guruSlot[$guruId][$hariId][$timeslotId])) {
                return false; // HC-1
            }
            if (isset($kelasSlot[$kelasId][$hariId][$timeslotId])) {
                return false; // HC-2
            }

            $ruanganId = 0;
            if ($butuhLab) {
                $explicit = isset($a['ruangan_id']) ? (int) $a['ruangan_id'] : null;
                $resolved = SchedulingContext::resolveLabForPlacement(
                    $kmId,
                    $hariId,
                    $timeslotId,
                    (int) ($unit['lab_id'] ?? 0),
                    (int) $unit['jurusan_id'],
                    $this->labPoolByJurusan,
                    $labSlot,
                    $kmDayLab,
                    $explicit
                );
                if ($resolved === null) {
                    return false;
                }
                if ($explicit !== null && $explicit > 0 && $explicit !== $resolved) {
                    return false;
                }
                $ruanganId = $resolved;
                if (isset($labSlot[$ruanganId][$hariId][$timeslotId])) {
                    return false; // HC-3
                }
            }

            if (isset($this->guruBlokir[$guruId][$hariId])) {
                return false; // HC-4
            }
            $eligible = SchedulingContext::eligibleGurus($unit, $hariId, $this->guruPool, $this->guruBlokir, $guruMapel);
            if (! in_array($guruId, $eligible, true)) {
                return false;
            }

            $guruSlot[$guruId][$hariId][$timeslotId]   = true;
            $kelasSlot[$kelasId][$hariId][$timeslotId] = true;
            if ($butuhLab && $ruanganId > 0) {
                $labSlot[$ruanganId][$hariId][$timeslotId] = true;
                $kmDayLab[$kmId][$hariId] = $ruanganId;
            }
            $guruMapel[$guruId][(int) $unit['mapel_id']] = (int) ($guruMapel[$guruId][(int) $unit['mapel_id']] ?? 0) + 1;
        }

        return true;
    }

    /**
     * Build conflict indices from a schedule, optionally excluding one unit.
     *
     * @return array{guruSlot:array,kelasSlot:array,labSlot:array,guruMapel:array}
     */
    protected function buildIndex(array $schedule, int $ignoreUnitId = -1): array
    {
        $guruSlot = [];
        $kelasSlot = [];
        $labSlot = [];
        $guruMapel = [];

        foreach ($schedule as $unitId => $a) {
            if ($unitId === $ignoreUnitId || ! isset($this->units[$unitId])) {
                continue;
            }
            $unit       = $this->units[$unitId];
            $hariId     = (int) $a['hari_id'];
            $timeslotId = (int) $a['timeslot_id'];
            $guruId     = (int) $a['guru_id'];
            $kelasId    = (int) $unit['kelas_id'];
            $butuhLab   = (int) ($unit['butuh_lab'] ?? 0) === 1;
            $ruanganId  = $butuhLab ? (int) ($a['ruangan_id'] ?? 0) : 0;

            $guruSlot[$guruId][$hariId][$timeslotId]   = true;
            $kelasSlot[$kelasId][$hariId][$timeslotId] = true;
            if ($butuhLab && $ruanganId > 0) {
                $labSlot[$ruanganId][$hariId][$timeslotId] = true;
            }
            $guruMapel[$guruId][(int) $unit['mapel_id']] = (int) ($guruMapel[$guruId][(int) $unit['mapel_id']] ?? 0) + 1;
        }

        return compact('guruSlot', 'kelasSlot', 'labSlot', 'guruMapel');
    }

    // ------------------------------------------------------------------
    // Fitness / soft constraints
    // ------------------------------------------------------------------

    protected function fitness(array $schedule): float
    {
        $cacheKey = md5(serialize($schedule));
        if (isset($this->fitnessCache[$cacheKey])) {
            return $this->fitnessCache[$cacheKey];
        }

        $p = $this->penalties($schedule);
        $sum = 0.0;
        foreach ($this->w as $key => $weight) {
            $sum += $weight * ($p[$key] ?? 0.0);
        }

        $fit = 1.0 / (1.0 + $sum);
        if (count($this->fitnessCache) >= 5000) {
            $this->fitnessCache = [];
        }

        return $this->fitnessCache[$cacheKey] = $fit;
    }

    /**
     * All SC penalties normalized to ~0..1.
     *
     * @return array<string, float>
     */
    protected function penalties(array $schedule): array
    {
        $count = max(1, count($schedule));

        // Group data in one pass.
        $guruDaySlots  = []; // [guru][hari] => list slot_index
        $kelasDaySlots = []; // [kelas][hari] => list slot_index
        $kelasDayRoom  = []; // [kelas][hari][slot_index] => room_id
        $kelasMapelDay = []; // [kelas][mapel][hari] => count
        $guruDayCount  = []; // [guru][hari] => count
        $jurusanDayLab = []; // [jurusan][hari] => lab count
        $kelasDayFirst = []; // [kelas][hari] => [slot_index, mapel]

        $labPrefHits = 0;
        $labPrefTotal = 0;

        foreach ($schedule as $unitId => $a) {
            $unit    = $this->units[$unitId] ?? [];
            $hariId  = (int) $a['hari_id'];
            $slotIdx = (int) $a['slot_index'];
            $guruId  = (int) $a['guru_id'];
            $kelasId = (int) ($unit['kelas_id'] ?? 0);
            $mapelId = (int) ($unit['mapel_id'] ?? 0);
            $jurId   = (int) ($unit['jurusan_id'] ?? 0);
            $butuhLab = (int) ($unit['butuh_lab'] ?? 0) === 1;
            $roomId  = $butuhLab
                ? (int) ($a['ruangan_id'] ?? 0)
                : (int) ($this->homeroomMap[$kelasId] ?? 0);

            if ($butuhLab) {
                $labPrefTotal++;
                $preferred = (int) ($unit['lab_id'] ?? 0);
                if ($preferred > 0 && $roomId !== $preferred) {
                    $labPrefHits++;
                }
            }

            $guruDaySlots[$guruId][$hariId][]  = $slotIdx;
            $kelasDaySlots[$kelasId][$hariId][] = $slotIdx;
            $kelasDayRoom[$kelasId][$hariId][$slotIdx] = $roomId;
            $kelasMapelDay[$kelasId][$mapelId][$hariId] = (int) ($kelasMapelDay[$kelasId][$mapelId][$hariId] ?? 0) + 1;
            $guruDayCount[$guruId][$hariId] = (int) ($guruDayCount[$guruId][$hariId] ?? 0) + 1;
            if ($butuhLab) {
                $jurusanDayLab[$jurId][$hariId] = (int) ($jurusanDayLab[$jurId][$hariId] ?? 0) + 1;
            }

            if (! isset($kelasDayFirst[$kelasId][$hariId]) || $slotIdx < $kelasDayFirst[$kelasId][$hariId][0]) {
                $kelasDayFirst[$kelasId][$hariId] = [$slotIdx, $mapelId];
            }
        }

        // SC-4/SC-5: heavy mapel in morning (SC-4), light mapel in afternoon (SC-5).
        $sc4Violations = 0;
        $sc4Total      = 0;
        $sc5Violations = 0;
        $sc5Total      = 0;
        foreach ($schedule as $unitId => $a) {
            $bobot   = (int) ($this->units[$unitId]['bobot_kognitif'] ?? 5);
            $hariId  = (int) $a['hari_id'];
            $slotIdx = (int) $a['slot_index'];
            $cap     = $this->dailyCap[$hariId] ?? 1;
            
            if ($bobot >= 7) {
                $sc4Total++;
                $threshold4 = $cap * 2.0 / 3.0;
                if ($slotIdx >= $threshold4) {
                    $sc4Violations++;
                }
            } elseif ($bobot <= 3) {
                $sc5Total++;
                $threshold5 = $cap / 3.0;
                if ($slotIdx < $threshold5) {
                    $sc5Violations++;
                }
            }
        }
        $sc4 = $sc4Total > 0 ? $sc4Violations / $sc4Total : 0.0;
        $sc5 = $sc5Total > 0 ? $sc5Violations / $sc5Total : 0.0;

        // SC-1 teacher gaps: average normalized gap per (guru, hari) pair.
        $sc1 = 0.0;
        $sc1Pairs = 0;
        foreach ($guruDaySlots as $days) {
            foreach ($days as $hariId => $slots) {
                $sc1 += min(1.0, $this->gapCount($slots) / max(1, $this->dailyCap[$hariId] ?? 1));
                $sc1Pairs++;
            }
        }
        $sc1 = $sc1Pairs > 0 ? $sc1 / $sc1Pairs : 0.0;

        // SC-2 student (class) gaps: average normalized gap per (kelas, hari) pair.
        $sc2 = 0.0;
        $sc2Pairs = 0;
        foreach ($kelasDaySlots as $days) {
            foreach ($days as $hariId => $slots) {
                $sc2 += min(1.0, $this->gapCount($slots) / max(1, $this->dailyCap[$hariId] ?? 1));
                $sc2Pairs++;
            }
        }
        $sc2 = $sc2Pairs > 0 ? $sc2 / $sc2Pairs : 0.0;

        // SC-3 subject distribution across the week (avoid piling same mapel one day)
        $sc3 = 0.0;
        $sc3Groups = 0;
        foreach ($kelasMapelDay as $mapels) {
            foreach ($mapels as $days) {
                $vals = array_values($days);
                $totalJp = array_sum($vals);
                $maxPerDay = max($vals);
                // 0 = perfectly spread (1/day), 1 = all on one day
                $sc3 += $totalJp > 1 ? ($maxPerDay - 1) / ($totalJp - 1) : 0.0;
                $sc3Groups++;
            }
        }
        $sc3 = $sc3Groups > 0 ? $sc3 / $sc3Groups : 0.0;

        // SC-6 guru load balance across days
        $sc6 = 0.0;
        $numHari = $this->numHari;
        $guruCount = max(1, count($guruDayCount));
        foreach ($guruDayCount as $days) {
            $vals = array_values($days);
            $total = array_sum($vals);
            $avg = $total / $numHari;
            $dev = 0.0;
            foreach ($vals as $v) {
                $dev += abs($v - $avg);
            }
            $dev += ($numHari - count($vals)) * $avg; // zero-days deviation
            $sc6 += $total > 0 ? min(1.0, $dev / $total) : 0.0;
        }
        $sc6 /= $guruCount;

        // SC-8 room transitions per class-day
        $sc8 = 0.0;
        foreach ($kelasDayRoom as $days) {
            foreach ($days as $rooms) {
                ksort($rooms);
                $prev = null;
                foreach ($rooms as $room) {
                    if ($prev !== null && $room !== $prev) {
                        $sc8 += 1;
                    }
                    $prev = $room;
                }
            }
        }
        $sc8 = min(1.0, $sc8 / $count);

        // SC-10 first-slot rotation: penalize repeated first mapel across days
        $sc10 = 0.0;
        $kelasCount = max(1, count($kelasDayFirst));
        foreach ($kelasDayFirst as $days) {
            $firstMapels = array_column($days, 1);
            $numDays = count($firstMapels);
            $numUnique = count(array_unique($firstMapels));
            // 0 = fully rotated (semua beda), 1 = semua hari mapel pertama sama
            $sc10 += $numDays > 1 ? 1.0 - (($numUnique - 1) / ($numDays - 1)) : 0.0;
        }
        $sc10 = $kelasCount > 0 ? $sc10 / $kelasCount : 0.0;

        // SC-11 lab load balance across jurusan
        $sc11 = 0.0;
        $jurCount = max(1, count($jurusanDayLab));
        foreach ($jurusanDayLab as $days) {
            $vals = array_values($days);
            $total = array_sum($vals);
            $avg = $total / $this->numHari;
            $dev = 0.0;
            foreach ($vals as $v) { 
                $dev += abs($v - $avg); 
            }
            $dev += ($this->numHari - count($vals)) * $avg; // zero-days
            
            $maxDev = $total > 0 ? $total * ($this->numHari - 1) / $this->numHari : 1;
            $sc11 += min(1.0, $dev / max(1.0, $maxDev * 2.0));
        }
        $sc11 = $jurCount > 0 ? $sc11 / $jurCount : 0.0;

        // SC-12 pack lab classes (jurusan+tingkat) onto fewer parallel-filled days
        $sc12 = SchedulingContext::labDayPackPenalty($schedule, $this->units, $this->labPoolByJurusan, $this->numHari);

        // SC-9: teacher continuity per kelas_mapel (one guru per class-subject pair)
        $sc9 = 0.0;
        $kmGurus = [];
        foreach ($schedule as $unitId => $a) {
            $unit = $this->units[$unitId] ?? [];
            $kmId = (int) ($unit['kelas_mapel_id'] ?? 0);
            if ($kmId <= 0) {
                continue;
            }
            $kmGurus[$kmId][(int) $a['guru_id']] = true;
        }
        $kmCount = max(1, count($kmGurus));
        foreach ($kmGurus as $gurus) {
            if (count($gurus) > 1) {
                $sc9 += 1.0;
            }
        }
        $sc9 = min(1.0, $sc9 / $kmCount);

        // SC-7: teacher day/time preference
        $sc7 = 0.0;
        $sc7Checks = 0;
        foreach ($schedule as $unitId => $a) {
            $guruId = (int) $a['guru_id'];
            $prefs  = $this->guruPreferensi[$guruId] ?? [];
            if ($prefs === []) {
                continue;
            }
            $hariId  = (int) $a['hari_id'];
            $slotIdx = (int) $a['slot_index'];
            $tsId    = (int) ($a['timeslot_id'] ?? 0);
            foreach ($prefs as $pref) {
                $pHari = $pref['hari_id'];
                $pTs   = $pref['timeslot_id'];
                $bobot = max(1, min(10, (int) $pref['bobot'])) / 10.0;
                $hariMatch = $pHari === null || $pHari === $hariId;
                $slotMatch = $pTs === null || $pTs === $tsId;
                $matches = $hariMatch && $slotMatch;
                if ($pref['tipe'] === 'prefer') {
                    if (! $matches) {
                        $sc7 += $bobot;
                    }
                    $sc7Checks++;
                } else {
                    if ($matches) {
                        $sc7 += $bobot;
                    }
                    $sc7Checks++;
                }
            }
        }
        $sc7 = $sc7Checks > 0 ? min(1.0, $sc7 / $sc7Checks) : 0.0;

        $labPref = $labPrefTotal > 0 ? $labPrefHits / $labPrefTotal : 0.0;

        return [
            'sc1'  => $sc1,
            'sc2'  => $sc2,
            'sc3'  => $sc3,
            'sc4'  => $sc4,
            'sc5'  => $sc5,
            'sc6'  => $sc6,
            'sc7'  => $sc7,
            'sc8'  => $sc8,
            'sc9'  => $sc9,
            'sc10' => $sc10,
            'sc11' => $sc11,
            'sc12' => $sc12,
            'lab_pref' => $labPref,
        ];
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @return array<int, list<array{hari_id:?int,timeslot_id:?int,tipe:string,bobot:int}>>
     */
    protected function indexPreferensi(array $rows): array
    {
        $index = [];
        foreach ($rows as $row) {
            $gid = (int) ($row['guru_id'] ?? 0);
            if ($gid <= 0) {
                continue;
            }
            $index[$gid][] = [
                'hari_id'     => isset($row['hari_id']) && $row['hari_id'] !== '' ? (int) $row['hari_id'] : null,
                'timeslot_id' => isset($row['timeslot_id']) && $row['timeslot_id'] !== '' ? (int) $row['timeslot_id'] : null,
                'tipe'        => ($row['tipe'] ?? 'prefer') === 'avoid' ? 'avoid' : 'prefer',
                'bobot'       => (int) ($row['bobot'] ?? 5),
            ];
        }

        return $index;
    }

    /**
     * Empty slots between the first and last used slot of a day.
     *
     * @param list<int> $slots
     */
    protected function gapCount(array $slots): float
    {
        if (count($slots) < 2) {
            return 0.0;
        }
        $min = min($slots);
        $max = max($slots);

        return (float) max(0, ($max - $min + 1) - count($slots));
    }

    /**
     * SC-9: first assigned guru for kelas_mapel in schedule locks siblings.
     */
    protected function lockedGuruForKelasMapel(int $kelasMapelId, array $schedule): ?int
    {
        foreach ($schedule as $unitId => $a) {
            $unit = $this->units[$unitId] ?? [];
            if ((int) ($unit['kelas_mapel_id'] ?? 0) === $kelasMapelId) {
                return (int) $a['guru_id'];
            }
        }

        return null;
    }
}

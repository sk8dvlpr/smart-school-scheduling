<?php

namespace App\Libraries;

/**
 * Warm-start and HC-safe swap repair from a parent schedule history.
 */
class HistoryRepairEngine
{
    /**
     * @param array<string, mixed> $engineData units, jp_slots_by_hari, guru_pool, guru_blokir, homeroom_map, hari_data
     * @param array<int, array<string, mixed>> $units
     * @return array{seed_assignments: array<int, array>, repair_report: array<string, mixed>}
     */
    public function repairFromHistory(array $engineData, int $parentLogId, array $units): array
    {
        $db = \Config\Database::connect();
        $jadwalRows = $db->table('jadwal')
            ->where('schedule_log_id', $parentLogId)
            ->orderBy('kelas_id', 'ASC')
            ->orderBy('hari_id', 'ASC')
            ->orderBy('timeslot_id', 'ASC')
            ->get()
            ->getResultArray();

        $jpSlotsByHari = $engineData['jp_slots_by_hari'] ?? [];
        $slotIndexMap  = $this->buildSlotIndexMap($jpSlotsByHari);

        $unitsByKm = [];
        foreach ($units as $unitId => $unit) {
            $kmId = (int) $unit['kelas_mapel_id'];
            $unitsByKm[$kmId][] = (int) $unitId;
        }

        $rowsByKm = [];
        foreach ($jadwalRows as $row) {
            $rowsByKm[(int) $row['kelas_mapel_id']][] = $row;
        }

        $seed       = [];
        $carried    = 0;
        $invalid    = 0;
        $swaps      = 0;

        $cspProbe = new CSPEngine(array_merge($engineData, ['csp_max_attempts' => 1]));

        foreach ($unitsByKm as $kmId => $unitIds) {
            $rows = $rowsByKm[$kmId] ?? [];
            sort($unitIds);
            foreach ($unitIds as $i => $unitId) {
                if (! isset($rows[$i])) {
                    continue;
                }
                $row = $rows[$i];
                $hariId     = (int) $row['hari_id'];
                $timeslotId = (int) $row['timeslot_id'];
                $slotIndex  = $slotIndexMap[$hariId][$timeslotId] ?? 0;
                $cand = [
                    'hari_id'     => $hariId,
                    'timeslot_id' => $timeslotId,
                    'slot_index'  => $slotIndex,
                    'guru_id'     => (int) $row['guru_id'],
                ];
                $unit = $units[$unitId] ?? null;
                if ($unit && (int) ($unit['butuh_lab'] ?? 0) === 1) {
                    $cand['ruangan_id'] = (int) ($row['ruangan_id'] ?? 0);
                }
                if ($cspProbe->canAssignUnit((int) $unitId, $cand, $seed)) {
                    $seed[(int) $unitId] = $cand;
                    $carried++;
                } else {
                    $invalid++;
                }
            }
        }

        $unplacedIds = array_values(array_diff(array_map('intval', array_keys($units)), array_map('intval', array_keys($seed))));

        foreach ($unplacedIds as $unitId) {
            if ($this->tryDirectPlace($unitId, $units, $engineData, $seed)) {
                $swaps++;
                continue;
            }
            if ($this->trySwapWithPlaced($unitId, $units, $engineData, $seed)) {
                $swaps++;
            }
        }

        return [
            'seed_assignments' => $seed,
            'repair_report'    => [
                'carried_over'    => $carried,
                'invalid_dropped' => $invalid,
                'swaps_applied'   => $swaps,
                'still_unplaced'  => count(array_diff(array_keys($units), array_keys($seed))),
                'parent_log_id'   => $parentLogId,
            ],
        ];
    }

    /**
     * @param array<int, list<array{id:int,jam_ke:int,slot_index:int}>> $jpSlotsByHari
     * @return array<int, array<int, int>>
     */
    protected function buildSlotIndexMap(array $jpSlotsByHari): array
    {
        $map = [];
        foreach ($jpSlotsByHari as $hariId => $slots) {
            foreach ($slots as $slot) {
                $map[(int) $hariId][(int) $slot['id']] = (int) $slot['slot_index'];
            }
        }

        return $map;
    }

    /**
     * @param array<int, array<string, mixed>> $units
     * @param array<int, array{hari_id:int,timeslot_id:int,slot_index:int,guru_id:int}> $seed
     */
    protected function tryDirectPlace(int $unitId, array $units, array $engineData, array &$seed): bool
    {
        $csp = new CSPEngine(array_merge($engineData, [
            'seed_assignments' => $seed,
            'csp_max_attempts'   => 1,
        ]));

        foreach ($csp->candidatesForUnit($unitId, 20) as $cand) {
            if ($csp->canAssignUnit($unitId, $cand, $seed)) {
                $seed[$unitId] = $cand;

                return true;
            }
        }

        return false;
    }

    /**
     * @param array<int, array<string, mixed>> $units
     * @param array<int, array{hari_id:int,timeslot_id:int,slot_index:int,guru_id:int}> $seed
     */
    protected function trySwapWithPlaced(int $unitId, array $units, array $engineData, array &$seed): bool
    {
        $unit    = $units[$unitId] ?? null;
        $kelasId = (int) ($unit['kelas_id'] ?? 0);
        if (! $unit) {
            return false;
        }

        $csp = new CSPEngine(array_merge($engineData, ['csp_max_attempts' => 1]));

        foreach ($seed as $otherId => $otherCand) {
            $otherUnit = $units[$otherId] ?? null;
            if (! $otherUnit || (int) $otherUnit['kelas_id'] !== $kelasId) {
                continue;
            }

            $trial = $seed;
            unset($trial[$otherId]);
            if ($csp->canAssignUnit($unitId, $otherCand, $trial)
                && $csp->canAssignUnit($otherId, $seed[$unitId] ?? $otherCand, array_merge($trial, [$unitId => $otherCand]))) {
                // swap slots between two units same class
                $trialSeed = $seed;
                $a = $trialSeed[$otherId];
                $trialSeed[$otherId] = [
                    'hari_id'     => $a['hari_id'],
                    'timeslot_id' => $a['timeslot_id'],
                    'slot_index'  => $a['slot_index'],
                    'guru_id'     => (int) $unit['mapel_id'] === (int) $otherUnit['mapel_id']
                        ? $a['guru_id']
                        : $a['guru_id'],
                ];
                unset($trialSeed[$otherId]);
                if ($csp->canAssignUnit($unitId, $a, $trialSeed)) {
                    $newA = $a;
                    $seed[$unitId]  = $newA;
                    $seed[$otherId] = $this->findAnyForUnit($otherId, $units, $engineData, $seed) ?? $seed[$otherId];

                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @param array<int, array{hari_id:int,timeslot_id:int,slot_index:int,guru_id:int}> $seed
     * @return array{hari_id:int,timeslot_id:int,slot_index:int,guru_id:int}|null
     */
    protected function findAnyForUnit(int $unitId, array $units, array $engineData, array $seed): ?array
    {
        $csp = new CSPEngine(array_merge($engineData, ['seed_assignments' => $seed, 'csp_max_attempts' => 1]));
        $cands = $csp->candidatesForUnit($unitId, 5);

        return $cands[0] ?? null;
    }
}
